# Reconstrução desktop da Árvore Familiar

## Objetivo

A nova versão deve priorizar leitura e navegação em telas desktop. O banco de dados atual permanece como fonte de verdade; nenhum registro genealógico será apagado ou transformado durante a troca da aplicação.

## Decisões

A árvore será renderizada como uma camada de cartões HTML posicionados sobre uma camada SVG de conexões. Isso permite texto selecionável, foco de teclado, acessibilidade, cartões maiores e melhor controle de CSS do que desenhar todo o conteúdo como texto SVG.

O contrato da API será plano. Cada pessoa terá `id`, `nome`, `nomeCurto`, `sexo`, `datas`, `localNascimento`, `foto`, `pais`, `filhos`, `conjuges` e `unioes`. A interface não dependerá de nomes internos como `data.nome` ou de uma biblioteca externa.

A pessoa em foco ocupará sempre o centro da linha principal. Pais ficam em linhas superiores, filhos em linhas inferiores e cônjuges ao lado da pessoa em foco ou da família correspondente. A ordenação é determinística e baseada no foco, evitando que a árvore pule de posição a cada clique.

O viewport terá pan por arraste no fundo, zoom pelo wheel e botões explícitos, enquadramento automático, centralização da pessoa atual e atualização do foco por query string. Clicar em um cartão apenas muda o foco; abrir o perfil será uma ação separada no painel contextual. Teclas de seta percorrem pais, filhos e cônjuges; Enter abre o perfil.

O painel contextual será recolhível. Em desktop, a árvore ocupará toda a largura útil; o painel não será uma coluna obrigatória. O estado do painel ficará no navegador e a área do canvas terá altura próxima ao viewport, sem a altura rígida de 680px da versão anterior.

## Critérios de aceite

A página deve carregar os 249 registros existentes sem erro de JavaScript. A busca deve encontrar nomes e selecionar o cartão. O foco deve atualizar a URL, redesenhar as gerações e manter a pessoa no centro. Zoom, pan, fit e centralização devem funcionar sem recarregar a página. A API deve retornar HTTP 200 para uma família válida e nunca devolver pessoas de outra família. A visualização deve permanecer utilizável em uma janela desktop de 1280×800 sem comprimir os cartões em um painel estreito.
