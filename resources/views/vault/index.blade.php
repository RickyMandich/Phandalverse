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
        @include('vault._sidebar', ['tree' => $tree])

        <!-- Area principale con grafo -->
        <main class="flex-grow-1 p-3" style="min-width: 0;">
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

                <div class="position-absolute bottom-0 start-0 m-2 p-2 rounded bg-black bg-opacity-75 small z-2">
                    @php
                        $legend = $graphConfig['legend'] ?? [];
                        $colors = $graphConfig['colors'] ?? [];
                    @endphp
                    @foreach($legend as $key => $label)
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <div
                                style="width: 12px; height: 12px; border-radius: 50%; background: {{ $colors[$key] ?? ($colors['default'] ?? '#888') }};">
                            </div>
                            <span>{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </main>
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

        function getNodeColor(node) {
            const path = (node.path || '').toLowerCase();
            const tags = node.tags || [];
            const colors = (graphConfig && graphConfig.colors) ? graphConfig.colors : {};
            const def = colors['default'] || '#888';

            if (tags.includes('universo') || path.includes('universi')) return colors['universo'] || def;
            if (tags.includes('città')) return colors['città'] || def;
            if (tags.includes('pg') || path.includes('giocanti')) return colors['pg'] || def;
            if (tags.includes('png') || path.includes('non giocanti')) return colors['png'] || def;
            if (tags.includes('saga') || path.includes('saghe')) return colors['saga'] || def;
            if (tags.includes('evento') || path.includes('eventi')) return colors['evento'] || def;
            if (path.includes('definizioni')) return colors['definizioni'] || def;
            if (path.includes('artefatti')) return colors['artefatti'] || def;
            return def;
        }

        const simulation = d3.forceSimulation(graphData.nodes)
            .force('link', d3.forceLink(graphData.links).id(d => d.id).distance(80).strength(0.5))
            .force('charge', d3.forceManyBody().strength(-200))
            .force('center', d3.forceCenter(width / 2, height / 2))
            .force('collision', d3.forceCollide().radius(30));

        const link = g.append('g').selectAll('line').data(graphData.links).enter().append('line').attr('class', 'link').attr('stroke-width', 1);

        const node = g.append('g').selectAll('g').data(graphData.nodes).enter().append('g').attr('class', 'node')
            .call(d3.drag().on('start', dragstarted).on('drag', dragged).on('end', dragended));

        node.append('circle')
            .attr('r', d => 5 + (d.connections || 0) * 0.5)
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

        node.append('text').attr('dx', 12).attr('dy', 4).text(d => d.name);

        simulation.on('tick', () => {
            link.attr('x1', d => d.source.x).attr('y1', d => d.source.y).attr('x2', d => d.target.x).attr('y2', d => d.target.y);
            node.attr('transform', d => `translate(${d.x},${d.y})`);
        });

        function dragstarted(event) { if (!event.active) simulation.alphaTarget(0.3).restart(); event.subject.fx = event.subject.x; event.subject.fy = event.subject.y; }
        function dragged(event) { event.subject.fx = event.x; event.subject.fy = event.y; }
        function dragended(event) { if (!event.active) simulation.alphaTarget(0); event.subject.fx = null; event.subject.fy = null; }

        function resetZoom() { svg.transition().duration(750).call(zoom.transform, d3.zoomIdentity); }
        function zoomIn() { svg.transition().duration(300).call(zoom.scaleBy, 1.3); }
        function zoomOut() { svg.transition().duration(300).call(zoom.scaleBy, 0.7); }

        window.addEventListener('resize', () => {
            width = container.clientWidth;
            height = container.clientHeight;
            svg.attr('width', width).attr('height', height);
            simulation.force('center', d3.forceCenter(width / 2, height / 2));
            simulation.alpha(0.3).restart();
        });
    </script>
@endsection