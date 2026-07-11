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
        'local_nascimento_lat' => ($dados['local_nascimento_lat'] ?? '') !== '' ? $dados['local_nascimento_lat'] : null,
        'local_nascimento_lng' => ($dados['local_nascimento_lng'] ?? '') !== '' ? $dados['local_nascimento_lng'] : null,
        'data_falecimento' => $dados['data_falecimento'] ?: null,
        'local_falecimento' => $dados['local_falecimento'] ?: null,
        'local_falecimento_lat' => ($dados['local_falecimento_lat'] ?? '') !== '' ? $dados['local_falecimento_lat'] : null,
        'local_falecimento_lng' => ($dados['local_falecimento_lng'] ?? '') !== '' ? $dados['local_falecimento_lng'] : null,
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

function atualizarUniao(int $uniaoId, string $tipo, ?string $dataInicio, ?string $dataFim, string $status): void {
    $pdo = getConexao();
    $stmt = $pdo->prepare('UPDATE unioes SET tipo = ?, data_inicio = ?, data_fim = ?, status = ? WHERE id = ?');
    $stmt->execute([$tipo, $dataInicio ?: null, $dataFim ?: null, $status, $uniaoId]);
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
// Uma mesma mídia (ex: certidão de casamento) pode estar vinculada a mais de uma pessoa.

function adicionarMidia(array $pessoaIds, string $tipo, string $caminho, string $titulo = ''): int {
    $pdo = getConexao();
    $stmt = $pdo->prepare('INSERT INTO midias (tipo, caminho_arquivo, titulo, enviado_por) VALUES (?, ?, ?, ?)');
    $stmt->execute([$tipo, $caminho, $titulo ?: null, usuarioAtualId()]);
    $midiaId = (int) $pdo->lastInsertId();
    foreach (array_unique(array_map('intval', $pessoaIds)) as $pid) {
        vincularMidiaAPessoa($midiaId, $pid);
    }
    return $midiaId;
}

function vincularMidiaAPessoa(int $midiaId, int $pessoaId): void {
    $pdo = getConexao();
    $stmt = $pdo->prepare('INSERT IGNORE INTO midia_pessoa (midia_id, pessoa_id) VALUES (?, ?)');
    $stmt->execute([$midiaId, $pessoaId]);
}

// Remove o vínculo com uma pessoa específica. Se não sobrar nenhum vínculo,
// o arquivo é apagado de vez (evita arquivos órfãos ocupando espaço).
function desvincularMidiaDePessoa(int $midiaId, int $pessoaId): void {
    $pdo = getConexao();
    $stmt = $pdo->prepare('DELETE FROM midia_pessoa WHERE midia_id = ? AND pessoa_id = ?');
    $stmt->execute([$midiaId, $pessoaId]);

    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM midia_pessoa WHERE midia_id = ?');
    $stmt->execute([$midiaId]);
    if ((int) $stmt->fetch()['total'] === 0) {
        excluirMidiaCompletamente($midiaId);
    }
}

function excluirMidiaCompletamente(int $midiaId): void {
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

function listarMidias(int $pessoaId): array {
    $pdo = getConexao();
    $stmt = $pdo->prepare(
        'SELECT m.* FROM midias m
         JOIN midia_pessoa mp ON mp.midia_id = m.id
         WHERE mp.pessoa_id = ?
         ORDER BY m.criado_em DESC'
    );
    $stmt->execute([$pessoaId]);
    return $stmt->fetchAll();
}

// Outras pessoas (além da atual) vinculadas à mesma mídia — usado para mostrar "também vinculada a: ..."
function listarPessoasDaMidia(int $midiaId, ?int $excetoPessoaId = null): array {
    $pdo = getConexao();
    $sql = 'SELECT p.id, p.nome_completo FROM pessoas p
            JOIN midia_pessoa mp ON mp.pessoa_id = p.id
            WHERE mp.midia_id = ?';
    $params = [$midiaId];
    if ($excetoPessoaId) {
        $sql .= ' AND p.id != ?';
        $params[] = $excetoPessoaId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Mídias já existentes no sistema que ainda não estão vinculadas a esta pessoa
// (para o fluxo "vincular arquivo já existente", ex: a certidão que a esposa já subiu)
function listarMidiasNaoVinculadas(int $pessoaId): array {
    $pdo = getConexao();
    $stmt = $pdo->prepare(
        "SELECT m.*, GROUP_CONCAT(p.nome_completo SEPARATOR ', ') AS vinculada_a
         FROM midias m
         JOIN midia_pessoa mp ON mp.midia_id = m.id
         JOIN pessoas p ON p.id = mp.pessoa_id
         WHERE m.id NOT IN (SELECT midia_id FROM midia_pessoa WHERE pessoa_id = ?)
         GROUP BY m.id
         ORDER BY m.criado_em DESC"
    );
    $stmt->execute([$pessoaId]);
    return $stmt->fetchAll();
}

function idade(?string $nascimento, ?string $falecimento, bool $falecido = false): ?int {
    if (!$nascimento) return null;
    // Se a pessoa é falecida mas não sabemos quando, não dá pra calcular idade —
    // calcular contra "hoje" gerava números absurdos (ex: 186 anos).
    if ($falecido && !$falecimento) return null;
    $inicio = new DateTime($nascimento);
    $fim = $falecimento ? new DateTime($falecimento) : new DateTime();
    return $inicio->diff($fim)->y;
}
