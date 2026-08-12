<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../scripts/GedcomParser.php';

exigirFamilia();
if (!usuarioPodeEditar()) { http_response_code(403); exit('Sem permissão para importar.'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: importar.php'); exit; }
exigirCsrf($_POST['csrf_token'] ?? null);
if (empty($_POST['confirmar'])) exit('Confirmação obrigatória.');
if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) exit('Não foi possível receber o arquivo.');
if ((int) $_FILES['arquivo']['size'] > 10 * 1024 * 1024) exit('O arquivo excede o limite de 10 MB.');

$nomeArquivo = basename((string) $_FILES['arquivo']['name']);
$ext = strtolower(pathinfo($nomeArquivo, PATHINFO_EXTENSION));
if (!in_array($ext, ['ged', 'gedcom', 'json'], true)) exit('Formato não permitido. Use GEDCOM ou JSON.');
$conteudo = file_get_contents($_FILES['arquivo']['tmp_name']);
if ($conteudo === false || trim($conteudo) === '') exit('O arquivo está vazio.');

$normalizarData = static function ($value): ?string {
    $value = trim((string) $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
    if (preg_match('/^(\d{4})$/', $value, $m)) return $m[1] . '-01-01';
    return null;
};
$first = static function (array $row, array $keys, $default = null) {
    foreach ($keys as $key) if (array_key_exists($key, $row) && $row[$key] !== '' && $row[$key] !== null) return $row[$key];
    return $default;
};
$pessoas = [];
$relacoesPais = [];
$unioes = [];
$tipo = $ext === 'json' ? 'json' : 'gedcom';

if ($tipo === 'gedcom') {
    $temp = tempnam(sys_get_temp_dir(), 'gedcom_');
    file_put_contents($temp, $conteudo);
    try {
        $parser = new GedcomParser();
        $parser->parse($temp);
    } finally { @unlink($temp); }
    foreach ($parser->individuos as $key => $person) {
        $name = trim((string) ($person['nomes'][0]['completo'] ?? ''));
        if ($name === '') continue;
        $pessoas[(string) $key] = [
            'nome' => $name,
            'apelido' => '',
            'sexo' => in_array($person['sexo'] ?? '', ['M', 'F'], true) ? $person['sexo'] : 'Desconhecido',
            'nascimento' => $normalizarData($person['nascimento_data']['data'] ?? null),
            'localNascimento' => $person['nascimento_local'] ?? '',
            'falecimento' => $normalizarData($person['falecimento_data']['data'] ?? null),
            'localFalecimento' => $person['falecimento_local'] ?? '',
            'falecido' => !empty($person['falecido']) ? 1 : 0,
            'biografia' => '',
        ];
    }
    foreach ($parser->familias as $family) {
        $a = (string) ($family['marido'] ?? ''); $b = (string) ($family['esposa'] ?? '');
        if ($a !== '' && $b !== '' && isset($pessoas[$a], $pessoas[$b])) $unioes[] = ['a' => $a, 'b' => $b, 'tipo' => 'casamento', 'inicio' => $normalizarData($family['casamento_data']['data'] ?? null), 'status' => 'ativo'];
        foreach (($family['filhos'] ?? []) as $child) foreach ([$a, $b] as $parent) if ($child && $parent && isset($pessoas[(string) $child], $pessoas[$parent])) $relacoesPais[] = ['filho' => (string) $child, 'pai' => $parent, 'tipo' => 'biologico'];
    }
} else {
    $json = json_decode($conteudo, true);
    if (!is_array($json)) exit('JSON inválido: ' . json_last_error_msg());
    $rawPeople = $json['pessoas'] ?? $json['people'] ?? [];
    if (!is_array($rawPeople) || !$rawPeople) exit('O JSON não contém uma lista pessoas/people.');
    foreach ($rawPeople as $index => $person) {
        if (!is_array($person)) continue;
        $key = (string) ($first($person, ['id', 'key', 'gedcom_id'], 'json_' . $index));
        $name = trim((string) $first($person, ['nome', 'name', 'nome_completo'], ''));
        if ($name === '') continue;
        $death = $normalizarData($first($person, ['falecimento', 'death', 'data_falecimento']));
        $pessoas[$key] = ['nome' => $name, 'apelido' => (string) $first($person, ['apelido', 'nickname'], ''), 'sexo' => in_array($first($person, ['sexo', 'sex'], 'Desconhecido'), ['M', 'F', 'Outro', 'Desconhecido'], true) ? $first($person, ['sexo', 'sex'], 'Desconhecido') : 'Desconhecido', 'nascimento' => $normalizarData($first($person, ['nascimento', 'birth', 'data_nascimento'])), 'localNascimento' => (string) $first($person, ['localNascimento', 'birthPlace', 'local_nascimento'], ''), 'falecimento' => $death, 'localFalecimento' => (string) $first($person, ['localFalecimento', 'deathPlace', 'local_falecimento'], ''), 'falecido' => $death ? 1 : (!empty($person['falecido']) || !empty($person['deceased']) ? 1 : 0), 'biografia' => (string) $first($person, ['biografia', 'bio', 'biography'], '')];
    }
    $relationships = $json['relacoes'] ?? $json['relationships'] ?? [];
    $parentRows = $relationships['parentais'] ?? $relationships['parents'] ?? $json['parents'] ?? [];
    foreach (is_array($parentRows) ? $parentRows : [] as $relation) if (is_array($relation)) { $child = (string) $first($relation, ['filho', 'child', 'filho_id'], ''); $parent = (string) $first($relation, ['pai', 'parent', 'pai_mae_id'], ''); if (isset($pessoas[$child], $pessoas[$parent])) $relacoesPais[] = ['filho' => $child, 'pai' => $parent, 'tipo' => in_array($first($relation, ['tipo', 'type'], 'biologico'), ['biologico', 'adotivo', 'padrasto_madrasta'], true) ? $first($relation, ['tipo', 'type'], 'biologico') : 'biologico']; }
    $unionRows = $relationships['unioes'] ?? $relationships['unions'] ?? $json['unions'] ?? [];
    foreach (is_array($unionRows) ? $unionRows : [] as $relation) if (is_array($relation)) { $a = (string) $first($relation, ['pessoa1', 'person1', 'pessoa1_id'], ''); $b = (string) $first($relation, ['pessoa2', 'person2', 'pessoa2_id'], ''); if (isset($pessoas[$a], $pessoas[$b])) $unioes[] = ['a' => $a, 'b' => $b, 'tipo' => in_array($first($relation, ['tipo', 'type'], 'casamento'), ['casamento', 'uniao_estavel', 'namoro', 'outro'], true) ? $first($relation, ['tipo', 'type'], 'casamento') : 'casamento', 'inicio' => $normalizarData($first($relation, ['inicio', 'start', 'data_inicio'])), 'fim' => $normalizarData($first($relation, ['fim', 'end', 'data_fim'])), 'status' => in_array($first($relation, ['status'], 'ativo'), ['ativo', 'divorciado', 'viuvo', 'encerrado'], true) ? $first($relation, ['status'], 'ativo') : 'ativo']; }
}

if (!$pessoas) exit('Nenhuma pessoa válida foi encontrada no arquivo.');
$pdo = getConexao();
$importId = null; $criados = 0; $atualizados = 0; $relacoesCriadas = 0; $unioesCriadas = 0;
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO importacoes (familia_id, usuario_id, tipo, arquivo_original, status) VALUES (?, ?, ?, ?, "em_andamento")');
    $stmt->execute([familiaAtualId(), usuarioAtualId(), $tipo, $nomeArquivo]); $importId = (int) $pdo->lastInsertId();
    $map = [];
    $find = $pdo->prepare('SELECT * FROM pessoas WHERE familia_id = ? AND LOWER(nome_completo) = LOWER(?)');
    foreach ($pessoas as $key => $person) {
        $find->execute([familiaAtualId(), $person['nome']]); $candidate = null;
        foreach ($find->fetchAll() as $row) if ($person['nascimento'] && $row['data_nascimento'] === $person['nascimento']) { $candidate = $row; break; }
        if ($candidate) {
            $id = (int) $candidate['id']; $updates = [];
            foreach (['sexo', 'data_nascimento', 'local_nascimento', 'data_falecimento', 'local_falecimento', 'falecido', 'biografia'] as $field) { $dbField = $field; $value = $field === 'data_nascimento' ? $person['nascimento'] : ($field === 'data_falecimento' ? $person['falecimento'] : ($field === 'local_nascimento' ? $person['localNascimento'] : ($field === 'local_falecimento' ? $person['localFalecimento'] : $person[$field]))); if (($candidate[$dbField] === null || $candidate[$dbField] === '' || ($field === 'falecido' && !$candidate[$dbField])) && $value !== null && $value !== '') $updates[$dbField] = $value; }
            if ($updates) { $set = implode(', ', array_map(static fn($field) => "$field = ?", array_keys($updates))); $values = array_values($updates); $values[] = $id; $values[] = familiaAtualId(); $pdo->prepare("UPDATE pessoas SET $set WHERE id = ? AND familia_id = ?")->execute($values); $atualizados++; }
        } else {
            $stmtPerson = $pdo->prepare('INSERT INTO pessoas (familia_id, nome_completo, apelido, sexo, data_nascimento, local_nascimento, data_falecimento, local_falecimento, falecido, biografia, origem, importacao_id, criado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmtPerson->execute([familiaAtualId(), $person['nome'], $person['apelido'] ?: null, $person['sexo'], $person['nascimento'], $person['localNascimento'] ?: null, $person['falecimento'], $person['localFalecimento'] ?: null, $person['falecido'], $person['biografia'] ?: null, 'gedcom', $importId, usuarioAtualId()]); $id = (int) $pdo->lastInsertId(); $criados++;
        }
        $map[(string) $key] = $id;
    }
    $parentStmt = $pdo->prepare('INSERT IGNORE INTO relacoes_parentais (filho_id, pai_mae_id, tipo, importacao_id) VALUES (?, ?, ?, ?)');
    foreach ($relacoesPais as $relation) if (isset($map[$relation['filho']], $map[$relation['pai']]) && $map[$relation['filho']] !== $map[$relation['pai']]) { $parentStmt->execute([$map[$relation['filho']], $map[$relation['pai']], $relation['tipo'], $importId]); $relacoesCriadas += $parentStmt->rowCount(); }
    $unionStmt = $pdo->prepare('INSERT INTO unioes (pessoa1_id, pessoa2_id, tipo, data_inicio, data_fim, status, importacao_id) SELECT ?, ?, ?, ?, ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM unioes WHERE ((pessoa1_id = ? AND pessoa2_id = ?) OR (pessoa1_id = ? AND pessoa2_id = ?)))');
    foreach ($unioes as $union) if (isset($map[$union['a']], $map[$union['b']]) && $map[$union['a']] !== $map[$union['b']]) { $a = $map[$union['a']]; $b = $map[$union['b']]; $unionStmt->execute([$a, $b, $union['tipo'], $union['inicio'] ?? null, $union['fim'] ?? null, $union['status'] ?? 'ativo', $importId, $a, $b, $b, $a]); $unioesCriadas += $unionStmt->rowCount(); }
    $pdo->prepare('UPDATE importacoes SET status="concluida", pessoas_criadas=?, pessoas_atualizadas=?, relacoes_criadas=?, unioes_criadas=?, finalizado_em=NOW() WHERE id=?')->execute([$criados, $atualizados, $relacoesCriadas, $unioesCriadas, $importId]);
    $pdo->commit();
    registrarAuditoria('importacao', $importId, 'conclusao', ['tipo' => $tipo, 'arquivo' => $nomeArquivo, 'pessoas_criadas' => $criados, 'relacoes' => $relacoesCriadas, 'unioes' => $unioesCriadas]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(422);
    exit('Importação revertida: ' . htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Importação concluída</title><link rel="icon" href="favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="css/style.css?v=explorer-2"></head><body><header class="topo"><a class="brand" href="index.php">Árvore Familiar</a><nav><a href="arvore.php">Árvore</a><a href="logout.php">Sair</a></nav></header><main class="container"><section class="card"><span class="eyebrow">Operação concluída</span><h1>Dados importados com segurança</h1><p class="lead">O arquivo <?= htmlspecialchars($nomeArquivo) ?> foi incorporado ao espaço familiar atual.</p><div class="stats-grid"><div class="card stat-card"><span class="stat-label">Pessoas criadas</span><strong class="stat-value"><?= $criados ?></strong></div><div class="card stat-card"><span class="stat-label">Pessoas atualizadas</span><strong class="stat-value"><?= $atualizados ?></strong></div><div class="card stat-card"><span class="stat-label">Relações</span><strong class="stat-value"><?= $relacoesCriadas ?></strong></div><div class="card stat-card"><span class="stat-label">Uniões</span><strong class="stat-value"><?= $unioesCriadas ?></strong></div></div><div class="form-actions"><a class="btn" href="arvore.php">Abrir árvore</a><a class="btn btn-secundario" href="importar.php">Importar outro arquivo</a></div></section></main></body></html>
