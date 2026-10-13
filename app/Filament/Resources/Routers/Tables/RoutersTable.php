<?php

namespace App\Filament\Resources\Routers\Tables;

use App\Models\Router;
use App\Services\Mikrotik\MikrotikConnectionService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

class RoutersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('host')
                    ->label('Host')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->copyable()
                    ->wrap(),
                TextColumn::make('port')
                    ->sortable()
                    ->fontFamily('mono'),
                TextColumn::make('connection_type')
                    ->label('Connection Type')
                    ->badge()
                    ->formatStateUsing(static fn (?string $state): string => match ($state) {
                        'api_ssl' => 'API SSL',
                        'api' => 'API',
                        default => 'Unknown',
                    })
                    ->color(static fn (?string $state): string => $state === 'api_ssl' ? 'info' : 'gray')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(static fn (?string $state): string => strtoupper($state ?: 'unknown'))
                    ->color(static fn (?string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('last_connected_at')
                    ->label('Last Connected')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'online' => 'Online',
                        'offline' => 'Offline',
                        'unknown' => 'Unknown',
                    ])
                    ->query(static function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'online' => $query->where('status', 'online'),
                            'offline' => $query->where('status', 'offline'),
                            'unknown' => $query->where(static function (Builder $query): void {
                                $query->whereNotIn('status', ['online', 'offline'])
                                    ->orWhereNull('status');
                            }),
                            default => $query,
                        };
                    }),
                SelectFilter::make('connection_type')
                    ->label('Connection Type')
                    ->options([
                        'api' => 'API',
                        'api_ssl' => 'API SSL',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Manage'),
                    Action::make('testConnection')
                        ->visible(static fn (): bool => auth()->user()?->hasPermission('edit routers') ?? false)
                        ->label('Test Connection')
                        ->icon('heroicon-o-signal')
                        ->color('success')
                        ->action(function (Router $record): void {
                            try {
                                $connected = app(MikrotikConnectionService::class)->testConnection($record);
                            } catch (Throwable $exception) {
                                Log::warning('Router connection test failed unexpectedly.', [
                                    'router_id' => $record->getKey(),
                                    'exception_class' => $exception::class,
                                ]);

                                $connected = false;
                            }

                            Notification::make()
                                ->title($connected ? 'Connection successful' : 'Connection failed')
                                ->body($connected ? 'The router responded to the API check.' : 'Unable to connect to MikroTik.')
                                ->color($connected ? 'success' : 'danger')
                                ->send();
                        }),
                    EditAction::make(),
                    DeleteAction::make()
                        ->modalHeading('Delete Router')
                        ->modalDescription('Are you sure you want to delete this router? Discovered device history will be retained.')
                        ->modalSubmitActionLabel('Delete Router'),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No routers configured')
            ->emptyStateDescription('Add a MikroTik router to begin managing your network.');
    }
}
