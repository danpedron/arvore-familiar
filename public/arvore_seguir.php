<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirFamilia();
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

exigirCsrf($_POST['csrf_token'] ?? null);
$nome = trim((string) ($_POST['nome'] ?? ''));
$url = trim((string) ($_POST['url'] ?? ''));
$observacao = trim((string) ($_POST['observacao'] ?? ''));

if ($nome === '' || mb_strlen($nome) > 180) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'erro' => 'Informe um nome válido para a árvore.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url) || mb_strlen($url) > 1000) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'erro' => 'Informe uma URL http ou https válida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getConexao();
    $stmt = $pdo->prepare(
        'INSERT INTO arvores_seguidas (familia_id, usuario_id, nome, url, observacao) VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE nome = VALUES(nome), observacao = VALUES(observacao), atualizado_em = CURRENT_TIMESTAMP'
    );
    $stmt->execute([familiaAtualId(), usuarioAtualId(), $nome, $url, $observacao !== '' ? $observacao : null]);
    registrarAuditoria('arvore_seguida', (int) $pdo->lastInsertId(), 'criacao', ['nome' => $nome, 'url' => $url]);
    echo json_encode(['sucesso' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'A estrutura de árvores seguidas ainda não foi ativada. Execute a migração 006.'], JSON_UNESCAPED_UNICODE);
}
