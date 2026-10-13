<?php

namespace App\Filament\Resources\Routers;

use App\Filament\Resources\Routers\Pages\CreateRouter;
use App\Filament\Resources\Routers\Pages\EditRouter;
use App\Filament\Resources\Routers\Pages\ListRouters;
use App\Filament\Resources\Routers\Pages\ViewRouter;
use App\Filament\Resources\Routers\Schemas\RouterForm;
use App\Filament\Resources\Routers\Tables\RoutersTable;
use App\Models\Router;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RouterResource extends Resource
{
    protected static ?string $model = Router::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Network Management';

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return RouterForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name'),
                        TextEntry::make('username')
                            ->label('Username'),
                        TextEntry::make('connection_type')
                            ->label('Connection Type')
                            ->formatStateUsing(static fn (?string $state): string => match ($state) {
                                'api_ssl' => 'API SSL',
                                'api' => 'API',
                                default => 'Unknown',
                            }),
                    ]),
                Section::make('Connection')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->color(static fn (?string $state): string => match ($state) {
                                'online' => 'success',
                                'offline' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('host'),
                        TextEntry::make('port'),
                        TextEntry::make('connection_type')
                            ->label('Connection Type')
                            ->formatStateUsing(static fn (?string $state): string => match ($state) {
                                'api_ssl' => 'API SSL',
                                'api' => 'API',
                                default => 'Unknown',
                            }),
                        TextEntry::make('last_connected_at')
                            ->dateTime()
                            ->placeholder('Never connected'),
                    ]),
                Section::make('System Information')
                    ->description('Fetched only when Refresh System Info is requested; no router API call is made when opening this page.')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextEntry::make('system_identity')
                            ->label('Identity')
                            ->state(fn ($livewire): ?string => $livewire->systemInfo['identity'] ?? null)
                            ->placeholder('Not retrieved'),
                        TextEntry::make('system_version')
                            ->label('RouterOS Version')
                            ->state(fn ($livewire): ?string => $livewire->systemInfo['version'] ?? null)
                            ->placeholder('Not retrieved'),
                        TextEntry::make('system_architecture')
                            ->label('Architecture')
                            ->state(fn ($livewire): ?string => $livewire->systemInfo['architecture'] ?? null)
                            ->placeholder('Not retrieved'),
                        TextEntry::make('system_cpu_load')
                            ->label('CPU Load')
                            ->state(fn ($livewire): ?string => isset($livewire->systemInfo['cpu_load']) ? "{$livewire->systemInfo['cpu_load']}%" : null)
                            ->placeholder('Not retrieved')
                            ->badge()
                            ->color('info'),
                        TextEntry::make('memory_total')
                            ->label('Total Memory')
                            ->state(fn ($livewire): string => $livewire->getFormattedMemory()['total'])
                            ->placeholder('N/A'),
                        TextEntry::make('memory_used')
                            ->label('Used Memory')
                            ->state(fn ($livewire): string => $livewire->getFormattedMemory()['used'])
                            ->placeholder('N/A'),
                        TextEntry::make('memory_free')
                            ->label('Free Memory')
                            ->state(fn ($livewire): string => $livewire->getFormattedMemory()['free'])
                            ->placeholder('N/A'),
                        TextEntry::make('memory_usage')
                            ->label('Memory Usage')
                            ->state(fn ($livewire): string => $livewire->getFormattedMemory()['usage'])
                            ->placeholder('N/A')
                            ->badge()
                            ->color('warning'),
                        TextEntry::make('system_uptime')
                            ->label('Uptime')
                            ->state(fn ($livewire): ?string => $livewire->systemInfo['uptime'] ?? null)
                            ->placeholder('Not retrieved'),
                        TextEntry::make('system_info_updated_at')
                            ->label('Last Updated')
                            ->state(fn ($livewire): ?string => $livewire->systemInfoUpdatedAt)
                            ->placeholder('Not retrieved'),
                        TextEntry::make('system_info_error')
                            ->label('System Information')
                            ->state(fn ($livewire): ?string => $livewire->systemInfoError)
                            ->visible(fn ($livewire): bool => filled($livewire->systemInfoError))
                            ->color('danger')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return RoutersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRouters::route('/'),
            'create' => CreateRouter::route('/create'),
            'view' => ViewRouter::route('/{record}'),
            'edit' => EditRouter::route('/{record}/edit'),
        ];
    }
}
