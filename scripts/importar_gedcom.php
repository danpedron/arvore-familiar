#!/usr/bin/env php
<?php
/**
 * Importador de GEDCOM — Árvore Familiar
 *
 * Uso:
 *   php scripts/importar_gedcom.php arquivo.ged [--dry-run] [--sem-backup] [--forcar]
 *
 * --dry-run     Simula a importação inteira (mostra o que seria feito) sem gravar nada no banco.
 * --sem-backup  Pula o backup automático via mysqldump antes de importar (NÃO recomendado).
 * --forcar      Não pede confirmação interativa (útil para rodar em script/cron).
 *
 * Regras de consistência:
 *  - Toda pessoa/relação/nome criado por esta importação é marcado com o ID da importação,
 *    permitindo reverter só o que ela criou (veja scripts/reverter_importacao.php).
 *  - Pessoas já existentes (mesmo nome + mesma data de nascimento) NÃO são duplicadas:
 *    só são atualizadas em campos que estavam vazios, ou ganham relações que ainda não existiam.
 *    Cada atualização de campo é logada (valor anterior/novo) pra poder reverter isso também.
 *  - Um backup completo do banco é feito automaticamente antes de qualquer alteração
 *    (a menos que --sem-backup seja usado), como rede de segurança extra.
 */

require __DIR__ . '/GedcomParser.php';
require __DIR__ . '/../config/database.php';

function linha(string $texto = ''): void { fwrite(STDOUT, $texto . PHP_EOL); }
function erro(string $texto): void { fwrite(STDERR, "ERRO: {$texto}" . PHP_EOL); }

function confirmar(string $pergunta): bool {
    fwrite(STDOUT, $pergunta . ' [s/N]: ');
    $resposta = trim(fgets(STDIN));
    return strtolower($resposta) === 's';
}

// --- Argumentos de linha de comando ---
$args = array_slice($argv, 1);
$arquivoGedcom = null;
$dryRun = false;
$semBackup = false;
$forcar = false;

foreach ($args as $arg) {
    if ($arg === '--dry-run') $dryRun = true;
    elseif ($arg === '--sem-backup') $semBackup = true;
    elseif ($arg === '--forcar') $forcar = true;
    elseif (!str_starts_with($arg, '--')) $arquivoGedcom = $arg;
}

if (!$arquivoGedcom) {
    erro('Uso: php scripts/importar_gedcom.php arquivo.ged [--dry-run] [--sem-backup] [--forcar]');
    exit(1);
}
if (!is_readable($arquivoGedcom)) {
    erro("Arquivo não encontrado ou sem permissão de leitura: {$arquivoGedcom}");
    exit(1);
}

linha('=== Importador de GEDCOM — Árvore Familiar ===');
linha('Arquivo: ' . $arquivoGedcom);
if ($dryRun) linha('Modo: SIMULAÇÃO (--dry-run) — nada será gravado no banco.');
linha('');

// --- 1) Backup automático (mysqldump) ---
$caminhoBackup = null;
if (!$dryRun && !$semBackup) {
    $pastaBackups = __DIR__ . '/../backups';
    if (!is_dir($pastaBackups)) mkdir($pastaBackups, 0755, true);
    $caminhoBackup = $pastaBackups . '/antes_importacao_' . date('Y-m-d_His') . '.sql';

    linha('Fazendo backup do banco antes de importar...');
    $cmd = 'mysqldump --single-transaction -h ' . escapeshellarg(DB_HOST) . ' -u ' . escapeshellarg(DB_USER);
    if (DB_PASS !== '') $cmd .= ' -p' . escapeshellarg(DB_PASS);
    $cmd .= ' ' . escapeshellarg(DB_NAME) . ' > ' . escapeshellarg($caminhoBackup) . ' 2>' . escapeshellarg($caminhoBackup . '.log');
    exec($cmd, $saidaIgnorada, $codigoRetorno);

    if ($codigoRetorno !== 0 || !file_exists($caminhoBackup) || filesize($caminhoBackup) === 0) {
        erro('Não foi possível criar o backup automático (mysqldump falhou ou não está instalado).');
        linha('Veja o log em: ' . $caminhoBackup . '.log');
        if (!$forcar && !confirmar('Quer continuar MESMO ASSIM, sem backup automático?')) {
            linha('Importação cancelada.');
            exit(1);
        }
        $caminhoBackup = null;
    } else {
        linha('Backup criado em: ' . $caminhoBackup);
        @unlink($caminhoBackup . '.log');
    }
    linha('');
} elseif ($semBackup && !$dryRun) {
    linha('Aviso: pulando backup automático (--sem-backup).');
    if (!$forcar && !confirmar('Tem certeza que quer continuar sem nenhum backup?')) {
        linha('Importação cancelada.');
        exit(1);
    }
}

// --- 2) Parse do arquivo GEDCOM ---
linha('Lendo e interpretando o arquivo GEDCOM...');
$parser = new GedcomParser();
try {
    $parser->parse($arquivoGedcom);
} catch (Throwable $e) {
    erro('Falha ao interpretar o GEDCOM: ' . $e->getMessage());
    exit(1);
}
$totalIndividuos = count($parser->individuos);
$totalFamilias = count($parser->familias);
linha("Encontrados: {$totalIndividuos} pessoas, {$totalFamilias} famílias/uniões.");
linha('');

if ($totalIndividuos === 0) {
    erro('Nenhuma pessoa encontrada no arquivo. Confira se é um GEDCOM válido.');
    exit(1);
}

if (!$forcar && !$dryRun) {
    if (!confirmar("Prosseguir com a importação de {$totalIndividuos} pessoas?")) {
        linha('Importação cancelada.');
        exit(0);
    }
}
linha('');

// --- 3) Importação em si, dentro de uma transação (dry-run faz ROLLBACK no final) ---
$pdo = getConexao();
$pdo->beginTransaction();

$importacaoId = null;
$pessoasCriadas = 0;
$pessoasAtualizadas = 0;
$relacoesCriadas = 0;
$unioesCriadas = 0;
$nomesCriados = 0;

try {
    $stmt = $pdo->prepare(
        'INSERT INTO importacoes (tipo, arquivo_original, status, backup_arquivo) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute(['gedcom', basename($arquivoGedcom), 'em_andamento', $caminhoBackup]);
    $importacaoId = (int) $pdo->lastInsertId();
    linha("Importação registrada com ID {$importacaoId}.");
    linha('');

    $mapaGedcomParaId = []; // xref GEDCOM (ex: I1) => id interno da pessoa

    $stmtBuscaPorNome = $pdo->prepare('SELECT * FROM pessoas WHERE LOWER(nome_completo) = LOWER(?)');
    $stmtNomesExistentes = $pdo->prepare('SELECT nome FROM nomes_pessoa WHERE pessoa_id = ?');

    linha('Processando pessoas...');
    foreach ($parser->individuos as $gedcomId => $ind) {
        $nomePrincipal = $ind['nomes'][0]['completo'] ?? null;
        if (!$nomePrincipal) {
            linha("  [aviso] Pessoa {$gedcomId} sem nome — ignorada.");
            continue;
        }

        $sexo = $ind['sexo'] ?: 'Desconhecido';
        $nascimento = $ind['nascimento_data']['data'] ?? null;
        $nascimentoAproximado = $ind['nascimento_data']['aproximada'] ?? false;
        $localNascimento = $ind['nascimento_local'] ?? null;
        $falecimento = $ind['falecimento_data']['data'] ?? null;
        $falecimentoAproximado = $ind['falecimento_data']['aproximada'] ?? false;
        $localFalecimento = $ind['falecimento_local'] ?? null;
        $falecido = $ind['falecido'] ? 1 : 0;

        $notas = [];
        if ($nascimento && $nascimentoAproximado) {
            $notas[] = "Data de nascimento aproximada (GEDCOM: {$ind['nascimento_data']['original']}).";
        }
        if ($falecimento && $falecimentoAproximado) {
            $notas[] = "Data de falecimento aproximada (GEDCOM: {$ind['falecimento_data']['original']}).";
        }
        $biografiaImportada = $notas ? implode(' ', $notas) : null;

        // --- Tenta encontrar pessoa já existente (mesmo nome + mesma data de nascimento) ---
        $stmtBuscaPorNome->execute([$nomePrincipal]);
        $candidatos = $stmtBuscaPorNome->fetchAll();

        $existente = null;
        if (count($candidatos) === 1) {
            $c = $candidatos[0];
            $mesmaData = ($c['data_nascimento'] === $nascimento)
                || ($c['data_nascimento'] === null && $nascimento === null);
            if ($mesmaData) $existente = $c;
        } elseif (count($candidatos) > 1) {
            foreach ($candidatos as $c) {
                if ($c['data_nascimento'] === $nascimento && $nascimento !== null) {
                    $existente = $c;
                    break;
                }
            }
        }

        if ($existente) {
            // --- Atualiza só o que estiver faltando, registrando cada mudança ---
            $camposParaPreencher = [
                'sexo' => ($existente['sexo'] === 'Desconhecido' && $sexo !== 'Desconhecido') ? $sexo : null,
                'data_nascimento' => ($existente['data_nascimento'] === null && $nascimento !== null) ? $nascimento : null,
                'local_nascimento' => ($existente['local_nascimento'] === null && $localNascimento !== null) ? $localNascimento : null,
                'data_falecimento' => ($existente['data_falecimento'] === null && $falecimento !== null) ? $falecimento : null,
                'local_falecimento' => ($existente['local_falecimento'] === null && $localFalecimento !== null) ? $localFalecimento : null,
                'falecido' => ($existente['falecido'] == 0 && $falecido == 1) ? 1 : null,
                'biografia' => ($existente['biografia'] === null && $biografiaImportada !== null) ? $biografiaImportada : null,
                'gedcom_id' => ($existente['gedcom_id'] === null) ? $gedcomId : null,
            ];

            $mudou = false;
            foreach ($camposParaPreencher as $campo => $novoValor) {
                if ($novoValor === null) continue;
                $mudou = true;
                $pdo->prepare("UPDATE pessoas SET {$campo} = ? WHERE id = ?")->execute([$novoValor, $existente['id']]);
                $pdo->prepare(
                    'INSERT INTO importacao_alteracoes (importacao_id, pessoa_id, campo, valor_anterior, valor_novo) VALUES (?, ?, ?, ?, ?)'
                )->execute([$importacaoId, $existente['id'], $campo, $existente[$campo] ?? null, $novoValor]);
            }

            if ($mudou) {
                $pessoasAtualizadas++;
                linha("  atualizada: {$nomePrincipal} (id {$existente['id']})");
            } else {
                linha("  já existia, sem novidade: {$nomePrincipal} (id {$existente['id']})");
            }

            $mapaGedcomParaId[$gedcomId] = (int) $existente['id'];
        } else {
            // --- Cria pessoa nova, marcada com a origem desta importação ---
            $stmt = $pdo->prepare(
                'INSERT INTO pessoas (nome_completo, sexo, data_nascimento, local_nascimento, data_falecimento, local_falecimento, falecido, biografia, origem, importacao_id, gedcom_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'gedcom\', ?, ?)'
            );
            $stmt->execute([
                $nomePrincipal, $sexo, $nascimento, $localNascimento,
                $falecimento, $localFalecimento, $falecido, $biografiaImportada,
                $importacaoId, $gedcomId,
            ]);
            $novoId = (int) $pdo->lastInsertId();
            $mapaGedcomParaId[$gedcomId] = $novoId;
            $pessoasCriadas++;
            linha("  criada: {$nomePrincipal} (id {$novoId})");
        }

        // --- Nomes adicionais (segundo NAME em diante no GEDCOM) ---
        $pessoaId = $mapaGedcomParaId[$gedcomId];
        if (count($ind['nomes']) > 1) {
            $stmtNomesExistentes->execute([$pessoaId]);
            $nomesJaExistentes = array_map('mb_strtolower', array_column($stmtNomesExistentes->fetchAll(), 'nome'));

            foreach (array_slice($ind['nomes'], 1) as $nomeExtra) {
                if (in_array(mb_strtolower($nomeExtra['completo']), $nomesJaExistentes, true)) continue;
                $pdo->prepare(
                    'INSERT INTO nomes_pessoa (pessoa_id, nome, tipo, importacao_id) VALUES (?, ?, \'casamento\', ?)'
                )->execute([$pessoaId, $nomeExtra['completo'], $importacaoId]);
                $nomesCriados++;
            }
        }
    }
    linha('');

    // --- 4) Relações (famílias GEDCOM => uniões + filiação) ---
    linha('Processando famílias e relações...');
    $stmtUniaoExistente = $pdo->prepare(
        'SELECT id FROM unioes WHERE (pessoa1_id = ? AND pessoa2_id = ?) OR (pessoa1_id = ? AND pessoa2_id = ?)'
    );
    $stmtRelacaoExistente = $pdo->prepare(
        'SELECT id FROM relacoes_parentais WHERE filho_id = ? AND pai_mae_id = ?'
    );

    foreach ($parser->familias as $famId => $fam) {
        $maridoId = isset($fam['marido']) ? ($mapaGedcomParaId[$fam['marido']] ?? null) : null;
        $esposaId = isset($fam['esposa']) ? ($mapaGedcomParaId[$fam['esposa']] ?? null) : null;

        if ($maridoId && $esposaId) {
            $stmtUniaoExistente->execute([$maridoId, $esposaId, $esposaId, $maridoId]);
            if (!$stmtUniaoExistente->fetch()) {
                $pdo->prepare(
                    'INSERT INTO unioes (pessoa1_id, pessoa2_id, tipo, data_inicio, importacao_id) VALUES (?, ?, \'casamento\', ?, ?)'
                )->execute([$maridoId, $esposaId, $fam['casamento_data']['data'] ?? null, $importacaoId]);
                $unioesCriadas++;
            }
        }

        foreach ($fam['filhos'] as $filhoGedcomId) {
            $filhoId = $mapaGedcomParaId[$filhoGedcomId] ?? null;
            if (!$filhoId) continue;

            foreach ([$maridoId, $esposaId] as $paiMaeId) {
                if (!$paiMaeId) continue;
                $stmtRelacaoExistente->execute([$filhoId, $paiMaeId]);
                if (!$stmtRelacaoExistente->fetch()) {
                    $pdo->prepare(
                        'INSERT INTO relacoes_parentais (filho_id, pai_mae_id, tipo, importacao_id) VALUES (?, ?, \'biologico\', ?)'
                    )->execute([$filhoId, $paiMaeId, $importacaoId]);
                    $relacoesCriadas++;
                }
            }
        }
    }
    linha('');

    // --- 5) Fecha o registro da importação ---
    $pdo->prepare(
        'UPDATE importacoes SET status = ?, pessoas_criadas = ?, pessoas_atualizadas = ?, relacoes_criadas = ?, unioes_criadas = ?, nomes_criados = ?, finalizado_em = NOW() WHERE id = ?'
    )->execute([
        $dryRun ? 'revertida' : 'concluida', // se for dry-run, já nasce marcada como revertida (nunca esteve de fato ativa)
        $pessoasCriadas, $pessoasAtualizadas, $relacoesCriadas, $unioesCriadas, $nomesCriados, $importacaoId,
    ]);

    if ($dryRun) {
        $pdo->rollBack();
        linha('=== SIMULAÇÃO CONCLUÍDA (nada foi gravado) ===');
    } else {
        $pdo->commit();
        linha('=== IMPORTAÇÃO CONCLUÍDA ===');
    }

    linha("Pessoas criadas:     {$pessoasCriadas}");
    linha("Pessoas atualizadas: {$pessoasAtualizadas}");
    linha("Uniões criadas:      {$unioesCriadas}");
    linha("Relações pai/filho:  {$relacoesCriadas}");
    linha("Nomes adicionais:    {$nomesCriados}");
    if ($caminhoBackup) linha("Backup salvo em:      {$caminhoBackup}");

    if (!$dryRun) {
        linha('');
        linha("Se precisar desfazer, rode: php scripts/reverter_importacao.php {$importacaoId}");
    }
} catch (Throwable $e) {
    $pdo->rollBack();
    erro('Falha durante a importação — nada foi gravado (rollback automático). Detalhe: ' . $e->getMessage());
    if ($caminhoBackup) linha('O backup feito antes de começar continua em: ' . $caminhoBackup);
    exit(1);
}
