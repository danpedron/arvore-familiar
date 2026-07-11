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
        #arvore-svg { width: 100%; height: 78vh; background: #fbfaf7; border-radius: 10px; cursor: grab; display: block; }
        .no-dados { text-align: center; padding: 60px 20px; color: #777; }
        .legenda { display: flex; gap: 20px; font-size: 0.85em; color: #666; margin-top: 8px; flex-wrap: wrap; }
        .legenda span { display: inline-flex; align-items: center; gap: 6px; }
        .legenda .linha { width: 24px; height: 0; border-top: 2px solid; display: inline-block; }
        .no-pessoa { cursor: pointer; }
        .no-pessoa .caixa { fill: #fff; stroke-width: 2; filter: drop-shadow(0 1px 2px rgba(0,0,0,0.12)); }
        .no-pessoa:hover .caixa { filter: drop-shadow(0 3px 6px rgba(0,0,0,0.2)); }
        .no-pessoa-texto { font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
        .no-pessoa-texto .nome { font-weight: 600; font-size: 12px; color: #2b2b2b; line-height: 1.25; }
        .no-pessoa-texto .datas { font-size: 10.5px; color: #888; margin-top: 2px; }
        .controles-zoom { display: flex; gap: 8px; margin-bottom: 10px; }
        .controles-zoom button { padding: 6px 12px; font-size: 0.85em; margin-top: 0; }
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
        <p style="color:#666;">Clique em uma pessoa para ver o perfil completo. Arraste para navegar e use a roda do mouse (ou os botões) para dar zoom.</p>
        <div class="controles-zoom">
            <button type="button" id="btn-zoom-in">+ Zoom</button>
            <button type="button" id="btn-zoom-out">− Zoom</button>
            <button type="button" id="btn-zoom-reset" class="btn-secundario">Centralizar</button>
        </div>
        <svg id="arvore-svg"></svg>
        <div class="legenda">
            <span><span class="linha" style="border-color:#3c5a48;"></span> Filiação (pais → filhos)</span>
            <span><span class="linha" style="border-color:#c98a3e; border-top-style:dashed;"></span> União / casamento</span>
            <span>🕊️ Falecido(a)</span>
        </div>
    </div>
</div>

<script>
const BOX_W = 160;
const BOX_H = 82;
const MARGEM = 50;

const corPorSexo = { M: '#7ba0c4', F: '#d98a9a', Outro: '#b39ddb', Desconhecido: '#c9c2b4' };

function anoOu(data) {
    return data ? new Date(data + 'T00:00:00').getFullYear() : '?';
}

async function iniciarArvore() {
    const resp = await fetch('arvore_dados.php');
    const dados = await resp.json();

    const svgEl = document.getElementById('arvore-svg');
    const container = svgEl.parentElement;

    if (!dados.nodes || dados.nodes.length === 0) {
        container.querySelector('.controles-zoom')?.remove();
        svgEl.outerHTML = '<div class="no-dados">Nenhuma pessoa cadastrada ainda. <a href="pessoa_editar.php">Adicione a primeira pessoa</a> para ver a árvore.</div>';
        return;
    }

    const niveisLargura = dados.niveisLargura;
    const maiorLargura = Math.max(...Object.values(niveisLargura));
    const maiorNivel = Math.max(...dados.nodes.map(n => n.nivel));

    const nodesPorId = new Map();
    dados.nodes.forEach(n => {
        const offsetNivel = (maiorLargura - niveisLargura[n.nivel]) / 2;
        n.renderX = MARGEM + offsetNivel + n.x;
        n.renderY = MARGEM + n.y;
        nodesPorId.set(n.id, n);
    });

    const larguraSvg = MARGEM * 2 + maiorLargura + BOX_W;
    const alturaSvg = MARGEM * 2 + (maiorNivel + 1) * dados.linhaPx;

    const svg = d3.select('#arvore-svg').attr('viewBox', [0, 0, larguraSvg, alturaSvg]);
    const g = svg.append('g');

    const zoom = d3.zoom().scaleExtent([0.2, 2.5]).on('zoom', (event) => {
        g.attr('transform', event.transform);
    });
    svg.call(zoom);

    // Centraliza a visualização inicialmente
    const escalaInicial = Math.min(1, (container.clientWidth || 900) / larguraSvg);
    const transformInicial = d3.zoomIdentity
        .translate((container.clientWidth - larguraSvg * escalaInicial) / 2, 20)
        .scale(escalaInicial);
    svg.call(zoom.transform, transformInicial);

    document.getElementById('btn-zoom-in').onclick = () => svg.transition().call(zoom.scaleBy, 1.3);
    document.getElementById('btn-zoom-out').onclick = () => svg.transition().call(zoom.scaleBy, 1 / 1.3);
    document.getElementById('btn-zoom-reset').onclick = () => svg.transition().call(zoom.transform, transformInicial);

    const grupoConectores = g.append('g').attr('class', 'conectores');
    const grupoNos = g.append('g').attr('class', 'nos');

    // --- Conectores de filiação (pais -> filhos), estilo "barramento" ---
    dados.familias.forEach(fam => {
        const paisNodes = fam.pais.map(id => nodesPorId.get(id)).filter(Boolean);
        const filhosNodes = fam.filhos.map(id => nodesPorId.get(id)).filter(Boolean);
        if (paisNodes.length === 0 || filhosNodes.length === 0) return;

        const unionX = paisNodes.reduce((s, n) => s + n.renderX + BOX_W / 2, 0) / paisNodes.length;
        const unionYBottom = paisNodes[0].renderY + BOX_H;
        const childTopY = filhosNodes[0].renderY;
        const busY = unionYBottom + (childTopY - unionYBottom) / 2;

        const childXs = filhosNodes.map(n => n.renderX + BOX_W / 2);
        const busMinX = Math.min(unionX, ...childXs);
        const busMaxX = Math.max(unionX, ...childXs);

        // Linha vertical da união até o barramento
        grupoConectores.append('line')
            .attr('x1', unionX).attr('y1', unionYBottom)
            .attr('x2', unionX).attr('y2', busY)
            .attr('stroke', '#3c5a48').attr('stroke-width', 2);

        // Barramento horizontal
        grupoConectores.append('line')
            .attr('x1', busMinX).attr('y1', busY)
            .attr('x2', busMaxX).attr('y2', busY)
            .attr('stroke', '#3c5a48').attr('stroke-width', 2);

        // Linhas verticais até cada filho
        childXs.forEach(cx => {
            grupoConectores.append('line')
                .attr('x1', cx).attr('y1', busY)
                .attr('x2', cx).attr('y2', childTopY)
                .attr('stroke', '#3c5a48').attr('stroke-width', 2);
        });
    });

    // --- Conectores de união/casamento (linha entre cônjuges) ---
    dados.unioes.forEach(u => {
        const n1 = nodesPorId.get(u.pessoa1);
        const n2 = nodesPorId.get(u.pessoa2);
        if (!n1 || !n2) return;
        const y = n1.renderY + BOX_H / 2;
        const x1 = Math.min(n1.renderX, n2.renderX) + BOX_W;
        const x2 = Math.max(n1.renderX, n2.renderX);
        grupoConectores.append('line')
            .attr('x1', x1).attr('y1', y)
            .attr('x2', x2).attr('y2', y)
            .attr('stroke', '#c98a3e').attr('stroke-width', 2)
            .attr('stroke-dasharray', '5,4');
    });

    // --- Nós (caixas retangulares) ---
    const noSel = grupoNos.selectAll('g.no-pessoa')
        .data(dados.nodes)
        .join('g')
        .attr('class', 'no-pessoa')
        .attr('transform', d => `translate(${d.renderX},${d.renderY})`)
        .on('click', (event, d) => { window.location.href = 'pessoa.php?id=' + d.id; });

    noSel.append('rect')
        .attr('class', 'caixa')
        .attr('width', BOX_W)
        .attr('height', BOX_H)
        .attr('rx', 12).attr('ry', 12)
        .attr('stroke', d => corPorSexo[d.sexo] || corPorSexo.Desconhecido)
        .attr('opacity', d => d.falecido ? 0.75 : 1);

    // Foto ou círculo com inicial
    noSel.each(function (d) {
        const grupo = d3.select(this);
        if (d.foto) {
            grupo.append('clipPath').attr('id', 'clip-' + d.id)
                .append('circle').attr('cx', 40).attr('cy', 41).attr('r', 27);
            grupo.append('image')
                .attr('href', d.foto)
                .attr('x', 13).attr('y', 14)
                .attr('width', 54).attr('height', 54)
                .attr('clip-path', 'url(#clip-' + d.id + ')')
                .attr('preserveAspectRatio', 'xMidYMid slice');
        } else {
            grupo.append('circle')
                .attr('cx', 40).attr('cy', 41).attr('r', 27)
                .attr('fill', corPorSexo[d.sexo] || corPorSexo.Desconhecido)
                .attr('opacity', 0.35);
            grupo.append('text')
                .attr('x', 40).attr('y', 47)
                .attr('text-anchor', 'middle')
                .attr('font-size', 20)
                .attr('font-weight', 700)
                .attr('fill', '#555')
                .text(d.nome.charAt(0).toUpperCase());
        }

        if (d.falecido) {
            grupo.append('text')
                .attr('x', 14).attr('y', 16)
                .attr('font-size', 13)
                .text('🕊️');
        }
    });

    // Texto (nome + datas) via foreignObject para permitir quebra de linha
    noSel.append('foreignObject')
        .attr('x', 80).attr('y', 10)
        .attr('width', BOX_W - 88).attr('height', BOX_H - 16)
        .append('xhtml:div')
        .attr('class', 'no-pessoa-texto')
        .html(d => {
            const nascimento = d.nascimento ? anoOu(d.nascimento) : '?';
            const falecimento = d.falecido ? (d.falecimento ? anoOu(d.falecimento) : '?') : null;
            const datas = falecimento ? `${nascimento} – ${falecimento}` : (d.nascimento ? `n. ${nascimento}` : '');
            return `<div class="nome">${escapeHtml(d.nome)}</div><div class="datas">${datas}</div>`;
        });
}

function escapeHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto;
    return div.innerHTML;
}

iniciarArvore();
</script>
</body>
</html>
