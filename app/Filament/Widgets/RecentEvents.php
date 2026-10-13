<?php

namespace App\Filament\Widgets;

use App\Models\NetworkEvent;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class RecentEvents extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -5;

    protected string $view = 'filament.widgets.recent-events';

    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 1,
        '2xl' => 4,
    ];

    /** @return Collection<int, NetworkEvent> */
    public function getRecentEvents(): Collection
    {
        return NetworkEvent::query()
            ->with('router:id,name')
            ->latest('created_at')
            ->limit(6)
            ->get();
    }
}
