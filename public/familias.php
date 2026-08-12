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
        if ($acao === 'referenciar_pessoa') {
            if (!usuarioPodeEditar()) throw new RuntimeException('Você precisa ser editor ou responsável pelo espaço de destino para incluir uma referência.');
            $pessoaId = (int) ($_POST['pessoa_id'] ?? 0);
            if (!$pessoaId) throw new RuntimeException('Selecione uma pessoa para referenciar.');
            $fonteStmt = $pdo->prepare(
                'SELECT p.id, p.nome_completo, p.familia_id, f.nome AS familia_nome
                 FROM pessoas p
                 JOIN familias f ON f.id = p.familia_id
                 WHERE p.id = ? AND p.familia_id <> ?
                 LIMIT 1'
            );
            $fonteStmt->execute([$pessoaId, familiaAtualId()]);
            $fonte = $fonteStmt->fetch();
            if (!$fonte) throw new RuntimeException('Pessoa não encontrada em um espaço que você pode consultar.');
            $idsLinhagem = associarPessoaComLinhagem($pessoaId, familiaAtualId(), usuarioAtualId());
            registrarAuditoria('familia_pessoas', $pessoaId, 'referencia_criada', [
                'familia_origem' => (int) $fonte['familia_id'],
                'familia_destino' => familiaAtualId(),
                'pessoas_incluidas' => count($idsLinhagem),
                'linhagem' => $idsLinhagem,
            ]);
            $mensagem = $fonte['nome_completo'] . ' e ' . (count($idsLinhagem) - 1) . ' pessoa(s) da linhagem conhecida agora estão disponíveis neste espaço. Relações de padrasto/madrasta e cônjuges sem vínculo parental não são importadas.';
        }
        if ($acao === 'desreferenciar_pessoa') {
            if (!usuarioPodeEditar()) throw new RuntimeException('Você precisa ser editor ou responsável pelo espaço para remover uma referência.');
            $pessoaId = (int) ($_POST['pessoa_id'] ?? 0);
            $rootStmt = $pdo->prepare(
                'SELECT 1 FROM familia_pessoa_escopos WHERE familia_id = ? AND referencia_raiz_id = ? AND pessoa_id = ? LIMIT 1'
            );
            $rootStmt->execute([familiaAtualId(), $pessoaId, $pessoaId]);
            if (!$rootStmt->fetchColumn()) throw new RuntimeException('Somente uma referência incluída diretamente pode ser removida; os ancestrais são mantidos pelo escopo da linhagem.');
            $removidas = removerPessoaComLinhagem($pessoaId, familiaAtualId());
            registrarAuditoria('familia_pessoas', $pessoaId, 'referencia_removida', ['pessoas_desassociadas' => $removidas]);
            $mensagem = 'A referência e as pessoas da linhagem que não eram mais necessárias foram removidas deste espaço. Os registros originais permanecem intactos.';
        }
        if ($acao === 'selecionar') {
            $id = (int) ($_POST['familia_id'] ?? 0);
            if (!atualizarContextoFamilia($id)) throw new RuntimeException('Você não tem acesso a esse espaço.');
            header('Location: arvore.php');
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
        if ($acao === 'atualizar') {
            if (!usuarioPodeAdministrarFamilia()) throw new RuntimeException('Somente o responsável pelo espaço pode alterar sua identificação.');
            $id = familiaAtualId();
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            if (!$id) throw new RuntimeException('Selecione um espaço antes de editá-lo.');
            if (mb_strlen($nome) < 3 || mb_strlen($nome) > 180) throw new RuntimeException('Informe um nome entre 3 e 180 caracteres.');
            if (mb_strlen($descricao) > 500) throw new RuntimeException('A descrição pode ter no máximo 500 caracteres.');
            $stmt = $pdo->prepare('UPDATE familias SET nome = ?, descricao = ? WHERE id = ?');
            $stmt->execute([$nome, $descricao ?: null, $id]);
            atualizarContextoFamilia($id);
            registrarAuditoria('familia', $id, 'identificacao_atualizada', ['nome' => $nome]);
            $mensagem = 'Nome e descrição do espaço atualizados.';
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

    if (!$erro && ($acao ?? '') === 'referenciar_pessoa' && ($_POST['retorno'] ?? '') === 'arvore') {
        header('Location: arvore.php?referencia=adicionada&foco=' . (int) ($pessoaId ?? 0));
        exit;
    }
}

$familias = listarFamiliasDaComunidade();
if (!$familias && !empty($_SESSION['usuario_id'])) {
    // Instalações anteriores à migração ainda podem estar sem associação.
    $erro = $erro ?: 'Execute a migração 005 no banco para habilitar espaços de família.';
}
$familiaSelecionada = familiaAtualId();
$familiaAtiva = null;
foreach ($familias as $familia) {
    if ((int) $familia['id'] === (int) $familiaSelecionada) {
        $familiaAtiva = $familia;
        break;
    }
}
$membros = [];
$referencias = [];
$pessoasDisponiveisReferencia = [];
if ($familiaSelecionada) {
    $stmt = $pdo->prepare('SELECT u.id, u.nome, u.email, fu.papel, fu.criado_em FROM usuarios u JOIN familia_usuarios fu ON fu.usuario_id = u.id WHERE fu.familia_id = ? ORDER BY FIELD(fu.papel, "owner", "editor", "viewer"), u.nome');
    $stmt->execute([$familiaSelecionada]);
    $membros = $stmt->fetchAll();
    $stmt = $pdo->prepare(
        "SELECT p.id, p.nome_completo, origem.id AS origem_familia_id, origem.nome AS origem_familia_nome, fp.criado_em
         FROM familia_pessoas fp
         JOIN pessoas p ON p.id = fp.pessoa_id
         JOIN familias origem ON origem.id = p.familia_id
         WHERE fp.familia_id = ? AND fp.tipo = 'referenciada'
           AND EXISTS (
               SELECT 1 FROM familia_pessoa_escopos raiz
               WHERE raiz.familia_id = fp.familia_id
                 AND raiz.referencia_raiz_id = fp.pessoa_id
                 AND raiz.pessoa_id = fp.pessoa_id
           )
         ORDER BY origem.nome, p.nome_completo"
    );
    $stmt->execute([$familiaSelecionada]);
    $referencias = $stmt->fetchAll();
    $stmt = $pdo->prepare(
        'SELECT p.id, p.nome_completo, f.id AS familia_id, f.nome AS familia_nome
         FROM pessoas p
         JOIN familias f ON f.id = p.familia_id
         LEFT JOIN familia_pessoas atual ON atual.familia_id = ? AND atual.pessoa_id = p.id
         WHERE p.familia_id <> ? AND atual.pessoa_id IS NULL
         ORDER BY f.nome, p.nome_completo'
    );
    $stmt->execute([$familiaSelecionada, $familiaSelecionada]);
    $pessoasDisponiveisReferencia = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Famílias · Árvore Familiar</title><link rel="stylesheet" href="css/style.css?v=community-1">
</head>
<body>
<header class="topo"><a class="brand" href="index.php">Árvore Familiar</a><nav><a href="index.php">Painel</a><a href="arvore.php">Explorar árvore</a><a href="familias.php" aria-current="page">Famílias</a><span class="user-chip"><?= htmlspecialchars(usuarioAtualNome() ?: '') ?></span><a href="logout.php">Sair</a></nav></header>
<main class="app-shell">
    <div class="page-heading"><div><span class="eyebrow">Comunidade genealógica</span><h1>Espaços da família</h1><p class="lead">Todos os espaços são visíveis para a comunidade. Você pode explorar qualquer árvore e incluir pessoas existentes sem recadastrar ninguém.</p></div><?php if ($familiaSelecionada && usuarioPodeEditar()): ?><a class="btn" href="#referenciar-pessoa">＋ Incluir pessoa existente</a><?php endif; ?></div>
    <?php if ($mensagem): ?><div class="sucesso" style="margin-bottom:18px"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="erro" style="margin-bottom:18px"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
    <section class="family-grid">
        <?php foreach ($familias as $familia): ?>
            <article class="surface family-card <?= (int) $familia['id'] === (int) $familiaSelecionada ? 'is-active' : '' ?>">
                <h3><?= htmlspecialchars($familia['nome']) ?></h3><p><?= htmlspecialchars($familia['descricao'] ?: 'Espaço colaborativo para a sua genealogia.') ?></p>
                <div class="family-meta"><span><?= (int) $familia['total_pessoas'] ?> <?= (int) $familia['total_pessoas'] === 1 ? 'pessoa disponível' : 'pessoas disponíveis' ?></span><?php if (!empty($familia['total_referenciadas'])): ?><span><?= (int) $familia['total_referenciadas'] ?> <?= (int) $familia['total_referenciadas'] === 1 ? 'referenciada' : 'referenciadas' ?></span><?php endif; ?><span><?= (int) $familia['total_membros'] ?> membros</span><span class="role"><?= htmlspecialchars($familia['papel']) ?></span></div>
                <form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>"><input type="hidden" name="acao" value="selecionar"><input type="hidden" name="familia_id" value="<?= (int) $familia['id'] ?>"><button class="btn <?= (int) $familia['id'] === (int) $familiaSelecionada ? 'btn-secundario' : '' ?>" type="submit"><?= (int) $familia['id'] === (int) $familiaSelecionada ? 'Espaço ativo' : 'Entrar neste espaço' ?></button></form>
            </article>
        <?php endforeach; ?>
        <article class="surface family-card family-form"><h3>Criar novo espaço</h3><p>Ideal para separar ramos, famílias ou projetos de pesquisa.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>"><input type="hidden" name="acao" value="criar"><label>Nome do espaço<input name="nome" required maxlength="180" placeholder="Ex.: Família Pedron"></label><label>Descrição <span class="muted">(opcional)</span><textarea name="descricao" maxlength="500" placeholder="Uma frase para identificar este espaço"></textarea></label><button class="btn" type="submit">Criar espaço</button></form></article>
    </section>

    <?php if ($familiaSelecionada && usuarioPodeEditar()): ?>
    <?php if (usuarioPodeAdministrarFamilia()): ?>
    <section class="surface panel" style="margin-top:22px"><div class="panel-header"><div><h2>Identificação do espaço</h2><span class="muted small">O nome aparece na árvore, na seleção de famílias e nos convites. O endereço interno do espaço não é alterado.</span></div></div>
        <form class="dashboard-layout" style="grid-template-columns:minmax(220px,1fr) minmax(220px,1.4fr) 150px;gap:10px" method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>"><input type="hidden" name="acao" value="atualizar"><label>Nome do espaço<input name="nome" required minlength="3" maxlength="180" value="<?= htmlspecialchars($familiaAtiva['nome'] ?? '') ?>"></label><label>Descrição <span class="muted">(opcional)</span><input name="descricao" maxlength="500" value="<?= htmlspecialchars($familiaAtiva['descricao'] ?? '') ?>" placeholder="Ex.: Descendentes de Antônio Pedron"></label><div style="display:flex;align-items:flex-end"><button class="btn" type="submit">Salvar nome</button></div></form>
    </section>
    <?php endif; ?>
    <section class="surface panel" id="referenciar-pessoa" style="margin-top:22px"><div class="panel-header"><div><h2>＋ Incluir pessoa existente</h2><span class="muted small">Pesquise alguém já cadastrado em qualquer outro espaço da comunidade. A pessoa e os pais biológicos/adotivos conhecidos passam a aparecer nesta árvore sem duplicar cadastros; padrastos, madrastas e cônjuges sem vínculo parental ficam fora.</span></div></div>
        <?php if ($pessoasDisponiveisReferencia): ?>
        <form class="dashboard-layout" style="grid-template-columns:minmax(0,1fr) 150px;gap:10px;margin-bottom:18px" method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>"><input type="hidden" name="acao" value="referenciar_pessoa"><label>Pessoa para referenciar<select name="pessoa_id" required><option value="">Selecione...</option><?php $familiaReferenciaAtual = ''; foreach ($pessoasDisponiveisReferencia as $opcao): if ($familiaReferenciaAtual !== $opcao['familia_nome']): $familiaReferenciaAtual = $opcao['familia_nome']; ?><option disabled>— <?= htmlspecialchars($familiaReferenciaAtual) ?> —</option><?php endif; ?><option value="<?= (int) $opcao['id'] ?>"><?= htmlspecialchars($opcao['nome_completo']) ?></option><?php endforeach; ?></select></label><div style="display:flex;align-items:flex-end"><button class="btn" type="submit">Referenciar pessoa</button></div></form>
        <?php else: ?><p class="muted">Não há pessoas disponíveis em outros espaços ou todas já estão incluídas nesta árvore.</p><?php endif; ?>
        <?php if ($referencias): ?><div class="family-reference-list"><?php foreach ($referencias as $referencia): ?><div class="family-reference-row"><div><strong><?= htmlspecialchars($referencia['nome_completo']) ?></strong><div class="muted small">Origem: <?= htmlspecialchars($referencia['origem_familia_nome']) ?></div></div><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>"><input type="hidden" name="acao" value="desreferenciar_pessoa"><input type="hidden" name="pessoa_id" value="<?= (int) $referencia['id'] ?>"><button class="btn btn-ghost btn-small" type="submit">Remover referência</button></form></div><?php endforeach; ?></div><?php endif; ?>
    </section>
    <?php if (usuarioPodeAdministrarFamilia()): ?>
    <section class="surface panel" style="margin-top:22px"><div class="panel-header"><div><h2>Membros de <?= htmlspecialchars(familiaAtualNome() ?: 'espaço') ?></h2><span class="muted small">O papel define se a pessoa pode apenas visualizar ou também editar.</span></div></div>
        <form class="dashboard-layout" style="grid-template-columns: minmax(0,1fr) 180px 130px; gap:10px; margin-bottom:18px" method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>"><input type="hidden" name="acao" value="convidar"><input type="email" name="email" placeholder="E-mail da conta" required><select name="papel"><option value="viewer">Visualizador</option><option value="editor">Editor</option></select><button class="btn" type="submit">Compartilhar</button></form>
        <?php foreach ($membros as $membro): ?><div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--line)"><div><strong><?= htmlspecialchars($membro['nome']) ?></strong><div class="muted small"><?= htmlspecialchars($membro['email']) ?></div></div><div style="display:flex;align-items:center;gap:12px"><span class="role" style="color:var(--brand);font-size:12px;font-weight:800"><?= htmlspecialchars($membro['papel']) ?></span><?php if ((int) $membro['id'] !== (int) usuarioAtualId()): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>"><input type="hidden" name="acao" value="remover_membro"><input type="hidden" name="usuario_id" value="<?= (int) $membro['id'] ?>"><button class="btn btn-ghost btn-small" type="submit">Remover</button></form><?php endif; ?></div></div><?php endforeach; ?>
    </section>
    <?php endif; ?>
    <?php endif; ?>
</main>
</body></html>
