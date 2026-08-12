<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirFamilia();
header('Content-Type: application/json; charset=utf-8');

if (!usuarioPodeEditar()) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'erro' => 'Seu papel neste espaço permite apenas visualização.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'JSON inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

exigirCsrf($payload['csrf_token'] ?? null);
$id = isset($payload['id']) ? (int) $payload['id'] : 0;
$dados = is_array($payload['data'] ?? null) ? $payload['data'] : [];

if ($id <= 0 || !buscarPessoa($id)) {
    http_response_code(404);
    echo json_encode(['sucesso' => false, 'erro' => 'Pessoa não encontrada neste espaço familiar.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$nome = trim((string) ($dados['nome_completo'] ?? ''));
if ($nome === '') {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'erro' => 'O nome de nascimento é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$sexo = (string) ($dados['sexo'] ?? 'Desconhecido');
if (!in_array($sexo, ['M', 'F', 'Outro', 'Desconhecido'], true)) $sexo = 'Desconhecido';
$normalizarData = static function ($value): ?string {
    $value = trim((string) $value);
    if ($value === '') return null;
    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $data && $data->format('Y-m-d') === $value ? $value : null;
};

try {
    salvarPessoa([
        'nome_completo' => $nome,
        'apelido' => trim((string) ($dados['apelido'] ?? '')),
        'sexo' => $sexo,
        'data_nascimento' => $normalizarData($dados['data_nascimento'] ?? null),
        'local_nascimento' => trim((string) ($dados['local_nascimento'] ?? '')),
        'data_falecimento' => $normalizarData($dados['data_falecimento'] ?? null),
        'local_falecimento' => trim((string) ($dados['local_falecimento'] ?? '')),
        'falecido' => !empty($dados['data_falecimento']) ? 1 : 0,
        'biografia' => '',
    ], $id);
    echo json_encode(['sucesso' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Não foi possível salvar os detalhes desta pessoa.'], JSON_UNESCAPED_UNICODE);
}
