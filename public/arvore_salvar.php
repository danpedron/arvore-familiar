<?php
/**
 * Recebe o estado atual da árvore depois de uma edição feita visualmente
 * (adicionar pessoa, editar nome/datas, desvincular parente) e sincroniza
 * com o banco. Nunca apaga pessoas — apenas cria/atualiza pessoas e
 * adiciona/remove relações (pai-filho e cônjuges), de forma aditiva e segura.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirLogin();
header('Content-Type: application/json; charset=utf-8');

$corpo = json_decode(file_get_contents('php://input'), true);
if (!is_array($corpo) || !isset($corpo['data']) || !is_array($corpo['data'])) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos.']);
    exit;
}

$pessoasRecebidas = $corpo['data'];
$pdo = getConexao();

try {
    $pdo->beginTransaction();

    // --- 1ª passada: garante que toda pessoa recebida existe no banco ---
    $mapaIdParaReal = []; // id recebido do family-chart (string) => id real no banco (int)

    foreach ($pessoasRecebidas as $pessoa) {
        if (!isset($pessoa['id'])) continue;
        $idRecebido = (string) $pessoa['id'];
        $dadosPessoa = $pessoa['data'] ?? [];

        $nome = trim($dadosPessoa['nome'] ?? '');
        if ($nome === '') continue; // placeholder ainda não preenchido — ignora

        $sexo = in_array($dadosPessoa['gender'] ?? null, ['M', 'F'], true) ? $dadosPessoa['gender'] : null;
        $nascimento = !empty($dadosPessoa['nascimento']) ? $dadosPessoa['nascimento'] : null;
        $falecimento = !empty($dadosPessoa['falecimento']) ? $dadosPessoa['falecimento'] : null;

        $existente = ctype_digit($idRecebido) ? buscarPessoa((int) $idRecebido) : null;

        if ($existente) {
            $campos = ['nome_completo' => $nome, 'data_nascimento' => $nascimento, 'data_falecimento' => $falecimento];
            if ($sexo !== null) $campos['sexo'] = $sexo;
            if ($falecimento) $campos['falecido'] = 1; // nunca desmarca "falecido" automaticamente, só marca
            atualizarCamposBasicos((int) $existente['id'], $campos);
            $mapaIdParaReal[$idRecebido] = (int) $existente['id'];
        } else {
            $novoId = criarPessoaBasica([
                'nome_completo' => $nome,
                'sexo' => $sexo ?? 'Desconhecido',
                'data_nascimento' => $nascimento,
                'data_falecimento' => $falecimento,
                'falecido' => $falecimento ? 1 : 0,
            ]);
            $mapaIdParaReal[$idRecebido] = $novoId;
        }
    }

    // --- 2ª passada: sincroniza relações (pais e cônjuges) ---
    // Filhos não são processados à parte: são só o inverso de "pais" do ponto de
    // vista da outra pessoa, e essa mesma relação já aparece nos dois sentidos
    // no array recebido (a biblioteca mantém isso sincronizado sozinha).
    foreach ($pessoasRecebidas as $pessoa) {
        if (!isset($pessoa['id'])) continue;
        $idRecebido = (string) $pessoa['id'];
        if (!isset($mapaIdParaReal[$idRecebido])) continue;
        $pessoaId = $mapaIdParaReal[$idRecebido];
        $rels = $pessoa['rels'] ?? [];

        // Pais
        $paisRecebidos = [];
        foreach (($rels['parents'] ?? []) as $pid) {
            if (isset($mapaIdParaReal[(string) $pid])) $paisRecebidos[] = $mapaIdParaReal[(string) $pid];
        }
        $paisAtuais = array_map('intval', array_column(listarPais($pessoaId), 'id'));

        foreach (array_diff($paisRecebidos, $paisAtuais) as $novoPaiId) {
            adicionarPaiMae($pessoaId, (int) $novoPaiId);
        }
        foreach (array_diff($paisAtuais, $paisRecebidos) as $paiRemovidoId) {
            removerPaiMae($pessoaId, (int) $paiRemovidoId);
        }

        // Cônjuges
        $conjugesRecebidos = [];
        foreach (($rels['spouses'] ?? []) as $cid) {
            if (isset($mapaIdParaReal[(string) $cid])) $conjugesRecebidos[] = $mapaIdParaReal[(string) $cid];
        }
        $conjugesAtuais = listarConjuges($pessoaId);
        $conjugesAtuaisIds = array_map('intval', array_column($conjugesAtuais, 'id'));

        foreach (array_diff($conjugesRecebidos, $conjugesAtuaisIds) as $novoConjugeId) {
            adicionarUniao($pessoaId, (int) $novoConjugeId);
        }
        foreach ($conjugesAtuais as $c) {
            if (!in_array((int) $c['id'], $conjugesRecebidos, true)) {
                removerUniao((int) $c['uniao_id']);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['sucesso' => true]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
