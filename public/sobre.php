<?php
require_once __DIR__ . '/../includes/auth.php';

$canonical = 'https://arvore.pedron.com.br/sobre.php';
$schema = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'AboutPage',
            '@id' => $canonical . '#webpage',
            'url' => $canonical,
            'name' => 'Sobre a Árvore Familiar',
            'description' => 'Como funciona a plataforma comunitária de genealogia Árvore Familiar.',
            'inLanguage' => 'pt-BR',
            'isPartOf' => ['@id' => 'https://arvore.pedron.com.br/#website'],
        ],
        [
            '@type' => 'Organization',
            '@id' => 'https://arvore.pedron.com.br/#organization',
            'name' => 'Árvore Familiar',
            'url' => 'https://arvore.pedron.com.br/',
            'logo' => 'https://arvore.pedron.com.br/favicon.svg',
        ],
        [
            '@type' => 'FAQPage',
            'mainEntity' => [
                [
                    '@type' => 'Question',
                    'name' => 'O que é a Árvore Familiar?',
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'É uma plataforma comunitária para montar árvores genealógicas, registrar pessoas, preservar fotos e documentos e explorar relações entre gerações.'],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'Posso colaborar com outras famílias?',
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Sim. Espaços familiares podem referenciar pessoas de outros espaços, preservando a origem do registro e permitindo que a linhagem relacionada seja encontrada no contexto atual.'],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'Preciso cadastrar uma pessoa novamente se ela já existir?',
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Não necessariamente. A pessoa pode ser referenciada de outro espaço, evitando duplicação e mantendo a conexão com a família de origem.'],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'A Árvore Familiar aceita GEDCOM?',
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Sim. O projeto oferece importação de dados familiares em GEDCOM ou JSON para ajudar a iniciar uma árvore existente.'],
                ],
            ],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Conheça a Árvore Familiar: uma plataforma comunitária para genealogia, árvores familiares, memórias, fotos, documentos e colaboração entre gerações.">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <meta property="og:type" content="article">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="Árvore Familiar">
    <meta property="og:title" content="Sobre a Árvore Familiar — genealogia colaborativa">
    <meta property="og:description" content="Entenda como criar espaços familiares, conectar pessoas e preservar a história da sua família.">
    <meta property="og:url" content="<?= htmlspecialchars($canonical) ?>">
    <meta property="og:image" content="https://arvore.pedron.com.br/images/og-arvore-familiar.jpg">
    <meta property="og:image:alt" content="Ilustração de gerações conectadas em uma árvore familiar">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="https://arvore.pedron.com.br/images/og-arvore-familiar.jpg">
    <title>Sobre a Árvore Familiar — genealogia colaborativa</title>
    <link rel="stylesheet" href="css/style.css?v=seo-public-1">
    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?></script>
</head>
<body class="public-home">
<header class="topo public-topo">
    <a class="brand" href="/">Árvore Familiar</a>
    <nav aria-label="Navegação principal">
        <a href="/#como-funciona">Como funciona</a>
        <a href="sobre.php" aria-current="page">Sobre o projeto</a>
        <?php if (usuarioLogado()): ?><a href="arvore.php">Minha árvore</a><?php else: ?><a href="login.php">Entrar</a><a class="btn btn-small" href="registro.php">Criar conta</a><?php endif; ?>
    </nav>
</header>

<main class="public-content">
    <section class="public-page-heading">
        <span class="eyebrow">Sobre o projeto</span>
        <h1>Genealogia feita para ser compreendida e compartilhada.</h1>
        <p>A Árvore Familiar nasceu para tornar a pesquisa dos antepassados mais simples: uma árvore visual, espaços familiares claros e colaboração sem a complexidade de uma plataforma enorme.</p>
    </section>

    <section class="public-prose-grid">
        <article class="public-prose-card"><span class="public-feature-number">01</span><h2>Uma árvore para explorar</h2><p>O Family Chart organiza pais, filhos, uniões e gerações em uma visualização navegável. Você pode mover o canvas, ampliar, escolher o foco e alternar entre os modos explorador, linhagem e leque.</p></article>
        <article class="public-prose-card"><span class="public-feature-number">02</span><h2>Espaços que se conectam</h2><p>Cada família pode manter seu espaço e, ao mesmo tempo, referenciar pessoas que já existem na comunidade. A origem do registro fica visível, reduzindo duplicações e ajudando a preservar a procedência da informação.</p></article>
        <article class="public-prose-card"><span class="public-feature-number">03</span><h2>Memória além dos nomes</h2><p>Além de relações, uma árvore familiar guarda datas, lugares, fotos, documentos e acontecimentos. O objetivo é transformar registros soltos em uma história que possa ser revisitada por outras gerações.</p></article>
    </section>

    <section class="public-text-section">
        <div>
            <span class="eyebrow">Como começar</span>
            <h2>Você pode começar com uma única pessoa</h2>
        </div>
        <div class="public-text-section-body"><p>Crie uma conta, abra um espaço familiar e registre o que você já sabe. Depois, convide parentes para complementar datas e relações, importe um arquivo GEDCOM ou JSON, ou localize uma pessoa já existente em outro espaço.</p><p>Quando uma referência é incluída, as relações genealógicas conectadas podem acompanhar a pessoa no espaço atual. Padrastos e cônjuges sem vínculo parental não são trazidos apenas por inferência, mantendo a árvore coerente.</p></div>
    </section>

    <section class="public-faq" id="perguntas-frequentes">
        <div class="public-section-heading"><span class="eyebrow">Perguntas frequentes</span><h2>Antes de montar sua árvore</h2></div>
        <details open><summary>O que é a Árvore Familiar?</summary><p>É uma plataforma comunitária para montar árvores genealógicas, registrar pessoas, preservar fotos e documentos e explorar relações entre gerações.</p></details>
        <details><summary>Posso colaborar com outras famílias?</summary><p>Sim. Espaços familiares podem referenciar pessoas de outros espaços, preservando a origem do registro e permitindo que a linhagem relacionada seja encontrada no contexto atual.</p></details>
        <details><summary>Preciso cadastrar novamente alguém que já existe?</summary><p>Não necessariamente. A pessoa pode ser referenciada de outro espaço, evitando duplicação e mantendo a conexão com a família de origem.</p></details>
        <details><summary>A plataforma aceita GEDCOM?</summary><p>Sim. O projeto oferece importação de dados familiares em GEDCOM ou JSON para ajudar a iniciar uma árvore existente.</p></details>
    </section>

    <section class="public-cta-section public-cta-compact">
        <h2>Pronto para começar sua árvore?</h2>
        <p>Registre a primeira pessoa e deixe sua história crescer.</p>
        <a class="btn" href="<?= usuarioLogado() ? 'arvore.php' : 'registro.php' ?>"><?= usuarioLogado() ? 'Abrir minha árvore' : 'Criar meu espaço familiar' ?></a>
    </section>
</main>

<footer class="public-footer"><span>Árvore Familiar</span><nav aria-label="Links institucionais"><a href="/">Início</a><a href="login.php">Entrar</a><a href="registro.php">Criar conta</a><a href="https://github.com/danpedron/arvore-familiar" rel="external noopener">Código do projeto</a></nav></footer>
</body>
</html>
