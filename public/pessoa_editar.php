<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirLogin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$pessoa = $id ? buscarPessoa($id) : null;

if ($id && !$pessoa) {
    header('Location: index.php');
    exit;
}

// Suporte a "cadastrar e já vincular": veio de pessoa.php pedindo para
// criar uma nova pessoa e automaticamente ligá-la como pai/mãe, filho(a) ou cônjuge
$vincularA = isset($_GET['vincular_a']) ? (int) $_GET['vincular_a'] : null;
$tipoVinculo = $_GET['tipo_vinculo'] ?? null; // 'pai_mae' | 'filho' | 'conjuge'
$pessoaOrigem = $vincularA ? buscarPessoa($vincularA) : null;

$rotulosVinculo = [
    'pai_mae' => 'pai/mãe de',
    'filho' => 'filho(a) de',
    'conjuge' => 'cônjuge de',
];

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome_completo'] ?? '');
    $vincularAPost = isset($_POST['vincular_a']) ? (int) $_POST['vincular_a'] : null;
    $tipoVinculoPost = $_POST['tipo_vinculo'] ?? null;

    if ($nome === '') {
        $erro = 'O nome de nascimento é obrigatório.';
    } else {
        $novoId = salvarPessoa($_POST, $id);

        // Upload de foto de perfil (opcional)
        if (!empty($_FILES['foto']['name'])) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $permitidas)) {
                $nomeArquivo = 'uploads/fotos/' . uniqid('foto_') . '.' . $ext;
                move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . '/' . $nomeArquivo);
                atualizarFotoPerfil($novoId, $nomeArquivo);
            }
        }

        // Se veio de um pedido de vínculo automático (ex: "adicionar mãe" sem ela existir ainda)
        if (!$id && $vincularAPost && $tipoVinculoPost) {
            if ($tipoVinculoPost === 'pai_mae') {
                adicionarPaiMae($vincularAPost, $novoId);
            } elseif ($tipoVinculoPost === 'filho') {
                adicionarPaiMae($novoId, $vincularAPost);
            } elseif ($tipoVinculoPost === 'conjuge') {
                adicionarUniao($vincularAPost, $novoId);
            }
            header('Location: pessoa.php?id=' . $vincularAPost);
            exit;
        }

        header('Location: pessoa.php?id=' . $novoId);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= $pessoa ? 'Editar' : 'Nova' ?> pessoa - Árvore Familiar</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="topo">
    <a href="index.php">🌳 Árvore Familiar</a>
    <nav><a href="index.php">Voltar</a></nav>
</header>

<div class="container">
    <div class="card">
        <h1><?= $pessoa ? 'Editar pessoa' : 'Nova pessoa' ?></h1>

        <?php if ($pessoaOrigem && $tipoVinculo): ?>
            <p class="sucesso">
                Ao salvar, esta pessoa será vinculada automaticamente como <strong><?= htmlspecialchars($rotulosVinculo[$tipoVinculo] ?? '') ?> <?= htmlspecialchars($pessoaOrigem['nome_completo']) ?></strong>.
            </p>
        <?php endif; ?>

        <?php if ($erro): ?><p class="erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <?php if ($vincularA && $tipoVinculo): ?>
                <input type="hidden" name="vincular_a" value="<?= $vincularA ?>">
                <input type="hidden" name="tipo_vinculo" value="<?= htmlspecialchars($tipoVinculo) ?>">
            <?php endif; ?>

            <label>Nome de nascimento (nome de batismo) *</label>
            <input type="text" name="nome_completo" required value="<?= htmlspecialchars($pessoa['nome_completo'] ?? '') ?>">
            <p style="font-size:0.85em; color:#777; margin-top:4px;">
                Use o nome com que a pessoa nasceu, mesmo que tenha mudado de sobrenome depois (ex: por casamento).
                Nomes adotados posteriormente podem ser adicionados na página da pessoa, depois de salvar.
            </p>

            <label>Apelido</label>
            <input type="text" name="apelido" value="<?= htmlspecialchars($pessoa['apelido'] ?? '') ?>">

            <label>Sexo</label>
            <select name="sexo">
                <?php foreach (['Desconhecido' => 'Não informado', 'M' => 'Masculino', 'F' => 'Feminino', 'Outro' => 'Outro'] as $val => $label): ?>
                    <option value="<?= $val ?>" <?= ($pessoa['sexo'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>

            <label>Foto de perfil (opcional)</label>
            <input type="file" name="foto" accept="image/*">

            <label>Data de nascimento</label>
            <input type="date" name="data_nascimento" value="<?= htmlspecialchars($pessoa['data_nascimento'] ?? '') ?>">

            <label>Local de nascimento</label>
            <input type="text" name="local_nascimento" value="<?= htmlspecialchars($pessoa['local_nascimento'] ?? '') ?>">

            <label>
                <input type="checkbox" name="falecido" value="1" style="width:auto; display:inline-block;" <?= !empty($pessoa['falecido']) ? 'checked' : '' ?>>
                Pessoa falecida
            </label>

            <label>Data de falecimento</label>
            <input type="date" name="data_falecimento" value="<?= htmlspecialchars($pessoa['data_falecimento'] ?? '') ?>">

            <label>Local de falecimento</label>
            <input type="text" name="local_falecimento" value="<?= htmlspecialchars($pessoa['local_falecimento'] ?? '') ?>">

            <label>Biografia / notas</label>
            <textarea name="biografia" rows="4"><?= htmlspecialchars($pessoa['biografia'] ?? '') ?></textarea>

            <button type="submit">Salvar</button>
        </form>
    </div>
</div>
</body>
</html>
