<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirFamilia();

$pdo = getConexao();
$familiaId = familiaAtualId();
$busca = trim($_GET['busca'] ?? '');
$pessoas = listarPessoas($busca);

$stmt = $pdo->prepare(
    'SELECT COUNT(*) AS total,
            COALESCE(SUM(falecido = 0), 0) AS vivas,
            COALESCE(SUM(falecido = 1), 0) AS falecidas,
            COALESCE(SUM(data_nascimento IS NULL), 0) AS sem_datas
     FROM pessoas WHERE familia_id = ?'
);
$stmt->execute([$familiaId]);
$estatisticas = $stmt->fetch() ?: ['total' => 0, 'vivas' => 0, 'falecidas' => 0, 'sem_datas' => 0];

$stmt = $pdo->prepare('SELECT * FROM pessoas WHERE familia_id = ? ORDER BY atualizado_em DESC LIMIT 6');
$stmt->execute([$familiaId]);
$recentes = $stmt->fetchAll();

$familias = listarFamiliasDoUsuario();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel · <?= htmlspecialchars(familiaAtualNome() ?: 'Árvore Familiar') ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="topo">
    <a class="brand" href="index.php">Árvore Familiar</a>
    <nav>
        <a href="index.php" aria-current="page">Painel</a>
        <a href="arvore.php">Explorar árvore</a>
        <a href="familias.php">Famílias</a>
        <span class="user-chip"><?= htmlspecialchars(usuarioAtualNome() ?: '') ?></span>
        <a href="logout.php">Sair</a>
    </nav>
</header>

<main class="app-shell">
    <div class="dashboard-hero">
        <div>
            <span class="eyebrow">Espaço da família</span>
            <h1><?= htmlspecialchars(familiaAtualNome() ?: 'Minha família') ?></h1>
            <p class="lead">Um lugar simples para registrar pessoas, preservar documentos e explorar as conexões entre gerações.</p>
        </div>
        <div class="dashboard-actions">
            <a class="btn btn-secundario" href="familias.php">Gerenciar espaço</a>
            <?php if (usuarioPodeEditar()): ?><a class="btn" href="pessoa_editar.php">+ Nova pessoa</a><?php endif; ?>
        </div>
    </div>

    <div class="stats-grid">
        <div class="surface stat-card"><span class="stat-label">Pessoas</span><strong class="stat-value"><?= (int) $estatisticas['total'] ?></strong></div>
        <div class="surface stat-card"><span class="stat-label">Vivas</span><strong class="stat-value"><?= (int) $estatisticas['vivas'] ?></strong></div>
        <div class="surface stat-card"><span class="stat-label">Falecidas</span><strong class="stat-value"><?= (int) $estatisticas['falecidas'] ?></strong></div>
        <div class="surface stat-card"><span class="stat-label">A completar</span><strong class="stat-value"><?= (int) $estatisticas['sem_datas'] ?></strong></div>
    </div>

    <div class="dashboard-layout">
        <section class="surface panel">
            <div class="panel-header">
                <div><h2><?= $busca !== '' ? 'Resultados da busca' : 'Pessoas da família' ?></h2><span class="muted small"><?= count($pessoas) ?> registro(s) neste espaço</span></div>
                <form class="searchbar" method="get" role="search">
                    <span class="search-icon">⌕</span>
                    <input type="search" name="busca" placeholder="Buscar pessoa…" value="<?= htmlspecialchars($busca) ?>" aria-label="Buscar pessoa">
                </form>
            </div>
            <?php if (empty($pessoas)): ?>
                <div class="empty-state"><h3><?= $busca !== '' ? 'Nenhuma pessoa encontrada' : 'A árvore ainda está vazia' ?></h3><p><?= $busca !== '' ? 'Tente outro nome ou remova o filtro.' : 'Adicione a primeira pessoa para começar a conectar a sua história.' ?></p><?php if ($busca === '' && usuarioPodeEditar()): ?><a class="btn" href="pessoa_editar.php">Adicionar primeira pessoa</a><?php endif; ?></div>
            <?php else: ?>
                <div class="person-grid">
                    <?php foreach ($pessoas as $p): ?>
                        <a class="person-tile" href="pessoa.php?id=<?= (int) $p['id'] ?>">
                            <img class="portrait" src="<?= htmlspecialchars(urlFotoOuPlaceholder($p['foto_perfil'])) ?>" alt="">
                            <div class="person-info"><p class="person-name"><?= htmlspecialchars($p['nome_completo']) ?></p><p class="person-dates"><?= $p['data_nascimento'] ? date('Y', strtotime($p['data_nascimento'])) : 'Data não informada' ?><?php if ($p['falecido']): ?> · <?= $p['data_falecimento'] ? date('Y', strtotime($p['data_falecimento'])) : 'falecido(a)' ?><?php endif; ?></p></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <aside>
            <section class="surface quick-card">
                <h3>Continue de onde parou</h3>
                <p>Use os atalhos para explorar ou ampliar o acervo.</p>
                <a class="quick-link" href="arvore.php">Abrir explorador <span>→</span></a>
                <a class="quick-link" href="pessoa_editar.php">Registrar pessoa <span>→</span></a>
                <a class="quick-link" href="familias.php">Trocar família <span>→</span></a>
            </section>
            <section class="surface quick-card" style="margin-top:14px">
                <h3>Atualizado recentemente</h3>
                <?php if (empty($recentes)): ?><p>Nenhum registro recente.</p><?php else: ?>
                    <?php foreach ($recentes as $p): ?><a class="quick-link" href="pessoa.php?id=<?= (int) $p['id'] ?>"><span><?= htmlspecialchars($p['nome_completo']) ?><small style="display:block;color:var(--muted);font-weight:400;margin-top:2px">Atualizado em <?= date('d/m/Y', strtotime($p['atualizado_em'])) ?></small></span><span>→</span></a><?php endforeach; ?>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</main>
</body>
</html>
