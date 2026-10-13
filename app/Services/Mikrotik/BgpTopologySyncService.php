<?php

namespace App\Services\Mikrotik;

use App\Models\BgpSession;
use App\Models\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class BgpTopologySyncService
{
    public function __construct(private MikrotikConnectionService $connectionService) {}

    /**
     * @return array{
     *     routersTotal: int,
     *     routersSynced: int,
     *     routersFailed: int,
     *     sessionsUpdated: int,
     *     noOnlineRouters: bool,
     *     errors: list<array{router: string, message: string}>
     * }
     */
    public function sync(): array
    {
        $routers = Router::query()
            ->select(['id', 'name', 'host', 'port', 'username', 'password', 'connection_type', 'status'])
            ->where('status', 'online')
            ->orderBy('id')
            ->get();

        $result = [
            'routersTotal' => $routers->count(),
            'routersSynced' => 0,
            'routersFailed' => 0,
            'sessionsUpdated' => 0,
            'noOnlineRouters' => $routers->isEmpty(),
            'errors' => [],
        ];

        foreach ($routers as $router) {
            try {
                $rows = $this->connectionService->getModuleRows($router, 'bgp-topology');
                $checkedAt = now();

                foreach ($rows as $row) {
                    $peerName = $this->stringOrNull($row['name'] ?? null);
                    $remoteAddress = $this->stringOrNull(
                        $row['remote-address'] ?? $row['remote.address'] ?? $row['remote-address6'] ?? null,
                    );

                    if ($peerName === null && $remoteAddress === null) {
                        continue;
                    }

                    BgpSession::query()->updateOrCreate([
                        'router_id' => $router->getKey(),
                        'remote_address' => $remoteAddress,
                        'peer_name' => $peerName,
                    ], [
                        'local_as' => $this->integerOrNull($row['local-as'] ?? $row['local.as'] ?? null),
                        'remote_as' => $this->integerOrNull($row['remote-as'] ?? null),
                        'state' => strtolower($this->stringOrNull($row['state'] ?? null) ?? 'unknown'),
                        'uptime' => $this->stringOrNull($row['uptime'] ?? null),
                        'prefixes_received' => $this->integerOrNull($row['prefix-count'] ?? $row['prefixes'] ?? null),
                        'prefixes_sent' => $this->integerOrNull($row['prefixes-sent'] ?? null),
                        'checked_at' => $checkedAt,
                    ]);

                    $result['sessionsUpdated']++;
                }

                $result['routersSynced']++;
            } catch (Throwable $exception) {
                $result['routersFailed']++;
                $result['errors'][] = [
                    'router' => $router->name,
                    'message' => 'Unable to read BGP sessions from this router.',
                ];

                Log::warning('BGP topology sync failed for router.', [
                    'router_id' => $router->getKey(),
                    'exception_class' => $exception::class,
                ]);
            }
        }

        if ($result['routersSynced'] > 0) {
            Cache::put('bgp-topology.last-sync', now()->toIso8601String());
        }

        return $result;
    }

    private function integerOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
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
