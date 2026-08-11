<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirLogin();

$pdo = getConexao();
$mensagem = null;
$erro = null;

function slugFamilia(string $nome): string {
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower(trim($nome)));
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?: 'familia';
    return trim($texto, '-') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        exigirCsrf($_POST['csrf_token'] ?? null);
        $acao = $_POST['acao'] ?? '';
        if ($acao === 'selecionar') {
            $id = (int) ($_POST['familia_id'] ?? 0);
            if (!atualizarContextoFamilia($id)) throw new RuntimeException('Você não tem acesso a esse espaço.');
            header('Location: index.php');
            exit;
        }
        if ($acao === 'criar') {
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            if (mb_strlen($nome) < 3) throw new RuntimeException('Informe um nome de família com pelo menos 3 caracteres.');
            $stmt = $pdo->prepare('INSERT INTO familias (nome, slug, descricao, criado_por) VALUES (?, ?, ?, ?)');
            $stmt->execute([$nome, slugFamilia($nome), $descricao ?: null, usuarioAtualId()]);
            $id = (int) $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO familia_usuarios (familia_id, usuario_id, papel) VALUES (?, ?, 'owner')")->execute([$id, usuarioAtualId()]);
            definirFamiliaAtual($id);
            atualizarContextoFamilia();
            registrarAuditoria('familia', $id, 'criacao', ['nome' => $nome]);
            $mensagem = 'Novo espaço criado. Você já pode adicionar pessoas e compartilhar o acesso.';
        }
        if ($acao === 'convidar') {
            if (!usuarioPodeAdministrarFamilia()) throw new RuntimeException('Somente o responsável pelo espaço pode gerenciar membros.');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $papel = in_array($_POST['papel'] ?? '', ['editor', 'viewer'], true) ? $_POST['papel'] : 'viewer';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Informe um e-mail válido.');
            $stmt = $pdo->prepare('SELECT id, nome FROM usuarios WHERE email = ?');
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            if (!$usuario) throw new RuntimeException('Essa conta ainda não existe. Peça para a pessoa criar uma conta antes de compartilhar o espaço.');
            $stmt = $pdo->prepare('INSERT INTO familia_usuarios (familia_id, usuario_id, papel) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE papel = VALUES(papel)');
            $stmt->execute([familiaAtualId(), $usuario['id'], $papel]);
            registrarAuditoria('familia_usuario', (int) $usuario['id'], 'permissao_atualizada', ['papel' => $papel]);
            $mensagem = 'Acesso compartilhado com ' . $usuario['nome'] . '.';
        }
        if ($acao === 'remover_membro') {
            if (!usuarioPodeAdministrarFamilia()) throw new RuntimeException('Somente o responsável pelo espaço pode gerenciar membros.');
            $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
            if ($usuarioId === usuarioAtualId()) throw new RuntimeException('Você não pode remover a si próprio do espaço.');
            $pdo->prepare('DELETE FROM familia_usuarios WHERE familia_id = ? AND usuario_id = ?')->execute([familiaAtualId(), $usuarioId]);
            registrarAuditoria('familia_usuario', $usuarioId, 'remocao');
            $mensagem = 'Acesso removido.';
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$familias = listarFamiliasDoUsuario();
if (!$familias && !empty($_SESSION['usuario_id'])) {
    // Instalações anteriores à migração ainda podem estar sem associação.
    $erro = $erro ?: 'Execute a migração 005 no banco para habilitar espaços de família.';
}
$familiaSelecionada = familiaAtualId();
$membros = [];
if ($familiaSelecionada) {
    $stmt = $pdo->prepare('SELECT u.id, u.nome, u.email, fu.papel, fu.criado_em FROM usuarios u JOIN familia_usuarios fu ON fu.usuario_id = u.id WHERE fu.familia_id = ? ORDER BY FIELD(fu.papel, "owner", "editor", "viewer"), u.nome');
    $stmt->execute([$familiaSelecionada]);
    $membros = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Famílias · Árvore Familiar</title><link rel="stylesheet" href="css/style.css?v=e928b1d">
</head>
<body>
<header class="topo"><a class="brand" href="index.php">Árvore Familiar</a><nav><a href="index.php">Painel</a><a href="arvore.php">Explorar árvore</a><a href="familias.php" aria-current="page">Famílias</a><span class="user-chip"><?= htmlspecialchars(usuarioAtualNome() ?: '') ?></span><a href="logout.php">Sair</a></nav></header>
<main class="app-shell">
    <div class="page-heading"><div><span class="eyebrow">Administração</span><h1>Espaços da família</h1><p class="lead">Separe árvores diferentes e escolha quem pode visualizar ou editar cada história.</p></div></div>
    <?php if ($mensagem): ?><div class="sucesso" style="margin-bottom:18px"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="erro" style="margin-bottom:18px"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
    <section class="family-grid">
        <?php foreach ($familias as $familia): ?>
            <article class="surface family-card <?= (int) $familia['id'] === (int) $familiaSelecionada ? 'is-active' : '' ?>">
                <h3><?= htmlspecialchars($familia['nome']) ?></h3><p><?= htmlspecialchars($familia['descricao'] ?: 'Espaço colaborativo para a sua genealogia.') ?></p>
                <div class="family-meta"><span><?= (int) $familia['total_pessoas'] ?> pessoas</span><span><?= (int) $familia['total_membros'] ?> membros</span><span class="role"><?= htmlspecialchars($familia['papel']) ?></span></div>
                <form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>"><input type="hidden" name="acao" value="selecionar"><input type="hidden" name="familia_id" value="<?= (int) $familia['id'] ?>"><button class="btn <?= (int) $familia['id'] === (int) $familiaSelecionada ? 'btn-secundario' : '' ?>" type="submit"><?= (int) $familia['id'] === (int) $familiaSelecionada ? 'Espaço ativo' : 'Entrar neste espaço' ?></button></form>
            </article>
        <?php endforeach; ?>
        <article class="surface family-card family-form"><h3>Criar novo espaço</h3><p>Ideal para separar ramos, famílias ou projetos de pesquisa.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>"><input type="hidden" name="acao" value="criar"><label>Nome do espaço<input name="nome" required maxlength="180" placeholder="Ex.: Família Pedron"></label><label>Descrição <span class="muted">(opcional)</span><textarea name="descricao" maxlength="500" placeholder="Uma frase para identificar este espaço"></textarea></label><button class="btn" type="submit">Criar espaço</button></form></article>
    </section>

    <?php if ($familiaSelecionada && usuarioPodeAdministrarFamilia()): ?>
    <section class="surface panel" style="margin-top:22px"><div class="panel-header"><div><h2>Membros de <?= htmlspecialchars(familiaAtualNome() ?: 'espaço') ?></h2><span class="muted small">O papel define se a pessoa pode apenas visualizar ou também editar.</span></div></div>
        <form class="dashboard-layout" style="grid-template-columns: minmax(0,1fr) 180px 130px; gap:10px; margin-bottom:18px" method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>"><input type="hidden" name="acao" value="convidar"><input type="email" name="email" placeholder="E-mail da conta" required><select name="papel"><option value="viewer">Visualizador</option><option value="editor">Editor</option></select><button class="btn" type="submit">Compartilhar</button></form>
        <?php foreach ($membros as $membro): ?><div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--line)"><div><strong><?= htmlspecialchars($membro['nome']) ?></strong><div class="muted small"><?= htmlspecialchars($membro['email']) ?></div></div><div style="display:flex;align-items:center;gap:12px"><span class="role" style="color:var(--brand);font-size:12px;font-weight:800"><?= htmlspecialchars($membro['papel']) ?></span><?php if ((int) $membro['id'] !== (int) usuarioAtualId()): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>"><input type="hidden" name="acao" value="remover_membro"><input type="hidden" name="usuario_id" value="<?= (int) $membro['id'] ?>"><button class="btn btn-ghost btn-small" type="submit">Remover</button></form><?php endif; ?></div></div><?php endforeach; ?>
    </section>
    <?php endif; ?>
</main>
</body></html>
