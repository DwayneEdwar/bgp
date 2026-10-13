<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouterMetric extends Model
{
    public $timestamps = false;

    protected $fillable = ['router_id', 'cpu_load', 'memory_total', 'memory_free', 'uptime', 'sampled_at'];

    protected function casts(): array
    {
        return ['sampled_at' => 'datetime'];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }
}
