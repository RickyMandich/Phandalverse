@extends('layouts.app')

@section('title', 'Vault - Grafo')

@section('content-class', 'container-fluid px-0')

@section('style')
<style>
    .graph-container {
        width: 100%;
        height: calc(100vh - 200px);
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        border-radius: 8px;
        position: relative;
        overflow: hidden;
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
        transition: stroke-opacity 0.3s;
    }
    
    .link:hover {
        stroke-opacity: 1;
        stroke: #63b3ed;
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
        transition: all 0.2s;
    }
    
    .graph-controls button:hover {
        background: rgba(99, 179, 237, 0.3);
        border-color: #63b3ed;
    }
    
    .view-toggle {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .view-toggle a {
        padding: 8px 16px;
        border-radius: 4px;
        text-decoration: none;
        transition: all 0.2s;
    }
    
    .view-toggle a.active {
        background: #4299e1;
        color: #fff;
    }
    
    .view-toggle a:not(.active) {
        background: rgba(255,255,255,0.1);
        color: #a0aec0;
    }
    
    .view-toggle a:not(.active):hover {
        background: rgba(255,255,255,0.2);
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
</style>
@endsection

@section('content')
<div class="vault-graph-page">
    @if(Auth::check() && Auth::user()->isAdmin())
    <div class="view-toggle d-flex align-items-center gap-2 mb-3">
        <a href="{{ route('vault.show') }}?view=tree" class="{{ $currentView === 'tree' ? 'active' : '' }}">
            📂 Vista Albero
        </a>
        <a href="{{ route('vault.show') }}?view=graph" class="{{ $currentView === 'graph' ? 'active' : '' }}">
            🕸️ Vista Grafo
        </a>

        <form action="{{ route('admin.vault.setDefaultView') }}" method="POST" class="d-inline ms-3">
            @csrf
            <select name="view" class="form-select form-select-sm" style="width: auto; background: rgba(0,0,0,0.5); color: #fff; border-color: #4a5568;" onchange="this.form.submit()">
                <option value="tree" {{ \App\Models\SystemSetting::getVaultDefaultView() === 'tree' ? 'selected' : '' }}>Default: Albero</option>
                <option value="graph" {{ \App\Models\SystemSetting::getVaultDefaultView() === 'graph' ? 'selected' : '' }}>Default: Grafo</option>
            </select>
        </form>
    </div>
    @endif
    
    <div class="graph-container">
        <div class="graph-controls">
            <button onclick="resetZoom()" title="Reset Zoom">🔄 Reset</button>
            <button onclick="zoomIn()" title="Zoom In">➕</button>
            <button onclick="zoomOut()" title="Zoom Out">➖</button>
        </div>
        
        <svg id="vault-graph"></svg>
        
        <div class="tooltip-graph" id="tooltip"></div>
        
        <div class="legend">
            <div class="legend-item">
                <div class="legend-color" style="background: #D66B5C;"></div>
                <span>Universi</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #D6C05C;"></div>
                <span>Città</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #C6307F;"></div>
                <span>Personaggi (PG)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #7A7AFF;"></div>
                <span>PNG</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #5CD67A;"></div>
                <span>Saghe</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #5CBCD6;"></div>
                <span>Eventi</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #888;"></div>
                <span>Altri</span>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
const graphData = @json($graphData);

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

// Color scale based on folder/tags
function getNodeColor(node) {
    const path = node.path.toLowerCase();
    const tags = node.tags || [];

    if (tags.includes('universo') || path.includes('universi')) return '#D66B5C';
    if (tags.includes('città')) return '#D6C05C';
    if (tags.includes('pg') || path.includes('giocanti')) return '#C6307F';
    if (tags.includes('png') || path.includes('non giocanti')) return '#7A7AFF';
    if (tags.includes('saga') || path.includes('saghe')) return '#5CD67A';
    if (tags.includes('evento') || path.includes('eventi')) return '#5CBCD6';
    if (path.includes('definizioni')) return '#AD7FA8';
    if (path.includes('artefatti')) return '#FCE94F';
    return '#888';
}

// Force simulation
const simulation = d3.forceSimulation(graphData.nodes)
    .force('link', d3.forceLink(graphData.links)
        .id(d => d.id)
        .distance(80)
        .strength(0.5))
    .force('charge', d3.forceManyBody()
        .strength(-200))
    .force('center', d3.forceCenter(width / 2, height / 2))
    .force('collision', d3.forceCollide().radius(30));

// Links
const link = g.append('g')
    .selectAll('line')
    .data(graphData.links)
    .enter()
    .append('line')
    .attr('class', 'link')
    .attr('stroke-width', 1);

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
    .attr('r', d => 5 + (d.connections || 0) * 0.5)
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
    .attr('dx', 12)
    .attr('dy', 4)
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

