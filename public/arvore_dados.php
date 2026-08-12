<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigirFamilia();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');

$pdo = getConexao();
$familiaId = familiaAtualId();

$pessoasStmt = $pdo->prepare(
    'SELECT p.id, p.nome_completo, p.apelido, p.sexo, p.foto_perfil, p.data_nascimento,
            p.data_falecimento, p.falecido, p.local_nascimento, p.criado_em, p.atualizado_em,
            fp.tipo AS associacao_tipo, origem.id AS origem_familia_id, origem.nome AS origem_familia_nome
     FROM pessoas p
     JOIN familia_pessoas fp ON fp.pessoa_id = p.id AND fp.familia_id = ?
     JOIN familias origem ON origem.id = p.familia_id
     ORDER BY p.nome_completo COLLATE utf8mb4_general_ci, p.id'
);
$pessoasStmt->execute([$familiaId]);
$pessoas = $pessoasStmt->fetchAll();

$ids = array_map(static fn(array $p): string => (string) $p['id'], $pessoas);
$validIds = array_fill_keys($ids, true);

$parentStmt = $pdo->prepare(
    'SELECT rp.filho_id, rp.pai_mae_id, rp.tipo
     FROM relacoes_parentais rp
     INNER JOIN familia_pessoas filho ON filho.pessoa_id = rp.filho_id AND filho.familia_id = ?
     INNER JOIN familia_pessoas pai ON pai.pessoa_id = rp.pai_mae_id AND pai.familia_id = ?
     ORDER BY rp.filho_id, rp.pai_mae_id'
);
$parentStmt->execute([$familiaId, $familiaId]);
$parentRows = $parentStmt->fetchAll();

$unionStmt = $pdo->prepare(
    'SELECT u.id, u.pessoa1_id, u.pessoa2_id, u.tipo, u.status,
            u.data_inicio, u.data_fim
     FROM unioes u
     INNER JOIN familia_pessoas p1 ON p1.pessoa_id = u.pessoa1_id AND p1.familia_id = ?
     INNER JOIN familia_pessoas p2 ON p2.pessoa_id = u.pessoa2_id AND p2.familia_id = ?
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
        'pessoa1' => (string) $row['pessoa1_id'],
        'pessoa2' => (string) $row['pessoa2_id'],
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
    $editavel = usuarioPodeEditarPessoa((int) $person['id']);
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
        'associacao' => (string) ($person['associacao_tipo'] ?? 'propria'),
        'editavel' => $editavel,
        // Mantido para clientes antigos, mas agora representa a permissão efetiva.
        'somenteLeitura' => !$editavel,
        'origemFamiliaId' => $person['origem_familia_id'] !== null ? (string) $person['origem_familia_id'] : null,
        'origemFamiliaNome' => (string) ($person['origem_familia_nome'] ?? ''),
        'pais' => $uniqueIds($parents[$id] ?? []),
        'filhos' => $uniqueIds($children[$id] ?? []),
        'conjuges' => $uniqueIds($spouses[$id] ?? []),
        'unioes' => $unionsByPerson[$id] ?? [],
    ];
}

$familyStmt = $pdo->prepare('SELECT id, nome, slug, descricao FROM familias WHERE id = ?');
$familyStmt->execute([$familiaId]);

$currentUserId = usuarioAtualId();
$userPersonId = null;
if ($currentUserId) {
    $focusStmt = $pdo->prepare(
        'SELECT p.id
         FROM pessoas p
         JOIN familia_pessoas fp ON fp.pessoa_id = p.id AND fp.familia_id = ?
         INNER JOIN usuarios u ON u.id = ?
         WHERE LOWER(TRIM(p.nome_completo)) = LOWER(TRIM(u.nome))
         ORDER BY p.id
         LIMIT 1'
    );
    $focusStmt->execute([$familiaId, $currentUserId]);
    $userPersonId = $focusStmt->fetchColumn();
    if ($userPersonId === false) {
        $candidateStmt = $pdo->prepare(
            'SELECT MIN(p.id), COUNT(*)
             FROM pessoas p
             JOIN familia_pessoas fp ON fp.pessoa_id = p.id AND fp.familia_id = ?
             WHERE p.criado_por = ?'
        );
        $candidateStmt->execute([$familiaId, $currentUserId]);
        [$candidateId, $candidateCount] = $candidateStmt->fetch(PDO::FETCH_NUM) ?: [null, 0];
        $userPersonId = ((int) $candidateCount === 1) ? $candidateId : null;
    }
    $userPersonId = $userPersonId !== null ? (string) $userPersonId : null;
}

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
    'usuario' => [
        'id' => $currentUserId ? (string) $currentUserId : null,
        'pessoaId' => $userPersonId,
    ],
    'geradoEm' => date(DATE_ATOM),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
