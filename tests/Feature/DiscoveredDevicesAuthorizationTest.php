<?php

namespace Tests\Feature;

use App\Filament\Widgets\DiscoveredDevices;
use App\Models\DiscoveredDevice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Router;
use App\Models\User;
use App\Services\Mikrotik\MikrotikConnectionService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class DiscoveredDevicesAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_without_create_routers_is_forbidden_from_register_action_without_creating_router(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $device = $this->createDiscoveredDevice();
        $connectionService = $this->mock(MikrotikConnectionService::class);
        $connectionService->shouldNotReceive('checkConnection');

        $routerCountBefore = Router::query()->count();

        $component = Livewire::test(DiscoveredDevices::class);
        $registerAction = collect($component->instance()->getTable()->getRecordActions())
            ->first(static fn (Action $action): bool => $action->getName() === 'registerRouter');

        $this->assertInstanceOf(Action::class, $registerAction);
        $registerCallback = $registerAction->getActionFunction();

        try {
            $registerCallback($device, [
                'username' => 'api-operator',
                'password' => 'api-secret',
                'connection_type' => 'api',
            ]);
            $this->fail('Expected the Register callback to reject unauthorized users.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame($routerCountBefore, Router::query()->count());
        $this->assertNull($device->fresh()->router_id);
    }

    public function test_user_with_create_routers_can_register_discovered_device(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::query()->create(['name' => 'Temporary Router Creator']);
        $permission = Permission::query()->firstOrCreate(['name' => 'create routers']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $device = $this->createDiscoveredDevice();
        $connectionService = $this->mock(MikrotikConnectionService::class);
        $connectionService->shouldReceive('checkConnection')
            ->once()
            ->withArgs(static fn (Router $router): bool => $router->host === '192.0.2.80'
                && (int) $router->port === 28728
                && $router->username === 'api-operator'
                && $router->password === 'api-secret')
            ->andReturn(true);

        $component = Livewire::test(DiscoveredDevices::class);
        $registerAction = collect($component->instance()->getTable()->getRecordActions())
            ->first(static fn (Action $action): bool => $action->getName() === 'registerRouter');

        $this->assertInstanceOf(Action::class, $registerAction);
        $registerCallback = $registerAction->getActionFunction();
        $registerCallback($device, [
            'username' => 'api-operator',
            'password' => 'api-secret',
            'connection_type' => 'api',
        ]);

        $router = Router::query()->where('host', '192.0.2.80')->where('port', 28728)->firstOrFail();
        $this->assertSame($router->getKey(), $device->fresh()->router_id);
        $this->assertSame('online', $router->status);
    }

    private function createDiscoveredDevice(): DiscoveredDevice
    {
        return DiscoveredDevice::query()->create([
            'ip_address' => '192.0.2.80',
            'api_port' => 28728,
            'identity' => 'Discovered Border',
            'api_available' => true,
            'status' => 'online',
        ]);
    }
}
