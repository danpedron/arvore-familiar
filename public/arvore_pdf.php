<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirFamilia();

ob_start();
include __DIR__ . '/arvore_dados.php';
$json = ob_get_clean();
// arvore_dados.php define Content-Type JSON; este endpoint precisa retornar HTML imprimível.
header_remove('Content-Type');
header('Content-Type: text/html; charset=utf-8');
$data = json_decode($json, true) ?: [];
$pessoas = $data['pessoas'] ?? [];
$byId = [];
foreach ($pessoas as $pessoa) $byId[(string) $pessoa['id']] = $pessoa;
$foco = (string) ($_GET['foco'] ?? ($pessoas[0]['id'] ?? ''));
if (!isset($byId[$foco]) && $pessoas) $foco = (string) $pessoas[0]['id'];
$acima = max(1, min(5, (int) ($_GET['acima'] ?? 2)));
$abaixo = max(1, min(5, (int) ($_GET['abaixo'] ?? 2)));
$modo = in_array($_GET['modo'] ?? 'explorer', ['explorer', 'lineage', 'fan'], true) ? $_GET['modo'] : 'explorer';
$levels = [$foco => 0];
$queue = [[$foco, 0]];
while ($queue) {
    [$id, $level] = array_shift($queue);
    $pessoa = $byId[$id] ?? null;
    if (!$pessoa) continue;
    $next = $level < 0 ? ($level > -$acima ? ($pessoa['pais'] ?? []) : []) : ($level < $abaixo ? ($pessoa['filhos'] ?? []) : []);
    foreach ($next as $nextId) {
        $nextId = (string) $nextId;
        if (!isset($byId[$nextId]) || isset($levels[$nextId])) continue;
        $levels[$nextId] = $level + ($level < 0 ? -1 : 1);
        $queue[] = [$nextId, $levels[$nextId]];
    }
    foreach (($pessoa['conjuges'] ?? []) as $nextId) {
        $nextId = (string) $nextId;
        if (isset($byId[$nextId]) && !isset($levels[$nextId])) { $levels[$nextId] = $level; $queue[] = [$nextId, $level]; }
    }
}
$rows = [];
foreach ($levels as $id => $level) $rows[$level][] = $id;
ksort($rows);
foreach ($rows as &$row) usort($row, static fn($a, $b) => strcasecmp($byId[$a]['nome'] ?? '', $byId[$b]['nome'] ?? ''));
unset($row);
$former = static function (array $a, array $b): bool {
    foreach (($a['unioes'] ?? []) as $union) {
        if ((string) ($union['pessoa1'] ?? '') !== (string) $b['id'] && (string) ($union['pessoa2'] ?? '') !== (string) $b['id']) continue;
        return in_array(strtolower((string) ($union['status'] ?? '')), ['divorciado', 'encerrado', 'viuvo'], true) || !empty($union['fim']);
    }
    return false;
};
$familyName = $data['familia']['nome'] ?? familiaAtualNome() ?? 'Família';
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Árvore — <?= htmlspecialchars($familyName) ?></title>
<style>
@page { size: A3 landscape; margin: 10mm; }
* { box-sizing:border-box; }
body { margin:0; color:#293235; font:10pt Arial,sans-serif; background:#fff; }
.print-head { display:flex; justify-content:space-between; align-items:end; margin-bottom:10mm; border-bottom:1px solid #d8dddf; padding-bottom:4mm; }
.print-head h1 { margin:0; font-size:20pt; } .print-head p { margin:2mm 0 0; color:#737d81; }
.print-note { color:#737d81; font-size:8pt; text-align:right; }
.graph { position:relative; min-height:150mm; overflow:hidden; border:1px solid #e0e4e5; background:#f8f9f9; }
.columns { display:flex; align-items:flex-start; gap:12mm; padding:12mm; }
.column { min-width:56mm; display:grid; gap:5mm; } .column-title { margin-bottom:1mm; color:#879094; font-size:8pt; font-weight:bold; text-transform:uppercase; letter-spacing:.1em; }
.card { position:relative; min-height:26mm; display:flex; gap:3mm; align-items:center; padding:3mm; border:1px solid #56aab4; border-radius:2mm; background:#fff; break-inside:avoid; }
.card.female { border-color:#d58a99; } .card.focus { border-width:2px; box-shadow:0 0 0 2px #d4eef0; } .card.former { border-style:dashed; }
.avatar { flex:none; display:grid; place-items:center; width:15mm; height:15mm; border-radius:50%; color:white; background:#62abb5; font-size:13pt; font-weight:bold; } .female .avatar { background:#d58a99; }
.info { min-width:0; } .name { max-width:40mm; overflow:hidden; margin:0; font-size:8.5pt; line-height:1.15; font-weight:bold; } .dates { margin:1mm 0 0; color:#697478; font-size:7.5pt; } .tag { margin-top:1mm; color:#b46f3b; font-size:6.5pt; font-weight:bold; }
.ex { position:absolute; top:2mm; right:2mm; padding:1mm 1.5mm; border-radius:50%; color:#b46f3b; background:#fff0e6; font-size:6.5pt; font-weight:bold; }
.legend { display:flex; gap:8mm; margin-top:4mm; color:#6f797d; font-size:8pt; } .legend span { display:inline-flex; align-items:center; gap:2mm; } .dot { width:3mm; height:3mm; border-radius:50%; background:#f0c483; } .dash { width:8mm; border-top:1px dashed #b46f3b; }
@media print { .no-print { display:none !important; } }
</style>
</head>
<body>
<header class="print-head">
  <div><h1><?= htmlspecialchars($familyName) ?></h1><p>Árvore genealógica · modo <?= htmlspecialchars($modo) ?> · foco: <?= htmlspecialchars($byId[$foco]['nome'] ?? '—') ?></p></div>
  <div class="print-note">Gerado em <?= date('d/m/Y H:i') ?><br>Use “Salvar como PDF” no diálogo de impressão.</div>
</header>
<section class="graph"><div class="columns">
<?php foreach ($rows as $level => $ids): ?>
  <div class="column"><div class="column-title"><?= $level < 0 ? abs($level) . 'ª geração acima' : ($level > 0 ? $level . 'ª geração abaixo' : 'Pessoa em foco') ?></div>
  <?php foreach ($ids as $id): $person = $byId[$id]; $isFormer = $id !== $foco && $former($byId[$foco], $person); $initial = mb_strtoupper(mb_substr($person['nome'] ?? '?', 0, 1)); ?>
    <article class="card <?= ($person['sexo'] ?? '') === 'F' ? 'female' : '' ?><?= $id === $foco ? ' focus' : '' ?><?= $isFormer ? ' former' : '' ?>">
      <div class="avatar"><?= htmlspecialchars($initial) ?></div><div class="info"><h2 class="name"><?= htmlspecialchars($person['nome']) ?></h2><p class="dates"><?= htmlspecialchars($person['datas']) ?></p><?php if ($isFormer): ?><div class="tag">Ex-união</div><?php endif; ?></div><?php if ($isFormer): ?><span class="ex">ex</span><?php endif; ?>
    </article>
  <?php endforeach; ?></div>
<?php endforeach; ?>
</div></section>
<div class="legend"><span><i class="dot"></i> marcador de união</span><span><i class="dash"></i> união encerrada / ex-cônjuge</span></div>
<script>window.addEventListener('load',()=>setTimeout(()=>window.print(),350));</script>
</body>
</html>
