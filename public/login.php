<?php
require_once __DIR__ . '/../includes/auth.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (autenticarUsuario($email, $senha)) {
        header('Location: index.php');
        exit;
    }
    $erro = 'E-mail ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Entrar - Árvore Familiar</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container" style="max-width:400px;margin-top:60px;">
    <div class="card">
        <h1>Entrar</h1>
        <?php if ($erro): ?><p class="erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
        <form method="post">
            <label>E-mail</label>
            <input type="email" name="email" required>
            <label>Senha</label>
            <input type="password" name="senha" required>
            <button type="submit">Entrar</button>
        </form>
        <p style="margin-top:16px;">Ainda não tem conta? <a href="registro.php">Criar conta</a></p>
    </div>
</div>
</body>
</html>
