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
    echo json_encode(['nodes' => [], 'familias' => [], 'unioes' => [], 'niveisLargura' => []]);
    exit;
}

// --- Mapas de apoio ---
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

$todosIds = array_map(fn($p) => (int) $p['id'], $pessoas);

// --- 1) Atribuir geração (nível) via BFS multi-fonte a partir de quem não tem pais cadastrados ---
$temPais = array_unique(array_map('intval', array_column($relacoes, 'filho_id')));
$raizes = array_values(array_diff($todosIds, $temPais));
if (empty($raizes)) {
    $raizes = [$todosIds[0]];
}

$nivel = [];
$fila = [];
foreach ($raizes as $r) {
    $nivel[$r] = 0;
    $fila[] = $r;
}
while (!empty($fila)) {
    $atual = array_shift($fila);
    $nivelAtual = $nivel[$atual];

    foreach ($conjugesDe[$atual] ?? [] as $c) {
        if (!isset($nivel[$c])) {
            $nivel[$c] = $nivelAtual;
            $fila[] = $c;
        }
    }
    foreach ($filhosDe[$atual] ?? [] as $f) {
        if (!isset($nivel[$f]) || $nivel[$f] < $nivelAtual + 1) {
            $nivel[$f] = $nivelAtual + 1;
            $fila[] = $f;
        }
    }
    foreach ($paisDe[$atual] ?? [] as $p) {
        if (!isset($nivel[$p])) {
            $nivel[$p] = $nivelAtual - 1;
            $fila[] = $p;
        }
    }
}
foreach ($todosIds as $id) {
    if (!isset($nivel[$id])) {
        $nivel[$id] = 0;
    }
}

// Normaliza para começar em 0 (caso tenha ficado negativo por causa de raízes "descobertas depois")
$menorNivel = min($nivel);
if ($menorNivel < 0) {
    foreach ($nivel as $pid => $nv) {
        $nivel[$pid] = $nv - $menorNivel;
    }
}

// --- 2) Layout: dentro de cada nível, agrupar casais e ordenar por "baricentro" dos pais ---
$colunaPx = 190; // largura de cada "slot" (caixa + espaçamento)
$linhaPx = 200;  // altura entre gerações

$porNivel = [];
foreach ($todosIds as $pid) {
    $porNivel[$nivel[$pid]][] = $pid;
}
ksort($porNivel);

$xPos = [];
$niveisLargura = [];

foreach ($porNivel as $nv => $idsNivel) {
    sort($idsNivel);
    $usados = [];
    $unidades = [];

    foreach ($idsNivel as $pid) {
        if (in_array($pid, $usados, true)) continue;
        $parceiro = null;
        foreach ($conjugesDe[$pid] ?? [] as $c) {
            if (($nivel[$c] ?? null) === $nv && !in_array($c, $usados, true) && in_array($c, $idsNivel, true)) {
                $parceiro = $c;
                break;
            }
        }
        if ($parceiro !== null) {
            $unidades[] = ['membros' => [$pid, $parceiro]];
            $usados[] = $pid;
            $usados[] = $parceiro;
        } else {
            $unidades[] = ['membros' => [$pid]];
            $usados[] = $pid;
        }
    }

    // Ordena pela posição média dos pais já calculada no nível anterior (baricentro)
    foreach ($unidades as &$u) {
        $somaX = 0;
        $qtd = 0;
        foreach ($u['membros'] as $m) {
            foreach ($paisDe[$m] ?? [] as $pai) {
                if (isset($xPos[$pai])) {
                    $somaX += $xPos[$pai];
                    $qtd++;
                }
            }
        }
        $u['bary'] = $qtd > 0 ? ($somaX / $qtd) : PHP_INT_MAX;
    }
    unset($u);

    usort($unidades, function ($a, $b) {
        if ($a['bary'] === $b['bary']) return $a['membros'][0] <=> $b['membros'][0];
        return $a['bary'] <=> $b['bary'];
    });

    $cursor = 0;
    foreach ($unidades as $u) {
        foreach ($u['membros'] as $i => $m) {
            $xPos[$m] = ($cursor + $i) * $colunaPx;
        }
        $cursor += count($u['membros']);
    }
    $niveisLargura[$nv] = $cursor * $colunaPx;
}

// --- 3) Monta grupos familiares (mesmo conjunto de pais => mesmo "barramento" de conexão) ---
$familiasPorChave = [];
foreach ($paisDe as $filhoId => $listaPais) {
    $chave = implode('-', $listaPais);
    if (!isset($familiasPorChave[$chave])) {
        $familiasPorChave[$chave] = ['pais' => $listaPais, 'filhos' => []];
    }
    $familiasPorChave[$chave]['filhos'][] = (int) $filhoId;
}
$familias = array_values($familiasPorChave);

// --- 4) Monta nós de saída ---
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
        'x' => $xPos[$id],
        'y' => $nivel[$id] * $linhaPx,
    ];
}

$listaUnioes = [];
foreach ($unioes as $u) {
    $listaUnioes[] = [
        'id' => (int) $u['id'],
        'pessoa1' => (int) $u['pessoa1_id'],
        'pessoa2' => (int) $u['pessoa2_id'],
        'tipo' => $u['tipo'],
        'status' => $u['status'],
    ];
}

echo json_encode([
    'nodes' => $nodes,
    'familias' => $familias,
    'unioes' => $listaUnioes,
    'niveisLargura' => $niveisLargura,
    'colunaPx' => $colunaPx,
    'linhaPx' => $linhaPx,
], JSON_UNESCAPED_UNICODE);
