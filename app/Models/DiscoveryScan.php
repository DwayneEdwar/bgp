<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscoveryScan extends Model
{
    protected $fillable = [
        'subnet',
        'ports',
        'status',
        'devices_found',
        'failure_code',
        'requested_by',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'ports' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
