<?php

namespace App\Console\Commands;

use App\Services\Mikrotik\NetworkDiscoveryService;
use Illuminate\Console\Command;

class NetworkDiscoveryCommand extends Command
{
    protected $signature = 'network:discover
                            {subnet : Contoh 192.168.101.0/24}';

    protected $description = 'Scan jaringan untuk mendeteksi MikroTik API';

    public function handle(
        NetworkDiscoveryService $discovery
    ): int {
        $subnet = $this->argument('subnet');

        $this->info("Scanning {$subnet}...");

        try {
            $devices = $discovery->scanSubnet($subnet);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (empty($devices)) {
            $this->warn('Tidak ada perangkat dengan API port 8728 yang ditemukan.');

            return self::SUCCESS;
        }

        $this->newLine();

        $this->table(
            [
                'ID',
                'IP Address',
                'API Port',
                'Status',
                'Registered',
            ],
            collect($devices)->map(function ($device) {
                return [
                    $device->id,
                    $device->ip_address,
                    $device->api_port,
                    $device->status,
                    $device->router_id ? 'Ya' : 'Tidak',
                ];
            })->toArray()
        );

        return self::SUCCESS;
    }
}
