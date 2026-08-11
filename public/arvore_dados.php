<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirFamilia();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');

$pdo = getConexao();
$familiaId = familiaAtualId();

$pessoasStmt = $pdo->prepare(
    'SELECT id, nome_completo, apelido, sexo, foto_perfil, data_nascimento,
            data_falecimento, falecido, local_nascimento, atualizado_em
     FROM pessoas
     WHERE familia_id = ?
     ORDER BY nome_completo COLLATE utf8mb4_general_ci, id'
);
$pessoasStmt->execute([$familiaId]);
$pessoas = $pessoasStmt->fetchAll();

$ids = array_map(static fn(array $p): string => (string) $p['id'], $pessoas);
$validIds = array_fill_keys($ids, true);

$parentStmt = $pdo->prepare(
    'SELECT rp.filho_id, rp.pai_mae_id, rp.tipo
     FROM relacoes_parentais rp
     INNER JOIN pessoas filho ON filho.id = rp.filho_id AND filho.familia_id = ?
     INNER JOIN pessoas pai ON pai.id = rp.pai_mae_id AND pai.familia_id = ?
     ORDER BY rp.filho_id, rp.pai_mae_id'
);
$parentStmt->execute([$familiaId, $familiaId]);
$parentRows = $parentStmt->fetchAll();

$unionStmt = $pdo->prepare(
    'SELECT u.id, u.pessoa1_id, u.pessoa2_id, u.tipo, u.status,
            u.data_inicio, u.data_fim
     FROM unioes u
     INNER JOIN pessoas p1 ON p1.id = u.pessoa1_id AND p1.familia_id = ?
     INNER JOIN pessoas p2 ON p2.id = u.pessoa2_id AND p2.familia_id = ?
     ORDER BY u.pessoa1_id, u.pessoa2_id, u.id'
);
$unionStmt->execute([$familiaId, $familiaId]);
$unionRows = $unionStmt->fetchAll();

$parents = [];
$children = [];
foreach ($parentRows as $row) {
    $child = (string) $row['filho_id'];
    $parent = (string) $row['pai_mae_id'];
    if (!isset($validIds[$child], $validIds[$parent])) continue;
    $parents[$child][] = $parent;
    $children[$parent][] = $child;
}

$spouses = [];
$unionsByPerson = [];
foreach ($unionRows as $row) {
    $personOne = (string) $row['pessoa1_id'];
    $personTwo = (string) $row['pessoa2_id'];
    if (!isset($validIds[$personOne], $validIds[$personTwo])) continue;
    $spouses[$personOne][] = $personTwo;
    $spouses[$personTwo][] = $personOne;
    $union = [
        'id' => (int) $row['id'],
        'tipo' => (string) ($row['tipo'] ?? ''),
        'status' => (string) ($row['status'] ?? ''),
        'inicio' => $row['data_inicio'],
        'fim' => $row['data_fim'],
    ];
    $unionsByPerson[$personOne][] = $union;
    $unionsByPerson[$personTwo][] = $union;
}

$uniqueIds = static fn(array $values): array => array_values(array_unique(array_map('strval', $values)));
$year = static fn(?string $date): ?string => $date ? substr($date, 0, 4) : null;
$dates = static function (array $person) use ($year): string {
    $birth = $year($person['data_nascimento'] ?? null);
    $death = $year($person['data_falecimento'] ?? null);
    if (!empty($person['falecido'])) return ($birth ?: '?') . ' — ' . ($death ?: '?');
    return $birth ? 'n. ' . $birth : 'Datas não informadas';
};

$output = [];
foreach ($pessoas as $person) {
    $id = (string) $person['id'];
    $name = trim((string) $person['nome_completo']);
    $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
    $shortName = (string) ($person['apelido'] ?: ($parts[0] ?? $name));
    $gender = in_array($person['sexo'], ['M', 'F'], true) ? $person['sexo'] : 'neutral';
    $output[] = [
        'id' => $id,
        'nome' => $name,
        'nomeCurto' => $shortName,
        'sexo' => $person['sexo'],
        'gender' => $gender,
        'datas' => $dates($person),
        'nascimento' => $person['data_nascimento'],
        'criadoEm' => $person['criado_em'],
        'atualizadoEm' => $person['atualizado_em'],
        'localNascimento' => (string) ($person['local_nascimento'] ?? ''),
        'foto' => caminhoFotoValido($person['foto_perfil']),
        'status' => !empty($person['falecido']) ? 'falecido' : 'vivo',
        'pais' => $uniqueIds($parents[$id] ?? []),
        'filhos' => $uniqueIds($children[$id] ?? []),
        'conjuges' => $uniqueIds($spouses[$id] ?? []),
        'unioes' => $unionsByPerson[$id] ?? [],
    ];
}

$familyStmt = $pdo->prepare('SELECT id, nome, slug, descricao FROM familias WHERE id = ?');
$familyStmt->execute([$familiaId]);

$totalPeople = count($pessoas);
$alive = count(array_filter($pessoas, static fn(array $person): bool => empty($person['falecido'])));

http_response_code(200);
echo json_encode([
    'familia' => $familyStmt->fetch() ?: ['id' => $familiaId, 'nome' => familiaAtualNome()],
    'totais' => [
        'pessoas' => $totalPeople,
        'vivas' => $alive,
        'falecidas' => $totalPeople - $alive,
        'relacoes' => count($parentRows),
        'unioes' => count($unionRows),
    ],
    'pessoas' => $output,
    'geradoEm' => date(DATE_ATOM),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
