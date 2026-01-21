@extends('layouts.app')

@section('title', 'Vault')

@section('content-class', 'container-fluid px-0')

@section('style')
    <style>
        /* Custom SVG styling for D3 Graph */
        .graph-container {
            height: 75vh;
            min-height: 500px;
        }

        .node circle {
            cursor: pointer;
            stroke: #fff;
            stroke-width: 1.5px;
            transition: all 0.3s ease;
        }

        .node circle:hover {
            stroke-width: 3px;
            filter: brightness(1.3);
        }

        .node text {
            font-size: 10px;
            fill: #fff;
            pointer-events: none;
            text-shadow: 0 0 3px rgba(0, 0, 0, 0.8);
        }

        .link {
            stroke: #4a5568;
            stroke-opacity: 0.6;
        }

        .tooltip-graph {
            display: none;
        }
    </style>
@endsection

@section('content')
    <div class="d-flex gap-0 h-100 w-100">
        @include('vault._sidebar', ['tree' => $tree, 'note' => $note])

        <!-- Area principale con grafo -->
        <section class="flex-grow-1 p-3" style="min-width: 0;">
            <div class="graph-container position-relative w-100 overflow-hidden bg-dark rounded-3 border border-secondary">
                <div class="position-absolute top-0 end-0 m-2 z-2 d-flex gap-2">
                    <button class="btn btn-sm btn-dark bg-opacity-75 border-secondary text-white" onclick="resetZoom()"
                        title="Reset Zoom">🔄</button>
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
                        @foreach ($legend as $key => $label)
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
        </section>
    </div>
@endsection

@section('script')
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script>
        // Sidebar toggle is handled by the sidebar partial script

        // Graph
        const graphData = @json($graphData);
        const graphConfig = @json($graphConfig ?? []);
        const container = document.querySelector('.graph-container');
        let width = container.clientWidth;
        let height = container.clientHeight;

        const svg = d3.select('#vault-graph')
            .attr('width', width)
            .attr('height', height);

        const g = svg.append('g');

        const zoom = d3.zoom()
            .scaleExtent([0.1, 4])
            .on('zoom', (event) => g.attr('transform', event.transform));

        svg.call(zoom);

        // Color scale based on Obsidian colorGroups
        function getNodeColor(node) {
            const path = (node.path || '').toLowerCase();
            const tags = (node.tags || []).map(t => t.toLowerCase());
            const groups = graphConfig.colorGroups || [];

            // Iterate in reverse because later groups override earlier ones in Obsidian
            for (let i = groups.length - 1; i >= 0; i--) {
                const group = groups[i];
                const query = (group.query || '').toLowerCase();
                const colorObj = group.color || {};
                const rgb = colorObj.rgb;

                if (rgb === undefined) continue;

                const hex = '#' + (rgb & 0xFFFFFF).toString(16).padStart(6, '0');

                // Simple query parsing: path:... and tag:#...
                let matches = true;

                // Matches path
                const pathMatch = query.match(/path:([^\s]+)/);
                if (pathMatch) {
                    if (!path.includes(pathMatch[1])) matches = false;
                }

                // Matches tag
                const tagMatch = query.match(/tag:#([^\s]+)/);
                if (tagMatch) {
                    if (!tags.includes(tagMatch[1])) matches = false;
                }

                if (matches && (pathMatch || tagMatch)) {
                    return hex;
                }
            }

            const colors = graphConfig.colors || {};
            return colors['default'] || '#888';
        }

        // Force simulation
        const repelStrength = -(graphConfig.repelStrength || 20) * 10;
        const linkDistance = graphConfig.linkDistance || 30;
        const linkStrength = graphConfig.linkStrength || 1;
        const centerStrength = graphConfig.centerStrength || 0.77;

        const simulation = d3.forceSimulation(graphData.nodes)
            .force('link', d3.forceLink(graphData.links)
                .id(d => d.id)
                .distance(linkDistance)
                .strength(linkStrength))
            .force('charge', d3.forceManyBody()
                .strength(repelStrength))
            .force('center', d3.forceCenter(width / 2, height / 2).strength(centerStrength))
            .force('collision', d3.forceCollide().radius(d => (5 + (d.connections || 0) * 0.5) * (graphConfig
                .nodeSizeMultiplier || 1) + 2));

        // Links
        const lineSizeMultiplier = graphConfig.lineSizeMultiplier || 1;
        const link = g.append('g')
            .selectAll('line')
            .data(graphData.links)
            .enter()
            .append('line')
            .attr('class', 'link')
            .attr('stroke-width', 1 * lineSizeMultiplier);

        // Nodes
        const nodeSizeMultiplier = graphConfig.nodeSizeMultiplier || 1;
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
            .attr('r', d => (5 + (d.connections || 0) * 0.5) * nodeSizeMultiplier)
            .attr('fill', d => getNodeColor(d))
            .on('click', (event, d) => window.location.href = '/vault/' + d.url)
            .on('mouseover', (event, d) => {
                const tooltip = document.getElementById('tooltip');
                tooltip.style.display = 'block';
                tooltip.style.left = (event.pageX + 10) + 'px';
                tooltip.style.top = (event.pageY - 30) + 'px';
                tooltip.innerHTML = `<strong>${d.name}</strong><br><small>${d.path}</small>`;
            })
            .on('mouseout', () => document.getElementById('tooltip').style.display = 'none');

        node.append('text')
            .attr('dx', 12 * nodeSizeMultiplier)
            .attr('dy', 4)
            .text(d => d.name)
            .style('display', graphConfig.showTags === false ? 'none' : 'block');

        simulation.on('tick', () => {
            link.attr('x1', d => d.source.x).attr('y1', d => d.source.y).attr('x2', d => d.target.x).attr('y2', d =>
                d.target.y);
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

        function resetZoom() {
            svg.transition().duration(750).call(zoom.transform, d3.zoomIdentity);
        }

        function zoomIn() {
            svg.transition().duration(300).call(zoom.scaleBy, 1.3);
        }

        function zoomOut() {
            svg.transition().duration(300).call(zoom.scaleBy, 0.7);
        }

        window.addEventListener('resize', () => {
            width = container.clientWidth;
            height = container.clientHeight;
            svg.attr('width', width).attr('height', height);
            simulation.force('center', d3.forceCenter(width / 2, height / 2));
            simulation.alpha(0.3).restart();
        });
    </script>
@endsection