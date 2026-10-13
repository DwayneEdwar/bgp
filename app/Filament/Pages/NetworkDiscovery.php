<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DiscoveredDevices;
use App\Jobs\RunNetworkDiscoveryScan;
use App\Models\DiscoveryScan;
use App\Services\Mikrotik\NetworkDiscoveryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use UnitEnum;

class NetworkDiscovery extends Page
{
    protected string $view = 'filament.pages.network-discovery';

    protected static ?string $title = 'Network Discovery';

    protected static ?string $navigationLabel = 'Network Discovery';

    protected static string|UnitEnum|null $navigationGroup = 'Network Management';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?int $navigationSort = 1;

    protected static bool $shouldRegisterNavigation = true;

    public ?int $currentScanId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasPermission('view routers'), 403);
    }

    /**
     * @return array<class-string>
     */
    public function getHeaderWidgets(): array
    {
        return [DiscoveredDevices::class];
    }

    protected function getHeaderActions(): array
    {
        $detectedNetworks = app(NetworkDiscoveryService::class)->detectServerNetworks();

        return [
            Action::make('scanServerNetwork')
                ->label('Auto Detect Network')
                ->icon('heroicon-o-signal')
                ->color('primary')
                ->schema([
                    Select::make('subnet')
                        ->label('Server network')
                        ->options($detectedNetworks)
                        ->required()
                        ->native(false)
                        ->helperText($detectedNetworks === [] ? 'No suitable server IPv4 interface was detected. Enter a subnet manually.' : 'Detected from a network interface on this Laravel server.'),
                    TextInput::make('api_port')
                        ->label('API port')
                        ->numeric()
                        ->rules(['integer', 'between:1,65535'])
                        ->default(8728)
                        ->required()
                        ->helperText('Common defaults: API 8728, API SSL 8729. Custom API ports are supported.'),
                ])
                ->disabled($detectedNetworks === [])
                ->action(fn (array $data) => $this->queueScan(
                    app(NetworkDiscoveryService::class),
                    (string) $data['subnet'],
                    [(int) $data['api_port']],
                )),
            Action::make('scanManualSubnet')
                ->label('Manual Subnet')
                ->icon('heroicon-o-globe-alt')
                ->schema([
                    TextInput::make('subnet')
                        ->label('IPv4 subnet')
                        ->placeholder('192.168.101.0/24')
                        ->required()
                        ->regex('/^\d{1,3}(?:\.\d{1,3}){3}\/(?:2[4-9]|30)$/')
                        ->helperText('Scans are limited to /24 through /30 (up to 254 host addresses).'),
                    TextInput::make('api_port')
                        ->label('API port')
                        ->numeric()
                        ->rules(['integer', 'between:1,65535'])
                        ->default(8728)
                        ->required()
                        ->helperText('Common defaults: API 8728, API SSL 8729. Custom API ports are supported.'),
                ])
                ->action(fn (array $data) => $this->queueScan(
                    app(NetworkDiscoveryService::class),
                    (string) $data['subnet'],
                    [(int) $data['api_port']],
                )),
        ];
    }

    public function getCurrentScan(): ?DiscoveryScan
    {
        if ($this->currentScanId === null) {
            return null;
        }

        return DiscoveryScan::query()->find($this->currentScanId);
    }

    /** @return Collection<int, DiscoveryScan> */
    public function getRecentScans(): Collection
    {
        return DiscoveryScan::query()
            ->latest('created_at')
            ->limit(5)
            ->get();
    }

    private function queueScan(NetworkDiscoveryService $service, string $subnet, array $ports): void
    {
        try {
            [$networkAddress, $prefixLength] = $service->parseSubnet($subnet);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'subnet' => $exception->getMessage(),
            ]);
        }

        if ($prefixLength < 24) {
            throw ValidationException::withMessages([
                'subnet' => 'Choose a subnet between /24 and /30 to limit the scan size.',
            ]);
        }

        $port = $ports[0] ?? null;

        if (! is_int($port) || $port < 1 || $port > 65535) {
            throw ValidationException::withMessages([
                'api_port' => 'Enter a TCP port between 1 and 65535.',
            ]);
        }

        $scan = DiscoveryScan::query()->create([
            'subnet' => "{$networkAddress}/{$prefixLength}",
            'ports' => [$port],
            'status' => 'queued',
            'requested_by' => auth()->id(),
        ]);

        $this->currentScanId = (int) $scan->getKey();
        RunNetworkDiscoveryScan::dispatch($this->currentScanId);

        Notification::make()
            ->title('Network scan queued')
            ->body("Scanning {$scan->subnet} on TCP port {$port}.")
            ->info()
            ->send();
    }
}
