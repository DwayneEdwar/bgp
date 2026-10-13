<?php

namespace Tests\Feature;

use App\Filament\Navigation\NocNavigation;
use App\Filament\Pages\BgpTopology;
use App\Filament\Pages\RouterData;
use App\Models\Role;
use App\Models\User;
use App\Services\Mikrotik\MikrotikConnectionService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BgpSettingsPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_bgp_settings_reuses_existing_router_data_page_without_connecting_on_render(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::query()->firstOrCreate(['name' => 'Super Admin']));
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $connectionService = $this->mock(MikrotikConnectionService::class);
        $connectionService->shouldNotReceive('getModuleRows');
        $connectionService->shouldNotReceive('getPrintRows');
        $connectionService->shouldNotReceive('getSystemInfo');

        $response = $this->get(RouterData::getUrl(['module' => 'bgp-configuration'], panel: 'admin'));

        $response
            ->assertOk()
            ->assertSee('BGP Settings')
            ->assertSee('Refresh Data')
            ->assertSee('Configuration changes are not supported by the existing MikroTik adapter.')
            ->assertSee('Open BGP Topology');

        $this->assertSame('BGP Settings', NocNavigation::placeholder('bgp-configuration')['label'] ?? null);
        $this->assertNotSame('', BgpTopology::getUrl(panel: 'admin'));
    }
}
