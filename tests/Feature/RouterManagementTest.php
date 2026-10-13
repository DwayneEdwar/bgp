<?php

namespace Tests\Feature;

use App\Filament\Resources\Routers\Pages\CreateRouter;
use App\Filament\Resources\Routers\Pages\EditRouter;
use App\Filament\Resources\Routers\Pages\ListRouters;
use App\Filament\Resources\Routers\Pages\ViewRouter;
use App\Models\DiscoveredDevice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Router;
use App\Models\User;
use App\Services\Mikrotik\MikrotikConnectionService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Tests\TestCase;

class RouterManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $superAdmin = Role::query()->firstOrCreate(['name' => 'Super Admin']);
        $user->roles()->attach($superAdmin);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_router_list_uses_persisted_data_without_probing_routers(): void
    {
        $router = $this->createRouter('Border', '192.0.2.1', 28728, 'online');
        $service = $this->mock(MikrotikConnectionService::class);
        $service->shouldNotReceive('testConnection');
        $service->shouldNotReceive('getSystemInfo');

        $this->get(route('filament.admin.resources.routers.index'))
            ->assertOk()
            ->assertSee('Border')
            ->assertSee('192.0.2.1')
            ->assertSee('28728')
            ->assertDontSee('router-secret');

        Livewire::test(ListRouters::class)
            ->assertActionExists(TestAction::make('testConnection')->table($router))
            ->assertActionExists(TestAction::make('view')->table($router))
            ->assertActionExists(TestAction::make('edit')->table($router))
            ->assertActionExists(TestAction::make('delete')->table($router));

        $this->assertSame('online', $router->fresh()->status);
    }

    public function test_router_create_encrypts_password_and_preserves_custom_ports(): void
    {
        $plainPassword = 'do-not-render-this-password';

        Livewire::test(CreateRouter::class)
            ->fillForm([
                'name' => 'Border Router',
                'host' => '192.168.101.1',
                'port' => 28728,
                'username' => 'api-operator',
                'password' => $plainPassword,
                'connection_type' => 'api_ssl',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $router = Router::query()->where('host', '192.168.101.1')->firstOrFail();

        $this->assertSame(28728, (int) $router->port);
        $this->assertSame('api_ssl', $router->connection_type);
        $this->assertSame(0, Router::query()->where('host', '192.168.101.1')->where('port', 8728)->count());
        $this->assertSame('unknown', $router->status);
        $this->assertSame($plainPassword, $router->password);
        $this->assertNotSame($plainPassword, $router->getRawOriginal('password'));
        $this->assertSame($plainPassword, Crypt::decryptString($router->getRawOriginal('password')));
    }

    public function test_router_form_rejects_invalid_ip_port_and_duplicate_host_port_pair(): void
    {
        $this->createRouter('Existing', '192.0.2.10', 8728);

        Livewire::test(CreateRouter::class)
            ->fillForm([
                'name' => 'Invalid Router',
                'host' => '999.999.999.999',
                'port' => 70000,
                'username' => 'operator',
                'password' => 'new-secret',
                'connection_type' => 'api',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'host' => 'ipv4',
                'port' => 'between',
            ]);

        Livewire::test(CreateRouter::class)
            ->fillForm([
                'name' => 'Duplicate Router',
                'host' => '192.0.2.10',
                'port' => 8728,
                'username' => 'operator',
                'password' => 'new-secret',
                'connection_type' => 'api',
            ])
            ->call('create')
            ->assertHasFormErrors(['host']);

        Livewire::test(CreateRouter::class)
            ->fillForm([
                'name' => 'Different API port',
                'host' => '192.0.2.10',
                'port' => 28728,
                'username' => 'operator',
                'password' => 'new-secret',
                'connection_type' => 'api',
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_router_edit_keeps_password_blank_and_retains_it_when_omitted(): void
    {
        $router = $this->createRouter('Edge', '192.0.2.11', 8728, password: 'existing-password');

        Livewire::test(EditRouter::class, ['record' => $router->getRouteKey()])
            ->assertSchemaStateSet(['password' => null])
            ->fillForm([
                'name' => 'Edge Updated',
                'host' => '192.0.2.11',
                'port' => 8728,
                'username' => 'operator',
                'password' => '',
                'connection_type' => 'api',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('existing-password', $router->fresh()->password);
        $this->assertSame('Edge Updated', $router->fresh()->name);
    }

    public function test_router_detail_displays_safe_information_without_connecting_on_page_load(): void
    {
        $router = $this->createRouter('Core', '192.0.2.12', 8728, 'offline', 'router-secret');
        $service = $this->mock(MikrotikConnectionService::class);
        $service->shouldNotReceive('testConnection');
        $service->shouldNotReceive('getSystemInfo');

        $this->get(route('filament.admin.resources.routers.view', ['record' => $router->getRouteKey()]))
            ->assertOk()
            ->assertSee('Basic Information')
            ->assertSee('Connection')
            ->assertSee('System Information')
            ->assertSee('192.0.2.12')
            ->assertSee('8728')
            ->assertSee('Not retrieved')
            ->assertDontSee('router-secret');
    }

    public function test_router_detail_memory_formatter_handles_normal_and_zero_memory(): void
    {
        $router = $this->createRouter('Memory Router', '192.0.2.13');

        $page = Livewire::test(ViewRouter::class, ['record' => $router->getRouteKey()]);
        $page->set('systemInfo', [
            'identity' => 'edge',
            'version' => '7.15',
            'architecture' => 'arm',
            'cpu_load' => 12,
            'memory_total' => 32 * 1024 * 1024,
            'memory_free' => 9 * 1024 * 1024,
            'uptime' => '3d12h24m',
        ]);

        $memory = $page->instance()->getFormattedMemory();
        $this->assertSame('32 MB', $memory['total']);
        $this->assertSame('23 MB', $memory['used']);
        $this->assertSame('9 MB', $memory['free']);
        $this->assertSame('71.9%', $memory['usage']);

        $page->set('systemInfo.memory_total', 0);
        $this->assertSame('N/A', $page->instance()->getFormattedMemory()['usage']);
    }

    public function test_manual_test_connection_updates_status_and_last_connected_without_notification_secrets(): void
    {
        $router = $this->createRouter('Manual Probe', '192.0.2.14', 8728, password: 'secret-to-keep-private');
        $service = $this->mock(MikrotikConnectionService::class);
        $service->shouldReceive('testConnection')
            ->once()
            ->withArgs(fn (Router $argument): bool => $argument->is($router))
            ->andReturnUsing(function (Router $argument): bool {
                $argument->update([
                    'status' => 'online',
                    'last_connected_at' => now(),
                ]);

                return true;
            });

        Livewire::test(ListRouters::class)
            ->callAction(TestAction::make('testConnection')->table($router))
            ->assertNotified('Connection successful')
            ->assertDontSee('secret-to-keep-private');

        $this->assertSame('online', $router->fresh()->status);
        $this->assertNotNull($router->fresh()->last_connected_at);
    }

    public function test_router_delete_retains_discovered_device_and_nulls_router_id(): void
    {
        $router = $this->createRouter('Delete me', '192.0.2.15');
        $device = DiscoveredDevice::query()->create([
            'ip_address' => '192.0.2.15',
            'api_port' => 8728,
            'status' => 'online',
            'router_id' => $router->getKey(),
        ]);

        $this->assertSame($router->getKey(), $device->fresh()->router_id);
        $this->assertTrue($router->discoveredDevices()->exists());

        $router->delete();

        $this->assertDatabaseHas('discovered_devices', [
            'id' => $device->getKey(),
            'router_id' => null,
        ]);
    }

    private function createRouter(
        string $name,
        string $host,
        int $port = 8728,
        string $status = 'unknown',
        string $password = 'router-secret',
    ): Router {
        return Router::query()->create([
            'name' => $name,
            'host' => $host,
            'port' => $port,
            'username' => 'api-user',
            'password' => $password,
            'connection_type' => 'api',
            'status' => $status,
        ]);
    }
}
