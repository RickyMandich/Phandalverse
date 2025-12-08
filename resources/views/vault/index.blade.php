@extends('layouts.app')

@section('title', 'Vault')

@section('content-class', 'container-fluid px-0')

@section('style')
<style>
    .vault-main {
        flex: 1;
        position: relative;
        overflow: hidden;
    }
    
    /* Tree styles */
    .vault-tree {
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
    }
    .vault-tree ul {
        list-style: none;
        padding-left: 1.2rem;
        margin: 0;
    }
    .vault-tree > ul {
        padding-left: 0;
    }
    .vault-tree li {
        padding: 0.15rem 0;
    }
    .vault-tree .folder {
        font-weight: bold;
        color: #f0ad4e;
        cursor: pointer;
    }
    .vault-tree .folder::before {
        content: '📁 ';
    }
    .vault-tree .folder.open::before {
        content: '📂 ';
    }
    .vault-tree .file {
        color: #5bc0de;
    }
    .vault-tree .file::before {
        content: '📄 ';
    }
    .vault-tree .file:hover {
        color: #fff;
        text-decoration: underline;
    }
    
    /* Graph styles */
    .graph-container {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    }
    
    #vault-graph {
        width: 100%;
        height: 100%;
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
        text-shadow: 0 0 3px rgba(0,0,0,0.8);
    }
    
    .link {
        stroke: #4a5568;
        stroke-opacity: 0.6;
    }
    
    .graph-controls {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 10;
        display: flex;
        gap: 8px;
    }
    
    .graph-controls button {
        padding: 8px 12px;
        border-radius: 4px;
        border: 1px solid #4a5568;
        background: rgba(0,0,0,0.5);
        color: #fff;
        cursor: pointer;
    }
    
    .graph-controls button:hover {
        background: rgba(99, 179, 237, 0.3);
        border-color: #63b3ed;
    }

    .tooltip-graph {
        position: absolute;
        background: rgba(0, 0, 0, 0.9);
        color: #fff;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 12px;
        pointer-events: none;
        z-index: 100;
        display: none;
    }

    .legend {
        position: absolute;
        bottom: 10px;
        left: 10px;
        background: rgba(0,0,0,0.7);
        padding: 10px;
        border-radius: 8px;
        font-size: 12px;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 4px;
    }

    .legend-color {
        width: 12px;
        height: 12px;
        border-radius: 50%;
    }
</style>
@endsection

@section('content')
<div class="vault-layout d-flex">
    @include('vault._sidebar', ['tree' => $tree])

    <!-- Area principale con grafo -->
    <main class="vault-main flex-grow-1">
        <div class="graph-container">
            <div class="graph-controls">
                <button onclick="resetZoom()" title="Reset Zoom">🔄</button>
                <button onclick="zoomIn()" title="Zoom In">➕</button>
                <button onclick="zoomOut()" title="Zoom Out">➖</button>
            </div>

            <svg id="vault-graph"></svg>

            <div class="tooltip-graph" id="tooltip"></div>

            <div class="legend">
                @php
                    $legend = $graphConfig['legend'] ?? [];
                    $colors = $graphConfig['colors'] ?? [];
                @endphp
                @foreach($legend as $key => $label)
                    <div class="legend-item">
                        <div class="legend-color" style="background: {{ $colors[$key] ?? ($colors['default'] ?? '#888') }};"></div>
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
    if (tags.includes('città') ) return colors['città'] || def;
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

