<?php
require_once __DIR__ . '/../config/database.php';

function listarPessoas(string $busca = ''): array {
    $pdo = getConexao();
    if ($busca !== '') {
        $stmt = $pdo->prepare('SELECT * FROM pessoas WHERE nome_completo LIKE ? ORDER BY nome_completo');
        $stmt->execute(['%' . $busca . '%']);
    } else {
        $stmt = $pdo->query('SELECT * FROM pessoas ORDER BY nome_completo');
    }
    return $stmt->fetchAll();
}

function buscarPessoa(int $id): ?array {
    $pdo = getConexao();
    $stmt = $pdo->prepare('SELECT * FROM pessoas WHERE id = ?');
    $stmt->execute([$id]);
    $pessoa = $stmt->fetch();
    return $pessoa ?: null;
}

function salvarPessoa(array $dados, ?int $id = null): int {
    $pdo = getConexao();

    $campos = [
        'nome_completo' => $dados['nome_completo'],
        'apelido' => $dados['apelido'] ?: null,
        'sexo' => $dados['sexo'],
        'data_nascimento' => $dados['data_nascimento'] ?: null,
        'local_nascimento' => $dados['local_nascimento'] ?: null,
        'data_falecimento' => $dados['data_falecimento'] ?: null,
        'local_falecimento' => $dados['local_falecimento'] ?: null,
        'falecido' => !empty($dados['falecido']) ? 1 : 0,
        'biografia' => $dados['biografia'] ?: null,
    ];

    if ($id) {
        $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($campos)));
        $stmt = $pdo->prepare("UPDATE pessoas SET $set WHERE id = :id");
        $campos['id'] = $id;
        $stmt->execute($campos);
        return $id;
    }

    $campos['criado_por'] = usuarioAtualId();
    $colunas = implode(', ', array_keys($campos));
    $marcadores = ':' . implode(', :', array_keys($campos));
    $stmt = $pdo->prepare("INSERT INTO pessoas ($colunas) VALUES ($marcadores)");
    $stmt->execute($campos);
    return (int) $pdo->lastInsertId();
}

function excluirPessoa(int $id): void {
    $pdo = getConexao();
    $stmt = $pdo->prepare('DELETE FROM pessoas WHERE id = ?');
    $stmt->execute([$id]);
}

function atualizarFotoPerfil(int $pessoaId, string $caminho): void {
    $pdo = getConexao();
    $stmt = $pdo->prepare('UPDATE pessoas SET foto_perfil = ? WHERE id = ?');
    $stmt->execute([$caminho, $pessoaId]);
}

// --- Relações de parentesco ---

function adicionarPaiMae(int $filhoId, int $paiMaeId, string $tipo = 'biologico'): void {
    if ($filhoId === $paiMaeId) return;
    $pdo = getConexao();
    $stmt = $pdo->prepare('INSERT IGNORE INTO relacoes_parentais (filho_id, pai_mae_id, tipo) VALUES (?, ?, ?)');
    $stmt->execute([$filhoId, $paiMaeId, $tipo]);
}

function removerPaiMae(int $filhoId, int $paiMaeId): void {
    $pdo = getConexao();
    $stmt = $pdo->prepare('DELETE FROM relacoes_parentais WHERE filho_id = ? AND pai_mae_id = ?');
    $stmt->execute([$filhoId, $paiMaeId]);
}

function listarPais(int $pessoaId): array {
    $pdo = getConexao();
    $stmt = $pdo->prepare(
        'SELECT p.*, rp.tipo FROM pessoas p
         JOIN relacoes_parentais rp ON rp.pai_mae_id = p.id
         WHERE rp.filho_id = ?'
    );
    $stmt->execute([$pessoaId]);
    return $stmt->fetchAll();
}

function listarFilhos(int $pessoaId): array {
    $pdo = getConexao();
    $stmt = $pdo->prepare(
        'SELECT p.*, rp.tipo FROM pessoas p
         JOIN relacoes_parentais rp ON rp.filho_id = p.id
         WHERE rp.pai_mae_id = ?'
    );
    $stmt->execute([$pessoaId]);
    return $stmt->fetchAll();
}

// --- Uniões / cônjuges ---

function adicionarUniao(int $pessoa1Id, int $pessoa2Id, string $tipo = 'casamento', ?string $dataInicio = null): void {
    if ($pessoa1Id === $pessoa2Id) return;
    $pdo = getConexao();
    $stmt = $pdo->prepare('INSERT INTO unioes (pessoa1_id, pessoa2_id, tipo, data_inicio) VALUES (?, ?, ?, ?)');
    $stmt->execute([$pessoa1Id, $pessoa2Id, $tipo, $dataInicio ?: null]);
}

function listarConjuges(int $pessoaId): array {
    $pdo = getConexao();
    $stmt = $pdo->prepare(
        'SELECT p.*, u.tipo, u.status, u.data_inicio, u.data_fim, u.id AS uniao_id
         FROM pessoas p
         JOIN unioes u ON (u.pessoa1_id = p.id OR u.pessoa2_id = p.id)
         WHERE (u.pessoa1_id = ? OR u.pessoa2_id = ?) AND p.id != ?'
    );
    $stmt->execute([$pessoaId, $pessoaId, $pessoaId]);
    return $stmt->fetchAll();
}

function removerUniao(int $uniaoId): void {
    $pdo = getConexao();
    $stmt = $pdo->prepare('DELETE FROM unioes WHERE id = ?');
    $stmt->execute([$uniaoId]);
}

// --- Nomes adicionais (nome de casada, religioso etc.) ---

function adicionarNomeAdicional(int $pessoaId, string $nome, string $tipo = 'casamento', ?int $uniaoId = null, string $observacao = ''): void {
    $pdo = getConexao();
    $stmt = $pdo->prepare('INSERT INTO nomes_pessoa (pessoa_id, nome, tipo, uniao_id, observacao) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$pessoaId, $nome, $tipo, $uniaoId ?: null, $observacao ?: null]);
}

function listarNomesAdicionais(int $pessoaId): array {
    $pdo = getConexao();
    $stmt = $pdo->prepare('SELECT * FROM nomes_pessoa WHERE pessoa_id = ? ORDER BY criado_em');
    $stmt->execute([$pessoaId]);
    return $stmt->fetchAll();
}

function removerNomeAdicional(int $nomeId): void {
    $pdo = getConexao();
    $stmt = $pdo->prepare('DELETE FROM nomes_pessoa WHERE id = ?');
    $stmt->execute([$nomeId]);
}

// --- Mídias (fotos e documentos) ---

function adicionarMidia(int $pessoaId, string $tipo, string $caminho, string $titulo = ''): void {
    $pdo = getConexao();
    $stmt = $pdo->prepare('INSERT INTO midias (pessoa_id, tipo, caminho_arquivo, titulo, enviado_por) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$pessoaId, $tipo, $caminho, $titulo ?: null, usuarioAtualId()]);
}

function listarMidias(int $pessoaId): array {
    $pdo = getConexao();
    $stmt = $pdo->prepare('SELECT * FROM midias WHERE pessoa_id = ? ORDER BY criado_em DESC');
    $stmt->execute([$pessoaId]);
    return $stmt->fetchAll();
}

function excluirMidia(int $midiaId): void {
    $pdo = getConexao();
    $stmt = $pdo->prepare('SELECT caminho_arquivo FROM midias WHERE id = ?');
    $stmt->execute([$midiaId]);
    $midia = $stmt->fetch();
    if ($midia && file_exists(__DIR__ . '/../public/' . $midia['caminho_arquivo'])) {
        unlink(__DIR__ . '/../public/' . $midia['caminho_arquivo']);
    }
    $stmt = $pdo->prepare('DELETE FROM midias WHERE id = ?');
    $stmt->execute([$midiaId]);
}

function idade(?string $nascimento, ?string $falecimento = null): ?int {
    if (!$nascimento) return null;
    $inicio = new DateTime($nascimento);
    $fim = $falecimento ? new DateTime($falecimento) : new DateTime();
    return $inicio->diff($fim)->y;
}
