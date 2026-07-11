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
    <link rel="stylesheet" href="https://unpkg.com/family-chart/dist/styles/family-chart.css">
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script src="https://unpkg.com/family-chart/dist/family-chart.min.js"></script>
    <style>
        #FamilyChart { width: 100%; height: 78vh; background: #fbfaf7; border-radius: 10px; }
        .no-dados { text-align: center; padding: 60px 20px; color: #777; }
        .barra-arvore { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 10px; }
        .barra-arvore .f3-search-cont { position: relative; min-width: 240px; }
        #btn-ver-perfil { display: none; }
        .legenda { display: flex; gap: 20px; font-size: 0.85em; color: #666; margin-top: 10px; flex-wrap: wrap; }
        .legenda span { display: inline-flex; align-items: center; gap: 6px; }
        .legenda .bolinha { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }

        /* A biblioteca desenha as linhas de conexão com stroke="#fff" fixo via JS,
           pensado para fundo escuro. Como nosso fundo é claro, precisamos sobrescrever
           a cor por CSS (que tem prioridade sobre o atributo definido no SVG). */
        .f3 .link { stroke: #8a8578 !important; stroke-width: 2px !important; }
        .f3 .link.f3-path-to-main { stroke: #3c5a48 !important; }
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
        <p style="color:#666;">Clique em uma pessoa para centralizar a árvore nela e explorar seus parentes. Use a busca para pular direto para alguém, ou o botão abaixo para abrir o perfil completo.</p>

        <div class="barra-arvore">
            <div id="busca-pessoa-cont" class="f3-search-cont"></div>
            <a id="btn-ver-perfil" href="#" class="btn">Ver perfil completo</a>
        </div>

        <div id="FamilyChart" class="f3"></div>

        <div class="legenda">
            <span><span class="bolinha" style="background:#7b9fac;"></span> Masculino</span>
            <span><span class="bolinha" style="background:#c48a92;"></span> Feminino</span>
            <span>🕊️ / "†" indica falecido(a)</span>
        </div>
    </div>
</div>

<script>
async function iniciarArvore() {
    const resp = await fetch('arvore_dados.php');
    const dados = await resp.json();

    const contChart = document.getElementById('FamilyChart');

    if (!dados || dados.length === 0) {
        contChart.outerHTML = '<div class="no-dados">Nenhuma pessoa cadastrada ainda. <a href="pessoa_editar.php">Adicione a primeira pessoa</a> para ver a árvore.</div>';
        document.querySelector('.barra-arvore').remove();
        return;
    }

    const chart = f3.createChart('#FamilyChart', dados)
        .setTransitionTime(700)
        .setShowSiblingsOfMain(true);

    chart.setCardHtml()
        .setCardDisplay([['nome'], ['datas']])
        .setStyle('imageCircleRect');

    const btnPerfil = document.getElementById('btn-ver-perfil');

    // Sempre que a árvore recentraliza em alguém, atualiza o botão de perfil completo
    chart.setAfterUpdate(() => {
        const principal = chart.getMainDatum();
        if (principal) {
            btnPerfil.href = 'pessoa.php?id=' + principal.id;
            btnPerfil.textContent = 'Ver perfil completo de ' + (principal.data.nome || '');
            btnPerfil.style.display = 'inline-block';
        }
    });

    // Busca de pessoa por nome (dropdown com autocomplete já embutido na biblioteca)
    chart.setPersonDropdown(d => d.data.nome, {
        cont: document.getElementById('busca-pessoa-cont'),
        placeholder: 'Buscar pessoa pelo nome...',
    });

    // Se a URL tiver ?foco=ID, começa centralizado nessa pessoa (útil ao vir do perfil de alguém)
    const params = new URLSearchParams(window.location.search);
    const focoId = params.get('foco');
    if (focoId && dados.some(d => d.id === focoId)) {
        chart.updateMainId(focoId);
    }

    chart.updateTree({ initial: true, tree_position: 'fit' });
}

iniciarArvore();
</script>
</body>
</html>
