<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirFamilia();

$pdo = getConexao();
$familiaId = familiaAtualId();
$stmt = $pdo->prepare('SELECT COUNT(*) AS total, SUM(falecido = 0) AS vivas, SUM(falecido = 1) AS falecidas FROM pessoas WHERE familia_id = ?');
$stmt->execute([$familiaId]);
$totais = $stmt->fetch() ?: ['total' => 0, 'vivas' => 0, 'falecidas' => 0];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Explorar árvore · <?= htmlspecialchars(familiaAtualNome() ?: 'Árvore Familiar') ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="topo">
    <a class="brand" href="index.php">Árvore Familiar</a>
    <nav>
        <a href="index.php">Painel</a>
        <a href="arvore.php" aria-current="page">Explorar árvore</a>
        <a href="familias.php">Famílias</a>
        <span class="user-chip"><?= htmlspecialchars(usuarioAtualNome() ?: '') ?></span>
        <a href="logout.php">Sair</a>
    </nav>
</header>

<main class="tree-page">
    <div class="tree-intro">
        <div>
            <span class="eyebrow">Explorador genealógico</span>
            <h1>A história da família, em contexto.</h1>
            <p>Navegue por ascendentes e descendentes sem perder o foco. Clique em qualquer pessoa para torná-la o centro da história; arraste para percorrer e use o zoom para abrir novas ramificações.</p>
        </div>
        <div class="family-badge"><span>◈</span> <?= htmlspecialchars(familiaAtualNome() ?: 'Família ativa') ?></div>
    </div>

    <section class="tree-shell" aria-label="Explorador da árvore genealógica">
        <div class="tree-toolbar">
            <div class="tree-search">
                <span class="search-icon">⌕</span>
                <input id="tree-search" type="search" autocomplete="off" placeholder="Buscar uma pessoa pelo nome…" aria-label="Buscar pessoa">
                <div class="search-results" data-search-results hidden></div>
            </div>
            <div class="toolbar-group" aria-label="Controles de visualização">
                <button class="icon-btn" type="button" data-tree-action="zoom-out" title="Diminuir zoom" aria-label="Diminuir zoom">−</button>
                <button class="icon-btn" type="button" data-tree-action="zoom-in" title="Aumentar zoom" aria-label="Aumentar zoom">+</button>
                <button class="icon-btn" type="button" data-tree-action="fit" title="Enquadrar árvore" aria-label="Enquadrar árvore">□</button>
                <button class="icon-btn" type="button" data-tree-action="center" title="Centralizar pessoa em foco" aria-label="Centralizar pessoa em foco">◎</button>
            </div>
            <label>Ascendentes <input id="tree-ancestors" type="range" min="1" max="5" value="3" aria-label="Profundidade de ascendentes"></label>
            <label>Descendentes <input id="tree-descendants" type="range" min="1" max="5" value="3" aria-label="Profundidade de descendentes"></label>
            <span class="tree-status" id="tree-status">Carregando…</span>
        </div>
        <div class="tree-workspace">
            <div class="tree-viewport" id="tree-viewport">
                <div class="tree-stage" id="tree-stage"></div>
            </div>
            <aside class="tree-side" id="tree-person-panel" aria-live="polite">
                <div class="person-summary">
                    <span class="side-label">Pessoa em foco</span>
                    <h2 data-person-name>Selecione alguém</h2>
                    <p class="summary-dates" data-person-dates>—</p>
                    <p class="summary-location" data-person-location>—</p>
                    <p class="summary-relations" data-person-relations>—</p>
                </div>
                <div>
                    <a class="btn" data-person-profile href="index.php">Abrir perfil</a>
                    <a class="btn btn-secundario" href="pessoa_editar.php">Adicionar pessoa</a>
                </div>
                <div class="tree-guide">
                    <strong>Dica de navegação</strong>
                    <p>O enquadramento começa compacto para facilitar a leitura. Aumente a profundidade de gerações quando quiser ampliar o contexto, ou arraste o fundo para percorrer a árvore.</p>
                </div>
            </aside>
        </div>
    </section>
    <div class="tree-legend">
        <span><i class="legend-dot" style="background:#b9d1dc"></i> Masculino</span>
        <span><i class="legend-dot" style="background:#dfbdc4"></i> Feminino</span>
        <span>Linhas contínuas indicam parentesco</span>
        <span>Linhas tracejadas indicam união</span>
    </div>
</main>
<script src="js/tree-explorer.js"></script>
<script>
    const explorer = new TreeExplorer({
        root: '#tree-stage',
        stage: '#tree-stage',
        viewport: '#tree-viewport',
        panel: '#tree-person-panel',
        search: '#tree-search',
        ancestorRange: '#tree-ancestors',
        descendantRange: '#tree-descendants',
        status: '#tree-status',
        badge: document.querySelector('.family-badge'),
    });
    explorer.load();
</script>
</body>
</html>
