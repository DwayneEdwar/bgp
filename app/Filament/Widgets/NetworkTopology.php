<?php

namespace App\Filament\Widgets;

use App\Models\RouterNeighbor;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class NetworkTopology extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -7;

    protected string $view = 'filament.widgets.network-topology';

    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 2,
        '2xl' => 8,
    ];

    /** @return Collection<int, RouterNeighbor> */
    public function getObservedNeighbors(): Collection
    {
        return RouterNeighbor::query()
            ->with(['router:id,name,host', 'neighborRouter:id,name,host'])
            ->where('observed_at', '>=', now()->subMinutes(30))
            ->orderByDesc('observed_at')
            ->limit(12)
            ->get();
    }
}
