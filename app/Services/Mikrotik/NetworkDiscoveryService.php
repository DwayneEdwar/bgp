<?php

namespace App\Services\Mikrotik;

use App\Models\DiscoveredDevice;
use App\Models\Router;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class NetworkDiscoveryService
{
    /**
     * @param  list<int>  $ports
     * @return list<DiscoveredDevice>
     */
    public function scanSubnet(string $subnet, array $ports = [8728], float $timeout = 0.15): array
    {
        [$networkAddress, $prefixLength] = $this->parseSubnet($subnet);

        if ($prefixLength < 24 || $prefixLength > 30) {
            throw new InvalidArgumentException('Choose an IPv4 subnet between /24 and /30 to keep scans bounded.');
        }

        $ports = array_values(array_unique(array_filter($ports, static fn (int $port): bool => $port >= 1 && $port <= 65535)));

        if ($ports === []) {
            throw new InvalidArgumentException('Select at least one API port to scan.');
        }

        if (count($ports) > 4) {
            throw new InvalidArgumentException('A scan can check at most four explicitly selected ports.');
        }

        [$firstHost, $lastHost] = $this->hostRange($networkAddress, $prefixLength);
        $discovered = [];

        for ($address = $firstHost; $address <= $lastHost; $address++) {
            $host = long2ip($address);

            foreach ($ports as $port) {
                if (! $this->checkPort($host, $port, $timeout)) {
                    continue;
                }

                $now = Carbon::now();
                $router = Router::query()
                    ->where('host', $host)
                    ->where('port', $port)
                    ->first();

                $device = DiscoveredDevice::query()->firstOrNew([
                    'ip_address' => $host,
                    'api_port' => $port,
                ]);

                $device->fill([
                    'api_available' => true,
                    'status' => 'online',
                    'first_seen_at' => $device->first_seen_at ?? $now,
                    'last_seen_at' => $now,
                    'router_id' => $router?->getKey() ?? $device->router_id,
                ]);
                $device->save();

                $discovered[] = $device;
            }
        }

        return $discovered;
    }

    /**
     * @return array<string, string>
     */
    public function detectServerNetworks(): array
    {
        if (! function_exists('net_get_interfaces')) {
            return [];
        }

        $networks = [];

        foreach (net_get_interfaces() ?: [] as $interfaceName => $interface) {
            foreach ($interface['unicast'] ?? [] as $address) {
                if (($address['family'] ?? null) !== AF_INET) {
                    continue;
                }

                $ip = $address['address'] ?? null;
                $netmask = $address['netmask'] ?? null;

                if (! is_string($ip) || ! is_string($netmask) || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    continue;
                }

                if (str_starts_with($ip, '127.') || str_starts_with($ip, '169.254.')) {
                    continue;
                }

                $prefixLength = $this->prefixLength($netmask);

                if ($prefixLength < 24) {
                    $prefixLength = 24;
                }

                if ($prefixLength > 30) {
                    continue;
                }

                $subnet = $this->networkAddress($ip, $prefixLength).'/'.$prefixLength;
                $networks[$subnet] = "{$subnet} · {$interfaceName} ({$ip}/".$this->prefixLength($netmask).')';
            }
        }

        return $networks;
    }

    /**
     * @return array{string, int}
     */
    public function parseSubnet(string $subnet): array
    {
        if (! preg_match('/^(\d{1,3}(?:\.\d{1,3}){3})\/(\d{1,2})$/', trim($subnet), $matches)) {
            throw new InvalidArgumentException('Enter a valid IPv4 subnet in CIDR notation, for example 192.168.101.0/24.');
        }

        $ip = $matches[1];
        $prefixLength = (int) $matches[2];

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || $prefixLength < 16 || $prefixLength > 30) {
            throw new InvalidArgumentException('The IPv4 CIDR range must use a valid address and prefix from /16 to /30.');
        }

        $networkAddress = $this->networkAddress($ip, $prefixLength);

        return [$networkAddress, $prefixLength];
    }

    private function checkPort(string $ip, int $port, float $timeout): bool
    {
        $connection = @fsockopen($ip, $port, $errorCode, $errorMessage, $timeout);

        if (! is_resource($connection)) {
            return false;
        }

        fclose($connection);

        return true;
    }

    /**
     * @return array{int, int}
     */
    private function hostRange(string $networkAddress, int $prefixLength): array
    {
        $network = (int) ip2long($networkAddress);
        $hostCount = 2 ** (32 - $prefixLength);
        $firstHost = $network + 1;
        $lastHost = $network + $hostCount - 2;

        return [$firstHost, $lastHost];
    }

    private function networkAddress(string $ip, int $prefixLength): string
    {
        $ipAddress = (int) ip2long($ip);
        $mask = (int) (-1 << (32 - $prefixLength));

        return long2ip($ipAddress & $mask);
    }

    private function prefixLength(string $netmask): int
    {
        $mask = ip2long($netmask);

        if ($mask === false) {
            return 32;
        }

        return substr_count(decbin((int) $mask), '1');
    }
}
