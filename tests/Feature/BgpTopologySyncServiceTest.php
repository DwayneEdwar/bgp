<?php

namespace Tests\Feature;

use App\Models\BgpSession;
use App\Models\Router;
use App\Services\Mikrotik\BgpTopologySyncService;
use App\Services\Mikrotik\MikrotikConnectionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BgpTopologySyncServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sync_saves_allowed_bgp_fields_and_continues_after_a_router_failure(): void
    {
        $successfulRouter = $this->createRouter('Core', '192.0.2.1', 'online');
        $failedRouter = $this->createRouter('Border', '192.0.2.2', 'online');
        $offlineRouter = $this->createRouter('Offline', '192.0.2.3', 'offline');
        $existingSession = BgpSession::query()->create([
            'router_id' => $failedRouter->getKey(),
            'peer_name' => 'Existing Peer',
            'remote_address' => '192.0.2.8',
            'remote_as' => 64514,
            'local_as' => 64512,
            'state' => 'established',
            'checked_at' => now()->subMinute(),
        ]);
        $connectionService = $this->mock(MikrotikConnectionService::class);
        $connectionService->shouldReceive('getModuleRows')
            ->twice()
            ->withArgs(fn (Router $router, string $module): bool => $module === 'bgp-topology'
                && in_array($router->getKey(), [$successfulRouter->getKey(), $failedRouter->getKey()], true))
            ->andReturnUsing(function (Router $router): array {
                if ($router->name === 'Border') {
                    throw new \InvalidArgumentException('Private router response details.');
                }

                return [[
                    'name' => 'Transit A',
                    'remote-address' => '192.0.2.9',
                    'remote-as' => '64513',
                    'local-as' => '64512',
                    'state' => 'established',
                    'uptime' => '2d4h',
                    'prefix-count' => '1000',
                    'prefixes-sent' => '200',
                    'password' => 'must-not-be-saved',
                ]];
            });

        $result = app(BgpTopologySyncService::class)->sync();

        $session = BgpSession::query()->whereBelongsTo($successfulRouter, 'router')->firstOrFail();
        $this->assertSame(2, $result['routersTotal']);
        $this->assertSame(1, $result['routersSynced']);
        $this->assertSame(1, $result['routersFailed']);
        $this->assertSame(1, $result['sessionsUpdated']);
        $this->assertSame('192.0.2.9', $session->remote_address);
        $this->assertSame(64512, $session->local_as);
        $this->assertSame(64513, $session->remote_as);
        $this->assertSame(1000, $session->prefixes_received);
        $this->assertSame(200, $session->prefixes_sent);
        $this->assertSame('Border', $result['errors'][0]['router']);
        $this->assertStringNotContainsString('Private router response', json_encode($result));
        $this->assertSame('established', $existingSession->fresh()->state);
        $this->assertNotNull(Cache::get('bgp-topology.last-sync'));
        $this->assertSame('offline', $offlineRouter->fresh()->status);
    }

    public function test_sync_reports_when_no_routers_are_online_without_calling_mikrotik(): void
    {
        $this->createRouter('Offline', '192.0.2.20', 'offline');
        $connectionService = $this->mock(MikrotikConnectionService::class);
        $connectionService->shouldNotReceive('getModuleRows');

        $result = app(BgpTopologySyncService::class)->sync();

        $this->assertTrue($result['noOnlineRouters']);
        $this->assertSame(0, $result['routersTotal']);
        $this->assertSame(0, $result['sessionsUpdated']);
    }

    private function createRouter(string $name, string $host, string $status): Router
    {
        return Router::query()->create([
            'name' => $name,
            'host' => $host,
            'port' => 28728,
            'username' => 'api-user',
            'password' => 'api-secret',
            'connection_type' => 'api',
            'status' => $status,
        ]);
    }
}
