<?php

namespace App\Filament\Widgets;

use App\Filament\Support\RouterStatusSummary;
use App\Models\Alert;
use App\Models\BgpSession;
use App\Models\DiscoveredDevice;
use Filament\Widgets\Widget;

class NocOverviewMetrics extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -20;

    protected string $view = 'filament.widgets.noc-overview-metrics';

    protected int|string|array $columnSpan = 'full';

    /**
     * @var list<array{label: string, metrics: list<array{label: string, value: int, note: string}>}>
     */
    public array $metricGroups = [];

    public function mount(): void
    {
        $this->refreshMetrics();
    }

    public function refreshMetrics(): void
    {
        $routers = app(RouterStatusSummary::class)->get();
        $discoveredDevices = DiscoveredDevice::query()->count();
        $registeredDevices = DiscoveredDevice::query()->whereNotNull('router_id')->count();
        $recentBgpSessions = BgpSession::query()->where('checked_at', '>=', now()->subMinutes(15));
        $bgpCounts = (clone $recentBgpSessions)->selectRaw('LOWER(state) AS state_key, COUNT(*) AS state_count')->groupBy('state_key')->pluck('state_count', 'state_key');
        $activeAlerts = Alert::query()->where('status', 'open')->count();

        $this->metricGroups = [
            [
                'label' => 'Network Overview',
                'metrics' => [
                    ['label' => 'Total Routers', 'value' => $routers['total'], 'note' => 'Database records'],
                    ['label' => 'Online Routers', 'value' => $routers['online'], 'note' => 'Stored status'],
                    ['label' => 'Offline Routers', 'value' => $routers['offline'], 'note' => 'Stored status'],
                    ['label' => 'Unknown Routers', 'value' => $routers['unknown'], 'note' => 'Stored status'],
                ],
            ],
            [
                'label' => 'Discovered Devices',
                'metrics' => [
                    ['label' => 'Discovered Devices', 'value' => $discoveredDevices, 'note' => 'Database records'],
                    ['label' => 'Registered Devices', 'value' => $registeredDevices, 'note' => 'Linked to a Router'],
                ],
            ],
            [
                'label' => 'BGP & Alerts',
                'metrics' => [
                    ['label' => 'BGP Sessions', 'value' => (clone $recentBgpSessions)->count(), 'note' => 'Collected within 15 minutes'],
                    ['label' => 'BGP Established', 'value' => (int) $bgpCounts->get('established', 0), 'note' => 'Latest collected sessions'],
                    ['label' => 'BGP Down', 'value' => (int) $bgpCounts->get('idle', 0) + (int) $bgpCounts->get('down', 0), 'note' => 'Idle or down sessions'],
                    ['label' => 'Active Alerts', 'value' => $activeAlerts, 'note' => 'Open alerts'],
                ],
            ],
        ];
    }
}
