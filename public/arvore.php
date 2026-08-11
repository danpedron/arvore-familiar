<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirFamilia();

$familiaNome = familiaAtualNome() ?: 'Família ativa';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Árvore · <?= htmlspecialchars($familiaNome) ?></title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="tree-page-body">
<header class="topo">
    <a class="brand" href="index.php">Árvore Familiar</a>
    <nav aria-label="Navegação principal">
        <a href="index.php">Painel</a>
        <a href="arvore.php" aria-current="page">Árvore</a>
        <a href="familias.php">Famílias</a>
        <span class="user-chip"><?= htmlspecialchars(usuarioAtualNome() ?: '') ?></span>
        <a href="logout.php">Sair</a>
    </nav>
</header>

<main class="tree-page">
    <header class="tree-heading">
        <div>
            <span class="eyebrow">Navegador genealógico</span>
            <h1>Explore uma geração por vez.</h1>
            <p>Escolha uma pessoa para colocá-la no centro. Pais ficam acima, filhos abaixo e cônjuges ao lado. Arraste o fundo, use o zoom ou pressione as setas do teclado.</p>
        </div>
        <div class="tree-heading-meta">
            <span class="family-badge">◈ <?= htmlspecialchars($familiaNome) ?></span>
            <span class="tree-total" data-tree-total>Carregando…</span>
        </div>
    </header>

    <section class="tree-shell" aria-label="Árvore genealógica">
        <div class="tree-toolbar">
            <div class="tree-search">
                <span class="search-icon" aria-hidden="true">⌕</span>
                <input id="tree-search" type="search" autocomplete="off" placeholder="Buscar pessoa e pressionar Enter…" aria-label="Buscar pessoa">
                <div id="tree-search-results" class="tree-search-results" hidden></div>
            </div>
            <div class="tree-toolbar-group" aria-label="Controles da árvore">
                <button class="tree-tool" type="button" data-tree-action="zoom-out" title="Diminuir zoom" aria-label="Diminuir zoom">−</button>
                <span class="tree-zoom" id="tree-zoom">100%</span>
                <button class="tree-tool" type="button" data-tree-action="zoom-in" title="Aumentar zoom" aria-label="Aumentar zoom">+</button>
                <button class="tree-tool" type="button" data-tree-action="fit" title="Enquadrar árvore" aria-label="Enquadrar árvore">⛶</button>
                <button class="tree-tool" type="button" data-tree-action="center" title="Centralizar pessoa em foco" aria-label="Centralizar pessoa em foco">◎</button>
                <button class="tree-tool" type="button" data-tree-action="toggle-panel" title="Mostrar ou ocultar detalhes" aria-label="Mostrar ou ocultar detalhes" aria-pressed="true">Detalhes</button>
            </div>
            <label class="tree-range" for="tree-ancestors">Acima <input id="tree-ancestors" type="range" min="1" max="5" value="2" aria-label="Gerações acima"></label>
            <label class="tree-range" for="tree-descendants">Abaixo <input id="tree-descendants" type="range" min="1" max="5" value="2" aria-label="Gerações abaixo"></label>
            <span class="tree-status" id="tree-status" role="status" aria-live="polite">Carregando a árvore…</span>
        </div>

        <div class="tree-main">
            <div class="tree-viewport" id="tree-viewport" tabindex="0" aria-label="Área interativa da árvore. Arraste para mover e use a roda do mouse para aproximar.">
                <div class="tree-stage" id="tree-stage"></div>
                <div class="tree-empty" id="tree-empty" hidden>
                    <div class="tree-empty-inner">
                        <div class="tree-empty-icon">✦</div>
                        <h2 data-empty-title>A árvore ainda está vazia</h2>
                        <p data-empty-message>Adicione a primeira pessoa para começar.</p>
                        <?php if (usuarioPodeEditar()): ?><a class="btn" href="pessoa_editar.php">Adicionar pessoa</a><?php endif; ?>
                    </div>
                </div>
            </div>

            <aside class="tree-panel" id="tree-person-panel" aria-live="polite">
                <span class="tree-panel-label">Pessoa selecionada</span>
                <img class="tree-panel-photo" data-person-photo alt="">
                <h2 data-person-name>Selecione uma pessoa</h2>
                <p class="tree-panel-dates" data-person-dates>—</p>
                <p class="tree-panel-location" data-person-location>—</p>
                <p class="tree-panel-relations" data-person-relations>—</p>
                <div class="tree-panel-actions">
                    <a class="btn btn-small" data-person-profile href="index.php">Abrir perfil</a>
                    <?php if (usuarioPodeEditar()): ?><a class="btn btn-small btn-secundario" href="pessoa_editar.php">Adicionar pessoa</a><?php endif; ?>
                </div>
                <div class="tree-guide">
                    <strong>Navegação rápida</strong>
                    <p>Clique em um cartão para mudar o foco. Com o cartão selecionado, use ↑ para pais, ↓ para filhos e ←/→ para cônjuges.</p>
                </div>
            </aside>
        </div>
    </section>

    <div class="tree-legend" aria-label="Legenda">
        <span><i class="legend-dot" style="background:#6a9ab0"></i> Masculino</span>
        <span><i class="legend-dot" style="background:#c17d8d"></i> Feminino</span>
        <span>linha verde: parentesco</span>
        <span>linha dourada tracejada: união</span>
    </div>
</main>

<script src="js/tree-view.js"></script>
<script>
  window.addEventListener('DOMContentLoaded', () => {
    const tree = new FamilyTreeView({
      viewport: '#tree-viewport',
      stage: '#tree-stage',
      panel: '#tree-person-panel',
      search: '#tree-search',
      results: '#tree-search-results',
      ancestorRange: '#tree-ancestors',
      descendantRange: '#tree-descendants',
      status: '#tree-status',
      zoomLabel: '#tree-zoom',
      empty: '#tree-empty',
    });
    tree.load();
  });
</script>
</body>
</html>
