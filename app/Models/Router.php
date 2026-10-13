<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Router extends Model
{
    protected $fillable = [
        'name',
        'host',
        'port',
        'username',
        'password',
        'connection_type',
        'status',
        'last_connected_at',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'last_connected_at' => 'datetime',
        ];
    }

    public function discoveredDevices(): HasMany
    {
        return $this->hasMany(DiscoveredDevice::class);
    }

    public function availabilityChecks(): HasMany
    {
        return $this->hasMany(AvailabilityCheck::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(RouterMetric::class);
    }

    public function trafficSamples(): HasMany
    {
        return $this->hasMany(TrafficSample::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function bgpSessions(): HasMany
    {
        return $this->hasMany(BgpSession::class);
    }

    public function neighbors(): HasMany
    {
        return $this->hasMany(RouterNeighbor::class);
    }
}
