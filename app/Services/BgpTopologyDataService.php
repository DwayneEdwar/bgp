<?php

namespace App\Services;

use App\Models\BgpSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class BgpTopologyDataService
{
    /** @return array<string, mixed> */
    public function build(): array
    {
        $sessions = BgpSession::query()
            ->select([
                'id',
                'router_id',
                'peer_name',
                'remote_address',
                'remote_as',
                'local_as',
                'state',
                'uptime',
                'prefixes_received',
                'prefixes_sent',
                'checked_at',
            ])
            ->with('router:id,name,host')
            ->whereNotNull('router_id')
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        if ($sessions->isEmpty()) {
            return [
                'nodes' => [],
                'edges' => [],
                'ringGuides' => [],
                'viewBox' => ['width' => 1000, 'height' => 700],
                'lastUpdatedAt' => $this->cachedLastSync(),
            ];
        }

        $nodesById = [];
        $edges = [];
        $peerIdsByRouter = [];
        $lastUpdatedAt = null;

        foreach ($sessions as $session) {
            $router = $session->router;

            if ($router === null) {
                continue;
            }

            $routerNodeId = 'router-'.$router->getKey();
            $peerNodeId = 'peer-'.$session->getKey();

            $nodesById[$routerNodeId] ??= [
                'id' => $routerNodeId,
                'kind' => 'router',
                'role' => 'Origin',
                'visualState' => 'origin',
                'asLabel' => $this->asLabel($session->local_as, 'Local AS unavailable'),
                'identity' => $router->name,
                'router' => $router->name,
                'ipAddress' => $router->host,
                'localAs' => $session->local_as,
                'remoteAs' => null,
                'state' => 'Origin',
                'uptime' => null,
                'prefixesReceived' => null,
                'prefixesSent' => null,
                'lastUpdate' => $this->dateValue($session->checked_at),
            ];

            $visualState = $this->visualState((string) $session->state);
            $nodesById[$peerNodeId] = [
                'id' => $peerNodeId,
                'kind' => 'peer',
                'role' => 'Direct Peer',
                'visualState' => $visualState,
                'asLabel' => $this->asLabel($session->remote_as, 'External peer'),
                'identity' => $session->peer_name ?: 'Peer',
                'router' => $router->name,
                'ipAddress' => $session->remote_address,
                'localAs' => $session->local_as,
                'remoteAs' => $session->remote_as,
                'state' => $this->stateLabel($visualState, (string) $session->state),
                'uptime' => $session->uptime,
                'prefixesReceived' => $session->prefixes_received,
                'prefixesSent' => $session->prefixes_sent,
                'lastUpdate' => $this->dateValue($session->checked_at),
            ];

            $peerIdsByRouter[$router->getKey()][] = $peerNodeId;
            $checkedAt = $this->dateValue($session->checked_at);
            $lastUpdatedAt ??= $checkedAt;

            $edges[] = [
                'id' => 'session-'.$session->getKey(),
                'source' => $routerNodeId,
                'target' => $peerNodeId,
                'visualState' => $visualState,
                'state' => $this->stateLabel($visualState, (string) $session->state),
                'direction' => null,
                'routerName' => $router->name,
                'peerName' => $session->peer_name,
                'localAs' => $session->local_as,
                'remoteAs' => $session->remote_as,
                'localAddress' => null,
                'remoteAddress' => $session->remote_address,
                'prefixesReceived' => $session->prefixes_received,
                'prefixesSent' => $session->prefixes_sent,
                'prefixLabel' => $this->prefixLabel($session->prefixes_received, $session->prefixes_sent),
                'uptime' => $session->uptime,
                'lastUpdate' => $checkedAt,
            ];
        }

        if ($peerIdsByRouter === []) {
            return [
                'nodes' => [],
                'edges' => [],
                'ringGuides' => [],
                'viewBox' => ['width' => 1000, 'height' => 700],
                'lastUpdatedAt' => $this->cachedLastSync(),
            ];
        }

        $ringGuides = [];
        $viewBox = $this->positionNodes($nodesById, $peerIdsByRouter, $ringGuides);

        return [
            'nodes' => array_values($nodesById),
            'edges' => $edges,
            'ringGuides' => $ringGuides,
            'viewBox' => $viewBox,
            'lastUpdatedAt' => $this->cachedLastSync() ?? $lastUpdatedAt,
        ];
    }

    /** @param array<string, array<string, mixed>> $nodesById
     * @param  array<int|string, list<string>>  $peerIdsByRouter
     * @param  list<array{id: string, x: int, y: int, radius: int, label: string}>  $ringGuides
     * @return array{width: int, height: int}
     */
    private function positionNodes(array &$nodesById, array $peerIdsByRouter, array &$ringGuides): array
    {
        $routerIds = array_keys($peerIdsByRouter);
        $maxPeerCount = max(array_map('count', $peerIdsByRouter));
        $radius = max(250, $maxPeerCount * 24);
        $spacing = max(1000, (($radius + 280) * 2) + 180);
        $columnCount = (int) ceil(sqrt(count($routerIds)));
        $rowCount = (int) ceil(count($routerIds) / $columnCount);
        $width = $columnCount * $spacing;
        $height = $rowCount * $spacing;

        foreach ($routerIds as $index => $routerId) {
            $column = $index % $columnCount;
            $row = intdiv($index, $columnCount);
            $centerX = (int) round(($column + 0.5) * $spacing);
            $centerY = (int) round(($row + 0.5) * $spacing);
            $routerNodeId = 'router-'.$routerId;
            $nodesById[$routerNodeId]['x'] = $centerX;
            $nodesById[$routerNodeId]['y'] = $centerY;

            foreach ([
                [$radius + 40, 'Direct peers'],
                [$radius + 150, 'Upstream / transit'],
                [$radius + 260, 'External AS'],
            ] as [$ringRadius, $label]) {
                $ringGuides[] = [
                    'id' => $routerNodeId.'-ring-'.$label,
                    'x' => $centerX,
                    'y' => $centerY,
                    'radius' => $ringRadius,
                    'label' => $label,
                ];
            }

            $peerIds = $peerIdsByRouter[$routerId];
            $peerCount = count($peerIds);

            foreach ($peerIds as $peerIndex => $peerId) {
                $angle = (-M_PI / 2) + ((2 * M_PI * $peerIndex) / $peerCount);
                $nodesById[$peerId]['x'] = (int) round($centerX + cos($angle) * $radius);
                $nodesById[$peerId]['y'] = (int) round($centerY + sin($angle) * $radius);
            }
        }

        return ['width' => $width, 'height' => $height];
    }

    private function visualState(string $state): string
    {
        return match (strtolower(trim($state))) {
            'established' => 'established',
            'active', 'connect', 'connecting', 'opensent', 'openconfirm' => 'connecting',
            'idle', 'down', 'disabled' => 'down',
            default => 'unknown',
        };
    }

    private function stateLabel(string $visualState, string $state): string
    {
        return match ($visualState) {
            'established' => 'Established',
            'connecting' => 'Connecting',
            'down' => 'Idle / Down',
            default => $state !== '' ? ucfirst($state) : 'Unknown',
        };
    }

    private function asLabel(mixed $asNumber, string $fallback): string
    {
        return is_numeric($asNumber) ? 'AS'.number_format((int) $asNumber) : $fallback;
    }

    private function prefixLabel(mixed $received, mixed $sent): ?string
    {
        $labels = [];

        if (is_numeric($received)) {
            $labels[] = number_format((int) $received).' RX';
        }

        if (is_numeric($sent)) {
            $labels[] = number_format((int) $sent).' TX';
        }

        return $labels !== [] ? implode(' / ', $labels).' pfx' : 'BGP';
    }

    private function dateValue(mixed $value): ?string
    {
        return $value instanceof Carbon ? $value->toIso8601String() : null;
    }

    private function cachedLastSync(): ?string
    {
        $lastSync = Cache::get('bgp-topology.last-sync');

        return is_string($lastSync) && $lastSync !== '' ? $lastSync : null;
    }
}
