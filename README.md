# Árvore Familiar — Genealogia Self-Hosted

Sistema simples e leve para registrar sua árvore genealógica, com fotos e documentos por pessoa, feito para uso em família (multi-usuário, todos podem editar).

## Requisitos

- PHP 8.0+ com extensão PDO MySQL
- MariaDB 10.x (ou MySQL 5.7+)
- Servidor web (Apache/Nginx) ou o servidor embutido do PHP

## Instalação

1. **Criar o banco de dados**

   Instalação nova — importe o schema completo:
   ```bash
   mysql -u root -p < database/schema.sql
   ```

   Se você já tinha instalado uma versão anterior deste projeto, rode as migrações na ordem:
   ```bash
   mysql -u root -p < database/migracao_001_nomes_pessoa.sql
   mysql -u root -p < database/migracao_002_geolocalizacao.sql
   mysql -u root -p < database/migracao_003_midia_multipla.sql
   mysql -u root -p < database/migracao_005_enterprise.sql
   ```

2. **Configurar a conexão**

   Copie o arquivo de exemplo e edite com suas credenciais reais do MariaDB:
   ```bash
   cp config/database.php-sample config/database.php
   ```
   Depois edite `config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'arvore_familiar');
   define('DB_USER', 'seu_usuario');
   define('DB_PASS', 'sua_senha');
   ```

   `config/database.php` está no `.gitignore` — ele não deve ser versionado, já que contém credenciais reais e é diferente em cada ambiente. Só o `config/database.php-sample` (com valores de exemplo) fica no repositório.

3. **Permissões da pasta de uploads**

   ```bash
   chmod -R 755 public/uploads
   ```

4. **Configurar o nginx**

   Use `nginx.conf.example` como base. O ponto importante é que o `root` do site aponte para a pasta `public/` (nunca para a raiz do projeto), e que exista um bloco bloqueando execução de `.php` dentro de `/uploads/` — isso substitui o `.htaccess` (que só funciona em Apache).

5. **Rodar o servidor**

   Para testar localmente sem nginx configurado:
   ```bash
   cd public
   php -S localhost:8000
   ```
   Acesse: http://localhost:8000

   Em produção, use o nginx apontando para `public/` conforme `nginx.conf.example`.

## Estrutura do projeto

```
arvore-familiar/
├── config/
│   ├── database.php         # Configuração de conexão (NÃO versionado, você cria a partir do -sample)
│   └── database.php-sample  # Modelo de configuração
├── database/
│   ├── schema.sql            # Estrutura completa do banco (instalação nova)
│   └── migracao_*.sql        # Migrações incrementais (instalações já existentes)
├── scripts/                  # Scripts de linha de comando (fora de public/, não acessíveis via web)
│   ├── GedcomParser.php       # Parser de arquivos GEDCOM
│   ├── importar_gedcom.php    # Importador (com backup automático e rastreamento)
│   ├── reverter_importacao.php
│   ├── listar_importacoes.php
│   └── verificar_consistencia.php  # Diagnóstico de ciclos/inconsistências no banco
├── backups/                  # Backups automáticos gerados antes de cada importação (gitignored)
├── includes/
│   ├── auth.php             # Login, registro, sessão
│   └── functions.php        # CRUD de pessoas, relações, mídias
├── public/                  # Raiz pública do site (aponte o nginx para cá)
│   ├── index.php             # Listagem de pessoas
│   ├── login.php / registro.php / logout.php
│   ├── pessoa.php            # Perfil da pessoa + relações + mídias
│   ├── pessoa_editar.php     # Criar/editar pessoa
│   ├── arvore.php            # Explorador SVG com foco, zoom, busca e gerações
│   ├── arvore_dados.php      # Endpoint JSON escopado pela família ativa
│   ├── familias.php          # Espaços de família, papéis e compartilhamento
│   ├── geocodificar.php      # Proxy para busca de locais (Nominatim/OSM)
│   ├── js/busca-local.js     # Autocomplete de locais no formulário
│   ├── js/tree-explorer.js   # Renderizador e interação da árvore
│   ├── css/style.css
│   └── uploads/               # Fotos e documentos enviados
├── nginx.conf.example       # Configuração de referência para nginx
└── README.md
```

**Requisitos adicionais pro importador GEDCOM**: extensão `php-mbstring` (para lidar com acentuação corretamente) e o binário `mysqldump` no PATH (geralmente já vem com `mariadb-client`/`mysql-client`).
```bash
sudo apt install php8.3-mbstring mariadb-client
sudo systemctl restart php8.3-fpm
```

## Espaços de família e papéis

A migração `migracao_005_enterprise.sql` adiciona isolamento multi-família. Cada pessoa pertence a um único espaço, e cada usuário participa de um ou mais espaços com um papel: `owner` administra membros e configurações; `editor` cria e altera pessoas, relações e mídias; `viewer` navega e consulta sem alterar dados. A tela `familias.php` permite criar um novo espaço, alternar o contexto ativo e compartilhar o acesso com outra conta já cadastrada.

A migração também cria a tabela `auditoria`, que registra as principais criações, atualizações e exclusões feitas pelo sistema. Em produção, recomenda-se manter o banco atrás de backups automáticos e restringir o acesso administrativo do MariaDB à máquina local.

## Como usar

1. Crie uma conta (todos os membros da família podem ter a sua) em `registro.php`.
2. Cadastre a primeira pessoa em "+ Nova pessoa".
3. No perfil de cada pessoa, você pode:
   - Vincular pais, cônjuges e filhos (basta selecionar pessoas já cadastradas)
   - Fazer upload de fotos e documentos (certidões, escrituras etc.)
   - Editar dados biográficos e biografia livre

## Local de nascimento/falecimento via OpenStreetMap

Os campos de local não são mais texto livre "cego": ao digitar, o sistema busca sugestões reais no OpenStreetMap (via Nominatim) e você escolhe uma da lista. Isso evita locais inexistentes ou digitados de forma inconsistente (ex: "Jaragua do sul" vs "Jaraguá do Sul - SC"), e guarda a latitude/longitude para uso futuro (como um mapa da árvore).

Por padrão, é preciso selecionar uma sugestão para o local ficar "verificado" (mostra um ✓ verde). Se você editar o texto manualmente sem selecionar, o campo continua sendo salvo (para não travar o cadastro de lugares antigos/rurais que o OpenStreetMap não conhece), mas fica marcado como "não verificado" — isso é intencional, já que genealogia frequentemente envolve topônimos históricos que não existem mais nos mapas atuais.

**Requisito adicional**: a extensão `php-curl` precisa estar habilitada, pois o servidor consulta a API do Nominatim em nome do usuário (`public/geocodificar.php` funciona como proxy — isso evita problemas de CORS e respeita a política de uso do Nominatim, que exige um User-Agent identificável e limita a 1 requisição por segundo).
```bash
sudo apt install php8.3-curl
sudo systemctl restart php8.3-fpm
```

## Edição de uniões e correção de idade

- Cada união/casamento agora pode ser editada depois de criada (tipo, datas, status) — botão "Editar" na seção de Cônjuges do perfil.
- Quando uma pessoa é marcada como falecida mas a data de falecimento não é conhecida, o sistema não calcula mais idade (antes isso gerava números absurdos, contando até a data de hoje).

## Fotos e documentos vinculados a mais de uma pessoa

Uma mesma mídia (ex: a certidão de casamento) pode agora ficar vinculada a várias pessoas ao mesmo tempo — no perfil de cada uma delas, ela aparece com o texto "Também vinculada a: ...". Ao enviar um novo arquivo, ele é vinculado só à pessoa atual; para vinculá-lo também a outra pessoa, vá ao perfil dela e use "Vincular um arquivo já cadastrado no sistema" (aparece uma lista dos arquivos que ainda não estão vinculados a ela). O botão "Desvincular" remove o vínculo apenas com aquela pessoa — o arquivo só é apagado de fato quando não sobra nenhum vínculo.

## Verificação de consistência do banco

Se a árvore travar o navegador (uso alto de CPU, aviso pra encerrar a aba), o motivo mais provável é uma inconsistência nos dados que confunde o algoritmo de layout da árvore — por exemplo, alguém sendo listado como ancestral de si mesmo (ciclo). `scripts/verificar_consistencia.php` varre o banco procurando especificamente por esse tipo de problema, sem corrigir nada sozinho (só relata, com sugestão de como corrigir cada caso):

```bash
php scripts/verificar_consistencia.php
```

O que ele verifica:
- 🔴 **Críticos** (causa mais provável de travamento): pessoa listada como pai/mãe de si mesma, **ciclos de ascendência** (ex: A é pai de B, B é pai de C, C é pai de A), pessoa casada consigo mesma, referências quebradas no banco.
- 🟡 **Atenção** (não trava, mas vale revisar): uniões duplicadas, mais de 2 pais biológicos pra uma pessoa, união entre alguém e seu próprio ascendente/descendente, possíveis pessoas duplicadas (mesmo nome + nascimento).
- ℹ️ **Informativo**: pessoas com número incomum de filhos/uniões cadastrados (pode indicar erro de importação, não necessariamente um problema).

Testei o script inserindo propositalmente cada um desses tipos de problema num banco de teste, incluindo um ciclo real de 3 pessoas — todos foram detectados corretamente. Termina com código de saída `1` se achar algo crítico (útil se quiser rodar via script/cron) ou `0` se estiver tudo limpo.

## Edição visual direto na árvore

Além de explorar, dá pra editar direto na árvore: botão "✏️ Editar / adicionar parentes" abre um formulário pra a pessoa atualmente centralizada, com opções de adicionar pai/mãe/cônjuge/filho (novo ou já existente) e editar nome/datas. Cada mudança é enviada pro servidor (`arvore_salvar.php`) e sincronizada com o banco de forma **aditiva segura**:

- Pessoas novas são criadas com os campos básicos (nome, sexo, datas); os demais campos (local, biografia, fotos) continuam só editáveis na tela de perfil completo.
- Editar uma pessoa já existente pela árvore só toca nos campos nome/sexo/datas — nunca apaga apelido, local, biografia ou outros dados que ela já tinha.
- Desvincular um parente pela árvore remove só a relação, nunca a pessoa em si.
- **Excluir uma pessoa não é possível pela árvore, de propósito** — a biblioteca até mostra a opção, mas o clique é interceptado e redireciona pra usar o botão "Excluir pessoa" do perfil completo (que pede confirmação). Isso evita apagar alguém sem querer durante uma edição rápida.

## Importação de GEDCOM

Existe um importador de linha de comando em `scripts/importar_gedcom.php` — não precisa passar pela interface web. Ele foi pensado pra ser seguro de rodar mesmo num banco já em uso:

```bash
# 1) Sempre teste antes com --dry-run (simula tudo, não grava nada)
php scripts/importar_gedcom.php caminho/para/arquivo.ged --dry-run

# 2) Depois de conferir que o resultado faz sentido, rode de verdade
php scripts/importar_gedcom.php caminho/para/arquivo.ged
```

O que ele faz:
- **Backup automático**: antes de qualquer alteração, roda `mysqldump` e salva em `backups/antes_importacao_<data>.sql`. Requer o binário `mysqldump` disponível no servidor.
- **Sem duplicar pessoas**: se já existe alguém com o mesmo nome completo E a mesma data de nascimento **preenchida e coincidente**, a pessoa NÃO é duplicada — o registro existente só é **atualizado nos campos que estavam vazios** (sexo, local de nascimento, data/local de falecimento, biografia). Campos já preenchidos nunca são sobrescritos. Se a data de nascimento não bate (ou nenhuma das duas tem data registrada), o importador **sempre cria uma pessoa nova**, mesmo com nome idêntico — nomes repetidos entre gerações são comuns em genealogia (ex: neto batizado com o nome do avô), e fundir duas pessoas diferentes só por causa do nome pode criar um ciclo (alguém virando pai E filho da mesma pessoa). Prefira ter uma duplicata fácil de mesclar depois a um ciclo difícil de detectar.
- **Rastreável**: toda pessoa criada pela importação é marcada com `origem = 'gedcom'` e um `importacao_id`. Toda alteração feita em pessoa já existente é logada campo a campo (valor anterior e novo) em `importacao_alteracoes`.
- **Reversível de verdade**: `scripts/reverter_importacao.php <id>` desfaz exatamente o que aquela importação fez — apaga as pessoas que ela criou, restaura os campos que ela alterou em pessoas pré-existentes, e remove as relações/uniões que ela adicionou. Não depende do backup pra isso (o backup é uma segunda rede de segurança, não a única).

```bash
# Ver o histórico de importações e seus IDs
php scripts/listar_importacoes.php

# Reverter uma importação específica
php scripts/reverter_importacao.php 3
```

Flags adicionais do importador: `--sem-backup` (pula o mysqldump, não recomendado) e `--forcar` (não pede confirmação interativa, útil em script/cron).

**Recomendação**: depois de toda importação, rode `php scripts/verificar_consistencia.php` — GEDCOMs grandes e antigos frequentemente têm nomes repetidos entre gerações sem data de nascimento, o que pode gerar pessoas duplicadas (detectável no item I do verificador) mesmo com a proteção acima.

**Limitações conhecidas**: o parser cobre o subconjunto de GEDCOM usado por praticamente todo exportador (nomes, sexo, nascimento, falecimento, uniões, filiação), mas não é 100% da especificação — não importa fontes/citações, notas longas, mídias anexadas no GEDCOM, ou múltiplos cônjuges por família além do casal principal. Datas GEDCOM aproximadas (`ABT`, `BEF`, `AFT`, `BET...AND`) são interpretadas com a melhor estimativa possível e marcadas como aproximadas na biografia da pessoa, já que o banco guarda datas exatas.

## Fotos que não existem mais em disco

Se o caminho de uma foto está salvo no banco mas o arquivo físico não existe mais (situação comum ao mover a pasta `public/uploads/` de um ambiente pra outro, já que ela normalmente não vai dentro do zip do projeto), o sistema não tenta mais carregar a imagem quebrada. `urlFotoOuPlaceholder()` (em `includes/functions.php`) confere com `file_exists()` antes de montar a URL e usa um placeholder local (SVG embutido, sem nenhuma requisição de rede) quando o arquivo não existe — isso evita 404 nos logs do nginx e bloqueios de ferramentas como fail2ban.

Se você tiver fotos "sumidas", vale conferir se a pasta `public/uploads/` foi realmente copiada pro servidor de produção (ela é ignorada pelo `.gitignore`, então não vem automaticamente com o `git pull`/deploy).

## Nomes de nascimento vs. nomes de casamento

O campo principal de cada pessoa (`nome_completo`) deve sempre ser o **nome de nascimento/batismo** — isso mantém a identidade da pessoa estável na árvore mesmo que ela tenha mudado de sobrenome por casamento (comum no Brasil, especialmente para mulheres). Sobrenomes ou nomes adotados depois (nome de casada, nome religioso etc.) são registrados separadamente na seção "Outros nomes" do perfil da pessoa, e podem ser vários (ex: mais de um casamento). Isso evita ter que escolher "qual nome" cadastrar e preserva o histórico completo.

## Cadastrando parentes que ainda não existem no sistema

Nas seções de Pais, Cônjuges e Filhos do perfil de uma pessoa, além de vincular alguém já cadastrado, há um botão "+ Cadastrar novo(a) ..." que abre o formulário de nova pessoa e, ao salvar, já cria o vínculo automaticamente — não é mais necessário cadastrar a pessoa antes e depois ir vinculá-la manualmente.

## Explorador da árvore

A página `arvore.php` usa agora um explorador SVG próprio, sem dependência de CDN externa. O layout foi projetado para leitura progressiva: uma pessoa é o foco, ascendentes ficam acima, descendentes abaixo e uniões aparecem como conexões laterais tracejadas. Isso deixa o relacionamento espacial mais previsível e evita que famílias grandes abram uma tela impossível de ler de uma só vez.

Funcionamento:
- Clique em qualquer pessoa para centralizar a árvore nela.
- Busca por nome com resultados rápidos e foco direto na pessoa encontrada.
- Controles de zoom, enquadramento, centralização, arraste e profundidade independente de ascendentes/descendentes.
- Painel lateral com datas, local, contagem de relações e acesso ao perfil completo.
- O parâmetro `?foco=ID` continua sendo aceito para links compartilháveis.
- `public/arvore_dados.php` devolve relações e metadados apenas da família ativa. O navegador calcula o subgrafo visível e desenha SVG de forma incremental, sem depender de CDN.

## Próximos passos sugeridos

- **Convites por e-mail com aceite por token**, aproveitando a tabela `convites_familia` já criada na migração.
- **Linha do tempo de eventos**, usando a tabela `eventos` já existente no schema.
- **Fontes e citações**, para conectar documentos e afirmações a registros históricos.

## Segurança

- Senhas são armazenadas com `password_hash()` (bcrypt).
- Todas as queries usam prepared statements (PDO) contra SQL injection.
- A pasta `uploads/` bloqueia execução de PHP via configuração do nginx (veja `nginx.conf.example`) — **não** use `.htaccess`, que é ignorado pelo nginx.
- Recomenda-se rodar atrás de HTTPS em produção.
