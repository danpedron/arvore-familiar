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

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome_completo'] ?? '');

    if ($nome === '') {
        $erro = 'O nome completo é obrigatório.';
    } else {
        $novoId = salvarPessoa($_POST, $id);

        // Upload de foto de perfil, se enviada
        if (!empty($_FILES['foto']['name'])) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $permitidas)) {
                $nomeArquivo = 'uploads/fotos/' . uniqid('foto_') . '.' . $ext;
                move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . '/' . $nomeArquivo);
                atualizarFotoPerfil($novoId, $nomeArquivo);
            }
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
        <?php if ($erro): ?><p class="erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <label>Nome completo *</label>
            <input type="text" name="nome_completo" required value="<?= htmlspecialchars($pessoa['nome_completo'] ?? '') ?>">

            <label>Apelido</label>
            <input type="text" name="apelido" value="<?= htmlspecialchars($pessoa['apelido'] ?? '') ?>">

            <label>Sexo</label>
            <select name="sexo">
                <?php foreach (['Desconhecido' => 'Não informado', 'M' => 'Masculino', 'F' => 'Feminino', 'Outro' => 'Outro'] as $val => $label): ?>
                    <option value="<?= $val ?>" <?= ($pessoa['sexo'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>

            <label>Foto de perfil</label>
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
