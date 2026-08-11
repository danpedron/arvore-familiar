<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('arvore_familiar');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
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
    return isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
}

function usuarioAtualNome(): ?string {
    return $_SESSION['usuario_nome'] ?? null;
}

function tokenCsrf(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarCsrf(?string $token): bool {
    return is_string($token) && $token !== '' && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function exigirCsrf(?string $token): void {
    if (!validarCsrf($token)) {
        http_response_code(419);
        exit('Sessão expirada. Recarregue a página e tente novamente.');
    }
}

function definirFamiliaAtual(?int $familiaId): void {
    if ($familiaId) {
        $_SESSION['familia_id'] = $familiaId;
    } else {
        unset($_SESSION['familia_id'], $_SESSION['familia_papel'], $_SESSION['familia_nome']);
    }
}

function familiaAtualId(): ?int {
    return isset($_SESSION['familia_id']) ? (int) $_SESSION['familia_id'] : null;
}

function familiaAtualNome(): ?string {
    return $_SESSION['familia_nome'] ?? null;
}

function familiaAtualPapel(): string {
    return $_SESSION['familia_papel'] ?? 'viewer';
}

function usuarioPodeEditar(): bool {
    return in_array(familiaAtualPapel(), ['owner', 'editor'], true);
}

function usuarioPodeAdministrarFamilia(): bool {
    return familiaAtualPapel() === 'owner';
}

function atualizarContextoFamilia(?int $familiaId = null): bool {
    $familiaId = $familiaId ?: familiaAtualId();
    if (!$familiaId || !usuarioAtualId()) {
        definirFamiliaAtual(null);
        return false;
    }

    $pdo = getConexao();
    $stmt = $pdo->prepare(
        'SELECT f.id, f.nome, fu.papel
         FROM familias f
         JOIN familia_usuarios fu ON fu.familia_id = f.id
         WHERE f.id = ? AND fu.usuario_id = ?'
    );
    $stmt->execute([$familiaId, usuarioAtualId()]);
    $familia = $stmt->fetch();
    if (!$familia) {
        definirFamiliaAtual(null);
        return false;
    }

    $_SESSION['familia_id'] = (int) $familia['id'];
    $_SESSION['familia_nome'] = $familia['nome'];
    $_SESSION['familia_papel'] = $familia['papel'];
    return true;
}

function exigirFamilia(): void {
    exigirLogin();
    if (!atualizarContextoFamilia()) {
        header('Location: familias.php');
        exit;
    }
}

function registrarUsuario(string $nome, string $email, string $senha): array {
    $pdo = getConexao();
    $nome = trim($nome);
    $email = strtolower(trim($email));
    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['sucesso' => false, 'erro' => 'Informe um nome e um e-mail válido.'];
    }
    if (mb_strlen($senha) < 8) {
        return ['sucesso' => false, 'erro' => 'A senha deve ter pelo menos 8 caracteres.'];
    }

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
    $email = strtolower(trim($email));
    $stmt = $pdo->prepare('SELECT id, nome, senha_hash FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int) $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        tokenCsrf();
        $familias = listarFamiliasDoUsuario((int) $usuario['id']);
        if (count($familias) === 1) {
            definirFamiliaAtual((int) $familias[0]['id']);
            atualizarContextoFamilia();
        }
        return true;
    }

    return false;
}

function listarFamiliasDoUsuario(?int $usuarioId = null): array {
    $usuarioId = $usuarioId ?: usuarioAtualId();
    if (!$usuarioId) return [];
    $pdo = getConexao();
    $stmt = $pdo->prepare(
        'SELECT f.id, f.nome, f.slug, f.descricao, f.criado_em, fu.papel,
                (SELECT COUNT(*) FROM pessoas p WHERE p.familia_id = f.id) AS total_pessoas,
                (SELECT COUNT(*) FROM familia_usuarios fu2 WHERE fu2.familia_id = f.id) AS total_membros
         FROM familias f
         JOIN familia_usuarios fu ON fu.familia_id = f.id
         WHERE fu.usuario_id = ?
         ORDER BY f.nome'
    );
    $stmt->execute([$usuarioId]);
    return $stmt->fetchAll();
}

function encerrarSessao(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}
