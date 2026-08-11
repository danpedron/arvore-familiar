<?php
require_once __DIR__ . '/../includes/auth.php';
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = registrarUsuario(trim($_POST['nome'] ?? ''), trim($_POST['email'] ?? ''), $_POST['senha'] ?? '');
    if ($resultado['sucesso']) {
        autenticarUsuario(trim($_POST['email'] ?? ''), $_POST['senha'] ?? '');
        header('Location: familias.php');
        exit;
    }
    $erro = $resultado['erro'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Criar conta · Árvore Familiar</title><link rel="stylesheet" href="css/style.css"></head>
<body class="auth-page"><main class="surface auth-card"><div class="auth-brand">Árvore Familiar</div><h1>Crie seu espaço</h1><p>Uma conta para organizar árvores e colaborar com outras pessoas da família.</p><?php if ($erro): ?><p class="erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?><form method="post"><label>Nome<input type="text" name="nome" autocomplete="name" required maxlength="150"></label><label>E-mail<input type="email" name="email" autocomplete="email" required maxlength="150"></label><label>Senha <span class="muted">(mínimo de 8 caracteres)</span><input type="password" name="senha" autocomplete="new-password" minlength="8" required></label><button type="submit" style="width:100%;margin-top:22px">Criar conta</button></form><p class="small" style="margin:18px 0 0;color:var(--muted)">Já tem conta? <a href="login.php">Entrar</a></p></main></body></html>
