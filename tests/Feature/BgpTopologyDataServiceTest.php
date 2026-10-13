<?php

namespace Tests\Feature;

use App\Models\BgpSession;
use App\Models\Router;
use App\Services\BgpTopologyDataService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BgpTopologyDataServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_recent_bgp_sessions_become_router_nodes_peer_nodes_and_directional_edges(): void
    {
        $router = $this->createRouter('Core-IDBSD', '192.0.2.1');
        $session = BgpSession::query()->create([
            'router_id' => $router->getKey(),
            'peer_name' => 'Transit Peer',
            'remote_address' => '192.0.2.2',
            'remote_as' => 17451,
            'local_as' => 65535,
            'state' => 'established',
            'uptime' => '2d4h',
            'prefixes_received' => 1_057_695,
            'prefixes_sent' => 19_477,
            'checked_at' => now(),
        ]);

        $graph = app(BgpTopologyDataService::class)->build();

        $this->assertCount(2, $graph['nodes']);
        $this->assertCount(1, $graph['edges']);
        $this->assertSame('AS65,535', $graph['nodes'][0]['asLabel']);
        $this->assertSame('Core-IDBSD', $graph['nodes'][0]['identity']);
        $this->assertSame('AS17,451', $graph['nodes'][1]['asLabel']);
        $this->assertSame('established', $graph['nodes'][1]['visualState']);
        $this->assertSame('1,057,695 RX / 19,477 TX pfx', $graph['edges'][0]['prefixLabel']);
        $this->assertNull($graph['edges'][0]['direction']);
        $this->assertSame('Core-IDBSD', $graph['edges'][0]['routerName']);
        $this->assertSame('Transit Peer', $graph['edges'][0]['peerName']);
        $this->assertCount(3, $graph['ringGuides']);
        $this->assertSame('router-'.$router->getKey(), $graph['edges'][0]['source']);
        $this->assertSame('peer-'.$session->getKey(), $graph['edges'][0]['target']);
        $this->assertSame('AS65,535', $graph['nodes'][0]['asLabel']);
    }

    public function test_empty_sessions_produce_an_empty_graph_and_stored_sessions_remain_visible(): void
    {
        Cache::forget('bgp-topology.last-sync');
        $emptyGraph = app(BgpTopologyDataService::class)->build();

        $this->assertSame([], $emptyGraph['nodes']);
        $this->assertSame([], $emptyGraph['edges']);
        $this->assertNull($emptyGraph['lastUpdatedAt']);

        $router = $this->createRouter('Core', '192.0.2.10');
        BgpSession::query()->create([
            'router_id' => $router->getKey(),
            'peer_name' => 'Stale Peer',
            'remote_as' => 64512,
            'state' => 'established',
            'checked_at' => now()->subMinutes(20),
        ]);

        $graph = app(BgpTopologyDataService::class)->build();

        $this->assertCount(2, $graph['nodes']);
        $this->assertCount(1, $graph['edges']);
        $this->assertSame('Stale Peer', $graph['nodes'][1]['identity']);
        $this->assertSame('established', $graph['nodes'][1]['visualState']);
        $this->assertNotNull($graph['lastUpdatedAt']);
    }

    public function test_topology_payload_never_contains_router_credentials(): void
    {
        $router = $this->createRouter('Safe Router', '192.0.2.20', 'router-user', 'router-password');
        BgpSession::query()->create([
            'router_id' => $router->getKey(),
            'peer_name' => 'Peer',
            'remote_address' => '192.0.2.21',
            'remote_as' => 64513,
            'local_as' => 64512,
            'state' => 'unknown',
            'checked_at' => now(),
        ]);

        $payload = json_encode(app(BgpTopologyDataService::class)->build(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('router-user', $payload);
        $this->assertStringNotContainsString('router-password', $payload);
    }

    private function createRouter(string $name, string $host, string $username = 'api-user', string $password = 'api-secret'): Router
    {
        return Router::query()->create([
            'name' => $name,
            'host' => $host,
            'port' => 28728,
            'username' => $username,
            'password' => $password,
            'connection_type' => 'api',
            'status' => 'online',
        ]);
    }
}
