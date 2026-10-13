<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DiscoveredDevices;
use App\Filament\Widgets\RouterMonitoring;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Devices extends Page
{
    protected string $view = 'filament.pages.devices';

    protected static ?string $title = 'Devices';

    protected static ?string $navigationLabel = 'Devices';

    protected static string|UnitEnum|null $navigationGroup = 'Network Management';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = true;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasPermission('view routers'), 403);
    }

    /**
     * @return array<class-string>
     */
    public function getHeaderWidgets(): array
    {
        return [RouterMonitoring::class, DiscoveredDevices::class];
    }

    /** @return array<string, int> */
    public function getColumns(): array
    {
        return [
            'default' => 1,
            '2xl' => 2,
        ];
    }
}
