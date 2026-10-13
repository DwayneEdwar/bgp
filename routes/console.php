<?php

use Illuminate\Foundation\Inspiring;
use App\Jobs\CollectNetworkSnapshot;
use App\Jobs\PurgeMonitoringHistory;
use App\Models\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    Router::query()->orderBy('id')->pluck('id')->each(
        static fn (int $routerId) => CollectNetworkSnapshot::dispatch([$routerId]),
    );
})->everyFiveMinutes()->name('collect-network-snapshots')->withoutOverlapping();

Schedule::job(new PurgeMonitoringHistory)->daily()->name('purge-monitoring-history')->withoutOverlapping();
