<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrafficSample extends Model
{
    public $timestamps = false;

    protected $fillable = ['router_id', 'interface_name', 'rx_bytes', 'tx_bytes', 'rx_packets', 'tx_packets', 'rx_bps', 'tx_bps', 'rx_pps', 'tx_pps', 'sampled_at'];

    protected function casts(): array
    {
        return ['sampled_at' => 'datetime'];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }
}
