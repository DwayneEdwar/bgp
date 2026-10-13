<?php

namespace App\Jobs;

use App\Models\AvailabilityCheck;
use App\Models\RouterMetric;
use App\Models\TrafficSample;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PurgeMonitoringHistory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        AvailabilityCheck::query()->where('checked_at', '<', now()->subDays(30))->delete();
        RouterMetric::query()->where('sampled_at', '<', now()->subDays(30))->delete();
        TrafficSample::query()->where('sampled_at', '<', now()->subDays(30))->delete();
    }
}
