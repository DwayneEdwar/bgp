<?php

namespace App\Filament\Pages;

use App\Filament\Navigation\NocNavigation;
use App\Models\ActivityLog;
use App\Models\Alert;
use App\Models\AvailabilityCheck;
use App\Models\BgpSession;
use App\Models\NetworkEvent;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikConnectionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnitEnum;

class RouterData extends Page
{
    protected string $view = 'filament.pages.router-data';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|UnitEnum|null $navigationGroup = 'Network Management';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    public string $moduleKey = '';

    public string $moduleTitle = 'Router Data';

    public ?string $moduleDescription = null;

    public ?int $routerId = null;

    public string $mapMode = 'physical';

    /** @var list<array<string, string>> */
    public array $rows = [];

    /** @var list<array{key: string, label: string}> */
    public array $columns = [];

    public ?string $lastUpdatedAt = null;

    public ?string $loadError = null;

    public function mount(): void
    {
        $key = request()->query('module');
        $module = is_string($key) ? NocNavigation::placeholder($key) : null;

        if ($module === null || ! in_array($key, self::supportedModules(), true)) {
            abort(404);
        }

        $this->moduleKey = $key;

        abort_unless(auth()->user()?->hasPermission($this->requiredPermission($key)), 403);

        $this->moduleTitle = $module['label'];
        $this->moduleDescription = $module['description'];
        $this->columns = $this->moduleColumns($key);
        $this->routerId = Router::query()->orderBy('name')->value('id');

        if (in_array($key, ['availability', 'alerts', 'activity-logs', 'network-topology', 'bgp-topology'], true)) {
            $this->loadDatabaseRows($key);
        } elseif ($key === 'physical-logical-map') {
            $this->loadPhysicalLogicalRows();
        }
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        if (in_array($this->moduleKey, ['availability', 'alerts', 'activity-logs'], true)) {
            return [];
        }

        $actions = [];

        if ($this->moduleKey === 'identity') {
            $actions[] = Action::make('updateIdentity')
                ->label('Update Identity')
                ->icon('heroicon-o-pencil-square')
                ->requiresConfirmation()
                ->modalHeading('Apply Router Identity')
                ->modalDescription('This changes the RouterOS identity on the selected router.')
                ->schema([
                    Select::make('router_id')
                        ->label('Router')
                        ->options(fn (): array => Router::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->default($this->routerId)
                        ->required()
                        ->searchable()
                        ->native(false),
                    TextInput::make('identity')->required()->maxLength(255),
                ])
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->hasPermission('manage configuration'), 403);
                    $router = Router::query()->findOrFail((int) $data['router_id']);

                    try {
                        app(MikrotikConnectionService::class)->setIdentity($router, (string) $data['identity']);
                    } catch (Throwable $exception) {
                        Log::warning('Router identity update failed.', [
                            'router_id' => $router->getKey(),
                            'exception_class' => $exception::class,
                        ]);
                        Notification::make()->title('Unable to update router identity.')->danger()->send();

                        return;
                    }

                    Notification::make()->title('Router identity updated')->success()->send();
                    $this->refreshData($router->getKey());
                });
        }

        if ($this->moduleKey === 'static-routes') {
            $actions[] = Action::make('addStaticRoute')
                ->label('Add Static Route')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Apply Static Route')
                ->modalDescription('This writes a static route to the selected MikroTik router.')
                ->schema([
                    Select::make('router_id')
                        ->label('Router')
                        ->options(fn (): array => Router::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->default($this->routerId)
                        ->required()
                        ->searchable()
                        ->native(false),
                    TextInput::make('destination')
                        ->label('Destination')
                        ->placeholder('198.51.100.0/24')
                        ->required()
                        ->maxLength(43),
                    TextInput::make('gateway')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('distance')
                        ->numeric()
                        ->rules(['integer', 'between:1,255'])
                        ->default(1)
                        ->required(),
                    TextInput::make('routing_mark')
                        ->label('Routing Mark')
                        ->maxLength(255),
                    TextInput::make('comment')
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->hasPermission('manage configuration'), 403);

                    $router = Router::query()->findOrFail((int) $data['router_id']);

                    try {
                        app(MikrotikConnectionService::class)->addStaticRoute($router, $data);
                    } catch (Throwable $exception) {
                        Log::warning('Static route create failed.', [
                            'router_id' => $router->getKey(),
                            'exception_class' => $exception::class,
                        ]);

                        Notification::make()->title('Unable to apply static route.')->danger()->send();

                        return;
                    }

                    Notification::make()->title('Static route applied')->success()->send();
                    $this->refreshData($router->getKey());
                });
        }

        $actions[] = Action::make('refreshData')
            ->label('Refresh Data')
            ->icon('heroicon-o-arrow-path')
            ->color('primary')
            ->schema([
                Select::make('router_id')
                    ->label('Router')
                    ->options(fn (): array => Router::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->default($this->routerId)
                    ->required()
                    ->searchable()
                    ->native(false),
            ])
            ->action(fn (array $data) => $this->refreshData((int) $data['router_id']));

        return $actions;
    }

    /** @return list<string> */
    public static function supportedModules(): array
    {
        return [
            'network-interfaces', 'ip-addresses', 'routing-table', 'static-routes', 'bgp', 'bgp-peers', 'bgp-routes',
            'network-monitor', 'traffic', 'cpu-memory', 'availability', 'alerts', 'logs',
            'network-topology', 'bgp-topology', 'physical-logical-map', 'identity', 'configuration-interfaces',
            'configuration-ip-address', 'dhcp', 'dns', 'nat', 'firewall', 'configuration-routes', 'bgp-configuration', 'activity-logs',
        ];
    }

    /** @return list<array{key: string, label: string}> */
    private function moduleColumns(string $moduleKey): array
    {
        return match ($moduleKey) {
            'network-interfaces', 'configuration-interfaces' => $this->columnsFor([
                'name' => 'Name', 'type' => 'Type', 'mac-address' => 'MAC Address', 'mtu' => 'MTU', 'running' => 'Running',
                'disabled' => 'Disabled', 'rx-byte' => 'RX Bytes', 'tx-byte' => 'TX Bytes', 'rx-packet' => 'RX Packets', 'tx-packet' => 'TX Packets',
            ]),
            'ip-addresses', 'configuration-ip-address' => $this->columnsFor([
                'address' => 'Address', 'network' => 'Network', 'interface' => 'Interface', 'disabled' => 'Disabled', 'dynamic' => 'Dynamic',
            ]),
            'routing-table', 'static-routes', 'configuration-routes' => $this->columnsFor([
                '.id' => 'RouterOS ID', 'dst-address' => 'Destination', 'gateway' => 'Gateway', 'interface' => 'Interface', 'distance' => 'Distance',
                'scope' => 'Scope', 'target-scope' => 'Target Scope', 'routing-mark' => 'Routing Mark', 'active' => 'Active',
                'dynamic' => 'Dynamic', 'static' => 'Static', 'protocol' => 'Protocol',
            ]),
            'bgp', 'bgp-peers', 'bgp-configuration' => $this->columnsFor([
                'name' => 'Peer', 'remote-address' => 'Remote Address', 'remote-as' => 'Remote AS', 'local-as' => 'Local AS',
                'state' => 'State', 'uptime' => 'Uptime', 'prefix-count' => 'Prefixes', 'disabled' => 'Disabled',
            ]),
            'bgp-routes' => $this->columnsFor([
                'dst-address' => 'Prefix', 'gateway' => 'Gateway', 'bgp-as-path' => 'AS Path', 'bgp-origin' => 'Origin',
                'bgp-local-pref' => 'Local Preference', 'bgp-med' => 'MED', 'distance' => 'Distance', 'active' => 'Active',
                'best' => 'Best', 'bgp-peer' => 'Peer',
            ]),
            'traffic' => $this->columnsFor([
                'name' => 'Interface', 'type' => 'Type', 'running' => 'Running', 'rx-byte' => 'RX Bytes', 'tx-byte' => 'TX Bytes',
                'rx-packet' => 'RX Packets', 'tx-packet' => 'TX Packets', 'rx-bps' => 'RX bps', 'tx-bps' => 'TX bps',
            ]),
            'cpu-memory', 'network-monitor' => $this->columnsFor([
                'identity' => 'Identity', 'version' => 'RouterOS Version', 'architecture' => 'Architecture', 'cpu_load' => 'CPU Load',
                'memory_total' => 'Total Memory', 'memory_free' => 'Free Memory', 'uptime' => 'Uptime',
            ]),
            'network-topology', 'physical-logical-map' => $this->columnsFor([
                'identity' => 'Neighbor Identity', 'address' => 'Neighbor Address', 'interface' => 'Local Interface', 'platform' => 'Platform', 'version' => 'RouterOS Version',
            ]),
            'bgp-topology' => $this->columnsFor([
                'name' => 'Peer', 'remote-address' => 'Remote Address', 'remote-as' => 'Remote AS', 'state' => 'State', 'uptime' => 'Uptime', 'prefix-count' => 'Prefixes',
            ]),
            'availability' => $this->columnsFor([
                'name' => 'Router', 'host' => 'Host', 'status' => 'Status', 'last_connected_at' => 'Last Connected',
            ]),
            'alerts' => $this->columnsFor([
                'type' => 'Type', 'severity' => 'Severity', 'message' => 'Message', 'status' => 'Status', 'created_at' => 'Created',
            ]),
            'activity-logs' => $this->columnsFor([
                'action' => 'Action', 'module' => 'Module', 'target' => 'Target', 'description' => 'Description', 'created_at' => 'Timestamp',
            ]),
            'logs' => $this->columnsFor([
                'time' => 'Time', 'topics' => 'Topics', 'message' => 'Message',
            ]),
            'identity' => $this->columnsFor(['name' => 'Identity']),
            'dhcp' => $this->columnsFor([
                'name' => 'Name', 'interface' => 'Interface', 'address-pool' => 'Address Pool', 'lease-time' => 'Lease Time', 'disabled' => 'Disabled', 'running' => 'Running',
            ]),
            'dns' => $this->columnsFor([
                'servers' => 'Servers', 'dynamic-servers' => 'Dynamic Servers', 'allow-remote-requests' => 'Allow Remote Requests', 'cache-size' => 'Cache Size', 'cache-used' => 'Cache Used',
            ]),
            'nat' => $this->columnsFor([
                'chain' => 'Chain', 'action' => 'Action', 'protocol' => 'Protocol', 'src-address' => 'Source', 'dst-address' => 'Destination',
                'to-addresses' => 'To Address', 'to-ports' => 'To Ports', 'in-interface' => 'In Interface', 'out-interface' => 'Out Interface', 'disabled' => 'Disabled', 'comment' => 'Comment',
            ]),
            'firewall' => $this->columnsFor([
                'chain' => 'Chain', 'action' => 'Action', 'protocol' => 'Protocol', 'src-address' => 'Source', 'dst-address' => 'Destination',
                'src-port' => 'Source Port', 'dst-port' => 'Destination Port', 'in-interface' => 'In Interface', 'out-interface' => 'Out Interface', 'disabled' => 'Disabled', 'comment' => 'Comment',
            ]),
            default => [],
        };
    }

    /** @param array<string, string> $columns @return list<array{key: string, label: string}> */
    private function columnsFor(array $columns): array
    {
        return collect($columns)->map(static fn (string $label, string $key): array => ['key' => $key, 'label' => $label])->values()->all();
    }

    public function deleteStaticRoute(string $routeId): void
    {
        abort_unless(auth()->user()?->hasPermission('manage configuration'), 403);

        if ($this->moduleKey !== 'static-routes' || $this->routerId === null) {
            abort(403);
        }

        $router = Router::query()->findOrFail($this->routerId);

        try {
            app(MikrotikConnectionService::class)->deleteStaticRoute($router, $routeId);
        } catch (Throwable $exception) {
            Log::warning('Static route delete failed.', [
                'router_id' => $router->getKey(),
                'exception_class' => $exception::class,
            ]);
            Notification::make()->title('Unable to delete static route.')->danger()->send();

            return;
        }

        Notification::make()->title('Static route deleted')->success()->send();
        $this->refreshData($router->getKey());
    }

    public function setStaticRouteDisabled(string $routeId, bool $disabled): void
    {
        abort_unless(auth()->user()?->hasPermission('manage configuration'), 403);

        if ($this->moduleKey !== 'static-routes' || $this->routerId === null) {
            abort(403);
        }

        $router = Router::query()->findOrFail($this->routerId);

        try {
            app(MikrotikConnectionService::class)->setStaticRouteDisabled($router, $routeId, $disabled);
        } catch (Throwable $exception) {
            Log::warning('Static route state change failed.', [
                'router_id' => $router->getKey(),
                'exception_class' => $exception::class,
            ]);
            Notification::make()->title('Unable to update static route.')->danger()->send();

            return;
        }

        Notification::make()->title($disabled ? 'Static route disabled' : 'Static route enabled')->success()->send();
        $this->refreshData($router->getKey());
    }

    private function requiredPermission(string $moduleKey): string
    {
        if ($moduleKey === 'activity-logs') {
            return 'view audit logs';
        }

        if ($moduleKey === 'alerts') {
            return 'view alerts';
        }

        if (in_array($moduleKey, ['logs'], true)) {
            return 'view logs';
        }

        if (in_array($moduleKey, ['bgp', 'bgp-peers', 'bgp-routes', 'bgp-topology', 'bgp-configuration'], true)) {
            return 'view BGP';
        }

        if (in_array($moduleKey, ['routing-table', 'static-routes', 'configuration-routes'], true)) {
            return $moduleKey === 'static-routes' ? 'view routing' : 'view routing';
        }

        if (in_array($moduleKey, ['network-monitor', 'traffic', 'cpu-memory', 'availability', 'network-topology', 'physical-logical-map'], true)) {
            return 'view monitoring';
        }

        if (in_array($moduleKey, ['identity', 'configuration-interfaces', 'configuration-ip-address', 'dhcp', 'dns', 'nat', 'firewall'], true)) {
            return str_starts_with($moduleKey, 'configuration-') || $moduleKey !== 'identity' ? 'view configuration' : 'view configuration';
        }

        return 'view routers';
    }

    public function acknowledgeAlert(int $alertId): void
    {
        abort_unless(auth()->user()?->hasPermission('manage alerts'), 403);
        $alert = Alert::query()->where('status', 'open')->findOrFail($alertId);
        $alert->update([
            'status' => 'acknowledged',
            'acknowledged_by' => auth()->id(),
            'acknowledged_at' => now(),
        ]);

        $this->recordAlertEvent($alert, 'acknowledged');
        $this->loadDatabaseRows('alerts');
    }

    public function resolveAlert(int $alertId): void
    {
        abort_unless(auth()->user()?->hasPermission('manage alerts'), 403);
        $alert = Alert::query()->whereIn('status', ['open', 'acknowledged'])->findOrFail($alertId);
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);

        $this->recordAlertEvent($alert, 'resolved');
        $this->loadDatabaseRows('alerts');
    }

    private function recordAlertEvent(Alert $alert, string $state): void
    {
        NetworkEvent::query()->create([
            'router_id' => $alert->router_id,
            'actor_id' => auth()->id(),
            'type' => "alert_{$state}",
            'module' => 'alerts',
            'target_type' => Alert::class,
            'target_id' => $alert->getKey(),
            'description' => "Alert {$state}.",
            'created_at' => now(),
        ]);

        ActivityLog::query()->create([
            'actor_id' => auth()->id(),
            'action' => "alert.{$state}",
            'module' => 'alerts',
            'target_type' => Alert::class,
            'target_id' => $alert->getKey(),
            'description' => "Alert {$state}.",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    private function loadDatabaseRows(string $moduleKey): void
    {
        if ($moduleKey === 'network-topology') {
            $this->rows = \App\Models\RouterNeighbor::query()
                ->with(['router:id,name,host', 'neighborRouter:id,name,host'])
                ->where('observed_at', '>=', now()->subMinutes(30))
                ->latest('observed_at')
                ->limit(100)
                ->get()
                ->map(static fn (\App\Models\RouterNeighbor $neighbor): array => [
                    'identity' => $neighbor->neighborRouter?->name ?? $neighbor->neighbor_identity ?? '',
                    'address' => $neighbor->neighbor_address ?? '',
                    'interface' => $neighbor->local_interface ?? '',
                    'platform' => $neighbor->platform ?? '',
                    'version' => $neighbor->protocol ?? '',
                ])
                ->all();

            return;
        }

        if ($moduleKey === 'bgp-topology') {
            $this->rows = BgpSession::query()
                ->with('router:id,name,host')
                ->where('checked_at', '>=', now()->subMinutes(15))
                ->latest('checked_at')
                ->limit(100)
                ->get()
                ->map(static fn (BgpSession $session): array => [
                    'name' => (string) ($session->peer_name ?? ''),
                    'remote-address' => (string) ($session->remote_address ?? ''),
                    'remote-as' => (string) ($session->remote_as ?? ''),
                    'state' => (string) $session->state,
                    'uptime' => (string) ($session->uptime ?? ''),
                    'prefix-count' => (string) ($session->prefixes_received ?? ''),
                    'router-name' => (string) ($session->router?->name ?? ''),
                ])
                ->all();

            return;
        }

        if ($moduleKey === 'availability') {
            $availability = AvailabilityCheck::query()
                ->selectRaw('router_id, COUNT(*) AS checks_total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS checks_online', ['online'])
                ->where('checked_at', '>=', now()->subDays(30))
                ->groupBy('router_id')
                ->get()
                ->keyBy('router_id');

            $this->rows = Router::query()
                ->select(['id', 'name', 'host', 'status', 'last_connected_at'])
                ->orderBy('name')
                ->get()
                ->map(function (Router $router) use ($availability): array {
                    $checks = $availability->get($router->getKey());
                    $total = (int) ($checks?->checks_total ?? 0);
                    $online = (int) ($checks?->checks_online ?? 0);

                    return [
                        'name' => (string) $router->name,
                        'host' => (string) $router->host,
                        'status' => (string) ($router->status ?: 'unknown'),
                        'last_connected_at' => $router->last_connected_at?->toDateTimeString() ?? 'Never',
                        'availability' => $total >= 2
                            ? number_format(($online / $total) * 100, 1).'%'
                            : 'Insufficient history',
                    ];
                })
                ->all();

            return;
        }

        if ($moduleKey === 'alerts') {
            $this->rows = Alert::query()
                ->with('router:id,name')
                ->latest()
                ->limit(100)
                ->get()
                ->map(static fn (Alert $alert): array => [
                    'id' => (string) $alert->getKey(),
                    'id' => (string) $alert->getKey(),
                    'type' => $alert->type,
                    'severity' => $alert->severity,
                    'message' => $alert->message,
                    'status' => $alert->status,
                    'created_at' => $alert->created_at?->toDateTimeString() ?? '',
                ])
                ->all();

            return;
        }

        $query = $moduleKey === 'activity-logs'
            ? ActivityLog::query()->with('actor:id,name')
            : NetworkEvent::query()->with(['router:id,name', 'actor:id,name']);

        $this->rows = $query
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(static fn (Model $event): array => [
                'action' => (string) ($event->action ?? $event->type),
                'module' => (string) $event->module,
                'target' => (string) ($event->target_type ?? 'Network').(isset($event->target_id) ? ' #'.$event->target_id : ''),
                'description' => (string) $event->description,
                'created_at' => $event->created_at?->toDateTimeString() ?? '',
            ])
            ->all();
    }

    public function setMapMode(string $mode): void
    {
        if ($this->moduleKey !== 'physical-logical-map' || ! in_array($mode, ['physical', 'logical'], true)) {
            abort(403);
        }

        $this->mapMode = $mode;
        $this->loadPhysicalLogicalRows();
    }

    private function loadPhysicalLogicalRows(): void
    {
        if ($this->mapMode === 'physical') {
            $this->loadDatabaseRows('network-topology');

            return;
        }

        $this->rows = BgpSession::query()
            ->with('router:id,name,host')
            ->where('checked_at', '>=', now()->subMinutes(15))
            ->latest('checked_at')
            ->limit(100)
            ->get()
            ->map(static fn (BgpSession $session): array => [
                'identity' => (string) ($session->router?->name ?? ''),
                'address' => (string) ($session->remote_address ?? ''),
                'interface' => (string) ($session->state ?? ''),
                'platform' => is_numeric($session->remote_as) ? 'AS '.$session->remote_as : '',
                'version' => $session->uptime ?? '',
            ])
            ->all();
    }

    private function refreshData(int $routerId): void
    {
        $router = Router::query()->find($routerId);

        if ($router === null) {
            $this->rows = [];
            $this->loadError = 'Select a registered router first.';

            return;
        }

        $this->routerId = $routerId;
        $this->loadError = null;

        try {
            $this->rows = app(MikrotikConnectionService::class)->getModuleRows($router, $this->moduleKey);

            if (in_array($this->moduleKey, ['network-topology', 'physical-logical-map'], true)) {
                $this->rows = array_map(static fn (array $row): array => [
                    'source_name' => (string) $router->name,
                    'source_host' => (string) $router->host,
                    ...$row,
                ], $this->rows);
            } elseif (in_array($this->moduleKey, ['bgp', 'bgp-peers', 'bgp-topology', 'bgp-configuration'], true)) {
                $this->rows = array_map(static fn (array $row): array => [
                    'router_name' => (string) $router->name,
                    'router_host' => (string) $router->host,
                    ...$row,
                ], $this->rows);
            }
        } catch (Throwable $exception) {
            Log::warning('Router data refresh failed.', [
                'router_id' => $router->getKey(),
                'module' => $this->moduleKey,
                'exception_class' => $exception::class,
            ]);

            $this->rows = [];
            $this->loadError = 'Unable to retrieve data from MikroTik.';
        }

        $this->lastUpdatedAt = now()->toDateTimeString();

        if ($this->loadError !== null) {
            Notification::make()->title($this->loadError)->danger()->send();

            return;
        }

        Notification::make()->title('Router data refreshed')->success()->send();
    }
}
