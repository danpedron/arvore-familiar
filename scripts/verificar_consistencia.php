#!/usr/bin/env php
<?php
/**
 * Verificador de consistência do banco — Árvore Familiar
 *
 * Uso:
 *   php scripts/verificar_consistencia.php
 *
 * Procura especificamente pelo tipo de problema que pode travar a
 * visualização da árvore (ciclos de ascendência, auto-relações, uniões
 * contraditórias) e também outras inconsistências de dados, mesmo que não
 * causem travamento. Não corrige nada sozinho — só relata, pra você decidir
 * o que fazer em cada caso (o próprio relatório já sugere os comandos SQL
 * ou a tela do sistema pra corrigir).
 */

require __DIR__ . '/../config/database.php';

function titulo(string $t): void { echo "\n=== {$t} ===\n"; }
function critico(string $t): void { echo "🔴 {$t}\n"; }
function atencao(string $t): void { echo "🟡 {$t}\n"; }
function info(string $t): void { echo "ℹ️  {$t}\n"; }
function ok(string $t): void { echo "✅ {$t}\n"; }

$pdo = getConexao();
$totalCriticos = 0;
$totalAtencao = 0;

function nomePessoa(PDO $pdo, int $id): string {
    static $cache = [];
    if (!isset($cache[$id])) {
        $stmt = $pdo->prepare('SELECT nome_completo FROM pessoas WHERE id = ?');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        $cache[$id] = $r ? $r['nome_completo'] : "(id {$id} não encontrado)";
    }
    return $cache[$id] . " (id {$id})";
}

echo "=== Verificador de consistência — Árvore Familiar ===\n";
$totalPessoas = (int) $pdo->query('SELECT COUNT(*) FROM pessoas')->fetchColumn();
$totalRelacoes = (int) $pdo->query('SELECT COUNT(*) FROM relacoes_parentais')->fetchColumn();
$totalUnioes = (int) $pdo->query('SELECT COUNT(*) FROM unioes')->fetchColumn();
echo "Pessoas: {$totalPessoas}  |  Relações pai/filho: {$totalRelacoes}  |  Uniões: {$totalUnioes}\n";

// --- A) Pessoa sendo pai/mãe de si mesma ---
titulo('A) Pessoa listada como pai/mãe de si mesma');
$stmt = $pdo->query('SELECT filho_id FROM relacoes_parentais WHERE filho_id = pai_mae_id');
$linhas = $stmt->fetchAll();
if (empty($linhas)) {
    ok('Nenhum caso encontrado.');
} else {
    foreach ($linhas as $l) {
        critico('Auto-relação: ' . nomePessoa($pdo, $l['filho_id']));
        echo "   Corrigir: DELETE FROM relacoes_parentais WHERE filho_id = {$l['filho_id']} AND pai_mae_id = {$l['filho_id']};\n";
        $totalCriticos++;
    }
}

// --- B) Ciclos de ascendência (A é ancestral de B e B é ancestral de A) ---
titulo('B) Ciclos de ascendência (causa mais provável de travamento na árvore)');
$paisDe = []; // filho_id => [pai_mae_id, ...]
foreach ($pdo->query('SELECT filho_id, pai_mae_id FROM relacoes_parentais') as $r) {
    $paisDe[$r['filho_id']][] = $r['pai_mae_id'];
}

// DFS com detecção de ciclo (cores: 0=não visitado, 1=em processamento, 2=concluído)
$cor = [];
$cicloEncontrado = [];

function detectarCiclo($no, &$paisDe, &$cor, &$caminho, &$ciclos) {
    $cor[$no] = 1;
    $caminho[] = $no;
    foreach ($paisDe[$no] ?? [] as $pai) {
        if (($cor[$pai] ?? 0) === 1) {
            // Achou ciclo: extrai o trecho do caminho a partir da primeira ocorrência de $pai
            $inicio = array_search($pai, $caminho);
            $ciclos[] = array_slice($caminho, $inicio);
        } elseif (($cor[$pai] ?? 0) === 0) {
            detectarCiclo($pai, $paisDe, $cor, $caminho, $ciclos);
        }
    }
    array_pop($caminho);
    $cor[$no] = 2;
}

$todosIds = array_unique(array_merge(array_keys($paisDe), ...array_values($paisDe ?: [])));
foreach ($todosIds as $id) {
    if (($cor[$id] ?? 0) === 0) {
        $caminho = [];
        detectarCiclo($id, $paisDe, $cor, $caminho, $cicloEncontrado);
    }
}

if (empty($cicloEncontrado)) {
    ok('Nenhum ciclo de ascendência encontrado.');
} else {
    // Remove ciclos duplicados (mesmo conjunto de pessoas detectado a partir de pontos de partida diferentes)
    $vistos = [];
    foreach ($cicloEncontrado as $ciclo) {
        $chave = implode(',', $ciclo);
        sort($ciclo);
        $chaveOrdenada = implode(',', $ciclo);
        if (isset($vistos[$chaveOrdenada])) continue;
        $vistos[$chaveOrdenada] = true;

        $nomes = array_map(fn($id) => nomePessoa($pdo, $id), $ciclo);
        critico('Ciclo encontrado envolvendo: ' . implode(' → ', $nomes));
        echo "   Isso significa que, seguindo a cadeia de pais, uma dessas pessoas acaba sendo ancestral dela mesma.\n";
        echo "   Revise cada uma na tela de perfil (seção \"Pais\") e remova o vínculo que estiver errado.\n";
        $totalCriticos++;
    }
}

// --- C) Pessoa sendo cônjuge de si mesma ---
titulo('C) Pessoa listada como cônjuge de si mesma');
$stmt = $pdo->query('SELECT id, pessoa1_id FROM unioes WHERE pessoa1_id = pessoa2_id');
$linhas = $stmt->fetchAll();
if (empty($linhas)) {
    ok('Nenhum caso encontrado.');
} else {
    foreach ($linhas as $l) {
        critico('Auto-união: ' . nomePessoa($pdo, $l['pessoa1_id']));
        echo "   Corrigir: DELETE FROM unioes WHERE id = {$l['id']};\n";
        $totalCriticos++;
    }
}

// --- D) Uniões duplicadas entre o mesmo par ---
titulo('D) Uniões duplicadas entre a mesma dupla de pessoas');
$stmt = $pdo->query(
    'SELECT LEAST(pessoa1_id, pessoa2_id) AS a, GREATEST(pessoa1_id, pessoa2_id) AS b, COUNT(*) AS total, GROUP_CONCAT(id) AS ids
     FROM unioes GROUP BY a, b HAVING total > 1'
);
$linhas = $stmt->fetchAll();
if (empty($linhas)) {
    ok('Nenhuma união duplicada encontrada.');
} else {
    foreach ($linhas as $l) {
        atencao('União repetida ' . $l['total'] . 'x entre ' . nomePessoa($pdo, $l['a']) . ' e ' . nomePessoa($pdo, $l['b']) . ' (ids: ' . $l['ids'] . ')');
        echo "   Mantenha só uma (a mais completa) e apague as outras pela tela de perfil.\n";
        $totalAtencao++;
    }
}

// --- E) Relações pai/filho duplicadas (não deveria ser possível pela UNIQUE KEY, mas confere) ---
titulo('E) Relações pai/filho duplicadas');
$stmt = $pdo->query(
    'SELECT filho_id, pai_mae_id, COUNT(*) AS total FROM relacoes_parentais GROUP BY filho_id, pai_mae_id HAVING total > 1'
);
$linhas = $stmt->fetchAll();
if (empty($linhas)) {
    ok('Nenhuma duplicata encontrada.');
} else {
    foreach ($linhas as $l) {
        atencao('Relação duplicada: ' . nomePessoa($pdo, $l['filho_id']) . ' → ' . nomePessoa($pdo, $l['pai_mae_id']));
        $totalAtencao++;
    }
}

// --- F) Pessoas com mais de 2 pais biológicos ---
titulo('F) Pessoas com mais de 2 pais/mães do tipo "biológico"');
$stmt = $pdo->query(
    "SELECT filho_id, COUNT(*) AS total FROM relacoes_parentais WHERE tipo = 'biologico' GROUP BY filho_id HAVING total > 2"
);
$linhas = $stmt->fetchAll();
if (empty($linhas)) {
    ok('Nenhum caso encontrado.');
} else {
    foreach ($linhas as $l) {
        atencao(nomePessoa($pdo, $l['filho_id']) . ' tem ' . $l['total'] . ' pais/mães biológicos cadastrados (o normal são 2).');
        echo "   Pode ser um vínculo errado, ou um caso legítimo (ex: um deveria ser marcado como adotivo/padrasto). Confira na tela de perfil.\n";
        $totalAtencao++;
    }
}

// --- G) Referências quebradas (segurança extra, não deveria acontecer com as FKs) ---
titulo('G) Referências quebradas (pessoa, união ou importação que não existe mais)');
$problemas = [];
foreach ($pdo->query('SELECT filho_id FROM relacoes_parentais WHERE filho_id NOT IN (SELECT id FROM pessoas)') as $r) {
    $problemas[] = "relacoes_parentais.filho_id={$r['filho_id']} não existe em pessoas";
}
foreach ($pdo->query('SELECT pai_mae_id FROM relacoes_parentais WHERE pai_mae_id NOT IN (SELECT id FROM pessoas)') as $r) {
    $problemas[] = "relacoes_parentais.pai_mae_id={$r['pai_mae_id']} não existe em pessoas";
}
foreach ($pdo->query('SELECT id, pessoa1_id FROM unioes WHERE pessoa1_id NOT IN (SELECT id FROM pessoas)') as $r) {
    $problemas[] = "unioes.pessoa1_id={$r['pessoa1_id']} (união id {$r['id']}) não existe em pessoas";
}
foreach ($pdo->query('SELECT id, pessoa2_id FROM unioes WHERE pessoa2_id NOT IN (SELECT id FROM pessoas)') as $r) {
    $problemas[] = "unioes.pessoa2_id={$r['pessoa2_id']} (união id {$r['id']}) não existe em pessoas";
}
if (empty($problemas)) {
    ok('Nenhuma referência quebrada encontrada.');
} else {
    foreach ($problemas as $p) {
        critico($p);
        $totalCriticos++;
    }
}

// --- H) Cônjuges que também são ancestrais/descendentes um do outro ---
titulo('H) Uniões entre pessoas que também são ancestral/descendente uma da outra');
function ehAncestral(int $possivelAncestral, int $pessoa, array &$paisDe, int $profundidadeMax = 15): bool {
    $visitados = [];
    $fila = [$pessoa];
    $profundidade = 0;
    while (!empty($fila) && $profundidade < $profundidadeMax) {
        $proximaFila = [];
        foreach ($fila as $atual) {
            foreach ($paisDe[$atual] ?? [] as $pai) {
                if ($pai === $possivelAncestral) return true;
                if (!isset($visitados[$pai])) {
                    $visitados[$pai] = true;
                    $proximaFila[] = $pai;
                }
            }
        }
        $fila = $proximaFila;
        $profundidade++;
    }
    return false;
}

$stmt = $pdo->query('SELECT id, pessoa1_id, pessoa2_id FROM unioes');
$encontrados = 0;
foreach ($stmt->fetchAll() as $u) {
    if (ehAncestral((int) $u['pessoa1_id'], (int) $u['pessoa2_id'], $paisDe) || ehAncestral((int) $u['pessoa2_id'], (int) $u['pessoa1_id'], $paisDe)) {
        atencao('União (id ' . $u['id'] . ') entre ' . nomePessoa($pdo, $u['pessoa1_id']) . ' e ' . nomePessoa($pdo, $u['pessoa2_id']) . ' — uma é ascendente da outra.');
        $totalAtencao++;
        $encontrados++;
    }
}
if ($encontrados === 0) ok('Nenhum caso encontrado.');

// --- I) Possíveis duplicatas (mesmo nome + mesma data de nascimento) ---
titulo('I) Possíveis pessoas duplicadas (mesmo nome completo e data de nascimento)');
$stmt = $pdo->query(
    "SELECT nome_completo, data_nascimento, COUNT(*) AS total, GROUP_CONCAT(id) AS ids
     FROM pessoas WHERE data_nascimento IS NOT NULL
     GROUP BY LOWER(nome_completo), data_nascimento HAVING total > 1"
);
$linhas = $stmt->fetchAll();
if (empty($linhas)) {
    ok('Nenhuma duplicata óbvia encontrada.');
} else {
    foreach ($linhas as $l) {
        atencao("Possível duplicata: \"{$l['nome_completo']}\" (nascimento {$l['data_nascimento']}) aparece {$l['total']}x — ids: {$l['ids']}");
        $totalAtencao++;
    }
}

// --- J) Pessoas com número incomum de vínculos (possível erro de importação) ---
titulo('J) Pessoas com número incomum de vínculos (pode indicar erro de importação)');
$limiteFilhos = 15;
$limiteConjuges = 5;
$stmt = $pdo->query('SELECT pai_mae_id, COUNT(*) AS total FROM relacoes_parentais GROUP BY pai_mae_id HAVING total > ' . $limiteFilhos);
$algo = false;
foreach ($stmt->fetchAll() as $l) {
    info(nomePessoa($pdo, $l['pai_mae_id']) . " tem {$l['total']} filhos cadastrados (acima do usual).");
    $algo = true;
}
$stmt = $pdo->query('SELECT pessoa1_id AS pid, COUNT(*) AS total FROM unioes GROUP BY pessoa1_id
                      UNION ALL SELECT pessoa2_id AS pid, COUNT(*) AS total FROM unioes GROUP BY pessoa2_id');
$contagem = [];
foreach ($stmt->fetchAll() as $l) { $contagem[$l['pid']] = ($contagem[$l['pid']] ?? 0) + $l['total']; }
foreach ($contagem as $pid => $total) {
    if ($total > $limiteConjuges) {
        info(nomePessoa($pdo, $pid) . " tem {$total} uniões cadastradas (acima do usual).");
        $algo = true;
    }
}
if (!$algo) ok('Nada fora do comum.');

// --- Resumo final ---
titulo('Resumo');
echo "Problemas críticos (podem causar travamento):  {$totalCriticos}\n";
echo "Pontos de atenção (revisar quando possível):    {$totalAtencao}\n";

if ($totalCriticos > 0) {
    echo "\n⚠️  Recomendo corrigir os itens 🔴 antes de tentar abrir a árvore de novo — são os candidatos mais prováveis pro travamento.\n";
    exit(1);
}
echo "\nNenhum problema crítico encontrado. Se a árvore ainda travar, o problema provavelmente não é de dados — pode ser volume mesmo (muitas pessoas de uma vez).\n";
exit(0);
