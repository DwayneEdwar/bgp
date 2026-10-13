<?php

namespace Tests\Feature;

use App\Filament\Pages\ComingSoon;
use App\Filament\Pages\NocDashboard;
use App\Filament\Resources\Routers\RouterResource;
use App\Models\DiscoveredDevice;
use App\Models\Router;
use App\Models\User;
use App\Services\Mikrotik\MikrotikConnectionService;
use Filament\Panel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NocDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_dashboard_request_redirects_to_login(): void
    {
        $this->get(NocDashboard::getUrl(panel: 'admin'))
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_filament_login_and_profile_routes_use_existing_auth_fields(): void
    {
        $this->withoutVite();

        $this->get(route('filament.admin.auth.login'))
            ->assertOk()
            ->assertSee('Email address')
            ->assertSee('Password')
            ->assertSee('Remember me');

        $this->actingAs(User::factory()->create())
            ->get(route('filament.admin.auth.profile'))
            ->assertOk()
            ->assertSee('Name')
            ->assertSee('Email');
    }

    public function test_authenticated_dashboard_renders_empty_and_unmonitored_states(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(NocDashboard::getUrl(panel: 'admin'));

        $response
            ->assertOk()
            ->assertSee('MikroTik Network Management &amp; NOC', false)
            ->assertSee('Network Operations Center')
            ->assertSee('Total Routers')
            ->assertSee('Router Status')
            ->assertSee('No router records available')
            ->assertSee('Discovered Devices')
            ->assertSee('No discovered devices available')
            ->assertSee('No recent network events')
            ->assertSee($user->name)
            ->assertSee($user->email)
            ->assertSee('Profile')
            ->assertSee('Logout');
    }

    public function test_module_placeholder_only_displays_allowlisted_module_details(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(ComingSoon::getUrl(['module' => 'bgp'], panel: 'admin'));

        $response
            ->assertOk()
            ->assertSee('BGP')
            ->assertSee('Coming Soon')
            ->assertSee('Modul BGP belum tersedia pada Step 1.');
    }

    public function test_dashboard_counts_discovered_and_registered_devices_from_saved_records(): void
    {
        $this->withoutVite();
        $router = Router::query()->create([
            'name' => 'Registered Router',
            'host' => '192.0.2.3',
            'port' => 8728,
            'username' => 'device-link-user',
            'password' => 'device-link-secret',
            'connection_type' => 'api',
            'status' => 'unknown',
        ]);
        DiscoveredDevice::query()->create([
            'ip_address' => '192.0.2.3',
            'api_port' => 8728,
            'hostname' => 'edge-device',
            'identity' => 'edge-01',
            'device_type' => 'router',
            'api_available' => true,
            'status' => 'online',
            'router_id' => $router->getKey(),
        ]);
        $user = User::factory()->create();
        $connectionService = $this->mock(MikrotikConnectionService::class);
        $connectionService->shouldNotReceive('testConnection');
        $connectionService->shouldNotReceive('getSystemInfo');

        $response = $this->actingAs($user)->get(NocDashboard::getUrl(panel: 'admin'));

        $response
            ->assertOk()
            ->assertSee('1')
            ->assertSee('edge-01')
            ->assertSee('Registered')
            ->assertDontSee('device-link-secret')
            ->assertDontSee('device-link-user');
    }

    public function test_dashboard_shows_persisted_router_status_without_exposing_credentials(): void
    {
        $this->withoutVite();
        $router = Router::query()->create([
            'name' => 'Core Router',
            'host' => '192.0.2.1',
            'port' => 8728,
            'username' => 'noc-user',
            'password' => 'router-secret-for-test',
            'connection_type' => 'api',
            'status' => 'online',
        ]);
        $user = User::factory()->create();
        $connectionService = $this->mock(MikrotikConnectionService::class);
        $connectionService->shouldNotReceive('testConnection');
        $connectionService->shouldNotReceive('getSystemInfo');

        $response = $this->actingAs($user)->get(NocDashboard::getUrl(panel: 'admin'));

        $response
            ->assertOk()
            ->assertSee('Total Routers')
            ->assertSee('Online')
            ->assertSee('192.0.2.1')
            ->assertSee('Stored status')
            ->assertSee('1')
            ->assertDontSee('router-secret-for-test')
            ->assertDontSee('noc-user');
    }

    public function test_users_can_access_only_the_admin_panel(): void
    {
        $user = User::factory()->make();

        $this->assertTrue($user->canAccessPanel(Panel::make()->id('admin')));
        $this->assertFalse($user->canAccessPanel(Panel::make()->id('other')));
    }

    public function test_existing_router_resource_list_keeps_test_connection_action_available(): void
    {
        $this->withoutVite();
        Router::query()->create([
            'name' => 'Core Router',
            'host' => '192.0.2.1',
            'port' => 8728,
            'username' => 'noc-user',
            'password' => 'router-secret-for-test',
            'connection_type' => 'api',
            'status' => 'unknown',
        ]);
        $user = User::factory()->create();
        $user->roles()->attach(\App\Models\Role::query()->firstOrCreate(['name' => 'Super Admin']));

        $response = $this->actingAs($user)->get(RouterResource::getUrl(panel: 'admin'));

        $response
            ->assertOk()
            ->assertSee('Core Router')
            ->assertSee('Test Connection')
            ->assertDontSee('router-secret-for-test')
            ->assertDontSee('noc-user');
    }

    public function test_existing_router_resource_remains_registered(): void
    {
        $this->assertSame(
            route('filament.admin.resources.routers.index'),
            RouterResource::getUrl(panel: 'admin'),
        );
    }
}
