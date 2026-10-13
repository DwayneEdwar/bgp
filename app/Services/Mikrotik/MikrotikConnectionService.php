<?php

namespace App\Services\Mikrotik;

use App\Models\Alert;
use App\Models\NetworkEvent;
use App\Models\Router;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use Throwable;

class MikrotikConnectionService
{
    /** @var list<string> */
    private const PRINT_ENDPOINTS = [
        '/interface/print',
        '/ip/address/print',
        '/ip/route/print',
        '/routing/route/print',
        '/routing/bgp/session/print',
        '/routing/bgp/peer/print',
        '/routing/bgp/instance/print',
        '/routing/bgp/connection/print',
        '/ip/neighbor/print',
        '/log/print',
        '/system/identity/print',
        '/ip/dhcp-server/print',
        '/ip/dns/print',
        '/ip/firewall/nat/print',
        '/ip/firewall/filter/print',
    ];

    public function testConnection(Router $router): bool
    {
        $previousStatus = $router->status;
        $connected = $this->checkConnection($router);
        $status = $connected ? 'online' : 'offline';

        $router->update($connected
            ? [
                'status' => 'online',
                'last_connected_at' => now(),
            ]
            : ['status' => 'offline']);

        if ($previousStatus !== $status) {
            $this->recordConnectionTransition($router, $previousStatus, $status);
        }

        return $connected;
    }

    private function recordConnectionTransition(Router $router, ?string $previousStatus, string $status): void
    {
        $now = now();
        $isOnline = $status === 'online';
        $type = $isOnline ? 'router_online' : 'router_offline';
        $message = $isOnline ? 'Router connection restored.' : 'Router connection lost.';

        try {
            NetworkEvent::query()->create([
                'router_id' => $router->getKey(),
                'actor_id' => auth()->id(),
                'type' => $type,
                'module' => 'availability',
                'target_type' => Router::class,
                'target_id' => $router->getKey(),
                'description' => $message,
                'metadata' => ['previous_status' => $previousStatus, 'status' => $status],
                'created_at' => $now,
            ]);

            Alert::query()->firstOrCreate([
                'router_id' => $router->getKey(),
                'type' => $type,
                'status' => 'open',
            ], [
                'severity' => $isOnline ? 'info' : 'critical',
                'message' => $message,
            ]);

            if ($isOnline) {
                Alert::query()
                    ->where('router_id', $router->getKey())
                    ->where('type', 'router_offline')
                    ->where('status', 'open')
                    ->update(['status' => 'resolved', 'resolved_at' => $now]);
            }
        } catch (Throwable $exception) {
            Log::warning('Router connection transition could not be recorded.', [
                'router_id' => $router->getKey(),
                'exception_class' => $exception::class,
            ]);
        }
    }

    public function checkConnection(Router $router): bool
    {
        try {
            $client = new Client($this->configurationFor($router));
            $response = $client->query(new Query('/system/identity/print'))->read();

            return ! empty($response);
        } catch (Throwable $exception) {
            Log::warning('RouterOS connection check failed.', [
                'router_id' => $router->exists ? $router->getKey() : null,
                'exception_class' => $exception::class,
            ]);

            return false;
        }
    }

    /**
     * Fetch one of the application's allowlisted print endpoints.
     *
     * @return list<array<string, string>>
     */
    public function getPrintRows(Router $router, string $endpoint): array
    {
        if (! in_array($endpoint, self::PRINT_ENDPOINTS, true)) {
            throw new InvalidArgumentException('Unsupported RouterOS endpoint.');
        }

        $client = new Client($this->configurationFor($router));
        $rows = $client->query(new Query($endpoint))->read();

        if (! is_array($rows)) {
            return [];
        }

        return array_slice(array_values(array_filter($rows, 'is_array')), 0, 500);
    }

    /**
     * @return list<array<string, string>>
     */
    public function getModuleRows(Router $router, string $moduleKey): array
    {
        if (in_array($moduleKey, ['bgp', 'bgp-peers', 'bgp-topology', 'bgp-configuration'], true)) {
            return $this->getBgpRows($router, $moduleKey);
        }

        if ($moduleKey === 'traffic') {
            return $this->getTrafficRows($router);
        }

        $endpoint = match ($moduleKey) {
            'network-interfaces', 'configuration-interfaces' => '/interface/print',
            'ip-addresses', 'configuration-ip-address' => '/ip/address/print',
            'routing-table', 'static-routes', 'configuration-routes' => '/ip/route/print',
            'bgp-routes' => $this->isRouterOsSeven($router) ? '/routing/route/print' : '/ip/route/print',
            'network-topology', 'physical-logical-map' => '/ip/neighbor/print',
            'identity' => '/system/identity/print',
            'dhcp' => '/ip/dhcp-server/print',
            'dns' => '/ip/dns/print',
            'nat' => '/ip/firewall/nat/print',
            'firewall' => '/ip/firewall/filter/print',
            'logs' => '/log/print',
            'cpu-memory', 'network-monitor' => null,
            default => throw new InvalidArgumentException('Unsupported router data module.'),
        };

        if ($endpoint === null) {
            return [$this->safeSystemInfo($this->getSystemInfo($router))];
        }

        $rows = $this->getPrintRows($router, $endpoint);

        if ($moduleKey === 'static-routes') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $this->isStaticRoute($row)));
        } elseif ($moduleKey === 'bgp-routes') {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => strtolower((string) ($row['protocol'] ?? '')) === 'bgp'));
        }

        return array_map(fn (array $row): array => $this->allowlistModuleRow($moduleKey, $row, $router->password), $rows);
    }

    /** @param array<string, mixed> $attributes */
    public function addStaticRoute(Router $router, array $attributes): void
    {
        $destination = trim((string) ($attributes['destination'] ?? ''));
        $gateway = trim((string) ($attributes['gateway'] ?? ''));
        $distance = (int) ($attributes['distance'] ?? 1);
        $routingMark = trim((string) ($attributes['routing_mark'] ?? ''));
        $comment = trim((string) ($attributes['comment'] ?? ''));

        if (! preg_match('/^(?:\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/', $destination)
            || ! filter_var(explode('/', $destination)[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || ! preg_match('/^[A-Za-z0-9_.:-]{1,255}$/', $gateway)
            || $distance < 1
            || $distance > 255
        ) {
            throw new InvalidArgumentException('Static route fields are invalid.');
        }

        $attributesForApi = [
            '=dst-address='.$destination,
            '=gateway='.$gateway,
            '=distance='.$distance,
        ];

        if ($routingMark !== '') {
            $attributesForApi[] = (int) explode('.', (string) ($this->getSystemInfo($router)['version'] ?? '0'))[0] >= 7
                ? '=routing-table='.$this->safeApiToken($routingMark)
                : '=routing-mark='.$this->safeApiToken($routingMark);
        }

        if ($comment !== '') {
            $attributesForApi[] = '=comment='.mb_substr($comment, 0, 255);
        }

        $this->execute($router, new Query('/ip/route/add', $attributesForApi));
    }

    public function deleteStaticRoute(Router $router, string $routeId): void
    {
        $this->assertStaticRoute($router, $routeId);
        $this->execute($router, new Query('/ip/route/remove', ['=.id='.$this->safeApiToken($routeId)]));
    }

    public function setStaticRouteDisabled(Router $router, string $routeId, bool $disabled): void
    {
        $this->assertStaticRoute($router, $routeId);
        $this->execute($router, new Query('/ip/route/set', [
            '=.id='.$this->safeApiToken($routeId),
            '=disabled='.($disabled ? 'yes' : 'no'),
        ]));
    }

    public function setIdentity(Router $router, string $identity): void
    {
        $identity = trim($identity);

        if ($identity === '' || mb_strlen($identity) > 255 || preg_match('/[\r\n\x00]/', $identity)) {
            throw new InvalidArgumentException('Router identity is invalid.');
        }

        $this->execute($router, new Query('/system/identity/set', ['=name='.$identity]));
    }

    /**
     * @return array{
     *     identity: ?string,
     *     version: ?string,
     *     architecture: ?string,
     *     cpu_load: ?int,
     *     memory_total: ?int,
     *     memory_free: ?int,
     *     uptime: ?string
     * }
     */
    public function getSystemInfo(Router $router): array
    {
        $client = new Client($this->configurationFor($router));
        $identityResponse = $client->query(new Query('/system/identity/print'))->read();
        $resourceResponse = $client->query(new Query('/system/resource/print'))->read();

        $identity = $identityResponse[0] ?? [];
        $resource = $resourceResponse[0] ?? [];

        return [
            'identity' => $this->safeRouterText($identity['name'] ?? null, $router),
            'version' => $this->safeRouterText($resource['version'] ?? null, $router),
            'architecture' => $this->safeRouterText($resource['architecture-name'] ?? null, $router),
            'cpu_load' => is_numeric($resource['cpu-load'] ?? null) ? (int) $resource['cpu-load'] : null,
            'memory_total' => is_numeric($resource['total-memory'] ?? null) ? (int) $resource['total-memory'] : null,
            'memory_free' => is_numeric($resource['free-memory'] ?? null) ? (int) $resource['free-memory'] : null,
            'uptime' => $this->stringOrNull($resource['uptime'] ?? null),
        ];
    }

    /** @return list<array<string, string>> */
    private function getBgpRows(Router $router, string $moduleKey): array
    {
        $peerEndpoint = $this->isRouterOsSeven($router)
            ? '/routing/bgp/connection/print'
            : '/routing/bgp/peer/print';
        $peerRows = $this->getPrintRows($router, $peerEndpoint);
        $sessionRows = $this->getPrintRows($router, '/routing/bgp/session/print');

        foreach ($peerRows as &$peer) {
            $peerAddress = (string) ($peer['remote-address'] ?? $peer['remote.address'] ?? '');

            foreach ($sessionRows as $session) {
                $sessionAddress = (string) ($session['remote-address'] ?? $session['remote.address'] ?? '');

                if ($peerAddress !== '' && $peerAddress === $sessionAddress) {
                    $peer = array_merge($peer, $session);
                    break;
                }
            }
        }
        unset($peer);

        $rows = $peerRows !== [] ? $peerRows : $sessionRows;

        return array_map(fn (array $row): array => $this->allowlistModuleRow($moduleKey, $row, $router->password), $rows);
    }

    private function isRouterOsSeven(Router $router): bool
    {
        $version = $this->getSystemInfo($router)['version'] ?? '';

        return (int) explode('.', (string) $version)[0] >= 7;
    }

    /** @param array<string, mixed> $row */
    private function isStaticRoute(array $row): bool
    {
        $dynamic = strtolower((string) ($row['dynamic'] ?? ''));
        $static = strtolower((string) ($row['static'] ?? ''));
        $protocol = strtolower((string) ($row['protocol'] ?? ''));

        return in_array($static, ['true', 'yes', '1'], true)
            || (in_array($dynamic, ['false', 'no', '0'], true) && $dynamic !== '')
            || $protocol === 'static';
    }

    /** @param array<string, mixed> $row @return array<string, string> */
    private function allowlistModuleRow(string $moduleKey, array $row, string $secret): array
    {
        $fields = match ($moduleKey) {
            'network-interfaces', 'configuration-interfaces' => ['name', 'type', 'mac-address', 'mtu', 'running', 'disabled', 'rx-byte', 'tx-byte', 'rx-packet', 'tx-packet'],
            'traffic' => ['name', 'type', 'running', 'rx-byte', 'tx-byte', 'rx-packet', 'tx-packet'],
            'ip-addresses', 'configuration-ip-address' => ['address', 'network', 'interface', 'disabled', 'dynamic'],
            'routing-table', 'static-routes', 'configuration-routes' => ['.id', 'dst-address', 'gateway', 'interface', 'distance', 'scope', 'target-scope', 'routing-mark', 'routing-table', 'active', 'dynamic', 'static', 'protocol', 'disabled', 'comment'],
            'bgp', 'bgp-peers', 'bgp-topology', 'bgp-configuration' => ['name', 'remote-address', 'remote.address', 'remote-address6', 'remote-as', 'local.as', 'local-as', 'state', 'uptime', 'prefix-count', 'prefixes', 'prefixes-sent', 'disabled'],
            'bgp-routes' => ['dst-address', 'gateway', 'bgp-as-path', 'bgp-origin', 'bgp-local-pref', 'bgp-med', 'distance', 'active', 'best', 'bgp-peer'],
            'network-topology', 'physical-logical-map' => ['identity', 'address', 'interface', 'platform', 'version'],
            'identity' => ['name'],
            'dhcp' => ['name', 'interface', 'address-pool', 'lease-time', 'disabled', 'running'],
            'dns' => ['servers', 'dynamic-servers', 'allow-remote-requests', 'cache-size', 'cache-used'],
            'nat' => ['.id', 'chain', 'action', 'protocol', 'src-address', 'dst-address', 'to-addresses', 'to-ports', 'in-interface', 'out-interface', 'disabled', 'comment'],
            'firewall' => ['.id', 'chain', 'action', 'protocol', 'src-address', 'dst-address', 'src-port', 'dst-port', 'in-interface', 'out-interface', 'disabled', 'comment'],
            'logs' => ['time', 'topics', 'message'],
            default => [],
        };

        $safe = [];

        foreach ($fields as $field) {
            $value = $row[$field] ?? null;

            if (is_scalar($value)) {
                $safe[$field] = $this->redactSensitiveText((string) $value, $secret);
            }
        }

        return $safe;
    }

    private function safeSystemInfo(array $systemInfo): array
    {
        return $systemInfo;
    }

    private function redactSensitiveText(string $value, string $secret): string
    {
        $redacted = $secret !== '' ? str_replace($secret, '[redacted]', $value) : $value;

        return preg_replace('/(?i)(password|passwd|secret|credential)(\s*[:=]\s*)[^\s,;]+/', '$1$2[redacted]', $redacted) ?? '';
    }

    private function assertStaticRoute(Router $router, string $routeId): void
    {
        $routeId = $this->safeApiToken($routeId);
        $routes = $this->getPrintRows($router, '/ip/route/print');

        foreach ($routes as $route) {
            if (($route['.id'] ?? null) === $routeId && $this->isStaticRoute($route)) {
                return;
            }
        }

        throw new InvalidArgumentException('The selected static route is no longer available.');
    }

    private function safeApiToken(string $value): string
    {
        if (! preg_match('/^[A-Za-z0-9_.:/*-]{1,255}$/', $value)) {
            throw new InvalidArgumentException('RouterOS identifier is invalid.');
        }

        return $value;
    }

    private function execute(Router $router, Query $query): void
    {
        $client = new Client($this->configurationFor($router));
        $client->query($query)->read();
    }

    private function configurationFor(Router $router): Config
    {
        return new Config([
            'host' => $router->host,
            'user' => $router->username,
            'pass' => $router->password,
            'port' => (int) $router->port,
            'ssl' => $router->connection_type === 'api_ssl',
            'timeout' => 5,
            'socket_timeout' => 5,
        ]);
    }

    private function safeRouterText(mixed $value, Router $router): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($router->password !== '') {
            $value = str_replace($router->password, '[redacted]', $value);
        }

        return $value !== '' ? $this->redactSensitiveText($value, $router->password) : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
