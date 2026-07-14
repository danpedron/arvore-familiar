<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirLogin();
header('Content-Type: application/json; charset=utf-8');

$pdo = getConexao();

$pessoas = $pdo->query('SELECT id, nome_completo, apelido, sexo, foto_perfil, data_nascimento, data_falecimento, falecido FROM pessoas')->fetchAll();
$relacoes = $pdo->query('SELECT filho_id, pai_mae_id FROM relacoes_parentais')->fetchAll();
$unioes = $pdo->query('SELECT pessoa1_id, pessoa2_id FROM unioes')->fetchAll();

// pai_mae_id => [filho_id, ...] e filho_id => [pai_mae_id, ...]
$paisDe = [];
$filhosDe = [];
foreach ($relacoes as $r) {
    $paisDe[$r['filho_id']][] = (string) $r['pai_mae_id'];
    $filhosDe[$r['pai_mae_id']][] = (string) $r['filho_id'];
}

// Cônjuges precisam aparecer nos dois sentidos (exigência do formato da biblioteca)
$conjugesDe = [];
foreach ($unioes as $u) {
    $conjugesDe[$u['pessoa1_id']][] = (string) $u['pessoa2_id'];
    $conjugesDe[$u['pessoa2_id']][] = (string) $u['pessoa1_id'];
}

function formatarDatas(array $p): string {
    $nascimento = $p['data_nascimento'] ? date('Y', strtotime($p['data_nascimento'])) : null;
    if ($p['falecido']) {
        $falecimento = $p['data_falecimento'] ? date('Y', strtotime($p['data_falecimento'])) : '?';
        $intervalo = $nascimento ? "{$nascimento} – {$falecimento}" : "† {$falecimento}";
        return "🕊️ {$intervalo}";
    }
    return $nascimento ? "n. {$nascimento}" : '';
}

$saida = [];
foreach ($pessoas as $p) {
    $id = (string) $p['id'];

    $dados = [
        'nome' => $p['nome_completo'],
        'datas' => formatarDatas($p),
        // Campos "crus" usados pelo formulário de edição (setFields) — o campo
        // 'datas' acima é só o texto exibido no card, não dá pra editar direto.
        'nascimento' => $p['data_nascimento'] ?? '',
        'falecimento' => $p['data_falecimento'] ?? '',
        'avatar' => caminhoFotoValido($p['foto_perfil']),
    ];
    // A biblioteca só reconhece 'M' ou 'F'; outros valores ficam sem gênero definido (estilo neutro)
    if ($p['sexo'] === 'M' || $p['sexo'] === 'F') {
        $dados['gender'] = $p['sexo'];
    }

    $saida[] = [
        'id' => $id,
        'data' => $dados,
        'rels' => [
            'parents' => $paisDe[$p['id']] ?? [],
            'spouses' => $conjugesDe[$p['id']] ?? [],
            'children' => $filhosDe[$p['id']] ?? [],
        ],
    ];
}

echo json_encode($saida, JSON_UNESCAPED_UNICODE);
