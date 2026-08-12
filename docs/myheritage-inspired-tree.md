# Especificação da árvore familiar — experiência de exploração

## Objetivo

A visualização principal será uma área de exploração desktop com painel lateral contextual e cartões genealógicos compactos. A interface se inspira em padrões comuns de exploradores genealógicos, sem copiar identidade visual, textos ou ativos de terceiros.

## Estrutura da tela

A tela terá três faixas. O cabeçalho superior identificará o espaço familiar e oferecerá busca, modo de visualização, exportação e importação. O painel lateral esquerdo mostrará a pessoa em foco, ações de perfil/edição, relações diretas, fotos e documentos. O canvas ocupará todo o restante da janela e aceitará pan, zoom e seleção por clique.

## Cartões

Cada pessoa será representada por um cartão horizontal compacto com avatar, nome de nascimento, anos de vida, marcadores de privacidade e ação de edição. Pessoas unidas serão posicionadas como casal, com um marcador central de união. Uniões com status `divorciado`, `encerrado` ou data de término serão desenhadas com linha tracejada e um selo discreto `ex-união`; a pessoa não será removida da linhagem nem ficará visualmente confundida com um cônjuge atual.

## Relações

Pais e filhos usarão conectores ortogonais com pontos de junção. Quando um casal tiver filhos, o conector partirá do marcador central da união. Cada posição vazia exibirá uma ação `Adicionar pai/mãe` ou `Adicionar filho`, respeitando o papel do usuário. Uniões anteriores aparecerão em uma faixa lateral ou abaixo do casal atual, evitando sobreposição.

## Modos

O modo padrão será `Explorador`, com foco central e cartões de casal. `Linhagem` organizará gerações em colunas horizontais, priorizando a linha de descendência do foco. `Leque` distribuirá ascendentes em arcos semicirculares acima do foco e descendentes em arcos abaixo, mantendo o painel lateral e os mesmos cartões.

## Ações

O painel terá `Perfil`, `Editar`, `Adicionar relação`, `Mais`, `Exportar PDF` e `Seguir outra árvore`. Seguir outra árvore salvará uma referência externa com rótulo e URL, sem misturar seus dados ao espaço familiar local. O link será exibido como atalho de navegação no cabeçalho e no painel.

## Importação e preservação

A importação GEDCOM/JSON será feita por endpoint autenticado, com limite de tamanho, validação de MIME/extensão, transação, deduplicação por identificador/nome+data e registro de importação. O usuário verá uma prévia antes de confirmar. Nenhuma importação alterará pessoas existentes sem registrar a alteração.
