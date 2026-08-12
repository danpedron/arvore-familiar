<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirFamilia();

$pdo = getConexao();
$familiaNome = familiaAtualNome() ?: 'Família ativa';
$csrf = tokenCsrf();
$seguidasStmt = $pdo->prepare(
    'SELECT id, nome, url, observacao, criado_em FROM arvores_seguidas WHERE familia_id = ? AND usuario_id = ? ORDER BY nome COLLATE utf8mb4_general_ci'
);
$seguidasStmt->execute([familiaAtualId(), usuarioAtualId()]);
$seguidas = $seguidasStmt->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Árvore · <?= htmlspecialchars($familiaNome) ?></title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="vendor/family-chart/family-chart.css?v=0.9.0">
    <link rel="stylesheet" href="css/style.css?v=family-ref-2">
</head>
<body class="tree-page-body">
<header class="topo tree-topbar">
    <a class="brand" href="index.php">Árvore Familiar</a>
    <nav aria-label="Navegação principal">
        <a href="index.php">Painel</a>
        <a href="arvore.php" aria-current="page">Árvore</a>
        <a href="familias.php">Famílias</a>
        <span class="user-chip"><?= htmlspecialchars(usuarioAtualNome() ?: '') ?></span>
        <a href="logout.php">Sair</a>
    </nav>
</header>

<main class="heritage-explorer" data-csrf="<?= htmlspecialchars($csrf) ?>">
    <header class="explorer-breadcrumb">
        <div class="explorer-family-title">
            <span class="family-mark">⌘</span>
            <strong><?= htmlspecialchars($familiaNome) ?></strong>
            <span class="breadcrumb-separator">›</span>
            <span data-breadcrumb-focus>Carregando pessoa em foco…</span>
        </div>
        <div class="explorer-header-actions">
            <button class="header-action" type="button" data-tree-action="follow" title="Seguir outra árvore">＋ Seguir outra árvore</button>
            <button class="header-action" type="button" data-tree-action="import" title="Importar GEDCOM ou JSON">Importar</button>
            <button class="header-action" type="button" data-tree-action="export" title="Abrir versão pronta para salvar como PDF">Exportar PDF</button>
        </div>
    </header>

    <section class="explorer-layout" aria-label="Explorador da árvore genealógica">
        <aside class="heritage-sidebar" id="tree-person-panel" aria-live="polite">
            <div class="sidebar-person-head">
                <img class="sidebar-person-photo" data-person-photo alt="">
                <div>
                    <span class="sidebar-kicker">Pessoa em foco</span>
                    <h1 data-person-name>Selecione uma pessoa</h1>
                    <p class="sidebar-dates" data-person-dates>—</p>
                    <p class="sidebar-place" data-person-location>Local não informado</p>
                </div>
            </div>
            <p class="sidebar-origin" data-person-origin hidden></p>
            <div class="sidebar-actions">
                <a class="sidebar-action is-primary" data-person-profile href="index.php">Perfil</a>
                <?php if (usuarioPodeEditar()): ?><button class="sidebar-action" type="button" data-tree-action="edit">Editar</button><?php endif; ?>
                <button class="sidebar-action" type="button" data-tree-action="add">Adicionar</button>
                <button class="sidebar-action" type="button" data-tree-action="more">Mais</button>
            </div>
            <div class="sidebar-section">
                <div class="section-heading"><span>Relações</span><strong data-person-relations>—</strong></div>
                <div class="relation-list" data-person-relation-list></div>
            </div>
            <div class="sidebar-section">
                <div class="section-heading"><span>Fotos e documentos</span><button type="button" data-tree-action="media">＋ Adicionar</button></div>
                <div class="media-summary" data-person-media>O acervo desta pessoa aparecerá aqui.</div>
            </div>
            <div class="sidebar-section sidebar-discoveries">
                <div class="section-heading"><span>Árvores seguidas</span><button type="button" data-tree-action="follow">＋ Adicionar</button></div>
                <div class="followed-tree-list" data-followed-trees>
                    <?php if (!$seguidas): ?>
                        <p class="muted">Nenhuma árvore adicionada.</p>
                    <?php else: foreach ($seguidas as $seguida): ?>
                        <a class="followed-tree-link" href="<?= htmlspecialchars($seguida['url']) ?>" target="_blank" rel="noopener noreferrer">
                            <span><?= htmlspecialchars($seguida['nome']) ?></span><small>↗</small>
                        </a>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            <div class="sidebar-tip"><strong>Exploração rápida</strong><p>Arraste o canvas para mover a árvore. Clique em um cartão para mudar o foco. Use as setas para navegar pelas relações.</p></div>
        </aside>

        <section class="explorer-workspace">
            <div class="explorer-toolbar">
                <div class="explorer-search">
                    <span aria-hidden="true">⌕</span>
                    <input id="tree-search" type="search" autocomplete="off" placeholder="Pesquisar na árvore" aria-label="Pesquisar pessoa">
                    <div id="tree-search-results" class="tree-search-results" hidden></div>
                </div>
                <label class="toolbar-select">Visualização
                    <select id="tree-mode" aria-label="Modo de visualização">
                        <option value="explorer">Explorador</option>
                        <option value="lineage">Linhagem</option>
                        <option value="fan">Leque</option>
                    </select>
                </label>
                <label class="toolbar-select">Ordenar
                    <select id="tree-sort" aria-label="Ordenar cartões">
                        <option value="nome_asc">Nome A–Z</option>
                        <option value="nome_desc">Nome Z–A</option>
                        <option value="nascimento_asc">Nascimento mais antigo</option>
                        <option value="nascimento_desc">Nascimento mais recente</option>
                        <option value="atualizado_desc">Atualizados recentemente</option>
                    </select>
                </label>
                <button class="toolbar-icon" type="button" data-tree-action="fit" title="Enquadrar árvore">⛶</button>
                <button class="toolbar-icon" type="button" data-tree-action="center" title="Centralizar pessoa">◎</button>
                <button class="toolbar-icon" type="button" data-tree-action="toggle-panel" aria-pressed="true" title="Ocultar painel lateral">‹</button>
            </div>

            <div class="explorer-canvas" id="tree-viewport" tabindex="0" aria-label="Canvas da árvore. Arraste para mover e use a roda do mouse para aproximar.">
                <div class="tree-stage" id="tree-stage"></div>
                <div class="canvas-empty" id="tree-empty" hidden>
                    <h2 data-empty-title>A árvore ainda está vazia</h2>
                    <p data-empty-message>Adicione a primeira pessoa para começar.</p>
                    <?php if (usuarioPodeEditar()): ?><a class="btn" href="pessoa_editar.php">Adicionar pessoa</a><?php endif; ?>
                </div>
                <div class="canvas-hint">Arraste para mover · Roda para zoom · Clique para selecionar</div>
            </div>

            <footer class="explorer-footer">
                <span class="tree-total" data-tree-total>Carregando…</span>
                <span id="tree-status" role="status" aria-live="polite">Carregando a árvore…</span>
                <div class="footer-controls">
                    <label>Acima <input id="tree-ancestors" type="range" min="1" max="5" value="2" aria-label="Gerações acima"></label>
                    <label>Abaixo <input id="tree-descendants" type="range" min="1" max="5" value="2" aria-label="Gerações abaixo"></label>
                    <button class="zoom-button" type="button" data-tree-action="zoom-out">−</button><span id="tree-zoom">100%</span><button class="zoom-button" type="button" data-tree-action="zoom-in">＋</button>
                </div>
            </footer>
        </section>
    </section>
</main>

<dialog class="tree-dialog" id="follow-dialog">
    <form method="post" action="arvore_seguir.php" data-follow-form>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <div class="dialog-head"><div><span class="sidebar-kicker">Atalho de exploração</span><h2>Seguir outra árvore</h2></div><button type="button" class="dialog-close" data-dialog-close>×</button></div>
        <p class="muted">Salve um link para outra árvore sem misturar os dados dela com este espaço familiar.</p>
        <label>Nome da árvore<input name="nome" required maxlength="180" placeholder="Ex.: Família Oliveira"></label>
        <label>URL da árvore<input name="url" type="url" required maxlength="1000" placeholder="https://exemplo.com/arvore"></label>
        <label>Observação (opcional)<textarea name="observacao" maxlength="500" rows="3"></textarea></label>
        <div class="dialog-actions"><button type="button" class="btn btn-secundario" data-dialog-close>Cancelar</button><button class="btn" type="submit">Salvar atalho</button></div>
        <p class="form-feedback" data-follow-feedback></p>
    </form>
</dialog>

<script src="vendor/family-chart/d3.min.js?v=7.9.0"></script>
<script src="vendor/family-chart/family-chart.min.js?v=0.9.0"></script>
<script src="js/tree-view.js?v=family-ref-1"></script>
<script src="js/tree-view-family-chart.js?v=family-ref-1"></script>
<script>
window.addEventListener('DOMContentLoaded', () => {
  const tree = new FamilyTreeView({
    viewport: '#tree-viewport', stage: '#tree-stage', panel: '#tree-person-panel',
    search: '#tree-search', results: '#tree-search-results', ancestorRange: '#tree-ancestors',
    descendantRange: '#tree-descendants', status: '#tree-status', zoomLabel: '#tree-zoom',
    sort: '#tree-sort', mode: '#tree-mode', total: '[data-tree-total]', empty: '#tree-empty',
    breadcrumb: '[data-breadcrumb-focus]', csrf: document.querySelector('.heritage-explorer')?.dataset.csrf,
  });
  tree.load();
});
</script>
</body>
</html>
