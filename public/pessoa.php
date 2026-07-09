<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirLogin();

$id = (int) ($_GET['id'] ?? 0);
$pessoa = buscarPessoa($id);

if (!$pessoa) {
    header('Location: index.php');
    exit;
}

// Ações via POST (adicionar relações e mídias)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'add_pai' && !empty($_POST['pai_mae_id'])) {
        adicionarPaiMae($id, (int) $_POST['pai_mae_id']);
    } elseif ($acao === 'add_filho' && !empty($_POST['filho_id'])) {
        adicionarPaiMae((int) $_POST['filho_id'], $id);
    } elseif ($acao === 'add_conjuge' && !empty($_POST['conjuge_id'])) {
        adicionarUniao($id, (int) $_POST['conjuge_id'], $_POST['tipo_uniao'] ?? 'casamento', $_POST['data_uniao'] ?? null);
    } elseif ($acao === 'remove_pai' && !empty($_POST['pai_mae_id'])) {
        removerPaiMae($id, (int) $_POST['pai_mae_id']);
    } elseif ($acao === 'remove_filho' && !empty($_POST['filho_id'])) {
        removerPaiMae((int) $_POST['filho_id'], $id);
    } elseif ($acao === 'remove_uniao' && !empty($_POST['uniao_id'])) {
        removerUniao((int) $_POST['uniao_id']);
    } elseif ($acao === 'add_midia' && !empty($_FILES['arquivo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));
        $tipo = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) ? 'foto' : 'documento';
        $pasta = $tipo === 'foto' ? 'uploads/fotos/' : 'uploads/documentos/';
        $nomeArquivo = $pasta . uniqid('midia_') . '.' . $ext;
        move_uploaded_file($_FILES['arquivo']['tmp_name'], __DIR__ . '/' . $nomeArquivo);
        adicionarMidia($id, $tipo, $nomeArquivo, trim($_POST['titulo'] ?? ''));
    } elseif ($acao === 'remove_midia' && !empty($_POST['midia_id'])) {
        excluirMidia((int) $_POST['midia_id']);
    } elseif ($acao === 'excluir_pessoa') {
        excluirPessoa($id);
        header('Location: index.php');
        exit;
    }

    header('Location: pessoa.php?id=' . $id);
    exit;
}

$pais = listarPais($id);
$filhos = listarFilhos($id);
$conjuges = listarConjuges($id);
$midias = listarMidias($id);
$todasPessoas = listarPessoas();
$idadeAtual = idade($pessoa['data_nascimento'], $pessoa['data_falecimento']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pessoa['nome_completo']) ?> - Árvore Familiar</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="topo">
    <a href="index.php">🌳 Árvore Familiar</a>
    <nav><a href="index.php">Voltar</a></nav>
</header>

<div class="container">
    <div class="card" style="display:flex; gap:20px; flex-wrap:wrap;">
        <img src="<?= $pessoa['foto_perfil'] ? htmlspecialchars($pessoa['foto_perfil']) : 'https://via.placeholder.com/160x160/e2ddd3/999?text=Sem+foto' ?>" style="width:160px; height:160px; object-fit:cover; border-radius:10px;">
        <div style="flex:1; min-width:220px;">
            <h1 style="margin:0 0 6px;"><?= htmlspecialchars($pessoa['nome_completo']) ?></h1>
            <?php if ($pessoa['apelido']): ?><p style="color:#777; margin:0 0 10px;">"<?= htmlspecialchars($pessoa['apelido']) ?>"</p><?php endif; ?>

            <?php if ($pessoa['data_nascimento']): ?>
                <p>🎂 <?= date('d/m/Y', strtotime($pessoa['data_nascimento'])) ?><?= $pessoa['local_nascimento'] ? ' — ' . htmlspecialchars($pessoa['local_nascimento']) : '' ?>
                <?php if ($idadeAtual !== null && !$pessoa['falecido']): ?> (<?= $idadeAtual ?> anos)<?php endif; ?></p>
            <?php endif; ?>

            <?php if ($pessoa['falecido']): ?>
                <p>🕊️ Falecido(a) <?= $pessoa['data_falecimento'] ? 'em ' . date('d/m/Y', strtotime($pessoa['data_falecimento'])) : '' ?>
                <?= $pessoa['local_falecimento'] ? ' — ' . htmlspecialchars($pessoa['local_falecimento']) : '' ?>
                <?php if ($idadeAtual !== null): ?> (aos <?= $idadeAtual ?> anos)<?php endif; ?></p>
            <?php endif; ?>

            <?php if ($pessoa['biografia']): ?><p><?= nl2br(htmlspecialchars($pessoa['biografia'])) ?></p><?php endif; ?>

            <a href="pessoa_editar.php?id=<?= $id ?>" class="btn">Editar dados</a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Excluir esta pessoa e todas as suas relações e mídias?');">
                <input type="hidden" name="acao" value="excluir_pessoa">
                <button type="submit" class="btn-perigo">Excluir pessoa</button>
            </form>
        </div>
    </div>

    <!-- Pais -->
    <div class="card">
        <h2>Pais</h2>
        <ul class="relacoes-lista">
            <?php foreach ($pais as $p): ?>
                <li>
                    <a href="pessoa.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['nome_completo']) ?></a>
                    <form method="post" onsubmit="return confirm('Remover este vínculo?');">
                        <input type="hidden" name="acao" value="remove_pai">
                        <input type="hidden" name="pai_mae_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn-perigo" style="margin:0; padding:4px 10px;">Remover</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" style="display:flex; gap:8px; align-items:end;">
            <input type="hidden" name="acao" value="add_pai">
            <div style="flex:1;">
                <label>Adicionar pai/mãe</label>
                <select name="pai_mae_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($todasPessoas as $tp): if ($tp['id'] == $id) continue; ?>
                        <option value="<?= $tp['id'] ?>"><?= htmlspecialchars($tp['nome_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">Adicionar</button>
        </form>
    </div>

    <!-- Cônjuges -->
    <div class="card">
        <h2>Cônjuges / Uniões</h2>
        <ul class="relacoes-lista">
            <?php foreach ($conjuges as $c): ?>
                <li>
                    <a href="pessoa.php?id=<?= $c['id'] ?>"><?= htmlspecialchars($c['nome_completo']) ?></a>
                    <span style="color:#777; font-size:0.85em;"><?= htmlspecialchars($c['tipo']) ?><?= $c['data_inicio'] ? ' desde ' . date('d/m/Y', strtotime($c['data_inicio'])) : '' ?></span>
                    <form method="post" onsubmit="return confirm('Remover esta união?');">
                        <input type="hidden" name="acao" value="remove_uniao">
                        <input type="hidden" name="uniao_id" value="<?= $c['uniao_id'] ?>">
                        <button type="submit" class="btn-perigo" style="margin:0; padding:4px 10px;">Remover</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap;">
            <input type="hidden" name="acao" value="add_conjuge">
            <div style="flex:1;">
                <label>Adicionar cônjuge</label>
                <select name="conjuge_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($todasPessoas as $tp): if ($tp['id'] == $id) continue; ?>
                        <option value="<?= $tp['id'] ?>"><?= htmlspecialchars($tp['nome_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Tipo</label>
                <select name="tipo_uniao">
                    <option value="casamento">Casamento</option>
                    <option value="uniao_estavel">União estável</option>
                    <option value="namoro">Namoro</option>
                    <option value="outro">Outro</option>
                </select>
            </div>
            <div>
                <label>Data de início</label>
                <input type="date" name="data_uniao">
            </div>
            <button type="submit">Adicionar</button>
        </form>
    </div>

    <!-- Filhos -->
    <div class="card">
        <h2>Filhos</h2>
        <ul class="relacoes-lista">
            <?php foreach ($filhos as $f): ?>
                <li>
                    <a href="pessoa.php?id=<?= $f['id'] ?>"><?= htmlspecialchars($f['nome_completo']) ?></a>
                    <form method="post" onsubmit="return confirm('Remover este vínculo?');">
                        <input type="hidden" name="acao" value="remove_filho">
                        <input type="hidden" name="filho_id" value="<?= $f['id'] ?>">
                        <button type="submit" class="btn-perigo" style="margin:0; padding:4px 10px;">Remover</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" style="display:flex; gap:8px; align-items:end;">
            <input type="hidden" name="acao" value="add_filho">
            <div style="flex:1;">
                <label>Adicionar filho(a)</label>
                <select name="filho_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($todasPessoas as $tp): if ($tp['id'] == $id) continue; ?>
                        <option value="<?= $tp['id'] ?>"><?= htmlspecialchars($tp['nome_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">Adicionar</button>
        </form>
    </div>

    <!-- Fotos e documentos -->
    <div class="card">
        <h2>Fotos e documentos</h2>
        <div class="midias-grid">
            <?php foreach ($midias as $m): ?>
                <div>
                    <?php if ($m['tipo'] === 'foto'): ?>
                        <img src="<?= htmlspecialchars($m['caminho_arquivo']) ?>" alt="">
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($m['caminho_arquivo']) ?>" target="_blank" class="btn" style="width:100%; text-align:center;">📄 <?= htmlspecialchars($m['titulo'] ?: 'Documento') ?></a>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('Excluir este arquivo?');" style="margin-top:4px;">
                        <input type="hidden" name="acao" value="remove_midia">
                        <input type="hidden" name="midia_id" value="<?= $m['id'] ?>">
                        <button type="submit" class="btn-perigo" style="margin:0; padding:4px 10px; width:100%;">Excluir</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="post" enctype="multipart/form-data" style="margin-top:16px;">
            <input type="hidden" name="acao" value="add_midia">
            <label>Título (opcional)</label>
            <input type="text" name="titulo">
            <label>Arquivo (foto ou documento)</label>
            <input type="file" name="arquivo" required>
            <button type="submit">Enviar</button>
        </form>
    </div>
</div>
</body>
</html>
