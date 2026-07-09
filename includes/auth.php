<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function usuarioLogado(): bool {
    return isset($_SESSION['usuario_id']);
}

function exigirLogin(): void {
    if (!usuarioLogado()) {
        header('Location: login.php');
        exit;
    }
}

function usuarioAtualId(): ?int {
    return $_SESSION['usuario_id'] ?? null;
}

function usuarioAtualNome(): ?string {
    return $_SESSION['usuario_nome'] ?? null;
}

function registrarUsuario(string $nome, string $email, string $senha): array {
    $pdo = getConexao();

    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['sucesso' => false, 'erro' => 'Já existe uma conta com este e-mail.'];
    }

    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash) VALUES (?, ?, ?)');
    $stmt->execute([$nome, $email, $hash]);

    return ['sucesso' => true, 'id' => $pdo->lastInsertId()];
}

function autenticarUsuario(string $email, string $senha): bool {
    $pdo = getConexao();
    $stmt = $pdo->prepare('SELECT id, nome, senha_hash FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        return true;
    }

    return false;
}

function encerrarSessao(): void {
    $_SESSION = [];
    session_destroy();
}
