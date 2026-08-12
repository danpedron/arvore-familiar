<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirFamilia();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');

$query = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($query) < 2) {
    echo json_encode(['sucesso' => true, 'pessoas' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$escapeLike = static fn(string $value): string => strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']);
$like = '%' . $escapeLike($query) . '%';
$pdo = getConexao();
$stmt = $pdo->prepare(
    "SELECT p.id, p.nome_completo, p.data_nascimento, p.falecido,
            f.id AS familia_id, f.nome AS familia_nome,
            atual.pessoa_id AS ja_incluida
     FROM pessoas p
     INNER JOIN familias f ON f.id = p.familia_id
     LEFT JOIN familia_pessoas atual ON atual.familia_id = ? AND atual.pessoa_id = p.id
     WHERE p.familia_id <> ?
       AND atual.pessoa_id IS NULL
       AND p.nome_completo LIKE ? ESCAPE '!'
     ORDER BY p.nome_completo COLLATE utf8mb4_general_ci, p.id
     LIMIT 30"
);
$stmt->execute([familiaAtualId(), familiaAtualId(), $like]);

$pessoas = [];
foreach ($stmt->fetchAll() as $pessoa) {
    $familiaOrigemId = (int) $pessoa['familia_id'];
    $pessoas[] = [
        'id' => (int) $pessoa['id'],
        'nome' => (string) $pessoa['nome_completo'],
        'nascimento' => $pessoa['data_nascimento'],
        'falecido' => (bool) $pessoa['falecido'],
        'familiaId' => $familiaOrigemId,
        'familiaNome' => (string) $pessoa['familia_nome'],
        'editavel' => in_array(papelUsuarioNaFamilia($familiaOrigemId), ['owner', 'editor'], true),
    ];
}

echo json_encode(['sucesso' => true, 'pessoas' => $pessoas], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

