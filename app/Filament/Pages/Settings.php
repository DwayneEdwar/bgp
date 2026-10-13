<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static ?string $title = 'Settings';

    protected static ?string $navigationLabel = 'Settings';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 3;

    protected static bool $shouldRegisterNavigation = true;

    /** @var array<string, string|int> */
    public array $settings = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasPermission('manage settings'), 403);

        $this->settings = [
            'application_name' => $this->readSetting('application_name', config('app.name')),
            'noc_refresh_interval' => $this->readSetting('noc_refresh_interval', 60),
            'default_dashboard' => $this->readSetting('default_dashboard', 'noc'),
            'default_network' => $this->readSetting('default_network', ''),
            'timezone' => $this->readSetting('timezone', config('app.timezone')),
            'theme' => $this->readSetting('theme', 'system'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveSettings')
                ->label('Save Settings')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->schema([
                    TextInput::make('application_name')->required()->maxLength(120),
                    TextInput::make('noc_refresh_interval')
                        ->label('NOC Refresh Interval (seconds)')
                        ->numeric()
                        ->rules(['integer', 'between:15,3600'])
                        ->required(),
                    Select::make('default_dashboard')
                        ->options(['noc' => 'NOC Dashboard', 'wallboard' => 'NOC Wallboard'])
                        ->required()
                        ->native(false),
                    TextInput::make('default_network')
                        ->placeholder('192.168.101.0/24')
                        ->maxLength(43),
                    TextInput::make('timezone')
                        ->required()
                        ->maxLength(80),
                    Select::make('theme')
                        ->options(['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'])
                        ->required()
                        ->native(false),
                ])
                ->fillForm(fn (): array => $this->settings)
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->hasPermission('manage settings'), 403);

                    foreach ($data as $key => $value) {
                        AppSetting::query()->updateOrCreate(['key' => $key], ['value' => ['value' => $value]]);
                    }

                    $this->settings = $data;

                    Notification::make()->title('Settings saved')->success()->send();
                }),
        ];
    }

    private function readSetting(string $key, string|int|null $default): string|int
    {
        $value = AppSetting::query()->where('key', $key)->value('value');

        return is_array($value) && isset($value['value']) && is_scalar($value['value'])
            ? $value['value']
            : ($default ?? '');
    }
}
