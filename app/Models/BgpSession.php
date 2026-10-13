<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BgpSession extends Model
{
    protected $fillable = [
        'router_id', 'peer_name', 'remote_address', 'remote_as', 'local_as', 'state', 'uptime',
        'prefixes_received', 'prefixes_sent', 'checked_at',
    ];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }
}
