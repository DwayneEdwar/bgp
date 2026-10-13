<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouterNeighbor extends Model
{
    protected $fillable = [
        'router_id', 'neighbor_router_id', 'neighbor_identity', 'neighbor_address', 'local_interface', 'platform', 'protocol', 'observed_at',
    ];

    protected function casts(): array
    {
        return ['observed_at' => 'datetime'];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function neighborRouter(): BelongsTo
    {
        return $this->belongsTo(Router::class, 'neighbor_router_id');
    }
}
