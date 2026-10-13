<?php

namespace App\Jobs;

use App\Models\DiscoveryScan;
use App\Services\Mikrotik\NetworkDiscoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunNetworkDiscoveryScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $scanId) {}

    public function handle(NetworkDiscoveryService $discoveryService): void
    {
        $scan = DiscoveryScan::query()->find($this->scanId);

        if ($scan === null || $scan->status !== 'queued') {
            return;
        }

        $scan->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $devices = $discoveryService->scanSubnet($scan->subnet, $scan->ports ?? []);

            $scan->update([
                'status' => 'completed',
                'devices_found' => count($devices),
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Network discovery scan failed.', [
                'scan_id' => $scan->getKey(),
                'exception_class' => $exception::class,
            ]);

            $scan->update([
                'status' => 'failed',
                'failure_code' => 'scan_failed',
                'finished_at' => now(),
            ]);
        }
    }
}
