<?php
require_once __DIR__ . '/../config/database.php';

function contextoFamiliaId(): int {
    $id = function_exists('familiaAtualId') ? familiaAtualId() : null;
    if (!$id) {
        throw new RuntimeException('Nenhuma família ativa foi selecionada.');
    }
    return (int) $id;
}

function registrarAuditoria(string $entidade, ?int $entidadeId, string $acao, array $detalhes = []): void {
    $familiaId = function_exists('familiaAtualId') ? familiaAtualId() : null;
    if (!$familiaId) return;
    $pdo = getConexao();
    $stmt = $pdo->prepare(
        'INSERT INTO auditoria (familia_id, usuario_id, entidade, entidade_id, acao, detalhes)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $familiaId,
        function_exists('usuarioAtualId') ? usuarioAtualId() : null,
        $entidade,
        $entidadeId,
        $acao,
        $detalhes ? json_encode($detalhes, JSON_UNESCAPED_UNICODE) : null,
    ]);
}

function pessoaEhNativaDaFamilia(int $pessoaId, ?int $familiaId = null): bool {
    $familiaId = $familiaId ?: contextoFamiliaId();
    $stmt = getConexao()->prepare('SELECT 1 FROM pessoas WHERE id = ? AND familia_id = ? LIMIT 1');
    $stmt->execute([$pessoaId, $familiaId]);
    return (bool) $stmt->fetchColumn();
}

function pessoaEhReferenciada(int $pessoaId, ?int $familiaId = null): bool {
    $familiaId = $familiaId ?: contextoFamiliaId();
    $stmt = getConexao()->prepare(
        "SELECT 1 FROM familia_pessoas fp JOIN pessoas p ON p.id = fp.pessoa_id
         WHERE fp.familia_id = ? AND fp.pessoa_id = ? AND fp.tipo = 'referenciada' LIMIT 1"
    );
    $stmt->execute([$familiaId, $pessoaId]);
    return (bool) $stmt->fetchColumn();
}

function exigirPessoaDaFamilia(int $pessoaId): array {
    $pessoa = buscarPessoa($pessoaId);
    if (!$pessoa) {
        throw new RuntimeException('Pessoa não encontrada nesta família.');
    }
    return $pessoa;
}

function papelUsuarioNaFamilia(int $familiaId, ?int $usuarioId = null): string {
    $usuarioId = $usuarioId ?: usuarioAtualId();
    if (!$usuarioId) return 'community';
    $stmt = getConexao()->prepare('SELECT papel FROM familia_usuarios WHERE familia_id = ? AND usuario_id = ? LIMIT 1');
    $stmt->execute([$familiaId, $usuarioId]);
    return (string) ($stmt->fetchColumn() ?: 'community');
}

function usuarioPodeEditarPessoa(int $pessoaId): bool {
    $pessoa = exigirPessoaDaFamilia($pessoaId);
    $familiaOrigemId = (int) ($pessoa['origem_familia_id'] ?? $pessoa['familia_id'] ?? 0);
    return $familiaOrigemId > 0 && in_array(papelUsuarioNaFamilia($familiaOrigemId), ['owner', 'editor'], true);
}

function exigirPessoaEditavel(int $pessoaId): array {
    $pessoa = exigirPessoaDaFamilia($pessoaId);
    if (!usuarioPodeEditarPessoa($pessoaId)) {
        $origem = $pessoa['origem_familia_nome'] ?? 'a família de origem';
        throw new RuntimeException('Você pode consultar esta pessoa pela comunidade, mas não tem papel de edição em ' . $origem . '.');
    }
    return $pessoa;
}

function associarPessoaAoEspaco(int $pessoaId, int $familiaId, string $tipo = 'propria', ?int $usuarioId = null): void {
    $stmt = getConexao()->prepare(
        'INSERT IGNORE INTO familia_pessoas (familia_id, pessoa_id, tipo, referenciada_por) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$familiaId, $pessoaId, $tipo, $usuarioId ?: usuarioAtualId()]);
}

/**
 * Retorna a raiz e o componente genealógico conectado por relações
 * biológicas/adotivas: pais, descendentes e parentes ligados por esses
 * vínculos. Relações de padrasto/madrasta ficam fora do escopo para que um
 * cônjuge atual sem vínculo parental não seja importado por acidente.
 */
function listarIdsDaLinhagem(int $raizId): array {
    $pdo = getConexao();
    $stmt = $pdo->prepare(
        "SELECT rp.filho_id, rp.pai_mae_id
         FROM relacoes_parentais rp
         WHERE (rp.filho_id = ? OR rp.pai_mae_id = ?)
           AND rp.tipo IN ('biologico', 'adotivo')
         ORDER BY rp.filho_id, rp.pai_mae_id"
    );

    $fila = [$raizId];
    $vistos = [];
    $resultado = [];
    while ($fila) {
        $atual = (int) array_shift($fila);
        if ($atual <= 0 || isset($vistos[$atual])) continue;
        $vistos[$atual] = true;
        $resultado[] = $atual;
        $stmt->execute([$atual, $atual]);
        foreach ($stmt->fetchAll() as $relacao) {
            $filhoId = (int) $relacao['filho_id'];
            $paiId = (int) $relacao['pai_mae_id'];
            $outroId = $filhoId === $atual ? $paiId : $filhoId;
            if ($outroId > 0 && !isset($vistos[$outroId])) $fila[] = $outroId;
        }
    }
    return $resultado;
}

/**
 * Inclui uma pessoa referenciada e seu componente genealógico conhecido no espaço.
 * As associações continuam como referenciadas e o escopo permite remover
 * somente o que não for mais necessário por outra raiz ou por uma pessoa nativa.
 */
function associarPessoaComLinhagem(int $raizId, int $familiaId, ?int $usuarioId = null): array {
    $pdo = getConexao();
    $ids = listarIdsDaLinhagem($raizId);
    if (!$ids) return [];

    $usuarioId = $usuarioId ?: usuarioAtualId();
    $iniciouTransacao = !$pdo->inTransaction();
    if ($iniciouTransacao) $pdo->beginTransaction();
    try {
        $incluir = $pdo->prepare(
            'INSERT IGNORE INTO familia_pessoas (familia_id, pessoa_id, tipo, referenciada_por) VALUES (?, ?, \'referenciada\', ?)'
        );
        $escopo = $pdo->prepare(
            'INSERT IGNORE INTO familia_pessoa_escopos (familia_id, referencia_raiz_id, pessoa_id) VALUES (?, ?, ?)'
        );
        foreach ($ids as $pessoaId) {
            $incluir->execute([$familiaId, $pessoaId, $usuarioId]);
            $escopo->execute([$familiaId, $raizId, $pessoaId]);
        }
        if ($iniciouTransacao) $pdo->commit();
    } catch (Throwable $e) {
        if ($iniciouTransacao && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return $ids;
}

/**
 * Remove uma raiz referenciada e apenas as pessoas derivadas que não são
 * compartilhadas por outra raiz nem pertencem originalmente ao espaço.
 */
function removerPessoaComLinhagem(int $raizId, int $familiaId): int {
    $pdo = getConexao();
    $iniciouTransacao = !$pdo->inTransaction();
    if ($iniciouTransacao) $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'DELETE FROM familia_pessoa_escopos WHERE familia_id = ? AND referencia_raiz_id = ?'
        )->execute([$familiaId, $raizId]);
        $stmt = $pdo->prepare(
            "DELETE FROM familia_pessoas fp
             WHERE fp.familia_id = ? AND fp.tipo = 'referenciada'
               AND NOT EXISTS (
                   SELECT 1 FROM familia_pessoa_escopos e
                   WHERE e.familia_id = fp.familia_id AND e.pessoa_id = fp.pessoa_id
               )"
        );
        $stmt->execute([$familiaId]);
        $removidas = $stmt->rowCount();
        if ($iniciouTransacao) $pdo->commit();
        return $removidas;
    } catch (Throwable $e) {
        if ($iniciouTransacao && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

// Placeholder local (SVG embutido, sem nenhuma requisição de rede) usado quando
// a pessoa não tem foto ou quando o caminho salvo no banco aponta pra um arquivo
// que não existe mais em disco (evita 404 no nginx e bloqueios por fail2ban).
const FOTO_PLACEHOLDER = 'data:image/svg+xml;utf8,' .
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">' .
    '<rect width="200" height="200" fill="%23e2ddd3"/>' .
    '<circle cx="100" cy="78" r="38" fill="%23b3ac9c"/>' .
    '<path d="M30 190 Q100 120 170 190 Z" fill="%23b3ac9c"/>' .
    '</svg>';

// Retorna a URL da foto se o arquivo realmente existir em disco, ou o placeholder caso contrário.
function urlFotoOuPlaceholder(?string $caminhoRelativo): string {
    if ($caminhoRelativo && file_exists(__DIR__ . '/../public/' . $caminhoRelativo)) {
        return $caminhoRelativo;
    }
    return FOTO_PLACEHOLDER;
}

// Igual à anterior, mas retorna string vazia (em vez de placeholder) quando não existe —
// útil pra APIs/JSON como a da árvore, onde quem consome já sabe lidar com "sem foto".
function caminhoFotoValido(?string $caminhoRelativo): string {
    if ($caminhoRelativo && file_exists(__DIR__ . '/../public/' . $caminhoRelativo)) {
        return $caminhoRelativo;
    }
    return '';
}

// Atualiza SÓ os campos informados (usado pela edição visual da árvore, que só
// conhece nome/sexo/datas — nunca deve apagar apelido, local, biografia etc.
// que a pessoa já tinha preenchido pela tela normal de edição).
function atualizarCamposBasicos(int $id, array $campos): void {
    if (empty($campos)) return;
    exigirPessoaEditavel($id);
    $permitidos = ['nome_completo', 'sexo', 'data_nascimento', 'data_falecimento', 'falecido'];
    $campos = array_intersect_key($campos, array_flip($permitidos));
    if (empty($campos)) return;

    $pdo = getConexao();
    $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($campos)));
    $campos['id'] = $id;
    $pdo->prepare("UPDATE pessoas SET $set WHERE id = :id")->execute($campos);
    registrarAuditoria('pessoa', $id, 'atualizacao_basica', ['campos' => array_keys($campos)]);
}

// Cria uma pessoa só com os campos básicos (usado quando alguém é adicionado
// direto pela árvore visual — os demais campos ficam vazios e podem ser
// completados depois na tela de edição normal).
function criarPessoaBasica(array $campos): int {
    if (!usuarioPodeEditar()) {
        throw new RuntimeException('Você precisa ser editor ou responsável pelo espaço atual para criar uma pessoa.');
    }
    $pdo = getConexao();
    $dados = [
        'familia_id' => contextoFamiliaId(),
        'nome_completo' => $campos['nome_completo'],
        'sexo' => $campos['sexo'] ?? 'Desconhecido',
        'data_nascimento' => $campos['data_nascimento'] ?? null,
        'data_falecimento' => $campos['data_falecimento'] ?? null,
        'falecido' => !empty($campos['falecido']) ? 1 : 0,
        'criado_por' => usuarioAtualId(),
    ];
    $colunas = implode(', ', array_keys($dados));
    $marcadores = ':' . implode(', :', array_keys($dados));
    $pdo->prepare("INSERT INTO pessoas ($colunas) VALUES ($marcadores)")->execute($dados);
    $idNovo = (int) $pdo->lastInsertId();
    associarPessoaAoEspaco($idNovo, (int) $dados['familia_id'], 'propria');
    return $idNovo;
}

function listarPessoas(string $busca = '', string $ordenar = 'nome_asc'): array {
    $pdo = getConexao();
    $familiaId = contextoFamiliaId();
    $ordens = [
        'nome_asc' => 'nome_completo COLLATE utf8mb4_general_ci ASC, id ASC',
        'nome_desc' => 'nome_completo COLLATE utf8mb4_general_ci DESC, id DESC',
        'nascimento_asc' => 'data_nascimento IS NULL ASC, data_nascimento ASC, nome_completo COLLATE utf8mb4_general_ci ASC',
        'nascimento_desc' => 'data_nascimento IS NULL ASC, data_nascimento DESC, nome_completo COLLATE utf8mb4_general_ci ASC',
        'atualizado_desc' => 'atualizado_em DESC, nome_completo COLLATE utf8mb4_general_ci ASC',
        'criado_desc' => 'criado_em DESC, nome_completo COLLATE utf8mb4_general_ci ASC',
    ];
    $orderBy = $ordens[$ordenar] ?? $ordens['nome_asc'];
    $sql = "SELECT p.*, fp.tipo AS associacao_tipo, fp.referenciada_por,
                   origem.id AS origem_familia_id, origem.nome AS origem_familia_nome
            FROM pessoas p
            JOIN familia_pessoas fp ON fp.pessoa_id = p.id AND fp.familia_id = ?
            JOIN familias origem ON origem.id = p.familia_id
            WHERE 1 = 1";
    $params = [$familiaId];
    if ($busca !== '') {
        $sql .= ' AND nome_completo LIKE ?';
        $params[] = '%' . $busca . '%';
    }
    $sql .= " ORDER BY $orderBy";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function buscarPessoa(int $id): ?array {
    $pdo = getConexao();
    $stmt = $pdo->prepare(
        'SELECT p.*, fp.tipo AS associacao_tipo, fp.referenciada_por,
                origem.id AS origem_familia_id, origem.nome AS origem_familia_nome
         FROM pessoas p
         JOIN familia_pessoas fp ON fp.pessoa_id = p.id AND fp.familia_id = ?
         JOIN familias origem ON origem.id = p.familia_id
         WHERE p.id = ?'
    );
    $stmt->execute([contextoFamiliaId(), $id]);
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
        exigirPessoaEditavel($id);
        $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($campos)));
        $stmt = $pdo->prepare("UPDATE pessoas SET $set WHERE id = :id");
        $campos['id'] = $id;
        $stmt->execute($campos);
        registrarAuditoria('pessoa', $id, 'atualizacao', ['nome' => $campos['nome_completo']]);
        return $id;
    }

    $campos['familia_id'] = contextoFamiliaId();
    $campos['criado_por'] = usuarioAtualId();
    $colunas = implode(', ', array_keys($campos));
    $marcadores = ':' . implode(', :', array_keys($campos));
    $stmt = $pdo->prepare("INSERT INTO pessoas ($colunas) VALUES ($marcadores)");
    $stmt->execute($campos);
    $idNovo = (int) $pdo->lastInsertId();
    associarPessoaAoEspaco($idNovo, (int) $campos['familia_id'], 'propria');
    registrarAuditoria('pessoa', $idNovo, 'criacao', ['nome' => $campos['nome_completo']]);
    return $idNovo;
}

function excluirPessoa(int $id): void {
    $pessoa = exigirPessoaEditavel($id);
    if ((int) ($pessoa['familia_id'] ?? 0) !== contextoFamiliaId()) {
        throw new RuntimeException('Uma pessoa referenciada não pode ser excluída deste espaço. Remova-a na família de origem.');
    }
    $pdo = getConexao();
    $stmt = $pdo->prepare('DELETE FROM pessoas WHERE id = ?');
    $stmt->execute([$id]);
    registrarAuditoria('pessoa', $id, 'exclusao');
}

function atualizarFotoPerfil(int $pessoaId, string $caminho): void {
    exigirPessoaEditavel($pessoaId);
    $pdo = getConexao();
    $stmt = $pdo->prepare('UPDATE pessoas SET foto_perfil = ? WHERE id = ?');
    $stmt->execute([$caminho, $pessoaId]);
    registrarAuditoria('pessoa', $pessoaId, 'foto_atualizada');
}

// --- Relações de parentesco ---

function adicionarPaiMae(int $filhoId, int $paiMaeId, string $tipo = 'biologico'): void {
    if ($filhoId === $paiMaeId) return;
    exigirPessoaEditavel($filhoId);
    exigirPessoaEditavel($paiMaeId);
    $pdo = getConexao();
    $stmt = $pdo->prepare('INSERT IGNORE INTO relacoes_parentais (filho_id, pai_mae_id, tipo) VALUES (?, ?, ?)');
    $stmt->execute([$filhoId, $paiMaeId, $tipo]);
    registrarAuditoria('relacao_parental', (int) $pdo->lastInsertId() ?: null, 'criacao', ['filho_id' => $filhoId, 'pai_mae_id' => $paiMaeId]);
}

function removerPaiMae(int $filhoId, int $paiMaeId): void {
    exigirPessoaEditavel($filhoId);
    exigirPessoaEditavel($paiMaeId);
    $pdo = getConexao();
    $stmt = $pdo->prepare('DELETE FROM relacoes_parentais WHERE filho_id = ? AND pai_mae_id = ?');
    $stmt->execute([$filhoId, $paiMaeId]);
}

function listarPais(int $pessoaId): array {
    $pdo = getConexao();
    $stmt = $pdo->prepare(
        'SELECT p.*, fp.tipo AS associacao_tipo, origem.nome AS origem_familia_nome, rp.tipo
         FROM pessoas p
         JOIN relacoes_parentais rp ON rp.pai_mae_id = p.id
         JOIN pessoas filho ON filho.id = rp.filho_id
         JOIN familia_pessoas fp ON fp.pessoa_id = p.id AND fp.familia_id = ?
         JOIN familias origem ON origem.id = p.familia_id
         JOIN familia_pessoas fp_filho ON fp_filho.pessoa_id = filho.id AND fp_filho.familia_id = ?
         WHERE rp.filho_id = ?'
    );
    $stmt->execute([contextoFamiliaId(), contextoFamiliaId(), $pessoaId]);
    return $stmt->fetchAll();
}

function listarFilhos(int $pessoaId): array {
    $pdo = getConexao();
    $stmt = $pdo->prepare(
        'SELECT p.*, fp.tipo AS associacao_tipo, origem.nome AS origem_familia_nome, rp.tipo
         FROM pessoas p
         JOIN relacoes_parentais rp ON rp.filho_id = p.id
         JOIN pessoas pai ON pai.id = rp.pai_mae_id
         JOIN familia_pessoas fp ON fp.pessoa_id = p.id AND fp.familia_id = ?
         JOIN familias origem ON origem.id = p.familia_id
         JOIN familia_pessoas fp_pai ON fp_pai.pessoa_id = pai.id AND fp_pai.familia_id = ?
         WHERE rp.pai_mae_id = ?'
    );
    $stmt->execute([contextoFamiliaId(), contextoFamiliaId(), $pessoaId]);
    return $stmt->fetchAll();
}

// --- Uniões / cônjuges ---

function adicionarUniao(int $pessoa1Id, int $pessoa2Id, string $tipo = 'casamento', ?string $dataInicio = null): void {
    if ($pessoa1Id === $pessoa2Id) return;
    exigirPessoaEditavel($pessoa1Id);
    exigirPessoaEditavel($pessoa2Id);
    $pdo = getConexao();
    $stmt = $pdo->prepare('INSERT INTO unioes (pessoa1_id, pessoa2_id, tipo, data_inicio) VALUES (?, ?, ?, ?)');
    $stmt->execute([$pessoa1Id, $pessoa2Id, $tipo, $dataInicio ?: null]);
    registrarAuditoria('uniao', (int) $pdo->lastInsertId(), 'criacao', ['pessoa1_id' => $pessoa1Id, 'pessoa2_id' => $pessoa2Id]);
}

function listarConjuges(int $pessoaId): array {
    exigirPessoaDaFamilia($pessoaId);
    $pdo = getConexao();
    $stmt = $pdo->prepare(
        'SELECT p.*, u.tipo, u.status, u.data_inicio, u.data_fim, u.id AS uniao_id
         FROM pessoas p
         JOIN unioes u ON (u.pessoa1_id = p.id OR u.pessoa2_id = p.id)
         JOIN pessoas pessoa_foco ON pessoa_foco.id = ?
         JOIN familia_pessoas fp ON fp.pessoa_id = p.id AND fp.familia_id = ?
         JOIN familia_pessoas fp_foco ON fp_foco.pessoa_id = pessoa_foco.id AND fp_foco.familia_id = ?
         WHERE (u.pessoa1_id = pessoa_foco.id OR u.pessoa2_id = pessoa_foco.id) AND p.id != pessoa_foco.id'
    );
    $stmt->execute([$pessoaId, contextoFamiliaId(), contextoFamiliaId()]);
    return $stmt->fetchAll();
}

function obterUniaoEditavel(int $uniaoId): ?array {
    $pdo = getConexao();
    $familiaId = contextoFamiliaId();
    $stmt = $pdo->prepare(
        'SELECT u.id, u.pessoa1_id, u.pessoa2_id
         FROM unioes u
         JOIN familia_pessoas fp1 ON fp1.pessoa_id = u.pessoa1_id AND fp1.familia_id = ?
         JOIN familia_pessoas fp2 ON fp2.pessoa_id = u.pessoa2_id AND fp2.familia_id = ?
         WHERE u.id = ?'
    );
    $stmt->execute([$familiaId, $familiaId, $uniaoId]);
    $uniao = $stmt->fetch();
    if (!$uniao) return null;
    exigirPessoaEditavel((int) $uniao['pessoa1_id']);
    exigirPessoaEditavel((int) $uniao['pessoa2_id']);
    return $uniao;
}

function removerUniao(int $uniaoId): void {
    $pdo = getConexao();
    $uniao = obterUniaoEditavel($uniaoId);
    if (!$uniao) return;
    $stmt = $pdo->prepare('DELETE FROM unioes WHERE id = ?');
    $stmt->execute([$uniaoId]);
    registrarAuditoria('uniao', $uniaoId, 'exclusao');
}

function atualizarUniao(int $uniaoId, string $tipo, ?string $dataInicio, ?string $dataFim, string $status): void {
    $pdo = getConexao();
    $uniao = obterUniaoEditavel($uniaoId);
    if (!$uniao) return;
    $stmt = $pdo->prepare('UPDATE unioes SET tipo = ?, data_inicio = ?, data_fim = ?, status = ? WHERE id = ?');
    $stmt->execute([$tipo, $dataInicio ?: null, $dataFim ?: null, $status, $uniaoId]);
    registrarAuditoria('uniao', $uniaoId, 'atualizacao');
}

// --- Nomes adicionais (nome de casada, religioso etc.) ---

function adicionarNomeAdicional(int $pessoaId, string $nome, string $tipo = 'casamento', ?int $uniaoId = null, string $observacao = ''): void {
    exigirPessoaEditavel($pessoaId);
    $pdo = getConexao();
    $stmt = $pdo->prepare('INSERT INTO nomes_pessoa (pessoa_id, nome, tipo, uniao_id, observacao) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$pessoaId, $nome, $tipo, $uniaoId ?: null, $observacao ?: null]);
    registrarAuditoria('nome_adicional', (int) $pdo->lastInsertId(), 'criacao', ['pessoa_id' => $pessoaId]);
}

function listarNomesAdicionais(int $pessoaId): array {
    $pdo = getConexao();
    $stmt = $pdo->prepare('SELECT n.* FROM nomes_pessoa n JOIN familia_pessoas fp ON fp.pessoa_id = n.pessoa_id AND fp.familia_id = ? WHERE n.pessoa_id = ? ORDER BY n.criado_em');
    $stmt->execute([contextoFamiliaId(), $pessoaId]);
    return $stmt->fetchAll();
}

function removerNomeAdicional(int $nomeId): void {
    $pdo = getConexao();
    $stmt = $pdo->prepare(
        'SELECT n.pessoa_id
         FROM nomes_pessoa n
         JOIN familia_pessoas fp ON fp.pessoa_id = n.pessoa_id AND fp.familia_id = ?
         WHERE n.id = ?'
    );
    $stmt->execute([contextoFamiliaId(), $nomeId]);
    $nome = $stmt->fetch();
    if (!$nome) return;
    exigirPessoaEditavel((int) $nome['pessoa_id']);
    $pdo->prepare('DELETE FROM nomes_pessoa WHERE id = ?')->execute([$nomeId]);
    registrarAuditoria('nome_adicional', $nomeId, 'exclusao');
}

// --- Mídias (fotos e documentos) ---
// Uma mesma mídia (ex: certidão de casamento) pode estar vinculada a mais de uma pessoa.

function adicionarMidia(array $pessoaIds, string $tipo, string $caminho, string $titulo = ''): int {
    $pessoaIds = array_values(array_unique(array_map('intval', $pessoaIds)));
    foreach ($pessoaIds as $pid) exigirPessoaEditavel($pid);
    $pdo = getConexao();
    $stmt = $pdo->prepare('INSERT INTO midias (tipo, caminho_arquivo, titulo, enviado_por) VALUES (?, ?, ?, ?)');
    $stmt->execute([$tipo, $caminho, $titulo ?: null, usuarioAtualId()]);
    $midiaId = (int) $pdo->lastInsertId();
    foreach (array_unique(array_map('intval', $pessoaIds)) as $pid) {
        vincularMidiaAPessoa($midiaId, $pid);
    }
    registrarAuditoria('midia', $midiaId, 'criacao', ['pessoas' => $pessoaIds]);
    return $midiaId;
}

function vincularMidiaAPessoa(int $midiaId, int $pessoaId): void {
    exigirPessoaEditavel($pessoaId);
    $pdo = getConexao();
    $stmt = $pdo->prepare('INSERT IGNORE INTO midia_pessoa (midia_id, pessoa_id) VALUES (?, ?)');
    $stmt->execute([$midiaId, $pessoaId]);
}

// Remove o vínculo com uma pessoa específica. Se não sobrar nenhum vínculo,
// o arquivo é apagado de vez (evita arquivos órfãos ocupando espaço).
function desvincularMidiaDePessoa(int $midiaId, int $pessoaId): void {
    $pdo = getConexao();
    exigirPessoaEditavel($pessoaId);
    $stmt = $pdo->prepare('DELETE FROM midia_pessoa WHERE midia_id = ? AND pessoa_id = ?');
    $stmt->execute([$midiaId, $pessoaId]);
    if ($stmt->rowCount() === 0) return;

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
         WHERE mp.pessoa_id = ? AND EXISTS (SELECT 1 FROM familia_pessoas fp WHERE fp.pessoa_id = mp.pessoa_id AND fp.familia_id = ?)
         ORDER BY m.criado_em DESC'
    );
    $stmt->execute([$pessoaId, contextoFamiliaId()]);
    return $stmt->fetchAll();
}

// Outras pessoas (além da atual) vinculadas à mesma mídia — usado para mostrar "também vinculada a: ..."
function listarPessoasDaMidia(int $midiaId, ?int $excetoPessoaId = null): array {
    $pdo = getConexao();
    $sql = 'SELECT p.id, p.nome_completo FROM pessoas p
            JOIN midia_pessoa mp ON mp.pessoa_id = p.id
            JOIN familia_pessoas fp ON fp.pessoa_id = p.id AND fp.familia_id = ?
            WHERE mp.midia_id = ?';
    $params = [contextoFamiliaId(), $midiaId];
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
         JOIN familia_pessoas fp ON fp.pessoa_id = p.id AND fp.familia_id = ?
         WHERE m.id NOT IN (SELECT midia_id FROM midia_pessoa WHERE pessoa_id = ?)
         GROUP BY m.id
         ORDER BY m.criado_em DESC"
    );
    $stmt->execute([contextoFamiliaId(), $pessoaId]);
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
