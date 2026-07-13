#!/usr/bin/env php
<?php
/**
 * Reverte uma importação (ex: GEDCOM) pelo ID.
 *
 * Uso:
 *   php scripts/reverter_importacao.php <id> [--forcar]
 *
 * O que é desfeito:
 *  - Pessoas CRIADAS por essa importação são excluídas (e, em cascata, suas
 *    relações, uniões, nomes adicionais e mídias vinculadas só a elas).
 *  - Campos que foram PREENCHIDOS em pessoas que já existiam antes da importação
 *    voltam ao valor anterior (registrado em importacao_alteracoes).
 *  - Relações/uniões criadas ENTRE pessoas que já existiam antes (portanto não
 *    removidas pela cascata acima) são excluídas explicitamente.
 *
 * Isso não depende do backup em SQL — é uma reversão cirúrgica, só do que essa
 * importação especificamente mudou. O backup automático (mysqldump) continua
 * disponível como último recurso, caso algo pareça inconsistente mesmo depois.
 */

require __DIR__ . '/../config/database.php';

function linha(string $texto = ''): void { fwrite(STDOUT, $texto . PHP_EOL); }
function erro(string $texto): void { fwrite(STDERR, "ERRO: {$texto}" . PHP_EOL); }
function confirmar(string $pergunta): bool {
    fwrite(STDOUT, $pergunta . ' [s/N]: ');
    return strtolower(trim(fgets(STDIN))) === 's';
}

$args = array_slice($argv, 1);
$importacaoId = null;
$forcar = false;
foreach ($args as $arg) {
    if ($arg === '--forcar') $forcar = true;
    elseif (ctype_digit($arg)) $importacaoId = (int) $arg;
}

if (!$importacaoId) {
    erro('Uso: php scripts/reverter_importacao.php <id> [--forcar]');
    linha('Use scripts/listar_importacoes.php para ver os IDs disponíveis.');
    exit(1);
}

$pdo = getConexao();

$stmt = $pdo->prepare('SELECT * FROM importacoes WHERE id = ?');
$stmt->execute([$importacaoId]);
$importacao = $stmt->fetch();

if (!$importacao) {
    erro("Importação com ID {$importacaoId} não encontrada.");
    exit(1);
}
if ($importacao['status'] === 'revertida') {
    erro('Essa importação já está marcada como revertida.');
    exit(1);
}

linha('=== Reverter importação #' . $importacaoId . ' ===');
linha('Arquivo original: ' . $importacao['arquivo_original']);
linha('Feita em: ' . $importacao['iniciado_em']);
linha('Pessoas criadas: ' . $importacao['pessoas_criadas']);
linha('Pessoas atualizadas: ' . $importacao['pessoas_atualizadas']);
linha('Uniões criadas: ' . $importacao['unioes_criadas']);
linha('Relações pai/filho criadas: ' . $importacao['relacoes_criadas']);
linha('Nomes adicionais criados: ' . $importacao['nomes_criados']);
if ($importacao['backup_arquivo']) {
    linha('Backup completo disponível em: ' . $importacao['backup_arquivo']);
}
linha('');

if (!$forcar && !confirmar('Confirma a reversão desta importação?')) {
    linha('Cancelado.');
    exit(0);
}

$pdo->beginTransaction();
try {
    // 1) Desfaz atualizações de campos em pessoas que já existiam antes da importação
    $stmt = $pdo->prepare('SELECT * FROM importacao_alteracoes WHERE importacao_id = ? ORDER BY id DESC');
    $stmt->execute([$importacaoId]);
    $alteracoes = $stmt->fetchAll();
    $camposPermitidos = ['sexo', 'data_nascimento', 'local_nascimento', 'data_falecimento', 'local_falecimento', 'falecido', 'biografia', 'gedcom_id'];
    foreach ($alteracoes as $alt) {
        if (!in_array($alt['campo'], $camposPermitidos, true)) continue; // segurança extra contra SQL dinâmico
        $pdo->prepare("UPDATE pessoas SET {$alt['campo']} = ? WHERE id = ?")
            ->execute([$alt['valor_anterior'], $alt['pessoa_id']]);
    }
    linha('Campos restaurados: ' . count($alteracoes));

    // 2) Remove relações/uniões criadas por esta importação entre pessoas que já existiam
    //    (as que envolvem pessoas criadas por ela já somem sozinhas no passo 4, via cascade)
    $removidasRelacoes = $pdo->prepare('DELETE FROM relacoes_parentais WHERE importacao_id = ?');
    $removidasRelacoes->execute([$importacaoId]);
    linha('Relações pai/filho removidas: ' . $removidasRelacoes->rowCount());

    $removidasUnioes = $pdo->prepare('DELETE FROM unioes WHERE importacao_id = ?');
    $removidasUnioes->execute([$importacaoId]);
    linha('Uniões removidas: ' . $removidasUnioes->rowCount());

    $removidosNomes = $pdo->prepare('DELETE FROM nomes_pessoa WHERE importacao_id = ?');
    $removidosNomes->execute([$importacaoId]);
    linha('Nomes adicionais removidos: ' . $removidosNomes->rowCount());

    // 3) Remove pessoas criadas por esta importação (cascade cuida do resto que sobrou vinculado a elas)
    $removidasPessoas = $pdo->prepare("DELETE FROM pessoas WHERE importacao_id = ? AND origem = 'gedcom'");
    $removidasPessoas->execute([$importacaoId]);
    linha('Pessoas removidas: ' . $removidasPessoas->rowCount());

    // 4) Marca a importação como revertida
    $pdo->prepare("UPDATE importacoes SET status = 'revertida' WHERE id = ?")->execute([$importacaoId]);

    $pdo->commit();
    linha('');
    linha('=== Reversão concluída com sucesso ===');
    if ($importacao['backup_arquivo']) {
        linha('Se ainda assim algo parecer errado, o backup completo de antes da importação está em:');
        linha('  ' . $importacao['backup_arquivo']);
        linha('Para restaurar tudo a partir dele: mysql -u SEU_USUARIO -p ' . DB_NAME . ' < "' . $importacao['backup_arquivo'] . '"');
    }
} catch (Throwable $e) {
    $pdo->rollBack();
    erro('Falha ao reverter — nada foi alterado (rollback automático). Detalhe: ' . $e->getMessage());
    exit(1);
}
