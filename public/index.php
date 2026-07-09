<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
exigirLogin();

$busca = trim($_GET['busca'] ?? '');
$pessoas = listarPessoas($busca);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Árvore Familiar</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="topo">
    <a href="index.php">🌳 Árvore Familiar</a>
    <nav>
        <a href="arvore.php">Ver árvore</a>
        <span>Olá, <?= htmlspecialchars(usuarioAtualNome()) ?></span>
        <a href="logout.php">Sair</a>
    </nav>
</header>

<div class="container">
    <div class="card" style="display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap;">
        <form method="get" style="flex:1; min-width:200px;">
            <input type="text" name="busca" placeholder="Buscar pessoa..." value="<?= htmlspecialchars($busca) ?>" style="margin-top:0;">
        </form>
        <a href="pessoa_editar.php" class="btn">+ Nova pessoa</a>
    </div>

    <?php if (empty($pessoas)): ?>
        <div class="card">
            <p>Nenhuma pessoa cadastrada ainda. Comece adicionando a primeira pessoa da árvore!</p>
        </div>
    <?php else: ?>
        <div class="grid-pessoas">
            <?php foreach ($pessoas as $p): ?>
                <a href="pessoa.php?id=<?= $p['id'] ?>" class="pessoa-card">
                    <img class="foto" src="<?= $p['foto_perfil'] ? htmlspecialchars($p['foto_perfil']) : 'https://via.placeholder.com/200x200/e2ddd3/999?text=Sem+foto' ?>" alt="">
                    <div class="info">
                        <p class="nome"><?= htmlspecialchars($p['nome_completo']) ?></p>
                        <p class="datas">
                            <?= $p['data_nascimento'] ? date('d/m/Y', strtotime($p['data_nascimento'])) : '?' ?>
                            <?php if ($p['falecido']): ?>
                                — <?= $p['data_falecimento'] ? date('d/m/Y', strtotime($p['data_falecimento'])) : 'falecido(a)' ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
