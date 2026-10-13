<?php

namespace App\Filament\Widgets;

use App\Models\DiscoveredDevice;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikConnectionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

class DiscoveredDevices extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 1,
        '2xl' => 6,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Discovered Devices')
            ->description('Saved discovery results. The dashboard does not start a new scan.')
            ->query(fn (): Builder => DiscoveredDevice::query()
                ->select([
                    'id',
                    'ip_address',
                    'hostname',
                    'identity',
                    'device_type',
                    'status',
                    'api_available',
                    'router_id',
                    'last_seen_at',
                ])
                ->orderByDesc('last_seen_at')
                ->orderBy('ip_address'))
            ->poll('60s.visible')
            ->columns([
                TextColumn::make('identity')
                    ->label('Device')
                    ->state(static fn (DiscoveredDevice $record): string => $record->identity ?: ($record->hostname ?: 'Unknown device'))
                    ->description(static fn (DiscoveredDevice $record): ?string => $record->device_type),
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->fontFamily('mono')
                    ->copyable()
                    ->visibleFrom('md'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(static fn (?string $state): string => ucfirst($state ?? 'unknown'))
                    ->color(static fn (?string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('router_id')
                    ->label('Registration')
                    ->state(static fn (DiscoveredDevice $record): string => $record->router_id !== null ? 'Registered' : 'Unregistered')
                    ->badge()
                    ->color(static fn (DiscoveredDevice $record): string => $record->router_id !== null ? 'info' : 'gray')
                    ->visibleFrom('md'),
                TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->dateTime()
                    ->placeholder('Not recorded')
                    ->visibleFrom('xl'),
            ])
            ->recordActions([
                Action::make('registerRouter')
                    ->label('Register')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->visible(static fn (DiscoveredDevice $record): bool => $record->router_id === null
                        && $record->api_available
                        && (auth()->user()?->hasPermission('create routers') ?? false))
                    ->schema([
                        TextInput::make('username')
                            ->required()
                            ->autocomplete(false),
                        TextInput::make('password')
                            ->password()
                            ->required()
                            ->autocomplete('new-password'),
                        Select::make('connection_type')
                            ->options([
                                'api' => 'API',
                                'api_ssl' => 'API SSL',
                            ])
                            ->default(static fn (DiscoveredDevice $record): string => (int) $record->api_port === 8729 ? 'api_ssl' : 'api')
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (DiscoveredDevice $record, array $data): void {
                        abort_unless(auth()->user()?->hasPermission('create routers'), 403);

                        $existingRouter = Router::query()
                            ->where('host', $record->ip_address)
                            ->where('port', $record->api_port)
                            ->first();

                        if ($existingRouter !== null) {
                            $record->router()->associate($existingRouter);
                            $record->save();

                            Notification::make()
                                ->title('Device linked to existing router')
                                ->success()
                                ->send();

                            return;
                        }

                        $router = new Router([
                            'name' => $record->identity ?: ($record->hostname ?: $record->ip_address),
                            'host' => $record->ip_address,
                            'port' => $record->api_port,
                            'username' => $data['username'],
                            'password' => $data['password'],
                            'connection_type' => $data['connection_type'],
                            'status' => 'unknown',
                        ]);

                        try {
                            $connected = app(MikrotikConnectionService::class)->checkConnection($router);
                        } catch (Throwable $exception) {
                            Log::warning('Discovered router registration connection check failed.', [
                                'discovered_device_id' => $record->getKey(),
                                'exception_class' => $exception::class,
                            ]);
                            $connected = false;
                        }

                        if (! $connected) {
                            Notification::make()
                                ->title('Connection failed')
                                ->body('Unable to connect to MikroTik. The router was not saved.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $router->status = 'online';
                        $router->last_connected_at = now();
                        $router->save();

                        $record->router()->associate($router);
                        $record->save();

                        Notification::make()
                            ->title('Router registered')
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->paginationMode(PaginationMode::Simple)
            ->emptyStateHeading('No discovered devices available')
            ->emptyStateDescription('Run Network Discovery to populate this list.');
    }
}
