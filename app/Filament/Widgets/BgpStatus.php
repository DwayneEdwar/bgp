<?php

namespace App\Filament\Widgets;

use App\Models\BgpSession;
use Filament\Widgets\Widget;

class BgpStatus extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -8;

    protected string $view = 'filament.widgets.bgp-status';

    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 1,
        '2xl' => 6,
    ];

    /** @return array{total: int, established: int, active: int, idle: int, down: int, prefixes_received: int, prefixes_sent: int} */
    public function getMetrics(): array
    {
        $sessions = BgpSession::query()->where('checked_at', '>=', now()->subMinutes(15));
        $states = (clone $sessions)->selectRaw('LOWER(state) AS state_key, COUNT(*) AS state_count')->groupBy('state_key')->pluck('state_count', 'state_key');

        return [
            'total' => (clone $sessions)->count(),
            'established' => (int) $states->get('established', 0),
            'active' => (int) $states->get('active', 0),
            'idle' => (int) $states->get('idle', 0),
            'down' => (int) $states->get('down', 0) + (int) $states->get('connect', 0),
            'prefixes_received' => (int) (clone $sessions)->sum('prefixes_received'),
            'prefixes_sent' => (int) (clone $sessions)->sum('prefixes_sent'),
        ];
    }
}
