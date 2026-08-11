<?php
require_once __DIR__ . '/../includes/auth.php';
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    if (autenticarUsuario($email, $senha)) {
        header('Location: ' . (familiaAtualId() ? 'index.php' : 'familias.php'));
        exit;
    }
    $erro = 'E-mail ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Entrar · Árvore Familiar</title><link rel="icon" href="favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="css/style.css?v=e928b1d"></head>
<body class="auth-page"><main class="surface auth-card"><div class="auth-brand">Árvore Familiar</div><h1>Bem-vindo de volta</h1><p>Entre para continuar preservando e explorando a história da sua família.</p><?php if ($erro): ?><p class="erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?><form method="post"><label>E-mail<input type="email" name="email" autocomplete="email" required></label><label>Senha<input type="password" name="senha" autocomplete="current-password" required></label><button type="submit" style="width:100%;margin-top:22px">Entrar</button></form><p class="small" style="margin:18px 0 0;color:var(--muted)">Ainda não tem conta? <a href="registro.php">Criar conta</a></p></main></body></html>
