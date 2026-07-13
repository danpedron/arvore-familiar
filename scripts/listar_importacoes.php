#!/usr/bin/env php
<?php
/**
 * Lista o histórico de importações (GEDCOM), com status e contadores.
 * Uso: php scripts/listar_importacoes.php
 */

require __DIR__ . '/../config/database.php';

$pdo = getConexao();
$importacoes = $pdo->query('SELECT * FROM importacoes ORDER BY id DESC')->fetchAll();

if (empty($importacoes)) {
    echo "Nenhuma importação registrada ainda." . PHP_EOL;
    exit(0);
}

printf("%-4s %-12s %-25s %-20s %6s %6s %6s %6s\n", 'ID', 'STATUS', 'ARQUIVO', 'DATA', 'CRIAD', 'ATUAL', 'UNIAO', 'RELAC');
echo str_repeat('-', 100) . PHP_EOL;

foreach ($importacoes as $imp) {
    printf(
        "%-4d %-12s %-25s %-20s %6d %6d %6d %6d\n",
        $imp['id'],
        $imp['status'],
        mb_strimwidth($imp['arquivo_original'], 0, 25, '…'),
        $imp['iniciado_em'],
        $imp['pessoas_criadas'],
        $imp['pessoas_atualizadas'],
        $imp['unioes_criadas'],
        $imp['relacoes_criadas']
    );
}

echo PHP_EOL . "Para reverter: php scripts/reverter_importacao.php <ID>" . PHP_EOL;
