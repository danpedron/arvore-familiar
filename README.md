# Árvore Familiar — Genealogia Self-Hosted

Sistema simples e leve para registrar sua árvore genealógica, com fotos e documentos por pessoa, feito para uso em família (multi-usuário, todos podem editar).

## Requisitos

- PHP 8.0+ com extensão PDO MySQL
- MariaDB 10.x (ou MySQL 5.7+)
- Servidor web (Apache/Nginx) ou o servidor embutido do PHP

## Instalação

1. **Criar o banco de dados**

   Importe o schema:
   ```bash
   mysql -u root -p < database/schema.sql
   ```

2. **Configurar a conexão**

   Edite `config/database.php` com suas credenciais do MariaDB:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'arvore_familiar');
   define('DB_USER', 'seu_usuario');
   define('DB_PASS', 'sua_senha');
   ```

3. **Permissões da pasta de uploads**

   ```bash
   chmod -R 755 public/uploads
   ```

4. **Rodar o servidor**

   Para testar localmente sem Apache/Nginx:
   ```bash
   cd public
   php -S localhost:8000
   ```
   Acesse: http://localhost:8000

   Em produção, aponte o DocumentRoot do Apache/Nginx para a pasta `public/`.

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
├── public/                  # Raiz pública do site
│   ├── index.php             # Listagem de pessoas
│   ├── login.php / registro.php / logout.php
│   ├── pessoa.php            # Perfil da pessoa + relações + mídias
│   ├── pessoa_editar.php     # Criar/editar pessoa
│   ├── css/style.css
│   └── uploads/               # Fotos e documentos enviados
└── README.md
```

## Como usar

1. Crie uma conta (todos os membros da família podem ter a sua) em `registro.php`.
2. Cadastre a primeira pessoa em "+ Nova pessoa".
3. No perfil de cada pessoa, você pode:
   - Vincular pais, cônjuges e filhos (basta selecionar pessoas já cadastradas)
   - Fazer upload de fotos e documentos (certidões, escrituras etc.)
   - Editar dados biográficos e biografia livre

## Próximos passos sugeridos (não incluídos nesta versão inicial)

- **Visualização gráfica da árvore** (hoje a navegação é por links entre perfis — funcional, mas não é uma árvore visual). Pode ser adicionada com D3.js ou a biblioteca `family-chart`, consumindo os dados via um endpoint JSON.
- **Importação/exportação GEDCOM**, caso queira migrar dados de/para o MyHeritage no futuro.
- **Controle de permissões por usuário**, caso queira restringir quem edita o quê (hoje todo usuário logado pode editar tudo).
- **Linha do tempo de eventos** (a tabela `eventos` já existe no schema, mas ainda não tem interface).

## Segurança

- Senhas são armazenadas com `password_hash()` (bcrypt).
- Todas as queries usam prepared statements (PDO) contra SQL injection.
- A pasta `uploads/` bloqueia execução de PHP via `.htaccess`.
- Recomenda-se rodar atrás de HTTPS em produção.
