<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirFamilia();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

$pdo = getConexao();
$familiaId = familiaAtualId();

$stmt = $pdo->prepare(
    'SELECT id, nome_completo, apelido, sexo, foto_perfil, data_nascimento,
            data_falecimento, falecido, local_nascimento, local_falecimento,
            atualizado_em
     FROM pessoas
     WHERE familia_id = ?
     ORDER BY nome_completo'
);
$stmt->execute([$familiaId]);
$pessoas = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT rp.filho_id, rp.pai_mae_id, rp.tipo
     FROM relacoes_parentais rp
     JOIN pessoas filho ON filho.id = rp.filho_id
     JOIN pessoas pai ON pai.id = rp.pai_mae_id
     WHERE filho.familia_id = ? AND pai.familia_id = ?'
);
$stmt->execute([$familiaId, $familiaId]);
$relacoes = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT u.id, u.pessoa1_id, u.pessoa2_id, u.tipo, u.status, u.data_inicio, u.data_fim
     FROM unioes u
     JOIN pessoas p1 ON p1.id = u.pessoa1_id
     JOIN pessoas p2 ON p2.id = u.pessoa2_id
     WHERE p1.familia_id = ? AND p2.familia_id = ?'
);
$stmt->execute([$familiaId, $familiaId]);
$unioes = $stmt->fetchAll();

function ano(?string $data): ?string {
    return $data ? date('Y', strtotime($data)) : null;
}

function textoDatas(array $p): string {
    $n = ano($p['data_nascimento'] ?? null);
    $f = ano($p['data_falecimento'] ?? null);
    if (!empty($p['falecido'])) {
        return ($n ?: '?') . ' — ' . ($f ?: '?');
    }
    return $n ? 'n. ' . $n : 'datas não informadas';
}

$paisDe = [];
$filhosDe = [];
foreach ($relacoes as $r) {
    $filho = (string) $r['filho_id'];
    $pai = (string) $r['pai_mae_id'];
    $paisDe[$filho][] = $pai;
    $filhosDe[$pai][] = $filho;
}

$conjugesDe = [];
$unioesPorPessoa = [];
foreach ($unioes as $u) {
    $a = (string) $u['pessoa1_id'];
    $b = (string) $u['pessoa2_id'];
    $conjugesDe[$a][] = $b;
    $conjugesDe[$b][] = $a;
    $unioesPorPessoa[$a][] = $u;
    $unioesPorPessoa[$b][] = $u;
}

$saida = [];
foreach ($pessoas as $p) {
    $id = (string) $p['id'];
    $sexo = in_array($p['sexo'], ['M', 'F'], true) ? $p['sexo'] : null;
    $nome = trim($p['nome_completo']);
    $partes = preg_split('/\s+/', $nome);
    $primeiroNome = $partes[0] ?? $nome;
    $sobrenome = count($partes) > 1 ? implode(' ', array_slice($partes, -2)) : '';
    $saida[] = [
        'id' => $id,
        'data' => [
            'nome' => $nome,
            'nomeCurto' => $p['apelido'] ?: $primeiroNome,
            'sobrenome' => $sobrenome,
            'apelido' => $p['apelido'] ?: '',
            'sexo' => $p['sexo'],
            'gender' => $sexo,
            'nascimento' => $p['data_nascimento'] ?: '',
            'falecimento' => $p['data_falecimento'] ?: '',
            'datas' => textoDatas($p),
            'status' => !empty($p['falecido']) ? 'falecido' : 'vivo',
            'localNascimento' => $p['local_nascimento'] ?: '',
            'localFalecimento' => $p['local_falecimento'] ?: '',
            'avatar' => caminhoFotoValido($p['foto_perfil']),
            'atualizadoEm' => $p['atualizado_em'],
            'unioes' => array_map(static fn($u) => [
                'id' => (int) $u['id'],
                'tipo' => $u['tipo'],
                'status' => $u['status'],
                'inicio' => $u['data_inicio'],
                'fim' => $u['data_fim'],
            ], $unioesPorPessoa[$id] ?? []),
        ],
        'rels' => [
            'parents' => array_values(array_unique($paisDe[$id] ?? [])),
            'spouses' => array_values(array_unique($conjugesDe[$id] ?? [])),
            'children' => array_values(array_unique($filhosDe[$id] ?? [])),
        ],
    ];
}

$totais = [
    'pessoas' => count($pessoas),
    'vivas' => count(array_filter($pessoas, static fn($p) => empty($p['falecido']))),
    'falecidas' => count(array_filter($pessoas, static fn($p) => !empty($p['falecido']))),
    'relacoes' => count($relacoes),
    'unioes' => count($unioes),
];

$familia = $pdo->prepare('SELECT id, nome, slug, descricao FROM familias WHERE id = ?');
$familia->execute([$familiaId]);

// O endpoint mantém uma estrutura explícita de nós e relações para consumo
// pela camada SVG própria, sem dependências externas no navegador.
echo json_encode([
    'familia' => $familia->fetch() ?: ['id' => $familiaId, 'nome' => familiaAtualNome()],
    'totais' => $totais,
    'pessoas' => $saida,
    'geradoEm' => date(DATE_ATOM),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
