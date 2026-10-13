<?php

namespace App\Filament\Support;

use App\Models\Router;

class RouterStatusSummary
{
    /**
     * @return array{total: int, online: int, offline: int, unknown: int}
     */
    public function get(): array
    {
        $countsByStatus = Router::query()
            ->select('status')
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (int|string $count): int => (int) $count);

        return [
            'total' => $countsByStatus->sum(),
            'online' => $countsByStatus->get('online', 0),
            'offline' => $countsByStatus->get('offline', 0),
            'unknown' => $countsByStatus
                ->except(['online', 'offline'])
                ->sum(),
        ];
    }
}
