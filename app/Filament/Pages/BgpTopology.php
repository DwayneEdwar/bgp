<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\BgpTopologyDataService;
use App\Services\Mikrotik\BgpTopologySyncService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnitEnum;

class BgpTopology extends Page
{
    protected string $view = 'filament.pages.bgp-topology';

    protected static ?string $slug = 'bgp-topology';

    protected static ?string $title = 'BGP Topology';

    protected static ?string $navigationLabel = 'BGP Topology';

    protected static string|UnitEnum|null $navigationGroup = 'Topology';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-share';

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed> */
    public array $topology = [];

    public bool $canSync = false;

    /** @var list<array{router: string, message: string}> */
    public array $syncErrors = [];

    public bool $syncHadNoOnlineRouters = false;

    public function mount(BgpTopologyDataService $topologyDataService): void
    {
        $user = $this->userWithPermission('view BGP');

        $this->canSync = $user->hasPermission('manage BGP');
        $this->topology = $topologyDataService->build();
    }

    public function refreshFromDatabase(BgpTopologyDataService $topologyDataService): void
    {
        $this->userWithPermission('view BGP');
        $this->publishTopology($topologyDataService);
    }

    public function syncFromMikroTik(
        BgpTopologySyncService $syncService,
        BgpTopologyDataService $topologyDataService,
    ): void {
        $this->userWithPermission('manage BGP');

        try {
            $result = $syncService->sync();
        } catch (Throwable $exception) {
            $this->syncErrors = [[
                'router' => 'BGP synchronization',
                'message' => 'Unable to synchronize BGP data. Existing topology has been preserved.',
            ]];
            $this->syncHadNoOnlineRouters = false;

            Log::warning('BGP topology synchronization failed.', [
                'exception_class' => $exception::class,
            ]);

            Notification::make()
                ->title('Unable to synchronize BGP data.')
                ->danger()
                ->send();

            return;
        }

        $this->syncErrors = $result['errors'];
        $this->syncHadNoOnlineRouters = $result['noOnlineRouters'];
        $this->publishTopology($topologyDataService);

        if ($result['noOnlineRouters']) {
            Notification::make()
                ->title('No online routers available for BGP synchronization.')
                ->warning()
                ->send();

            return;
        }

        if ($result['routersFailed'] > 0) {
            Notification::make()
                ->title('BGP synchronization completed with errors.')
                ->body("{$result['routersSynced']} routers synchronized; {$result['routersFailed']} failed.")
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('BGP synchronization completed.')
            ->body("{$result['routersSynced']} routers synchronized; {$result['sessionsUpdated']} sessions updated.")
            ->success()
            ->send();
    }

    private function publishTopology(BgpTopologyDataService $topologyDataService): void
    {
        $this->topology = $topologyDataService->build();
        $this->dispatch('topology-updated', topology: $this->topology);
    }

    private function userWithPermission(string $permission): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->hasPermission($permission), 403);

        return $user;
    }
}
