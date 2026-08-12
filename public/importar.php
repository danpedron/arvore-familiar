<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirFamilia();
if (!usuarioPodeEditar()) {
    header('Location: arvore.php');
    exit;
}
$csrf = tokenCsrf();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Importar dados · Árvore Familiar</title>
<link rel="icon" href="favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="css/style.css?v=explorer-2">
</head>
<body>
<header class="topo"><a class="brand" href="index.php">Árvore Familiar</a><nav><a href="index.php">Painel</a><a href="arvore.php">Árvore</a><a href="familias.php">Famílias</a><a href="logout.php">Sair</a></nav></header>
<main class="container import-page">
  <div class="page-heading"><div><span class="eyebrow">Acervo familiar</span><h1>Importar dados</h1><p class="lead">Preencha o espaço familiar com um arquivo GEDCOM ou JSON estruturado.</p></div><a href="arvore.php" class="btn btn-secundario">Voltar à árvore</a></div>
  <section class="card import-card">
    <div class="import-format-grid"><article><strong>GEDCOM</strong><p>Compatível com exportações de softwares genealógicos, preservando pessoas, famílias, uniões e filiação.</p></article><article><strong>JSON</strong><p>Use o formato documentado abaixo para importar pessoas e relações de sistemas próprios.</p></article></div>
    <form action="importar_processar.php" method="post" enctype="multipart/form-data" data-import-form>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <label>Arquivo GEDCOM ou JSON<input type="file" name="arquivo" accept=".ged,.gedcom,.json,application/json,text/plain" required></label>
      <label class="check-row"><input type="checkbox" name="confirmar" value="1" required> Entendo que o arquivo será mesclado ao espaço familiar atual e que um backup deve ser feito antes da importação.</label>
      <div class="form-actions"><button class="btn" type="submit">Validar e importar</button></div>
      <p class="form-feedback" data-import-feedback></p>
    </form>
  </section>
  <section class="card"><h2>Formato JSON aceito</h2><pre class="code-sample">{
  "pessoas": [
    {"id":"p1", "nome":"Maria Silva", "sexo":"F", "nascimento":"1950-04-12", "localNascimento":"Curitiba"}
  ],
  "relacoes": {
    "parentais": [{"filho":"p1", "pai":"p2"}],
    "unioes": [{"pessoa1":"p1", "pessoa2":"p3", "status":"ativo", "inicio":"1970-01-01"}]
  }
}</pre><p class="muted">Também são aceitos os nomes em inglês <code>people</code>, <code>relationships</code>, <code>parents</code>, <code>unions</code>, <code>name</code> e <code>birth</code>.</p></section>
  <section class="card import-safety"><h2>Como a importação funciona</h2><p>O sistema cria um registro de importação, executa tudo em uma transação e evita duplicar pessoas quando nome e data de nascimento coincidem. Relações são criadas apenas quando os dois lados foram encontrados. Se ocorrer erro, a transação é revertida.</p></section>
</main>
<script>
document.querySelector('[data-import-form]')?.addEventListener('submit', (event) => {
  const file = event.currentTarget.querySelector('input[type=file]').files[0];
  if (file && file.size > 10 * 1024 * 1024) { event.preventDefault(); document.querySelector('[data-import-feedback]').textContent = 'O arquivo deve ter no máximo 10 MB.'; }
});
</script>
</body>
</html>
