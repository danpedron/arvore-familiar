<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirLogin();
header('Content-Type: application/json; charset=utf-8');

$pdo = getConexao();

$pessoas = $pdo->query('SELECT id, nome_completo, apelido, sexo, foto_perfil, data_nascimento, data_falecimento, falecido FROM pessoas')->fetchAll();
$relacoes = $pdo->query('SELECT filho_id, pai_mae_id FROM relacoes_parentais')->fetchAll();
$unioes = $pdo->query('SELECT id, pessoa1_id, pessoa2_id, tipo, status FROM unioes')->fetchAll();

if (empty($pessoas)) {
    echo json_encode(['nodes' => [], 'links' => []]);
    exit;
}

// Monta mapas de apoio
$paisDe = [];   // filho_id => [pai_mae_id, ...]
$filhosDe = []; // pai_mae_id => [filho_id, ...]
foreach ($relacoes as $r) {
    $paisDe[$r['filho_id']][] = (int) $r['pai_mae_id'];
    $filhosDe[$r['pai_mae_id']][] = (int) $r['filho_id'];
}

$conjugesDe = []; // pessoa_id => [pessoa_id, ...]
foreach ($unioes as $u) {
    $conjugesDe[$u['pessoa1_id']][] = (int) $u['pessoa2_id'];
    $conjugesDe[$u['pessoa2_id']][] = (int) $u['pessoa1_id'];
}

// Define os "troncos" da árvore: pessoas sem pais cadastrados
$todosIds = array_map(fn($p) => (int) $p['id'], $pessoas);
$temPais = array_unique(array_map('intval', array_column($relacoes, 'filho_id')));
$raizes = array_values(array_diff($todosIds, $temPais));
if (empty($raizes)) {
    $raizes = [$todosIds[0]];
}

// BFS multi-fonte para atribuir gerações (nível 0 = raízes)
$nivel = [];
$fila = [];
foreach ($raizes as $r) {
    $nivel[$r] = 0;
    $fila[] = $r;
}

while (!empty($fila)) {
    $atual = array_shift($fila);
    $nivelAtual = $nivel[$atual];

    // Cônjuges ficam no mesmo nível
    foreach ($conjugesDe[$atual] ?? [] as $c) {
        if (!isset($nivel[$c])) {
            $nivel[$c] = $nivelAtual;
            $fila[] = $c;
        }
    }

    // Filhos ficam um nível abaixo
    foreach ($filhosDe[$atual] ?? [] as $f) {
        if (!isset($nivel[$f]) || $nivel[$f] < $nivelAtual + 1) {
            $nivel[$f] = $nivelAtual + 1;
            $fila[] = $f;
        }
    }

    // Pais ficam um nível acima (caso a BFS tenha começado por um filho)
    foreach ($paisDe[$atual] ?? [] as $p) {
        if (!isset($nivel[$p])) {
            $nivel[$p] = $nivelAtual - 1;
            $fila[] = $p;
        }
    }
}

// Qualquer pessoa isolada (sem nenhuma relação) recebe nível 0
foreach ($todosIds as $id) {
    if (!isset($nivel[$id])) {
        $nivel[$id] = 0;
    }
}

$nodes = [];
foreach ($pessoas as $p) {
    $id = (int) $p['id'];
    $nodes[] = [
        'id' => $id,
        'nome' => $p['nome_completo'],
        'apelido' => $p['apelido'],
        'sexo' => $p['sexo'],
        'foto' => $p['foto_perfil'],
        'falecido' => (bool) $p['falecido'],
        'nascimento' => $p['data_nascimento'],
        'falecimento' => $p['data_falecimento'],
        'nivel' => $nivel[$id],
    ];
}

$links = [];
foreach ($relacoes as $r) {
    $links[] = [
        'source' => (int) $r['pai_mae_id'],
        'target' => (int) $r['filho_id'],
        'tipo' => 'filiacao',
    ];
}
foreach ($unioes as $u) {
    $links[] = [
        'source' => (int) $u['pessoa1_id'],
        'target' => (int) $u['pessoa2_id'],
        'tipo' => 'uniao',
        'status' => $u['status'],
    ];
}

echo json_encode(['nodes' => $nodes, 'links' => $links], JSON_UNESCAPED_UNICODE);
