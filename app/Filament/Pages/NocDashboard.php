<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class NocDashboard extends Dashboard
{
    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $breadcrumb = 'Dashboard';

    protected static string|UnitEnum|null $navigationGroup = 'Dashboard';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = -2;

    protected string $view = 'filament.pages.noc-dashboard';

    protected Width|string|null $maxContentWidth = Width::Full;

    public function mount(): void
    {
        $user = auth()->user();

        abort_unless(
            $user?->hasPermission('view routers') && $user->hasPermission('view monitoring'),
            403,
        );
    }

    /**
     * @return array<string, int>
     */
    public function getColumns(): array
    {
        return [
            'default' => 1,
            'lg' => 2,
            '2xl' => 12,
        ];
    }
}
