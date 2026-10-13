<?php

namespace App\Filament\Pages;

use App\Models\Alert;
use App\Models\BgpSession;
use App\Models\NetworkEvent;
use App\Models\Router;
use App\Models\RouterNeighbor;
use App\Filament\Support\RouterStatusSummary;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class NocWallboard extends Page
{
    protected string $view = 'filament.pages.noc-wallboard';

    protected static ?string $title = 'NOC Wallboard';

    protected static ?string $navigationLabel = 'NOC Wallboard';

    protected static string|UnitEnum|null $navigationGroup = 'Dashboard';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static ?int $navigationSort = -1;

    protected static bool $shouldRegisterNavigation = true;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasPermission('view monitoring'), 403);
    }

    /** @return array<string, int> */
    public function getWallboardMetrics(): array
    {
        $routers = app(RouterStatusSummary::class)->get();
        $sessions = BgpSession::query()->where('checked_at', '>=', now()->subMinutes(15));
        $alerts = Alert::query()->where('status', 'open');

        return [
            'routers' => $routers['total'],
            'online' => $routers['online'],
            'offline' => $routers['offline'],
            'unknown' => $routers['unknown'],
            'alerts' => (clone $alerts)->count(),
            'critical' => (clone $alerts)->where('severity', 'critical')->count(),
            'bgp_sessions' => (clone $sessions)->count(),
            'bgp_established' => (clone $sessions)->whereRaw('LOWER(state) = ?', ['established'])->count(),
            'bgp_down' => (clone $sessions)->whereIn('state', ['down', 'idle', 'connect'])->count(),
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, RouterNeighbor> */
    public function getObservedLinks(): \Illuminate\Database\Eloquent\Collection
    {
        return RouterNeighbor::query()
            ->with(['router:id,name,host', 'neighborRouter:id,name,host'])
            ->where('observed_at', '>=', now()->subMinutes(30))
            ->latest('observed_at')
            ->limit(8)
            ->get();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, BgpSession> */
    public function getLatestBgpSessions(): \Illuminate\Database\Eloquent\Collection
    {
        return BgpSession::query()
            ->with('router:id,name')
            ->where('checked_at', '>=', now()->subMinutes(15))
            ->latest('checked_at')
            ->limit(8)
            ->get();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, NetworkEvent> */
    public function getLatestEvents(): \Illuminate\Database\Eloquent\Collection
    {
        return NetworkEvent::query()
            ->with('router:id,name')
            ->latest('created_at')
            ->limit(8)
            ->get();
    }

    public function getRefreshSeconds(): int
    {
        $setting = \App\Models\AppSetting::query()->where('key', 'noc_refresh_interval')->value('value');
        $value = is_array($setting) && is_numeric($setting['value'] ?? null) ? (int) $setting['value'] : 60;

        return min(3600, max(15, $value));
    }
}
