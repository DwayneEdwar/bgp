<?php

namespace App\Services\Mikrotik;

use App\Models\Router;
use Illuminate\Support\Facades\Log;
use Throwable;

class RouterStatusMonitoringService
{
    public function __construct(private readonly MikrotikConnectionService $connectionService) {}

    /**
     * Probe every registered router and return only fields safe for presentation.
     * Credentials and the Eloquent models stay inside this server-side service.
     *
     * @return list<array{
     *     __key: string,
     *     id: int,
     *     name: string,
     *     ip_address: string,
     *     status: string,
     *     cpu_load: ?int,
     *     memory_total: ?int,
     *     memory_free: ?int,
     *     uptime: ?string
     * }>
     */
    public function monitorAll(): array
    {
        $routers = Router::query()
            ->select(['id', 'name', 'host', 'port', 'username', 'password', 'status'])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($routers as $router) {
            $isOnline = false;
            $systemInfo = [];

            try {
                $isOnline = $this->connectionService->testConnection($router);
            } catch (Throwable) {
                Log::warning('Unexpected error while checking a registered router.', [
                    'router_id' => $router->getKey(),
                ]);

                try {
                    $router->update(['status' => 'offline']);
                } catch (Throwable) {
                    // Keep monitoring the remaining routers if status persistence also fails.
                }
            }

            if ($isOnline) {
                try {
                    $systemInfo = $this->connectionService->getSystemInfo($router);
                } catch (Throwable) {
                    Log::warning('Unable to retrieve system metrics for a registered router.', [
                        'router_id' => $router->getKey(),
                    ]);
                }
            }

            $rows[] = [
                '__key' => (string) $router->getKey(),
                'id' => (int) $router->getKey(),
                'name' => (string) $router->name,
                'ip_address' => (string) $router->host,
                'status' => $isOnline ? 'online' : 'offline',
                'cpu_load' => $systemInfo['cpu_load'] ?? null,
                'memory_total' => $systemInfo['memory_total'] ?? null,
                'memory_free' => $systemInfo['memory_free'] ?? null,
                'uptime' => $systemInfo['uptime'] ?? null,
            ];
        }

        return $rows;
    }
}
