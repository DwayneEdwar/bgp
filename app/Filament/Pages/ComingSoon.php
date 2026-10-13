<?php

namespace App\Filament\Pages;

use App\Filament\Navigation\NocNavigation;
use Filament\Pages\Page;

class ComingSoon extends Page
{
    protected string $view = 'filament.pages.coming-soon';

    protected static ?string $breadcrumb = 'Coming Soon';

    protected static bool $shouldRegisterNavigation = false;

    public string $moduleTitle = 'Module';

    public string $moduleDescription = 'Module belum tersedia.';

    public string $moduleGroup = '';

    public function mount(): void
    {
        $key = request()->query('module');
        $module = NocNavigation::placeholder(is_string($key) ? $key : null);

        if ($module === null) {
            return;
        }

        $this->moduleTitle = $module['label'];
        $this->moduleDescription = $module['description'];
        $this->moduleGroup = $module['group'];
    }

    public function getTitle(): string
    {
        return $this->moduleTitle;
    }
}
