<x-filament-panels::page>
    <div
        class="bgp-topology-page"
        x-data="bgpTopology(@js($topology))"
        x-init="init()"
        @pointermove.window="movePointer($event)"
        @pointerup.window="endPointer()"
        @pointercancel.window="endPointer()"
        @topology-updated.window="replaceTopology($event.detail.topology)"
        @keydown.escape.window="closeDetails()"
    >
        <div class="bgp-topology-heading">
            <div>
                <p class="bgp-topology-subtitle">Visual map of BGP sessions fetched from MikroTik devices</p>
                <p class="bgp-topology-last-sync">
                    Last sync:
                    @if ($topology['lastUpdatedAt'])
                        {{ \Illuminate\Support\Carbon::parse($topology['lastUpdatedAt'])->format('h:i:s A') }}
                    @else
                        Never
                    @endif
                </p>
                @if ($topology['nodes'] !== [])
                    <p class="bgp-topology-count">{{ count($topology['nodes']) }} nodes · {{ count($topology['edges']) }} sessions</p>
                @endif
            </div>
            <div class="bgp-topology-toolbar" aria-label="Topology controls">
                @if ($canSync)
                    <button type="button" class="bgp-toolbar-button bgp-toolbar-button--primary" wire:click="syncFromMikroTik" wire:loading.attr="disabled" wire:target="syncFromMikroTik" aria-label="Sync from MikroTik" title="Sync from MikroTik">
                        <x-filament::icon icon="heroicon-o-arrow-path" class="size-4" wire:loading.remove wire:target="syncFromMikroTik" />
                        <x-filament::loading-indicator class="size-4" wire:loading wire:target="syncFromMikroTik" />
                        <span wire:loading.remove wire:target="syncFromMikroTik">Sync from MikroTik</span>
                        <span wire:loading wire:target="syncFromMikroTik">Syncing BGP topology...</span>
                    </button>
                @endif
                <div class="bgp-auto-refresh" role="group" aria-label="Database auto refresh controls">
                    <button type="button" class="bgp-toolbar-button bgp-toolbar-button--compact" :class="autoRefresh ? 'is-active' : ''" x-on:click="toggleAutoRefresh()" :aria-pressed="autoRefresh" title="Toggle database auto refresh">
                        <x-filament::icon icon="heroicon-o-arrow-path-rounded-square" class="size-4" />
                        <span>Auto refresh</span>
                    </button>
                    <select x-model.number="autoRefreshSeconds" x-on:change="restartAutoRefresh()" aria-label="Auto refresh interval" title="Auto refresh interval">
                        <option value="10">10 sec</option>
                        <option value="30">30 sec</option>
                        <option value="60">60 sec</option>
                        <option value="300">5 min</option>
                    </select>
                </div>
                <button type="button" class="bgp-toolbar-button" x-on:click="fitView()" aria-label="Fit view" title="Fit view">
                    <x-filament::icon icon="heroicon-o-arrows-pointing-out" class="size-4" />
                    <span>Fit View</span>
                </button>
                <button type="button" class="bgp-toolbar-button" x-on:click="toggleFullscreen()" :aria-label="fullscreen ? 'Exit fullscreen' : 'Fullscreen'" :title="fullscreen ? 'Exit fullscreen' : 'Fullscreen'">
                    <x-filament::icon icon="heroicon-o-arrows-right-left" class="size-4" />
                    <span x-text="fullscreen ? 'Exit Fullscreen' : 'Fullscreen'"></span>
                </button>
            </div>
        </div>

        @if ($syncErrors !== [])
            <div class="bgp-sync-errors" role="alert">
                <div class="bgp-sync-errors-heading"><x-filament::icon icon="heroicon-o-exclamation-triangle" class="size-4" /><p>BGP synchronization completed with errors.</p></div>
                <ul>
                    @foreach ($syncErrors as $error)
                        <li><strong>{{ $error['router'] }}</strong><span>{{ $error['message'] }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($syncHadNoOnlineRouters)
            <div class="bgp-sync-errors" role="status">
                <div class="bgp-sync-errors-heading"><x-filament::icon icon="heroicon-o-signal-slash" class="size-4" /><p>No online routers available for BGP synchronization.</p></div>
            </div>
        @endif

        <section x-ref="canvasContainer" class="bgp-topology-canvas" aria-label="BGP topology graph">
            @if ($topology['nodes'] === [])
                <div class="bgp-topology-empty" role="status">
                    <span class="bgp-topology-empty-mark" aria-hidden="true">AS</span>
                    <h2>No BGP topology data available</h2>
                    <p>Sync from MikroTik to discover BGP sessions.</p>
                    @if ($canSync)
                        <button type="button" class="bgp-toolbar-button bgp-toolbar-button--primary" wire:click="syncFromMikroTik" wire:loading.attr="disabled" wire:target="syncFromMikroTik">
                            <x-filament::icon icon="heroicon-o-arrow-path" class="size-4" wire:loading.remove wire:target="syncFromMikroTik" />
                            <x-filament::loading-indicator class="size-4" wire:loading wire:target="syncFromMikroTik" />
                            <span wire:loading.remove wire:target="syncFromMikroTik">Sync from MikroTik</span>
                            <span wire:loading wire:target="syncFromMikroTik">Syncing BGP topology...</span>
                        </button>
                    @endif
                </div>
            @else
                <svg
                    x-ref="canvas"
                    class="bgp-topology-svg"
                    viewBox="0 0 {{ $topology['viewBox']['width'] }} {{ $topology['viewBox']['height'] }}"
                    role="img"
                    aria-label="BGP router and peer connections"
                    x-on:pointerdown="startPan($event)"
                    x-on:wheel.prevent="zoomAt($event)"
                >
                    <defs>
                        <pattern id="bgp-grid" width="32" height="32" patternUnits="userSpaceOnUse">
                            <path d="M 32 0 L 0 0 0 32" class="bgp-grid-line" />
                        </pattern>
                        <marker id="arrow-established" markerWidth="9" markerHeight="9" refX="7" refY="4.5" orient="auto"><path d="M0,0 L9,4.5 L0,9 z" class="bgp-arrow-established" /></marker>
                        <marker id="arrow-connecting" markerWidth="9" markerHeight="9" refX="7" refY="4.5" orient="auto"><path d="M0,0 L9,4.5 L0,9 z" class="bgp-arrow-connecting" /></marker>
                        <marker id="arrow-down" markerWidth="9" markerHeight="9" refX="7" refY="4.5" orient="auto"><path d="M0,0 L9,4.5 L0,9 z" class="bgp-arrow-down" /></marker>
                        <marker id="arrow-unknown" markerWidth="9" markerHeight="9" refX="7" refY="4.5" orient="auto"><path d="M0,0 L9,4.5 L0,9 z" class="bgp-arrow-unknown" /></marker>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#bgp-grid)" />
                    <g :transform="viewTransform">
                        @foreach ($topology['ringGuides'] as $guide)
                            <g aria-hidden="true">
                                <circle cx="{{ $guide['x'] }}" cy="{{ $guide['y'] }}" r="{{ $guide['radius'] }}" class="bgp-ring-guide" />
                                <text x="{{ $guide['x'] + 12 }}" y="{{ $guide['y'] - $guide['radius'] + 18 }}" class="bgp-ring-label">{{ $guide['label'] }}</text>
                            </g>
                        @endforeach
                        @foreach ($topology['edges'] as $edge)
                            <g
                                x-data="{ edge: @js($edge) }"
                                class="bgp-edge-group"
                                role="button"
                                tabindex="0"
                                :aria-label="edge.state + ' BGP session from AS' + (edge.localAs ?? ' unavailable') + ' to AS' + (edge.remoteAs ?? ' unavailable')"
                                :class="selected?.type === 'edge' && selected.item.id === edge.id ? 'is-selected' : ''"
                                x-on:pointerdown.stop
                                x-on:click.stop="selectEdge(edge)"
                                x-on:keydown.enter.stop.prevent="selectEdge(edge)"
                                x-on:keydown.space.stop.prevent="selectEdge(edge)"
                            >
                                <path x-bind:d="edgePath(edge)" class="bgp-edge-hit-area" />
                                <path
                                    x-bind:d="edgePath(edge)"
                                    x-bind:class="'bgp-edge bgp-edge--' + edge.visualState"
                                    :marker-end="edge.direction === 'outbound' ? 'url(#arrow-' + edge.visualState + ')' : null"
                                    :marker-start="edge.direction === 'inbound' ? 'url(#arrow-' + edge.visualState + ')' : null"
                                    aria-hidden="true"
                                />
                                <g x-bind:transform="'translate(' + edgeLabelPosition(edge).x + ' ' + edgeLabelPosition(edge).y + ')'" aria-hidden="true">
                                    <rect x="-62" y="-12" width="124" height="24" rx="5" class="bgp-prefix-label-bg" />
                                    <text text-anchor="middle" dominant-baseline="central" class="bgp-prefix-label" x-text="edge.prefixLabel"></text>
                                </g>
                            </g>
                        @endforeach

                        @foreach ($topology['nodes'] as $node)
                            <g
                                x-data="{ node: @js($node) }"
                                class="bgp-node bgp-node--{{ $node['visualState'] }}"
                                x-bind:class="selected?.type === 'node' && selected.item.id === node.id ? 'bgp-node bgp-node--' + node.visualState + ' is-selected' : 'bgp-node bgp-node--' + node.visualState"
                                x-bind:transform="nodeTransform(node.id)"
                                role="button"
                                tabindex="0"
                                x-bind:aria-label="node.role + ': ' + node.asLabel + ', ' + node.identity + ', ' + node.state"
                                x-on:pointerdown.stop.prevent="startNodeDrag($event, node.id)"
                                x-on:click.stop="selectNode(node)"
                                x-on:keydown.enter.stop.prevent="selectNode(node)"
                                x-on:keydown.space.stop.prevent="selectNode(node)"
                            >
                                <title x-text="node.role + ' · ' + node.asLabel + ' · ' + node.identity + ' · ' + node.state"></title>
                                <circle r="47" class="bgp-node-ring" />
                                <circle r="38" class="bgp-node-core" />
                                <circle cx="27" cy="-27" r="4" class="bgp-node-indicator" />
                                <text x="0" y="-3" text-anchor="middle" class="bgp-node-as" x-text="node.asLabel"></text>
                                <text x="0" y="15" text-anchor="middle" class="bgp-node-name" x-text="shortLabel(node.identity)"></text>
                                <text x="0" y="65" text-anchor="middle" class="bgp-node-role" x-text="node.role"></text>
                                <text x="0" y="81" text-anchor="middle" class="bgp-node-state" x-text="node.state"></text>
                            </g>
                        @endforeach
                    </g>
                </svg>
            @endif

            @if ($topology['nodes'] !== [])
                <div class="bgp-layout-toolbar" role="group" aria-label="Layout controls">
                    <button type="button" class="bgp-icon-button" x-on:click="saveLayout()" aria-label="Save layout" title="Save layout">
                        <x-filament::icon icon="heroicon-o-bookmark" class="size-4" />
                    </button>
                    <button type="button" class="bgp-icon-button" x-on:click="resetLayout()" aria-label="Reset layout" title="Reset layout">
                        <x-filament::icon icon="heroicon-o-arrow-uturn-left" class="size-4" />
                    </button>
                </div>
                <div class="bgp-topology-legend" :class="{ 'is-collapsed': !legendOpen }">
                    <button type="button" class="bgp-legend-toggle" x-on:click="legendOpen = !legendOpen" :aria-expanded="legendOpen" aria-controls="bgp-legend-content">
                        <span>Legend</span>
                        <x-filament::icon icon="heroicon-o-chevron-down" class="size-4" x-bind:class="{ 'rotate-180': legendOpen }" />
                    </button>
                    <div id="bgp-legend-content" x-show="legendOpen" x-transition.opacity class="bgp-legend-content">
                        <p class="bgp-legend-label">NODE</p>
                        <p><i class="bgp-legend-dot bgp-legend-dot--origin"></i>Origin / Local Router</p>
                        <p><i class="bgp-legend-dot bgp-legend-dot--established"></i>Direct Peer</p>
                        <p><i class="bgp-legend-ring"></i>Upstream / Transit guide</p>
                        <p><i class="bgp-legend-ring"></i>External AS guide</p>
                        <p class="bgp-legend-label">SESSION</p>
                        <p><i class="bgp-legend-line bgp-legend-line--established"></i>Established</p>
                        <p><i class="bgp-legend-line bgp-legend-line--connecting"></i>Connecting</p>
                        <p><i class="bgp-legend-line bgp-legend-line--down"></i>Idle / Down</p>
                        <p><i class="bgp-legend-line bgp-legend-line--unknown"></i>Unknown</p>
                        <div class="bgp-legend-help">
                            <span>Center = Local router</span>
                            <span>Arrow = reported direction; no arrow = unavailable</span>
                            <span>Scroll = Zoom · Drag background = Pan</span>
                            <span>Drag node · Click node or edge = Details</span>
                        </div>
                    </div>
                </div>

                <aside x-cloak x-show="selected" x-transition.opacity class="bgp-topology-details" aria-live="polite" aria-label="Selected topology details">
                    <div class="bgp-details-heading">
                        <div>
                            <p class="bgp-details-eyebrow" x-text="selectedTitle"></p>
                            <h2 x-text="selectedName"></h2>
                        </div>
                        <button type="button" class="bgp-details-close" x-on:click="selected = null" aria-label="Close details" title="Close details">
                            <x-filament::icon icon="heroicon-o-x-mark" class="size-4" />
                        </button>
                    </div>
                    <dl class="bgp-details-list">
                        <template x-for="field in detailFields" :key="field.label">
                            <div>
                                <dt x-text="field.label"></dt>
                                <dd x-text="field.value ?? '-'" :class="field.label === 'BGP State' ? 'bgp-detail-state bgp-detail-state--' + (selected?.item?.visualState ?? 'unknown') : ''"></dd>
                            </div>
                        </template>
                    </dl>
                </aside>

                <div class="bgp-topology-scale" aria-label="Current zoom level" x-text="Math.round(zoom * 100) + '%' "></div>
                <p class="bgp-layout-status" role="status" aria-live="polite" x-text="layoutStatus"></p>
            @endif

            <button x-cloak x-show="fullscreen" type="button" class="bgp-fullscreen-exit" x-on:click="toggleFullscreen()" aria-label="Exit fullscreen" title="Exit fullscreen">
                <x-filament::icon icon="heroicon-o-arrows-right-left" class="size-4" />
                <span>Exit Fullscreen</span>
            </button>

            <div wire:loading.flex wire:target="syncFromMikroTik" class="bgp-sync-overlay" role="status" aria-live="polite">
                <x-filament::loading-indicator class="size-5" />
                <span>Syncing BGP topology...</span>
            </div>
        </section>
    </div>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite('resources/js/app.js')
    @endif
</x-filament-panels::page>