<?php

namespace Tests\Feature;

use App\Filament\Pages\Devices;
use App\Filament\Pages\NetworkDiscovery;
use App\Models\Role;
use App\Models\User;
use App\Services\Mikrotik\MikrotikConnectionService;
use App\Services\Mikrotik\NetworkDiscoveryService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class NetworkDiscoveryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['name' => 'Super Admin']));
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_subnet_parser_normalizes_network_addresses_and_bounds_manual_scan_size(): void
    {
        $service = app(NetworkDiscoveryService::class);

        $this->assertSame(['192.168.101.0', 24], $service->parseSubnet('192.168.101.23/24'));

        $this->expectException(InvalidArgumentException::class);
        $service->scanSubnet('10.0.0.0/16');
    }

    public function test_discovery_and_device_pages_render_without_router_api_calls(): void
    {
        $connection = $this->mock(MikrotikConnectionService::class);
        $connection->shouldNotReceive('testConnection');
        $connection->shouldNotReceive('getSystemInfo');

        $this->get(NetworkDiscovery::getUrl(panel: 'admin'))
            ->assertOk()
            ->assertSee('Auto Detect Network')
            ->assertSee('Manual Subnet')
            ->assertSee('Discover MikroTik API endpoints')
            ->assertSee('No discovered devices available');

        $this->get(Devices::getUrl(panel: 'admin'))
            ->assertOk()
            ->assertSee('Devices')
            ->assertSee('Router Status')
            ->assertSee('Discovered Devices');
    }
}
