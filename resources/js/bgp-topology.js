document.addEventListener('alpine:init', () => {
    window.Alpine.data('bgpTopology', (topology) => ({
        topology,
        positions: {},
        zoom: 1,
        panX: 0,
        panY: 0,
        pointerMode: null,
        pointerStart: null,
        selected: null,
        suppressNextClick: false,
        legendOpen: true,
        autoRefresh: false,
        autoRefreshSeconds: 30,
        autoRefreshTimer: null,
        refreshInFlight: false,
        layoutStorageKey: 'bgp-topology-layout-v2',
        layoutRestored: false,
        layoutStatus: '',
        fullscreen: false,
        fullscreenHandler: null,

        init() {
            this.positions = Object.fromEntries(topology.nodes.map((node) => [node.id, { x: node.x, y: node.y }]));
            this.layoutRestored = this.restoreLayout();
            this.fullscreenHandler = () => {
                this.fullscreen = document.fullscreenElement === this.$refs.canvasContainer;
            };
            document.addEventListener('fullscreenchange', this.fullscreenHandler);

            this.$nextTick(() => {
                if (!this.layoutRestored || this.restoredLayoutNeedsFit()) {
                    this.fitView();

                    if (this.layoutRestored) this.persistLayout();
                }
            });
        },

        destroy() {
            this.stopAutoRefresh();
            if (this.fullscreenHandler) document.removeEventListener('fullscreenchange', this.fullscreenHandler);
        },

        readSavedLayout() {
            try {
                const savedLayout = window.localStorage.getItem(this.layoutStorageKey);

                return savedLayout ? JSON.parse(savedLayout) : null;
            } catch {
                return null;
            }
        },

        restoreLayout() {
            const savedLayout = this.readSavedLayout();

            if (!savedLayout || savedLayout.version !== 1 || typeof savedLayout.positions !== 'object') return false;

            for (const node of this.topology.nodes) {
                const position = savedLayout.positions[node.id];

                if (Number.isFinite(position?.x) && Number.isFinite(position?.y)) {
                    this.positions[node.id] = { x: position.x, y: position.y };
                }
            }

            this.zoom = Number.isFinite(savedLayout.zoom) ? Math.max(0.15, Math.min(2.5, savedLayout.zoom)) : 1;
            this.panX = Number.isFinite(savedLayout.panX) ? savedLayout.panX : 0;
            this.panY = Number.isFinite(savedLayout.panY) ? savedLayout.panY : 0;

            return true;
        },

        saveLayout() {
            try {
                this.persistLayout();
                this.layoutStatus = 'Layout saved on this browser.';
            } catch {
                this.layoutStatus = 'Layout could not be saved in this browser.';
            }
        },

        persistLayout() {
            window.localStorage.setItem(this.layoutStorageKey, JSON.stringify({
                version: 1,
                positions: this.positions,
                zoom: this.zoom,
                panX: this.panX,
                panY: this.panY,
            }));
        },

        restoredLayoutNeedsFit() {
            if (!this.$refs.canvas || this.zoom < 0.25) return true;

            const viewBox = this.$refs.canvas.viewBox.baseVal;
            const ringGuides = this.topology.ringGuides ?? [];
            const extents = this.topology.nodes.map((node) => {
                const position = this.positions[node.id];
                const padding = 90;

                return {
                    left: position.x - padding,
                    right: position.x + padding,
                    top: position.y - padding,
                    bottom: position.y + padding,
                };
            });

            for (const guide of ringGuides) {
                extents.push({
                    left: guide.x - guide.radius,
                    right: guide.x + guide.radius,
                    top: guide.y - guide.radius,
                    bottom: guide.y + guide.radius,
                });
            }

            const margin = 24;
            const left = Math.min(...extents.map((extent) => extent.left)) * this.zoom + this.panX;
            const right = Math.max(...extents.map((extent) => extent.right)) * this.zoom + this.panX;
            const top = Math.min(...extents.map((extent) => extent.top)) * this.zoom + this.panY;
            const bottom = Math.max(...extents.map((extent) => extent.bottom)) * this.zoom + this.panY;

            return left < margin || top < margin || right > viewBox.width - margin || bottom > viewBox.height - margin;
        },

        resetLayout() {
            if (this.readSavedLayout() && !window.confirm('Reset the saved BGP topology layout?')) return;

            try {
                window.localStorage.removeItem(this.layoutStorageKey);
            } catch {
                this.layoutStatus = 'Saved layout could not be removed.';
            }

            this.positions = Object.fromEntries(this.topology.nodes.map((node) => [node.id, { x: node.x, y: node.y }]));
            this.zoom = 1;
            this.panX = 0;
            this.panY = 0;
            this.layoutRestored = false;
            this.layoutStatus = 'Automatic layout restored.';
            this.$nextTick(() => this.fitView());
        },

        closeDetails() {
            this.selected = null;
        },

        replaceTopology(topology) {
            const previousPositions = this.positions;

            this.topology = topology;
            this.positions = Object.fromEntries(topology.nodes.map((node) => [
                node.id,
                previousPositions[node.id] ?? { x: node.x, y: node.y },
            ]));

            if (this.selected) {
                const items = this.selected.type === 'edge' ? topology.edges : topology.nodes;

                if (!items.some((item) => item.id === this.selected.item.id)) this.selected = null;
            }

            this.$nextTick(() => {
                if (!this.layoutRestored && Object.keys(previousPositions).length === 0) this.fitView();
            });
        },

        async toggleFullscreen() {
            try {
                if (document.fullscreenElement === this.$refs.canvasContainer) {
                    await document.exitFullscreen();
                } else {
                    await this.$refs.canvasContainer.requestFullscreen();
                }
            } catch {
                this.layoutStatus = 'Fullscreen is not available in this browser.';
            }
        },

        toggleAutoRefresh() {
            this.autoRefresh = !this.autoRefresh;
            this.restartAutoRefresh();
        },

        restartAutoRefresh() {
            this.stopAutoRefresh();

            if (!this.autoRefresh) return;

            this.autoRefreshTimer = window.setInterval(async () => {
                if (this.refreshInFlight || !this.$wire) return;

                this.refreshInFlight = true;

                try {
                    await this.$wire.refreshFromDatabase();
                } finally {
                    this.refreshInFlight = false;
                }
            }, this.autoRefreshSeconds * 1000);
        },

        stopAutoRefresh() {
            if (this.autoRefreshTimer !== null) window.clearInterval(this.autoRefreshTimer);
            this.autoRefreshTimer = null;
        },

        get viewTransform() {
            return `translate(${this.panX} ${this.panY}) scale(${this.zoom})`;
        },

        get selectedTitle() {
            if (!this.selected) return '';
            if (this.selected.type === 'edge') return 'BGP SESSION';

            return this.selected.item.role === 'Origin' ? 'ORIGIN ROUTER' : 'BGP PEER';
        },

        get selectedName() {
            if (!this.selected) return '';

            return this.selected.type === 'edge'
                ? `${this.selected.item.state} · ${this.selected.item.remoteAs ? `AS${this.selected.item.remoteAs}` : 'Remote AS unavailable'}`
                : `${this.selected.item.asLabel} · ${this.selected.item.identity}`;
        },

        get detailFields() {
            if (!this.selected) return [];

            const item = this.selected.item;
            const fields = this.selected.type === 'edge'
                ? [
                    ['Local Router', item.routerName],
                    ['Remote Peer', item.peerName],
                    ['Local AS', item.localAs],
                    ['Remote AS', item.remoteAs],
                    ['Local Address', item.localAddress],
                    ['Remote Address', item.remoteAddress],
                    ['BGP State', item.state],
                    ['Direction', item.direction],
                    ['Prefixes Received', this.formatNumber(item.prefixesReceived)],
                    ['Prefixes Sent', this.formatNumber(item.prefixesSent)],
                    ['Uptime', item.uptime],
                    ['Last Update', this.formatDate(item.lastUpdate)],
                ]
                : [
                    ['Router', item.router],
                    ['Identity', item.kind === 'router' ? null : item.identity],
                    ['IP Address', item.ipAddress],
                    ['Local AS', item.localAs],
                    ['Remote AS', item.remoteAs],
                    ['Role', item.role],
                    ['BGP State', item.state],
                    ['Uptime', item.uptime],
                    ['Prefixes Received', this.formatNumber(item.prefixesReceived)],
                    ['Prefixes Sent', this.formatNumber(item.prefixesSent)],
                    ['Last Update', this.formatDate(item.lastUpdate)],
                ];

            return fields.map(([label, value]) => ({ label, value: value === null || value === '' ? '-' : value }));
        },

        shortLabel(value) {
            const label = String(value ?? '-');

            return label.length > 17 ? `${label.slice(0, 16)}…` : label;
        },

        formatNumber(value) {
            return value === null || value === '' ? null : new Intl.NumberFormat('en-US').format(Number(value));
        },

        formatDate(value) {
            if (!value) return null;

            const date = new Date(value);

            return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
        },

        nodeTransform(nodeId) {
            const position = this.positions[nodeId];

            return position ? `translate(${position.x} ${position.y})` : '';
        },

        edgePath(edge) {
            const start = this.positions[edge.source];
            const end = this.positions[edge.target];

            if (!start || !end) return '';

            const deltaX = end.x - start.x;
            const deltaY = end.y - start.y;
            const distance = Math.max(1, Math.hypot(deltaX, deltaY));
            const curve = Math.min(48, distance * 0.12);
            const controlX = ((start.x + end.x) / 2) - ((deltaY / distance) * curve);
            const controlY = ((start.y + end.y) / 2) + ((deltaX / distance) * curve);

            return `M ${start.x} ${start.y} Q ${controlX} ${controlY} ${end.x} ${end.y}`;
        },

        edgeLabelPosition(edge) {
            const start = this.positions[edge.source];
            const end = this.positions[edge.target];

            if (!start || !end) return { x: 0, y: 0 };

            const deltaX = end.x - start.x;
            const deltaY = end.y - start.y;
            const distance = Math.max(1, Math.hypot(deltaX, deltaY));
            const curve = Math.min(48, distance * 0.12);
            const controlX = ((start.x + end.x) / 2) - ((deltaY / distance) * curve);
            const controlY = ((start.y + end.y) / 2) + ((deltaX / distance) * curve);

            return {
                x: (start.x + (2 * controlX) + end.x) / 4,
                y: (start.y + (2 * controlY) + end.y) / 4,
            };
        },

        startPan(event) {
            if (event.button !== 0 || event.target.closest('.bgp-node, .bgp-edge-group')) return;

            this.pointerMode = 'pan';
            this.pointerStart = { x: event.clientX, y: event.clientY, panX: this.panX, panY: this.panY, moved: false };
        },

        startNodeDrag(event, nodeId) {
            const position = this.positions[nodeId];

            if (!position) return;

            this.pointerMode = 'node';
            this.pointerStart = {
                x: event.clientX,
                y: event.clientY,
                nodeId,
                nodeX: position.x,
                nodeY: position.y,
                moved: false,
            };
        },

        movePointer(event) {
            if (!this.pointerStart || !this.pointerMode || !this.$refs.canvas) return;

            const rect = this.$refs.canvas.getBoundingClientRect();
            const viewBox = this.$refs.canvas.viewBox.baseVal;
            const unitX = viewBox.width / rect.width;
            const unitY = viewBox.height / rect.height;
            const deltaX = (event.clientX - this.pointerStart.x) * unitX;
            const deltaY = (event.clientY - this.pointerStart.y) * unitY;

            if (Math.abs(deltaX) + Math.abs(deltaY) > 4) this.pointerStart.moved = true;

            if (this.pointerMode === 'pan') {
                this.panX = this.pointerStart.panX + deltaX;
                this.panY = this.pointerStart.panY + deltaY;

                return;
            }

            this.positions[this.pointerStart.nodeId] = {
                x: this.pointerStart.nodeX + (deltaX / this.zoom),
                y: this.pointerStart.nodeY + (deltaY / this.zoom),
            };
        },

        endPointer() {
            if (this.pointerStart?.moved && this.pointerMode === 'node') this.suppressNextClick = true;

            this.pointerMode = null;
            this.pointerStart = null;
        },

        selectNode(node) {
            if (this.suppressNextClick) {
                this.suppressNextClick = false;

                return;
            }

            this.selected = { type: 'node', item: node };
        },

        selectEdge(edge) {
            this.selected = { type: 'edge', item: edge };
        },

        zoomAt(event) {
            if (!this.$refs.canvas) return;

            const rect = this.$refs.canvas.getBoundingClientRect();
            const viewBox = this.$refs.canvas.viewBox.baseVal;
            const pointX = ((event.clientX - rect.left) / rect.width) * viewBox.width;
            const pointY = ((event.clientY - rect.top) / rect.height) * viewBox.height;
            const oldZoom = this.zoom;
            const ratio = event.deltaY < 0 ? 1.12 : 1 / 1.12;
            const nextZoom = Math.max(0.15, Math.min(2.5, oldZoom * ratio));

            this.panX = pointX - ((pointX - this.panX) * (nextZoom / oldZoom));
            this.panY = pointY - ((pointY - this.panY) * (nextZoom / oldZoom));
            this.zoom = nextZoom;
        },

        fitView() {
            if (!this.$refs.canvas || this.topology.nodes.length === 0) return;

            const positions = Object.values(this.positions);
            const ringGuides = this.topology.ringGuides ?? [];
            const minX = Math.min(...positions.map((position) => position.x), ...ringGuides.map((guide) => guide.x - guide.radius));
            const maxX = Math.max(...positions.map((position) => position.x), ...ringGuides.map((guide) => guide.x + guide.radius));
            const minY = Math.min(...positions.map((position) => position.y), ...ringGuides.map((guide) => guide.y - guide.radius));
            const maxY = Math.max(...positions.map((position) => position.y), ...ringGuides.map((guide) => guide.y + guide.radius));
            const viewBox = this.$refs.canvas.viewBox.baseVal;
            const contentWidth = Math.max(1, maxX - minX + 180);
            const contentHeight = Math.max(1, maxY - minY + 180);

            this.zoom = Math.max(0.15, Math.min(
                (viewBox.width - 80) / contentWidth,
                (viewBox.height - 80) / contentHeight,
                1.5,
            ));
            this.panX = (viewBox.width / 2) - (((minX + maxX) / 2) * this.zoom);
            this.panY = (viewBox.height / 2) - (((minY + maxY) / 2) * this.zoom);
        },
    }));
});