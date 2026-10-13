<?php

namespace Tests\Feature;

use App\Models\Router;
use App\Services\Mikrotik\MikrotikConnectionService;
use App\Services\Mikrotik\RouterStatusMonitoringService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RouterStatusMonitoringServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_monitoring_returns_safe_router_rows_and_continues_after_a_router_error(): void
    {
        $onlineRouter = $this->createRouter('Alpha Router', '192.0.2.1', 'alpha-secret');
        $failingRouter = $this->createRouter('Beta Router', '192.0.2.2', 'beta-secret');
        $connectionService = $this->mock(MikrotikConnectionService::class);
        $connectionService
            ->shouldReceive('testConnection')
            ->twice()
            ->andReturnUsing(function (Router $router): bool {
                if ($router->name === 'Beta Router') {
                    throw new \RuntimeException('Private API exception with password-like content.');
                }

                $router->update([
                    'status' => 'online',
                    'last_connected_at' => now(),
                ]);

                return true;
            });
        $connectionService
            ->shouldReceive('getSystemInfo')
            ->once()
            ->withAnyArgs()
            ->andReturn([
                'cpu_load' => 18,
                'memory_total' => 1_000_000,
                'memory_free' => 400_000,
                'uptime' => '2d4h',
            ]);

        $rows = app(RouterStatusMonitoringService::class)->monitorAll();

        $this->assertSame(['Alpha Router', 'Beta Router'], array_column($rows, 'name'));
        $this->assertSame(['online', 'offline'], array_column($rows, 'status'));
        $this->assertSame(18, $rows[0]['cpu_load']);
        $this->assertSame('2d4h', $rows[0]['uptime']);
        $this->assertNull($rows[1]['cpu_load']);
        $this->assertSame('online', $onlineRouter->fresh()->status);
        $this->assertSame('offline', $failingRouter->fresh()->status);
        $this->assertSame([
            '__key',
            'id',
            'name',
            'ip_address',
            'status',
            'cpu_load',
            'memory_total',
            'memory_free',
            'uptime',
        ], array_keys($rows[0]));
        $this->assertStringNotContainsString('alpha-secret', json_encode($rows));
        $this->assertStringNotContainsString('beta-secret', json_encode($rows));
    }

    private function createRouter(string $name, string $host, string $password): Router
    {
        return Router::query()->create([
            'name' => $name,
            'host' => $host,
            'port' => 8728,
            'username' => "{$name}-user",
            'password' => $password,
            'connection_type' => 'api',
            'status' => 'unknown',
        ]);
    }
}
