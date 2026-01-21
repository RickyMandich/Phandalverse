@extends('layouts.app')

@section('title', 'Vault - Grafo')

@section('content-class', 'container-fluid px-0')

@section('style')
    <style>
        /* Custom SVG styling for D3 Graph */
        .graph-container {
            height: 75vh;
            min-height: 500px;
            background-color: #1a1a1a !important;
            /* Darker background like Obsidian */
        }

        .node circle {
            cursor: pointer;
            stroke: rgba(255, 255, 255, 0.2);
            /* Subtle stroke */
            stroke-width: 1px;
            transition: all 0.2s ease;
        }

        .node circle:hover {
            stroke: rgba(255, 255, 255, 0.8);
            stroke-width: 2px;
            filter: brightness(1.2);
        }

        .node text {
            font-size: 7px;
            /* Small and always visible */
            fill: #888;
            pointer-events: none;
            text-anchor: middle;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            transition: all 0.2s ease;
        }

        .node:hover text {
            font-size: 12px;
            /* Bigger on hover */
            fill: #ffffff;
            font-weight: bold;
            opacity: 1;
        }

        .link {
            stroke: #444444;
            /* Darker, more subtle links */
            stroke-opacity: 0.4;
            transition: stroke-opacity 0.3s;
        }

        .link-highlight {
            stroke-opacity: 0.8;
            stroke: #63b3ed;
        }

        .tooltip-graph {
            display: none;
            pointer-events: none;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
@endsection

@section('content')
    <div class="d-flex gap-0 h-100 w-100">
        @include('vault._sidebar', ['tree' => $tree ?? []])

        <main class="flex-grow-1 p-3" style="min-width: 0;">
            <div class="vault-graph-page h-100 d-flex flex-column">
                @if(Auth::check() && (Auth::isAdmin() || Auth::isMaster()))
                    <div class="d-flex gap-2 mb-3 align-items-center">
                        <a href="{{ route('vault.show') }}?view=tree"
                            class="btn btn-sm {{ $currentView === 'tree' ? 'btn-primary' : 'btn-outline-secondary' }}">
                            📂 Vista Albero
                        </a>
                        <a href="{{ route('vault.show') }}?view=graph"
                            class="btn btn-sm {{ $currentView === 'graph' ? 'btn-primary' : 'btn-outline-secondary' }}">
                            🕸️ Vista Grafo
                        </a>
                        @if(Auth::isAdmin())
                            <form action="{{ route('admin.vault.setDefaultView') }}" method="POST" class="d-inline ms-2">
                                @csrf
                                <select name="view" class="form-select form-select-sm bg-dark text-white border-secondary"
                                    style="width: auto;" onchange="this.form.submit()">
                                    <option value="tree" {{ \App\Models\SystemSetting::getVaultDefaultView() === 'tree' ? 'selected' : '' }}>Default: Albero</option>
                                    <option value="graph" {{ \App\Models\SystemSetting::getVaultDefaultView() === 'graph' ? 'selected' : '' }}>Default: Grafo</option>
                                </select>
                            </form>
                        @endif
                    </div>
                @endif

                <div
                    class="graph-container position-relative w-100 overflow-hidden bg-dark rounded-3 border border-secondary flex-grow-1">
                    <div class="position-absolute top-0 end-0 m-2 z-2 d-flex gap-2">
                        <button class="btn btn-sm btn-dark bg-opacity-75 border-secondary text-white" onclick="resetZoom()"
                            title="Reset Zoom">🔄 Reset</button>
                        <button class="btn btn-sm btn-dark bg-opacity-75 border-secondary text-white" onclick="zoomIn()"
                            title="Zoom In">➕</button>
                        <button class="btn btn-sm btn-dark bg-opacity-75 border-secondary text-white" onclick="zoomOut()"
                            title="Zoom Out">➖</button>
                    </div>

                    <svg id="vault-graph" class="w-100 h-100 d-block"></svg>

                    <div class="tooltip-graph position-absolute bg-black bg-opacity-90 text-white p-2 rounded small z-3"
                        id="tooltip"></div>

                    <div
                        class="position-absolute bottom-0 start-0 m-2 p-0 rounded bg-black bg-opacity-75 border border-secondary small z-2">
                        <button
                            class="btn btn-sm text-white w-100 d-flex align-items-center justify-content-between gap-3 px-2 py-1"
                            type="button" data-bs-toggle="collapse" data-bs-target="#graphLegend" aria-expanded="false"
                            aria-controls="graphLegend">
                            <span><i class="bi bi-list-ul me-1"></i> Legenda</span>
                            <i class="bi bi-chevron-up toggle-indicator"></i>
                        </button>
                        <div class="collapse p-2 pt-0" id="graphLegend">
                            @php
                                $legend = $graphConfig['legend'] ?? [];
                                $colors = $graphConfig['colors'] ?? [];
                            @endphp
                            @foreach($legend as $key => $label)
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <div
                                        style="min-width: 12px; width: 12px; height: 12px; border-radius: 50%; background: {{ $colors[$key] ?? ($colors['default'] ?? '#888') }};">
                                    </div>
                                    <span class="text-nowrap">{{ $label }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
@endsection

@section('script')
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script>
        const graphData = @json($graphData);
        const graphConfig = @json($graphConfig ?? []);

        const container = document.querySelector('.graph-container');
        const width = container.clientWidth;
        const height = container.clientHeight;

        const svg = d3.select('#vault-graph')
            .attr('width', width)
            .attr('height', height);

        const g = svg.append('g');

        // Zoom behavior
        const zoom = d3.zoom()
            .scaleExtent([0.1, 4])
            .on('zoom', (event) => {
                g.attr('transform', event.transform);
            });

        svg.call(zoom);

        // Color and Query Logic
        function getNodeColor(node) {
            const path = (node.path || '').toLowerCase();
            const tags = (node.tags || []).map(t => t.toLowerCase());
            const groups = graphConfig.colorGroups || [];

            for (let i = groups.length - 1; i >= 0; i--) {
                const group = groups[i];
                const query = (group.query || '').toLowerCase().trim();
                const colorObj = group.color || {};
                const rgb = colorObj.rgb;

                if (rgb === undefined) continue;

                // Split Obsidian query into parts (AND logic)
                const queryParts = query.split(/\s+/);
                let matchesAll = true;

                queryParts.forEach(part => {
                    if (part.startsWith('path:')) {
                        if (!path.includes(part.replace('path:', ''))) matchesAll = false;
                    } else if (part.startsWith('tag:#')) {
                        if (!tags.includes(part.replace('tag:#', ''))) matchesAll = false;
                    } else if (part.startsWith('tag:')) {
                        if (!tags.includes(part.replace('tag:', ''))) matchesAll = false;
                    }
                });

                if (matchesAll && queryParts.length > 0) {
                    return '#' + (rgb & 0xFFFFFF).toString(16).padStart(6, '0');
                }
            }

            const colors = graphConfig.colors || {};
            return colors['default'] || '#888';
        }

        // Force Simulation Configuration (Directly from .obsidian/graph.json)
        // Note: D3 units and Obsidian units differ, so we apply a standard conversion factor
        const repelStrength = -(graphConfig.repelStrength || 20) * 15;
        const linkDistance = (graphConfig.linkDistance || 30) * 2.5;
        const linkStrength = (graphConfig.linkStrength || 1) * 0.5;
        const centerStrength = (graphConfig.centerStrength || 0.77) * 0.5;
        const nodeSizeMultiplier = (graphConfig.nodeSizeMultiplier || 1.0) * 0.8;
        const lineSizeMultiplier = (graphConfig.lineSizeMultiplier || 1.0) * 0.5;

        const simulation = d3.forceSimulation(graphData.nodes)
            .force('link', d3.forceLink(graphData.links)
                .id(d => d.id)
                .distance(linkDistance)
                .strength(linkStrength))
            .force('charge', d3.forceManyBody()
                .strength(repelStrength))
            .force('center', d3.forceCenter(width / 2, height / 2).strength(centerStrength))
            .force('collision', d3.forceCollide().radius(d => {
                return (3 + Math.sqrt(d.connections || 0) * 1.5) * nodeSizeMultiplier + 5;
            }));

        // Links
        const link = g.append('g')
            .selectAll('line')
            .data(graphData.links)
            .enter()
            .append('line')
            .attr('class', 'link')
            .attr('stroke-width', 1 * lineSizeMultiplier);

        // Nodes
        const node = g.append('g')
            .selectAll('g')
            .data(graphData.nodes)
            .enter()
            .append('g')
            .attr('class', 'node')
            .call(d3.drag()
                .on('start', dragstarted)
                .on('drag', dragged)
                .on('end', dragended));

        node.append('circle')
            .attr('r', d => (3 + Math.sqrt(d.connections || 0) * 1.5) * nodeSizeMultiplier)
            .attr('fill', d => getNodeColor(d))
            .on('click', (event, d) => {
                window.location.href = '/vault/' + d.url;
            })
            .on('mouseover', (event, d) => {
                const tooltip = document.getElementById('tooltip');
                tooltip.style.display = 'block';
                tooltip.style.left = (event.pageX + 10) + 'px';
                tooltip.style.top = (event.pageY - 30) + 'px';
                tooltip.innerHTML = `<strong>${d.name}</strong><br><small>${d.path}</small>`;
            })
            .on('mouseout', () => {
                document.getElementById('tooltip').style.display = 'none';
            });

        node.append('text')
            .attr('dy', d => -((3 + Math.sqrt(d.connections || 0) * 1.5) * nodeSizeMultiplier + 5))
            .text(d => d.name);

        simulation.on('tick', () => {
            link
                .attr('x1', d => d.source.x)
                .attr('y1', d => d.source.y)
                .attr('x2', d => d.target.x)
                .attr('y2', d => d.target.y);

            node.attr('transform', d => `translate(${d.x},${d.y})`);
        });

        function dragstarted(event) {
            if (!event.active) simulation.alphaTarget(0.3).restart();
            event.subject.fx = event.subject.x;
            event.subject.fy = event.subject.y;
        }

        function dragged(event) {
            event.subject.fx = event.x;
            event.subject.fy = event.y;
        }

        function dragended(event) {
            if (!event.active) simulation.alphaTarget(0);
            event.subject.fx = null;
            event.subject.fy = null;
        }

        // Control functions
        function resetZoom() {
            svg.transition().duration(750).call(zoom.transform, d3.zoomIdentity);
        }

        function zoomIn() {
            svg.transition().duration(300).call(zoom.scaleBy, 1.3);
        }

        function zoomOut() {
            svg.transition().duration(300).call(zoom.scaleBy, 0.7);
        }

        // Handle resize
        window.addEventListener('resize', () => {
            const newWidth = container.clientWidth;
            const newHeight = container.clientHeight;
            svg.attr('width', newWidth).attr('height', newHeight);
            simulation.force('center', d3.forceCenter(newWidth / 2, newHeight / 2));
            simulation.alpha(0.3).restart();
        });
    </script>
@endsection