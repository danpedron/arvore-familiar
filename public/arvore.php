<?php
require_once __DIR__ . '/../includes/auth.php';
exigirLogin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Árvore Genealógica - Árvore Familiar</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <style>
        #arvore-svg { width: 100%; height: 75vh; background: #fbfaf7; border-radius: 10px; cursor: grab; }
        .no-dados { text-align: center; padding: 60px 20px; color: #777; }
        .legenda { display: flex; gap: 20px; font-size: 0.85em; color: #666; margin-top: 8px; flex-wrap: wrap; }
        .legenda span { display: inline-flex; align-items: center; gap: 6px; }
        .legenda .linha { width: 24px; height: 0; border-top: 2px solid; display: inline-block; }
    </style>
</head>
<body>
<header class="topo">
    <a href="index.php">🌳 Árvore Familiar</a>
    <nav>
        <a href="index.php">Lista de pessoas</a>
        <a href="logout.php">Sair</a>
    </nav>
</header>

<div class="container">
    <div class="card">
        <h1 style="margin-top:0;">Árvore genealógica</h1>
        <p style="color:#666;">Arraste os nós para reorganizar. Clique em uma pessoa para ver o perfil completo. Role a roda do mouse para dar zoom.</p>
        <svg id="arvore-svg"></svg>
        <div class="legenda">
            <span><span class="linha" style="border-color:#3c5a48;"></span> Filiação (pai/mãe → filho)</span>
            <span><span class="linha" style="border-color:#c98a3e; border-top-style:dashed;"></span> União / casamento</span>
        </div>
    </div>
</div>

<script>
async function iniciarArvore() {
    const resp = await fetch('arvore_dados.php');
    const dados = await resp.json();

    const svgEl = document.getElementById('arvore-svg');
    const container = svgEl.parentElement;

    if (!dados.nodes || dados.nodes.length === 0) {
        container.innerHTML = '<div class="no-dados">Nenhuma pessoa cadastrada ainda. <a href="pessoa_editar.php">Adicione a primeira pessoa</a> para ver a árvore.</div>';
        return;
    }

    const larguraContainer = container.clientWidth || 900;
    const altura = svgEl.clientHeight || 600;
    const espacamentoNivel = 160;

    const svg = d3.select('#arvore-svg')
        .attr('viewBox', [0, 0, larguraContainer, altura]);

    const g = svg.append('g');

    svg.call(d3.zoom().scaleExtent([0.3, 2.5]).on('zoom', (event) => {
        g.attr('transform', event.transform);
    }));

    const nodesPorId = new Map(dados.nodes.map(n => [n.id, n]));

    // Posição Y fixa por geração; posição X inicial distribuída
    const niveis = {};
    dados.nodes.forEach(n => {
        niveis[n.nivel] = niveis[n.nivel] || [];
        niveis[n.nivel].push(n);
    });
    Object.keys(niveis).forEach(nv => {
        const lista = niveis[nv];
        lista.forEach((n, i) => {
            n.x = (larguraContainer / (lista.length + 1)) * (i + 1);
            n.y = (parseInt(nv) + 2) * espacamentoNivel * 0.6 + 60;
            n.fy = n.y;
        });
    });

    const links = dados.links.map(l => ({
        source: l.source,
        target: l.target,
        tipo: l.tipo
    }));

    const simulacao = d3.forceSimulation(dados.nodes)
        .force('link', d3.forceLink(links).id(d => d.id).distance(l => l.tipo === 'uniao' ? 90 : 140).strength(0.4))
        .force('charge', d3.forceManyBody().strength(-260))
        .force('x', d3.forceX(larguraContainer / 2).strength(0.03))
        .force('collide', d3.forceCollide(48));

    const linkSel = g.append('g')
        .selectAll('line')
        .data(links)
        .join('line')
        .attr('stroke', d => d.tipo === 'uniao' ? '#c98a3e' : '#3c5a48')
        .attr('stroke-width', 2)
        .attr('stroke-dasharray', d => d.tipo === 'uniao' ? '5,4' : null)
        .attr('opacity', 0.8);

    const nodeSel = g.append('g')
        .selectAll('g.no')
        .data(dados.nodes)
        .join('g')
        .attr('class', 'no')
        .style('cursor', 'pointer')
        .call(d3.drag()
            .on('start', (event, d) => {
                if (!event.active) simulacao.alphaTarget(0.2).restart();
                d.fx = d.x;
            })
            .on('drag', (event, d) => {
                d.fx = event.x;
            })
            .on('end', (event, d) => {
                if (!event.active) simulacao.alphaTarget(0);
                d.fx = null;
            }))
        .on('click', (event, d) => {
            if (event.defaultPrevented) return; // evita clique acidental após arrastar
            window.location.href = 'pessoa.php?id=' + d.id;
        });

    const corPorSexo = { M: '#7ba0c4', F: '#d98a9a', Outro: '#b39ddb', Desconhecido: '#c9c2b4' };

    nodeSel.append('circle')
        .attr('r', 30)
        .attr('fill', d => corPorSexo[d.sexo] || corPorSexo.Desconhecido)
        .attr('stroke', d => d.falecido ? '#555' : '#fff')
        .attr('stroke-width', d => d.falecido ? 3 : 2)
        .attr('opacity', d => d.falecido ? 0.7 : 1);

    nodeSel.filter(d => d.foto).append('clipPath')
        .attr('id', d => 'clip-' + d.id)
        .append('circle')
        .attr('r', 28);

    nodeSel.filter(d => d.foto).append('image')
        .attr('href', d => d.foto)
        .attr('x', -28).attr('y', -28)
        .attr('width', 56).attr('height', 56)
        .attr('clip-path', d => 'url(#clip-' + d.id + ')')
        .attr('preserveAspectRatio', 'xMidYMid slice');

    nodeSel.append('text')
        .text(d => d.nome.split(' ').slice(0, 2).join(' '))
        .attr('text-anchor', 'middle')
        .attr('y', 46)
        .attr('font-size', 12)
        .attr('font-weight', 600)
        .attr('fill', '#2b2b2b');

    nodeSel.filter(d => d.falecido).append('text')
        .text('🕊️')
        .attr('text-anchor', 'middle')
        .attr('y', -34)
        .attr('font-size', 12);

    simulacao.on('tick', () => {
        linkSel
            .attr('x1', d => nodesPorId.get(d.source.id ?? d.source).x)
            .attr('y1', d => nodesPorId.get(d.source.id ?? d.source).y)
            .attr('x2', d => nodesPorId.get(d.target.id ?? d.target).x)
            .attr('y2', d => nodesPorId.get(d.target.id ?? d.target).y);

        nodeSel.attr('transform', d => `translate(${d.x},${d.y})`);
    });
}

iniciarArvore();
</script>
</body>
</html>
