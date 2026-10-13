<?php

namespace App\Filament\Navigation;

use App\Filament\Pages\BgpTopology;
use App\Filament\Pages\ComingSoon;
use App\Filament\Pages\NocWallboard;
use App\Filament\Pages\RouterData;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;

class NocNavigation
{
    /**
     * @var list<string>
     */
    private const GROUPS = [
        'Dashboard',
        'Network Management',
        'Routing',
        'Monitoring',
        'Topology',
        'Configuration',
        'System',
    ];

    /**
     * @var array<string, array{group: string, label: string, description: string, icon: string, sort: int}>
     */
    private const MODULES = [
        'network-interfaces' => [
            'group' => 'Network Management',
            'label' => 'Interfaces',
            'description' => 'Inventaris interface dan status operasional belum tersedia.',
            'icon' => 'heroicon-o-rectangle-stack',
            'sort' => 3,
        ],
        'ip-addresses' => [
            'group' => 'Network Management',
            'label' => 'IP Addresses',
            'description' => 'Manajemen alamat IP jaringan belum tersedia.',
            'icon' => 'heroicon-o-globe-alt',
            'sort' => 4,
        ],
        'routing-table' => [
            'group' => 'Routing',
            'label' => 'Routing Table',
            'description' => 'Pembacaan dan penelusuran routing table belum tersedia.',
            'icon' => 'heroicon-o-queue-list',
            'sort' => 1,
        ],
        'static-routes' => [
            'group' => 'Routing',
            'label' => 'Static Routes',
            'description' => 'Manajemen static route belum tersedia.',
            'icon' => 'heroicon-o-arrows-right-left',
            'sort' => 2,
        ],
        'bgp' => [
            'group' => 'Routing',
            'label' => 'BGP',
            'description' => 'Modul BGP belum tersedia pada Step 1.',
            'icon' => 'heroicon-o-share',
            'sort' => 3,
        ],
        'bgp-peers' => [
            'group' => 'Routing',
            'label' => 'BGP Peers',
            'description' => 'Pemantauan BGP peer belum tersedia pada Step 1.',
            'icon' => 'heroicon-o-circle-stack',
            'sort' => 4,
        ],
        'bgp-routes' => [
            'group' => 'Routing',
            'label' => 'BGP Routes',
            'description' => 'Penelusuran BGP route belum tersedia pada Step 1.',
            'icon' => 'heroicon-o-list-bullet',
            'sort' => 5,
        ],
        'network-monitor' => [
            'group' => 'Monitoring',
            'label' => 'Network Monitor',
            'description' => 'Pemantauan jaringan berkala belum tersedia.',
            'icon' => 'heroicon-o-signal',
            'sort' => 1,
        ],
        'traffic' => [
            'group' => 'Monitoring',
            'label' => 'Traffic',
            'description' => 'Pemantauan traffic jaringan belum tersedia.',
            'icon' => 'heroicon-o-chart-bar-square',
            'sort' => 2,
        ],
        'cpu-memory' => [
            'group' => 'Monitoring',
            'label' => 'CPU & Memory',
            'description' => 'Pengumpulan metrik CPU dan memory belum tersedia.',
            'icon' => 'heroicon-o-cpu-chip',
            'sort' => 3,
        ],
        'availability' => [
            'group' => 'Monitoring',
            'label' => 'Availability',
            'description' => 'Riwayat dan perhitungan availability belum tersedia.',
            'icon' => 'heroicon-o-clock',
            'sort' => 4,
        ],
        'alerts' => [
            'group' => 'Monitoring',
            'label' => 'Alerts',
            'description' => 'Alert dan mekanisme eskalasi belum tersedia.',
            'icon' => 'heroicon-o-bell-alert',
            'sort' => 5,
        ],
        'logs' => [
            'group' => 'Monitoring',
            'label' => 'Logs',
            'description' => 'Pengumpulan dan pencarian log perangkat belum tersedia.',
            'icon' => 'heroicon-o-document-text',
            'sort' => 6,
        ],
        'network-topology' => [
            'group' => 'Topology',
            'label' => 'Network Topology',
            'description' => 'Network topology akan ditampilkan setelah topology engine tersedia.',
            'icon' => 'heroicon-o-share',
            'sort' => 1,
        ],
        'bgp-topology' => [
            'group' => 'Topology',
            'label' => 'BGP Topology',
            'description' => 'Visualisasi hubungan BGP belum tersedia pada Step 1.',
            'icon' => 'heroicon-o-circle-stack',
            'sort' => 2,
        ],
        'physical-logical-map' => [
            'group' => 'Topology',
            'label' => 'Physical / Logical Map',
            'description' => 'Peta fisik dan logis jaringan belum tersedia.',
            'icon' => 'heroicon-o-map',
            'sort' => 3,
        ],
        'identity' => [
            'group' => 'Configuration',
            'label' => 'Identity',
            'description' => 'Pengaturan identity router belum tersedia.',
            'icon' => 'heroicon-o-identification',
            'sort' => 1,
        ],
        'configuration-interfaces' => [
            'group' => 'Configuration',
            'label' => 'Interfaces',
            'description' => 'Pengelolaan konfigurasi interface belum tersedia.',
            'icon' => 'heroicon-o-rectangle-stack',
            'sort' => 2,
        ],
        'configuration-ip-address' => [
            'group' => 'Configuration',
            'label' => 'IP Address',
            'description' => 'Pengelolaan konfigurasi IP address belum tersedia.',
            'icon' => 'heroicon-o-globe-alt',
            'sort' => 3,
        ],
        'dhcp' => [
            'group' => 'Configuration',
            'label' => 'DHCP',
            'description' => 'Konfigurasi DHCP belum tersedia.',
            'icon' => 'heroicon-o-computer-desktop',
            'sort' => 4,
        ],
        'dns' => [
            'group' => 'Configuration',
            'label' => 'DNS',
            'description' => 'Konfigurasi DNS belum tersedia.',
            'icon' => 'heroicon-o-globe-alt',
            'sort' => 5,
        ],
        'nat' => [
            'group' => 'Configuration',
            'label' => 'NAT',
            'description' => 'Konfigurasi NAT belum tersedia.',
            'icon' => 'heroicon-o-arrows-right-left',
            'sort' => 6,
        ],
        'firewall' => [
            'group' => 'Configuration',
            'label' => 'Firewall',
            'description' => 'Konfigurasi firewall belum tersedia.',
            'icon' => 'heroicon-o-shield-check',
            'sort' => 7,
        ],
        'configuration-routes' => [
            'group' => 'Configuration',
            'label' => 'Routes',
            'description' => 'Konfigurasi route belum tersedia.',
            'icon' => 'heroicon-o-queue-list',
            'sort' => 8,
        ],
        'bgp-configuration' => [
            'group' => 'Configuration',
            'label' => 'BGP Settings',
            'description' => 'Inspect BGP peer configuration reported by the selected RouterOS device. Configuration changes are not supported by the existing MikroTik adapter.',
            'icon' => 'heroicon-o-share',
            'sort' => 9,
        ],
        'activity-logs' => [
            'group' => 'System',
            'label' => 'Activity Logs',
            'description' => 'Audit aktivitas pengguna belum tersedia.',
            'icon' => 'heroicon-o-clipboard-document-list',
            'sort' => 4,
        ],
    ];

    /**
     * @return list<NavigationGroup>
     */
    public static function groups(): array
    {
        return array_map(static function (string $label): NavigationGroup {
            $group = NavigationGroup::make($label)->collapsible();

            return in_array($label, ['Dashboard', 'Network Management'], strict: true)
                ? $group
                : $group->collapsed();
        }, self::GROUPS);
    }

    /**
     * @return list<NavigationItem>
     */
    public static function items(): array
    {
        $items = [];

        foreach (self::MODULES as $key => $module) {
            $items[] = NavigationItem::make($module['label'])
                ->key($key)
                ->group($module['group'])
                ->icon($module['icon'])
                ->sort($module['sort'])
                ->badge(fn (): string => $key === 'bgp-topology' || in_array($key, RouterData::supportedModules(), true) ? 'Live data' : 'Soon', 'success')
                ->badgeTooltip(fn (): string => $key === 'bgp-topology' || in_array($key, RouterData::supportedModules(), true)
                    ? 'Reads current RouterOS data when you request a refresh'
                    : 'Module has not been implemented yet')
                ->url(fn (): string => self::moduleUrl($key))
                ->isActiveWhen(fn (): bool => self::moduleIsActive($key));
        }

        $items[] = NavigationItem::make('NOC Wallboard')
            ->key('noc-wallboard')
            ->group('Dashboard')
            ->icon('heroicon-o-computer-desktop')
            ->sort(-1)
            ->url(fn (): string => NocWallboard::getUrl(panel: 'admin'))
            ->isActiveWhen(fn (): bool => request()->routeIs(NocWallboard::getRouteName(Filament::getPanel('admin'))));

        return $items;
    }

    private static function moduleUrl(string $key): string
    {
        if ($key === 'bgp-topology') {
            return BgpTopology::getUrl(panel: 'admin');
        }

        if (in_array($key, RouterData::supportedModules(), true)) {
            return RouterData::getUrl(['module' => $key], panel: 'admin');
        }

        return ComingSoon::getUrl(['module' => $key], panel: 'admin');
    }

    private static function moduleIsActive(string $key): bool
    {
        if ($key === 'bgp-topology') {
            return request()->routeIs(BgpTopology::getRouteName(Filament::getPanel('admin')));
        }

        return (request()->routeIs(RouterData::getRouteName(Filament::getPanel('admin')))
                || request()->routeIs(ComingSoon::getRouteName(Filament::getPanel('admin'))))
            && request()->query('module') === $key;
    }

    /**
     * @return array{group: string, label: string, description: string, icon: string, sort: int}|null
     */
    public static function placeholder(?string $key): ?array
    {
        return $key !== null ? (self::MODULES[$key] ?? null) : null;
    }
}
