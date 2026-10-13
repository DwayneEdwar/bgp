<?php

namespace App\Filament\Resources\Routers\Pages;

use App\Filament\Resources\Routers\RouterResource;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikConnectionService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use Throwable;

class ViewRouter extends ViewRecord
{
    protected static string $resource = RouterResource::class;

    /**
     * @var array{
     *     identity: ?string,
     *     version: ?string,
     *     architecture: ?string,
     *     cpu_load: ?int,
     *     memory_total: ?int,
     *     memory_free: ?int,
     *     uptime: ?string
     * }|null
     */
    public ?array $systemInfo = null;

    public ?string $systemInfoUpdatedAt = null;

    public ?string $systemInfoError = null;

    protected function getHeaderActions(): array
    {
        return [
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
            Action::make('refreshSystemInfo')
                ->label('Refresh System Info')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(fn (Router $record): bool => $this->refreshSystemInfo($record))
                ->extraAttributes(fn (): array => ['wire:loading.attr' => 'disabled']),
            EditAction::make(),
            DeleteAction::make()
                ->modalHeading('Delete Router')
                ->modalDescription('Are you sure you want to delete this router? Discovered device history will be retained.')
                ->modalSubmitActionLabel('Delete Router'),
        ];
    }

    /**
     * @return array{total: string, used: string, free: string, usage: string}
     */
    public function getFormattedMemory(): array
    {
        $total = $this->systemInfo['memory_total'] ?? null;
        $free = $this->systemInfo['memory_free'] ?? null;

        if (! is_numeric($total) || ! is_numeric($free) || (int) $total <= 0) {
            return [
                'total' => 'N/A',
                'used' => 'N/A',
                'free' => 'N/A',
                'usage' => 'N/A',
            ];
        }

        $total = (int) $total;
        $free = max(0, min($total, (int) $free));
        $used = $total - $free;
        $usage = ($used / $total) * 100;

        return [
            'total' => Number::fileSize($total),
            'used' => Number::fileSize($used),
            'free' => Number::fileSize($free),
            'usage' => number_format($usage, 1).'%',
        ];
    }

    private function refreshSystemInfo(Router $router): bool
    {
        $this->systemInfoError = null;

        try {
            $systemInfo = app(MikrotikConnectionService::class)->getSystemInfo($router);
        } catch (Throwable $exception) {
            Log::warning('Unable to refresh router system information.', [
                'router_id' => $router->getKey(),
                'exception_class' => $exception::class,
            ]);

            $this->systemInfo = null;
            $this->systemInfoUpdatedAt = null;
            $this->systemInfoError = 'Unable to retrieve system information.';

            Notification::make()
                ->title('Unable to retrieve system information.')
                ->danger()
                ->send();

            return false;
        }

        $this->systemInfo = [
            'identity' => $this->safeString($systemInfo['identity'] ?? null),
            'version' => $this->safeString($systemInfo['version'] ?? null),
            'architecture' => $this->safeString($systemInfo['architecture'] ?? null),
            'cpu_load' => is_numeric($systemInfo['cpu_load'] ?? null) ? (int) $systemInfo['cpu_load'] : null,
            'memory_total' => is_numeric($systemInfo['memory_total'] ?? null) ? (int) $systemInfo['memory_total'] : null,
            'memory_free' => is_numeric($systemInfo['memory_free'] ?? null) ? (int) $systemInfo['memory_free'] : null,
            'uptime' => $this->safeString($systemInfo['uptime'] ?? null),
        ];
        $this->systemInfoUpdatedAt = now()->toDateTimeString();

        Notification::make()
            ->title('System information refreshed')
            ->success()
            ->send();

        return true;
    }

    private function safeString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
