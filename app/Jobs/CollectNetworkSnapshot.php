<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Models\AvailabilityCheck;
use App\Models\BgpSession;
use App\Models\NetworkEvent;
use App\Models\Router;
use App\Models\RouterMetric;
use App\Models\RouterNeighbor;
use App\Models\TrafficSample;
use App\Services\Mikrotik\MikrotikConnectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CollectNetworkSnapshot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 80;

    /** @param list<int> $routerIds */
    public function __construct(public array $routerIds = []) {}

    public function handle(MikrotikConnectionService $connectionService): void
    {
        $routers = Router::query()
            ->select(['id', 'name', 'host', 'port', 'username', 'password', 'connection_type', 'status', 'last_connected_at'])
            ->when($this->routerIds !== [], fn ($query) => $query->whereIn('id', $this->routerIds))
            ->orderBy('id')
            ->get();

        foreach ($routers as $router) {
            $checkedAt = now();

            try {
                $isOnline = $connectionService->testConnection($router);
                $status = $isOnline ? 'online' : 'offline';
                AvailabilityCheck::query()->create([
                    'router_id' => $router->getKey(),
                    'status' => $status,
                    'checked_at' => $checkedAt,
                ]);


                if (! $isOnline) {
                    continue;
                }

                $systemInfo = $connectionService->getSystemInfo($router);
                RouterMetric::query()->create([
                    'router_id' => $router->getKey(),
                    'cpu_load' => $systemInfo['cpu_load'],
                    'memory_total' => $systemInfo['memory_total'],
                    'memory_free' => $systemInfo['memory_free'],
                    'uptime' => $systemInfo['uptime'],
                    'sampled_at' => $checkedAt,
                ]);

                $this->recordThresholdAlerts($router, $systemInfo, $checkedAt);
                $this->collectTrafficCounters($connectionService, $router, $checkedAt);
                $this->collectNeighbors($connectionService, $router, $checkedAt);
                $this->collectBgpSessions($connectionService, $router, $checkedAt);
            } catch (Throwable $exception) {
                Log::warning('Router snapshot collection failed.', [
                    'router_id' => $router->getKey(),
                    'exception_class' => $exception::class,
                ]);
            }
        }

    }


    /** @param array<string, mixed> $systemInfo */
    private function recordThresholdAlerts(Router $router, array $systemInfo, mixed $checkedAt): void
    {
        $cpuLoad = $systemInfo['cpu_load'] ?? null;
        $totalMemory = $systemInfo['memory_total'] ?? null;
        $freeMemory = $systemInfo['memory_free'] ?? null;

        if (is_numeric($cpuLoad) && (int) $cpuLoad >= 85) {
            $this->openThresholdAlert($router, 'high_cpu', 'warning', 'Router CPU load is at or above 85%.', $checkedAt);
        } else {
            $this->resolveThresholdAlert($router, 'high_cpu', $checkedAt);
        }

        if (is_numeric($totalMemory) && is_numeric($freeMemory) && (int) $totalMemory > 0) {
            $usedPercent = (((int) $totalMemory - (int) $freeMemory) / (int) $totalMemory) * 100;

            if ($usedPercent >= 85) {
                $this->openThresholdAlert($router, 'high_memory', 'warning', 'Router memory usage is at or above 85%.', $checkedAt);
            } else {
                $this->resolveThresholdAlert($router, 'high_memory', $checkedAt);
            }
        }
    }

    private function openThresholdAlert(Router $router, string $type, string $severity, string $message, mixed $checkedAt): void
    {
        Alert::query()->firstOrCreate([
            'router_id' => $router->getKey(),
            'type' => $type,
            'status' => 'open',
        ], [
            'severity' => $severity,
            'message' => $message,
            'created_at' => $checkedAt,
        ]);
    }

    private function resolveThresholdAlert(Router $router, string $type, mixed $checkedAt): void
    {
        Alert::query()
            ->where('router_id', $router->getKey())
            ->where('type', $type)
            ->where('status', 'open')
            ->update(['status' => 'resolved', 'resolved_at' => $checkedAt]);
    }

    private function collectTrafficCounters(MikrotikConnectionService $connectionService, Router $router, mixed $sampledAt): void
    {
        $previousSamples = TrafficSample::query()
            ->where('router_id', $router->getKey())
            ->where('sampled_at', '>=', now()->subDay())
            ->orderByDesc('sampled_at')
            ->get()
            ->unique('interface_name')
            ->keyBy('interface_name');

        foreach ($connectionService->getPrintRows($router, '/interface/print') as $interface) {
            $name = $interface['name'] ?? null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            $rxBytes = $this->integerOrNull($interface['rx-byte'] ?? null);
            $txBytes = $this->integerOrNull($interface['tx-byte'] ?? null);
            $rxPackets = $this->integerOrNull($interface['rx-packet'] ?? null);
            $txPackets = $this->integerOrNull($interface['tx-packet'] ?? null);
            $previous = $previousSamples->get($name);
            $elapsedSeconds = $previous?->sampled_at?->diffInSeconds($sampledAt, absolute: true) ?? 0;

            $rate = static function (?int $current, ?int $previousValue, int $elapsed, bool $bits = false): ?int {
                if ($current === null || $previousValue === null || $elapsed < 1 || $current < $previousValue) {
                    return null;
                }

                $rate = ($current - $previousValue) / $elapsed;

                return (int) round($bits ? $rate * 8 : $rate);
            };

            TrafficSample::query()->create([
                'router_id' => $router->getKey(),
                'interface_name' => $name,
                'rx_bytes' => $rxBytes,
                'tx_bytes' => $txBytes,
                'rx_packets' => $rxPackets,
                'tx_packets' => $txPackets,
                'rx_bps' => $rate($rxBytes, $previous?->rx_bytes, $elapsedSeconds, bits: true),
                'tx_bps' => $rate($txBytes, $previous?->tx_bytes, $elapsedSeconds, bits: true),
                'rx_pps' => $rate($rxPackets, $previous?->rx_packets, $elapsedSeconds),
                'tx_pps' => $rate($txPackets, $previous?->tx_packets, $elapsedSeconds),
                'sampled_at' => $sampledAt,
            ]);
        }
    }

    private function collectNeighbors(MikrotikConnectionService $connectionService, Router $router, mixed $observedAt): void
    {
        foreach ($connectionService->getPrintRows($router, '/ip/neighbor/print') as $neighbor) {
            $neighborAddress = $neighbor['address'] ?? null;

            if (! is_string($neighborAddress) || $neighborAddress === '') {
                continue;
            }

            $matchedRouter = Router::query()->where('host', $neighborAddress)->first();

            RouterNeighbor::query()->updateOrCreate([
                'router_id' => $router->getKey(),
                'neighbor_address' => $neighborAddress,
                'local_interface' => $neighbor['interface'] ?? null,
            ], [
                'neighbor_router_id' => $matchedRouter?->getKey(),
                'neighbor_identity' => $neighbor['identity'] ?? null,
                'platform' => $neighbor['platform'] ?? null,
                'protocol' => $neighbor['protocol'] ?? null,
                'observed_at' => $observedAt,
            ]);
        }
    }

    private function collectBgpSessions(MikrotikConnectionService $connectionService, Router $router, mixed $checkedAt): void
    {
        try {
            $rows = $connectionService->getModuleRows($router, 'bgp-peers');
        } catch (Throwable $exception) {
            Log::info('BGP session endpoint not available for router.', [
                'router_id' => $router->getKey(),
                'exception_class' => $exception::class,
            ]);

            return;
        }

        foreach ($rows as $row) {
            $remoteAddress = $row['remote-address'] ?? null;
            $peerName = $row['name'] ?? null;

            if (! is_string($remoteAddress) && ! is_string($peerName)) {
                continue;
            }

            BgpSession::query()->updateOrCreate([
                'router_id' => $router->getKey(),
                'remote_address' => is_string($remoteAddress) ? $remoteAddress : null,
                'peer_name' => is_string($peerName) ? $peerName : null,
            ], [
                'remote_as' => $this->integerOrNull($row['remote-as'] ?? null),
                'local_as' => $this->integerOrNull($row['local-as'] ?? null),
                'state' => strtolower((string) ($row['state'] ?? 'unknown')),
                'uptime' => $row['uptime'] ?? null,
                'prefixes_received' => $this->integerOrNull($row['prefix-count'] ?? $row['prefixes'] ?? null),
                'checked_at' => $checkedAt,
            ]);
        }
    }

    private function integerOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
    }
}
