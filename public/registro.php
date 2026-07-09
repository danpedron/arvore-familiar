<?php
require_once __DIR__ . '/../includes/auth.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($nome === '' || $email === '' || strlen($senha) < 6) {
        $erro = 'Preencha todos os campos. A senha deve ter no mínimo 6 caracteres.';
    } else {
        $resultado = registrarUsuario($nome, $email, $senha);
        if ($resultado['sucesso']) {
            autenticarUsuario($email, $senha);
            header('Location: index.php');
            exit;
        }
        $erro = $resultado['erro'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Criar conta - Árvore Familiar</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container" style="max-width:400px;margin-top:60px;">
    <div class="card">
        <h1>Criar conta</h1>
        <?php if ($erro): ?><p class="erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
        <form method="post">
            <label>Nome</label>
            <input type="text" name="nome" required>
            <label>E-mail</label>
            <input type="email" name="email" required>
            <label>Senha (mín. 6 caracteres)</label>
            <input type="password" name="senha" required>
            <button type="submit">Criar conta</button>
        </form>
        <p style="margin-top:16px;">Já tem conta? <a href="login.php">Entrar</a></p>
    </div>
</div>
</body>
</html>
