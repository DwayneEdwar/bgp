<?php

namespace App\Filament\Widgets;

use App\Models\Router;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RouterMonitoring extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -15;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Router Status')
            ->description('Stored connection status and last successful connection. Refreshes database records every 60 seconds while visible.')
            ->queryStringIdentifier('router-monitoring')
            ->query(fn (): Builder => Router::query()
                ->select(['id', 'name', 'host', 'status', 'last_connected_at'])
                ->orderBy('name')
                ->orderBy('id'))
            ->poll('60s.visible')
            ->columns([
                TextColumn::make('name')
                    ->label('Router')
                    ->weight('medium')
                    ->wrap(),
                TextColumn::make('host')
                    ->label('IP Address')
                    ->fontFamily('mono')
                    ->copyable()
                    ->visibleFrom('md'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(static fn (?string $state): string => strtoupper($state ?: 'unknown'))
                    ->color(static fn (?string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('last_connected_at')
                    ->label('Last Connected')
                    ->dateTime()
                    ->placeholder('Never')
                    ->visibleFrom('lg'),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->paginationMode(PaginationMode::Default)
            ->emptyStateHeading('No router records available')
            ->emptyStateDescription('Add a Router to begin storing connection status.');
    }
}
