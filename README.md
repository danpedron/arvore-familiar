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
│   └── database.php       # Configuração de conexão
├── database/
│   └── schema.sql          # Estrutura do banco de dados
├── includes/
│   ├── auth.php             # Login, registro, sessão
│   └── functions.php        # CRUD de pessoas, relações, mídias
├── public/                  # Raiz pública do site (aponte o nginx para cá)
│   ├── index.php             # Listagem de pessoas
│   ├── login.php / registro.php / logout.php
│   ├── pessoa.php            # Perfil da pessoa + relações + mídias
│   ├── pessoa_editar.php     # Criar/editar pessoa
│   ├── arvore.php            # Visualização gráfica da árvore (D3.js)
│   ├── arvore_dados.php      # Endpoint JSON consumido pela árvore
│   ├── geocodificar.php      # Proxy para busca de locais (Nominatim/OSM)
│   ├── js/busca-local.js     # Autocomplete de locais no formulário
│   ├── css/style.css
│   └── uploads/               # Fotos e documentos enviados
├── nginx.conf.example       # Configuração de referência para nginx
└── README.md
```

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

## Nomes de nascimento vs. nomes de casamento

O campo principal de cada pessoa (`nome_completo`) deve sempre ser o **nome de nascimento/batismo** — isso mantém a identidade da pessoa estável na árvore mesmo que ela tenha mudado de sobrenome por casamento (comum no Brasil, especialmente para mulheres). Sobrenomes ou nomes adotados depois (nome de casada, nome religioso etc.) são registrados separadamente na seção "Outros nomes" do perfil da pessoa, e podem ser vários (ex: mais de um casamento). Isso evita ter que escolher "qual nome" cadastrar e preserva o histórico completo.

## Cadastrando parentes que ainda não existem no sistema

Nas seções de Pais, Cônjuges e Filhos do perfil de uma pessoa, além de vincular alguém já cadastrado, há um botão "+ Cadastrar novo(a) ..." que abre o formulário de nova pessoa e, ao salvar, já cria o vínculo automaticamente — não é mais necessário cadastrar a pessoa antes e depois ir vinculá-la manualmente.

## Visualização da árvore

A página `arvore.php` desenha a árvore genealógica em um layout hierárquico fixo (estilo genograma/organograma, parecido com o MyHeritage): cada geração fica em uma linha, pessoas são caixas retangulares com foto e nome, casais ficam lado a lado, e a posição horizontal de cada pessoa é calculada a partir da posição média de seus pais (algoritmo de "baricentro"), o que mantém a árvore organizada mesmo em famílias grandes — nada de nós flutuando aleatoriamente. Linhas verdes sólidas com barramento indicam filiação (pai/mãe → filhos); linhas laranja tracejadas indicam uniões/casamentos. Use os botões de zoom ou a roda do mouse, e arraste para navegar.

## Próximos passos sugeridos (não incluídos nesta versão inicial)

- **Importação/exportação GEDCOM**, caso queira migrar dados de/para o MyHeritage no futuro.
- **Controle de permissões por usuário**, caso queira restringir quem edita o quê (hoje todo usuário logado pode editar tudo).
- **Linha do tempo de eventos** (a tabela `eventos` já existe no schema, mas ainda não tem interface).

## Segurança

- Senhas são armazenadas com `password_hash()` (bcrypt).
- Todas as queries usam prepared statements (PDO) contra SQL injection.
- A pasta `uploads/` bloqueia execução de PHP via configuração do nginx (veja `nginx.conf.example`) — **não** use `.htaccess`, que é ignorado pelo nginx.
- Recomenda-se rodar atrás de HTTPS em produção.
