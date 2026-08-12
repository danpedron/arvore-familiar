<?php
require_once __DIR__ . '/../includes/auth.php';

$logado = usuarioLogado();
$ctaHref = $logado ? 'arvore.php' : 'registro.php';
$ctaLabel = $logado ? 'Abrir minha árvore' : 'Criar meu espaço familiar';
$canonical = 'https://arvore.pedron.com.br/';
$robots = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'inicio.php' ? 'noindex,follow' : 'index,follow,max-image-preview:large';
$schema = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            '@id' => $canonical . '#organization',
            'name' => 'Árvore Familiar',
            'url' => $canonical,
            'logo' => $canonical . 'favicon.svg',
            'description' => 'Plataforma comunitária para construir, preservar e explorar árvores genealógicas.',
            'sameAs' => ['https://github.com/danpedron/arvore-familiar'],
        ],
        [
            '@type' => 'WebSite',
            '@id' => $canonical . '#website',
            'url' => $canonical,
            'name' => 'Árvore Familiar',
            'inLanguage' => 'pt-BR',
            'publisher' => ['@id' => $canonical . '#organization'],
        ],
        [
            '@type' => 'WebPage',
            '@id' => $canonical . '#webpage',
            'url' => $canonical,
            'name' => 'Árvore Familiar — construa e compartilhe sua história',
            'description' => 'Monte sua árvore genealógica online, conecte parentes e preserve fotos, documentos e memórias em uma comunidade familiar.',
            'isPartOf' => ['@id' => $canonical . '#website'],
            'inLanguage' => 'pt-BR',
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Monte sua árvore genealógica online, conecte parentes e preserve fotos, documentos e memórias em uma comunidade familiar.">
    <meta name="robots" content="<?= htmlspecialchars($robots) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="Árvore Familiar">
    <meta property="og:title" content="Árvore Familiar — construa e compartilhe sua história">
    <meta property="og:description" content="Uma forma simples de montar sua árvore genealógica, conectar parentes e preservar a história da família.">
    <meta property="og:url" content="<?= htmlspecialchars($canonical) ?>">
    <meta property="og:image" content="https://arvore.pedron.com.br/images/og-arvore-familiar.jpg">
    <meta property="og:image:alt" content="Ilustração de gerações conectadas em uma árvore familiar">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="https://arvore.pedron.com.br/images/og-arvore-familiar.jpg">
    <meta name="twitter:title" content="Árvore Familiar — sua história, conectada">
    <meta name="twitter:description" content="Monte e compartilhe sua árvore genealógica com a família.">
    <title>Árvore Familiar — construa e compartilhe sua história</title>
    <link rel="stylesheet" href="css/style.css?v=seo-public-1">
    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?></script>
</head>
<body class="public-home">
<header class="topo public-topo">
    <a class="brand" href="/">Árvore Familiar</a>
    <nav aria-label="Navegação principal">
        <a href="#como-funciona">Como funciona</a>
        <a href="sobre.php">Sobre o projeto</a>
        <?php if ($logado): ?>
            <a href="arvore.php">Minha árvore</a>
        <?php else: ?>
            <a href="login.php">Entrar</a>
            <a class="btn btn-small" href="registro.php">Criar conta</a>
        <?php endif; ?>
    </nav>
</header>

<main>
    <section class="public-hero">
        <div class="public-hero-copy">
            <span class="eyebrow">Genealogia simples e colaborativa</span>
            <h1>Preserve suas raízes.<br><em>Conecte sua família.</em></h1>
            <p class="public-lead">A Árvore Familiar ajuda você a montar sua árvore genealógica online, organizar pessoas, fotos e documentos e compartilhar descobertas com quem faz parte da sua história.</p>
            <div class="public-actions">
                <a class="btn" href="<?= htmlspecialchars($ctaHref) ?>"><?= htmlspecialchars($ctaLabel) ?></a>
                <a class="btn btn-secundario" href="#como-funciona">Conhecer o projeto</a>
            </div>
            <p class="public-note">Comece com uma família, conecte pessoas existentes e deixe a árvore crescer junto com a comunidade.</p>
        </div>
        <div class="public-hero-art" aria-label="Ilustração de uma árvore genealógica com várias gerações">
            <div class="public-tree-line public-tree-line-one"></div>
            <div class="public-tree-line public-tree-line-two"></div>
            <div class="public-tree-card public-tree-card-top"><strong>Avós</strong><span>raízes e memórias</span></div>
            <div class="public-tree-card public-tree-card-left"><strong>Pais</strong><span>histórias conectadas</span></div>
            <div class="public-tree-card public-tree-card-right"><strong>Parentes</strong><span>família ampliada</span></div>
            <div class="public-tree-card public-tree-card-focus"><strong>Sua história</strong><span>uma geração de cada vez</span></div>
            <span class="public-tree-dot public-tree-dot-one"></span>
            <span class="public-tree-dot public-tree-dot-two"></span>
            <span class="public-tree-dot public-tree-dot-three"></span>
        </div>
    </section>

    <section class="public-section" id="como-funciona">
        <div class="public-section-heading">
            <span class="eyebrow">Feita para pessoas, não para complicar</span>
            <h2>Uma árvore genealógica que cresce com a família</h2>
            <p>Você não precisa conhecer genealogia para começar. Registre o que sabe, convide outras pessoas e descubra os vínculos conforme novas informações aparecem.</p>
        </div>
        <div class="public-feature-grid">
            <article class="public-feature"><span class="public-feature-number">01</span><h3>Comece pelo que você conhece</h3><p>Crie seu espaço familiar e cadastre pessoas, datas, locais, fotos e documentos importantes.</p></article>
            <article class="public-feature"><span class="public-feature-number">02</span><h3>Conecte histórias existentes</h3><p>Referencie pessoas de outros espaços sem recadastrar tudo. A linhagem parental pode acompanhar a referência.</p></article>
            <article class="public-feature"><span class="public-feature-number">03</span><h3>Explore as gerações</h3><p>Use a visualização em árvore, linhagem ou leque para entender relações, ancestrais e descendentes.</p></article>
        </div>
    </section>

    <section class="public-split-section">
        <div>
            <span class="eyebrow">Um projeto aberto à colaboração</span>
            <h2>Menos burocracia. Mais história compartilhada.</h2>
            <p>A comunidade permite que diferentes espaços familiares se encontrem. Uma pessoa pode ser referenciada onde fizer sentido, enquanto a origem e as permissões permanecem claras.</p>
            <a class="text-link" href="sobre.php">Saiba como a comunidade funciona <span>→</span></a>
            <a class="text-link" href="guia-arvore-genealogica.php">Leia o guia para começar sua árvore <span>→</span></a>
        </div>
        <div class="public-checklist">
            <p><strong>Árvore visual</strong><span>Pan, zoom e navegação por gerações.</span></p>
            <p><strong>Memória preservada</strong><span>Fotos, documentos, lugares e acontecimentos.</span></p>
            <p><strong>Importação simples</strong><span>Comece com dados GEDCOM ou JSON quando já tiver um acervo.</span></p>
        </div>
    </section>

    <section class="public-cta-section">
        <span class="eyebrow">A próxima história pode começar com você</span>
        <h2>Monte a árvore da sua família hoje</h2>
        <p>Crie seu espaço, convide quem conhece os detalhes e transforme lembranças dispersas em uma história que pode atravessar gerações.</p>
        <a class="btn" href="<?= htmlspecialchars($ctaHref) ?>"><?= htmlspecialchars($ctaLabel) ?></a>
    </section>
</main>

<footer class="public-footer">
    <span>Árvore Familiar</span>
    <nav aria-label="Links institucionais"><a href="sobre.php">Sobre o projeto</a><a href="login.php">Entrar</a><a href="registro.php">Criar conta</a></nav>
</footer>
</body>
</html>
