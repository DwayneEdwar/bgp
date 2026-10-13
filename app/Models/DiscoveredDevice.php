<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscoveredDevice extends Model
{
    protected $fillable = [
        'ip_address',
        'api_port',
        'hostname',
        'identity',
        'device_type',
        'api_available',
        'status',
        'first_seen_at',
        'last_seen_at',
        'router_id',
    ];

    protected function casts(): array
    {
        return [
            'api_available' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }
}
