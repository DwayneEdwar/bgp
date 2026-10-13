<?php

namespace Tests\Feature;

use App\Filament\Pages\BgpTopology;
use App\Models\BgpSession;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Router;
use App\Models\User;
use App\Services\Mikrotik\BgpTopologySyncService;
use App\Services\Mikrotik\MikrotikConnectionService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BgpTopologyPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_with_existing_bgp_permission_can_open_page_without_router_api_calls(): void
    {
        $this->withoutVite();
        $user = $this->createSuperAdmin();
        $connectionService = $this->mock(MikrotikConnectionService::class);
        $connectionService->shouldNotReceive('getModuleRows');
        $connectionService->shouldNotReceive('getPrintRows');
        $connectionService->shouldNotReceive('getSystemInfo');

        $response = $this->actingAs($user)
            ->get(BgpTopology::getUrl(panel: 'admin'))
            ->assertOk()
            ->assertSee('BGP Topology')
            ->assertSee('Visual map of BGP sessions fetched from MikroTik devices')
            ->assertSee('Fit View')
            ->assertSee('No BGP topology data available')
            ->assertSee('Sync from MikroTik to discover BGP sessions.');
    }

    public function test_unauthenticated_user_is_redirected_to_filament_login(): void
    {
        $this->get(BgpTopology::getUrl(panel: 'admin'))
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_authenticated_user_without_bgp_permission_is_forbidden(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get(BgpTopology::getUrl(panel: 'admin'))
            ->assertForbidden();
    }

    public function test_page_renders_real_session_data_without_exposing_router_credentials(): void
    {
        $this->withoutVite();
        $user = $this->createSuperAdmin();
        $router = Router::query()->create([
            'name' => 'Page Core Router',
            'host' => '192.0.2.31',
            'port' => 28728,
            'username' => 'page-preview-user',
            'password' => 'page-preview-secret',
            'connection_type' => 'api',
            'status' => 'online',
        ]);
        foreach (range(1, 5) as $peerNumber) {
            BgpSession::query()->create([
                'router_id' => $router->getKey(),
                'peer_name' => $peerNumber === 1 ? 'Page Transit Peer' : "Page Transit Peer {$peerNumber}",
                'remote_address' => '192.0.2.'.(31 + $peerNumber),
                'remote_as' => 64512 + $peerNumber,
                'local_as' => 64512,
                'state' => 'established',
                'uptime' => '1d2h',
                'prefixes_received' => $peerNumber === 1 ? 12_345 : 100 + $peerNumber,
                'prefixes_sent' => $peerNumber === 1 ? 678 : 10 + $peerNumber,
                'checked_at' => now(),
            ]);
        }
        $connectionService = $this->mock(MikrotikConnectionService::class);
        $connectionService->shouldNotReceive('getModuleRows');

        $response = $this->actingAs($user)
            ->get(BgpTopology::getUrl(panel: 'admin'))
            ->assertOk()
            ->assertSee('AS64,512')
            ->assertSee('Page Core Router')
            ->assertSee('AS64,513')
            ->assertSee('Page Transit Peer')
            ->assertSee('12,345 RX')
            ->assertSee('678 TX pfx')
            ->assertDontSee('page-preview-user')
            ->assertDontSee('page-preview-secret');

        $this->assertSame(6, substr_count($response->getContent(), 'class="bgp-node bgp-node--'));
        $this->assertSame(5, substr_count($response->getContent(), 'class="bgp-edge-group"'));
    }

    public function test_sync_action_uses_server_service_and_keeps_partial_failures_visible(): void
    {
        $user = $this->createSuperAdmin();
        $this->actingAs($user);
        $connectionService = $this->mock(MikrotikConnectionService::class);
        $connectionService->shouldNotReceive('getModuleRows');
        $syncService = $this->mock(BgpTopologySyncService::class);
        $syncService->shouldReceive('sync')->once()->andReturn([
            'routersTotal' => 2,
            'routersSynced' => 1,
            'routersFailed' => 1,
            'sessionsUpdated' => 1,
            'noOnlineRouters' => false,
            'errors' => [[
                'router' => 'Border Router',
                'message' => 'Unable to read BGP sessions from this router.',
            ]],
        ]);

        Livewire::test(BgpTopology::class)
            ->call('syncFromMikroTik')
            ->assertSet('syncErrors', [[
                'router' => 'Border Router',
                'message' => 'Unable to read BGP sessions from this router.',
            ]])
            ->assertSet('syncHadNoOnlineRouters', false)
            ->assertSet('canSync', true);
    }

    public function test_user_with_view_bgp_but_without_manage_bgp_cannot_sync(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::query()->firstOrCreate(['name' => 'Topology Viewer']);
        $permission = Permission::query()->firstOrCreate(['name' => 'view BGP']);
        $role->permissions()->syncWithoutDetaching([$permission->getKey()]);
        $user->roles()->attach($role);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get(BgpTopology::getUrl(panel: 'admin'))->assertOk();

        Livewire::test(BgpTopology::class)
            ->call('syncFromMikroTik')
            ->assertForbidden();
    }

    public function test_unexpected_sync_failure_keeps_the_loaded_topology(): void
    {
        $user = $this->createSuperAdmin();
        $this->actingAs($user);
        $connectionService = $this->mock(MikrotikConnectionService::class);
        $connectionService->shouldNotReceive('getModuleRows');
        $syncService = $this->mock(BgpTopologySyncService::class);
        $syncService->shouldReceive('sync')->once()->andThrow(new \RuntimeException('Sensitive upstream details.'));

        Livewire::test(BgpTopology::class)
            ->set('topology', [
                'nodes' => [[
                    'id' => 'router:1',
                    'kind' => 'router',
                    'role' => 'Origin',
                    'visualState' => 'origin',
                    'asLabel' => 'Local AS unavailable',
                    'identity' => 'Border',
                    'state' => 'Origin',
                    'x' => 500,
                    'y' => 350,
                ]],
                'edges' => [],
                'ringGuides' => [],
                'viewBox' => ['width' => 1000, 'height' => 700],
                'lastUpdatedAt' => null,
            ])
            ->call('syncFromMikroTik')
            ->assertSet('topology.nodes.0.id', 'router:1')
            ->assertSet('syncErrors.0.message', 'Unable to synchronize BGP data. Existing topology has been preserved.');
    }

    private function createSuperAdmin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::query()->firstOrCreate(['name' => 'Super Admin']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $user;
    }
}
