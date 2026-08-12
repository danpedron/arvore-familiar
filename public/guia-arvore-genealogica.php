<?php
require_once __DIR__ . '/../includes/auth.php';

$canonical = 'https://arvore.pedron.com.br/guia-arvore-genealogica.php';
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
    'headline' => 'Como montar uma árvore genealógica: guia para começar pela sua família',
    'description' => 'Um guia prático para reunir informações, conversar com parentes, registrar fontes e construir uma árvore genealógica online.',
    'inLanguage' => 'pt-BR',
    'image' => 'https://arvore.pedron.com.br/images/og-arvore-familiar.jpg',
    'datePublished' => '2026-08-12',
    'dateModified' => '2026-08-12',
    'author' => ['@type' => 'Organization', 'name' => 'Árvore Familiar'],
    'publisher' => ['@type' => 'Organization', 'name' => 'Árvore Familiar', 'logo' => ['@type' => 'ImageObject', 'url' => 'https://arvore.pedron.com.br/favicon.svg']],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Aprenda como montar uma árvore genealógica: por onde começar, quais dados perguntar à família, como registrar fontes e como preservar a história familiar.">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <meta property="og:type" content="article">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="Árvore Familiar">
    <meta property="og:title" content="Como montar uma árvore genealógica: guia para começar">
    <meta property="og:description" content="Um caminho prático para transformar memórias, documentos e conversas em uma história familiar conectada.">
    <meta property="og:url" content="<?= htmlspecialchars($canonical) ?>">
    <meta property="og:image" content="https://arvore.pedron.com.br/images/og-arvore-familiar.jpg">
    <meta property="og:image:alt" content="Ilustração de gerações conectadas em uma árvore familiar">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="https://arvore.pedron.com.br/images/og-arvore-familiar.jpg">
    <title>Como montar uma árvore genealógica: guia para começar</title>
    <link rel="stylesheet" href="css/style.css?v=seo-public-1">
    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?></script>
</head>
<body class="public-home">
<header class="topo public-topo">
    <a class="brand" href="/">Árvore Familiar</a>
    <nav aria-label="Navegação principal"><a href="/">Início</a><a href="sobre.php">Sobre o projeto</a><?php if (usuarioLogado()): ?><a href="arvore.php">Minha árvore</a><?php else: ?><a href="login.php">Entrar</a><a class="btn btn-small" href="registro.php">Criar conta</a><?php endif; ?></nav>
</header>

<main class="public-content public-article">
    <article>
        <header class="public-page-heading">
            <span class="eyebrow">Guia de genealogia</span>
            <h1>Como montar uma árvore genealógica sem se perder no começo</h1>
            <p>Uma árvore familiar não começa em um arquivo perfeito. Ela começa com uma pessoa, uma conversa e a vontade de preservar o que sua família sabe.</p>
        </header>

        <div class="public-article-intro"><p>Genealogia é o trabalho de conectar pessoas, datas, lugares e histórias. Para a maioria das famílias, o melhor primeiro passo não é procurar tudo na internet: é organizar as informações que já estão perto de você. Este guia mostra um caminho simples para construir uma árvore genealógica online e transformá-la em um registro que outras gerações consigam entender.</p></div>

        <section>
            <h2>1. Comece por você e avance uma geração por vez</h2>
            <p>Registre seu nome completo, data e local de nascimento. Em seguida, acrescente pais, avós e irmãos. A partir daí, cada pessoa pode abrir novas perguntas: quem eram os pais dela? Em que cidade nasceu? Houve uma mudança de sobrenome? Não tente preencher todos os campos de uma vez. Marque o que é conhecido, mantenha o que ainda é incerto como pendência e avance com cuidado.</p>
            <p>Esse método evita uma das frustrações mais comuns de quem começa uma árvore familiar: reunir dezenas de nomes soltos sem saber como eles se relacionam. Ao inserir relações parentais primeiro, a própria visualização ajuda a revelar lacunas e caminhos de pesquisa.</p>
        </section>

        <section>
            <h2>2. Converse com quem guarda as memórias</h2>
            <p>Avós, tios, madrinhas, vizinhos antigos e parentes mais velhos costumam lembrar de apelidos, cidades, profissões e acontecimentos que não aparecem em documentos. Uma conversa curta pode indicar onde procurar uma certidão, esclarecer uma fotografia ou revelar que duas pessoas de sobrenomes diferentes pertencem ao mesmo ramo.</p>
            <p>Prefira perguntas abertas. Em vez de perguntar apenas uma data, pergunte onde a pessoa morava, quem frequentava a casa, como conheceu o cônjuge ou quais festas a família celebrava. Depois, registre a fonte da informação e, quando possível, peça permissão para guardar fotos ou documentos.</p>
        </section>

        <section>
            <h2>3. Separe fatos confirmados de pistas</h2>
            <p>Uma árvore genealógica confiável distingue aquilo que já foi confirmado daquilo que ainda é uma hipótese. Um documento pode confirmar uma data; uma lembrança pode apontar uma cidade; um sobrenome semelhante pode sugerir uma conexão que exige mais pesquisa. Evite transformar suposições em fatos apenas para deixar a árvore completa.</p>
            <p>Na prática, isso significa guardar contexto. Ao registrar um nascimento, casamento, falecimento ou endereço, anote de onde veio a informação. Fotos de verso, certidões, histórias orais e recortes de jornal podem ser muito valiosos quando outra pessoa precisar revisar o registro.</p>
        </section>

        <section>
            <h2>4. Use a tecnologia para conectar, não para duplicar</h2>
            <p>Quando outra parte da família já registrou uma pessoa, não é preciso começar do zero. A Árvore Familiar permite localizar pessoas existentes em outros espaços e incluí-las como referência. Assim, a origem do registro fica identificada, a informação pode ser atualizada por quem tem permissão na família de origem e a linhagem parental conectada continua disponível para visualização.</p>
            <p>Esse modelo é especialmente útil quando famílias se unem por casamento, quando irmãos mantêm espaços diferentes ou quando parentes distantes querem colaborar sem perder o contexto da própria árvore.</p>
        </section>

        <section>
            <h2>5. Preserve também as histórias, não apenas as datas</h2>
            <p>Uma árvore com nomes é útil; uma árvore com memórias é viva. Sempre que possível, acrescente fotografias, documentos, locais e pequenos acontecimentos: a profissão que marcou uma geração, a cidade de origem, uma receita guardada pela avó ou a história por trás de um apelido. Essas informações ajudam quem vier depois a entender as pessoas como pessoas, e não apenas como nós em um diagrama.</p>
        </section>

        <section>
            <h2>6. Compartilhe com responsabilidade</h2>
            <p>Genealogia lida com informações pessoais. Antes de publicar ou compartilhar dados sobre pessoas vivas, converse com a família e respeite preferências. Uma boa prática é manter documentos sensíveis dentro do espaço autenticado e usar páginas públicas apenas para explicar o projeto, nunca para expor árvores ou perfis sem autorização.</p>
        </section>

        <aside class="public-article-callout"><span class="eyebrow">Próximo passo</span><h2>Transforme suas primeiras anotações em uma árvore visual.</h2><p>Crie um espaço familiar, comece por uma pessoa e convide alguém que conheça as histórias da geração anterior.</p><a class="btn" href="<?= usuarioLogado() ? 'arvore.php' : 'registro.php' ?>"><?= usuarioLogado() ? 'Abrir minha árvore' : 'Criar meu espaço familiar' ?></a></aside>
    </article>
</main>

<footer class="public-footer"><span>Árvore Familiar</span><nav aria-label="Links institucionais"><a href="/">Início</a><a href="sobre.php">Sobre o projeto</a><a href="registro.php">Criar conta</a></nav></footer>
</body>
</html>
