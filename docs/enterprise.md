# Árvore Familiar Enterprise

Esta versão mantém o projeto simples de hospedar com nginx, PHP-FPM 8.3 e MariaDB, mas introduz uma separação clara entre autenticação, espaços de família, domínio genealógico e visualização. O objetivo é permitir que diferentes famílias usem a mesma instalação sem misturar pessoas, relações, mídias ou permissões.

## O que mudou

A aplicação agora possui **espaços de família**. Uma conta pode participar de vários espaços e cada espaço tem seus próprios dados. O papel `owner` administra membros e pode compartilhar o espaço; `editor` cria e atualiza pessoas, relações e mídias; `viewer` acessa a árvore e os perfis em modo somente leitura.

A árvore não depende de biblioteca carregada por CDN. O renderer `public/js/tree-view.js` usa cartões HTML legíveis sobre uma camada SVG de conexões: ascendentes acima, descendentes abaixo, cônjuges lateralmente e a pessoa selecionada sempre destacada. A navegação inclui busca, foco por URL, zoom, pan, enquadramento, painel recolhível e profundidade independente de gerações. Os cartões também podem ser percorridos por teclado com as setas; Enter abre o perfil.

A tabela `auditoria` registra as principais criações, alterações e exclusões vinculadas ao espaço ativo. O isolamento é aplicado no domínio por `familia_id`, nas relações verificando as duas pessoas e nas mídias verificando o vínculo com a pessoa da família atual.

## Atualização em uma instalação existente

Faça o backup antes da migração. A migração foi escrita para preservar as pessoas existentes no espaço padrão e associar os usuários atuais como proprietários desse espaço.

```bash
cd /var/www/arvore
mysqldump --single-transaction --routines --events -u root -p arvore_familiar \
  > /var/backups/arvore-familiar-$(date +%Y%m%d-%H%M%S).sql
mysql -u root -p arvore_familiar < database/migracao_005_enterprise.sql
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl restart php8.3-fpm
```

Substitua `arvore_familiar` pelo nome real definido em `config/database.php`. Execute `migracao_005_enterprise.sql` uma única vez por banco. Depois entre em `/familias.php`, confirme o espaço padrão, crie novos espaços se necessário e compartilhe pelo e-mail de outra conta já cadastrada.

## Publicação via Git

A raiz pública do nginx deve ser `/var/www/arvore/public`; diretórios `config`, `includes`, `database`, `scripts` e `backups` não devem ser servidos pela web. Um fluxo de atualização simples é:

```bash
cd /var/www/arvore
git fetch origin
git checkout main
git pull --ff-only origin main
sudo chown -R www-data:www-data public/uploads
sudo nginx -t && sudo systemctl reload nginx
sudo systemctl restart php8.3-fpm
```

Para rollback, retorne ao commit anterior e recarregue os serviços:

```bash
git log --oneline -5
git checkout <commit-anterior>
sudo nginx -t && sudo systemctl reload nginx
sudo systemctl restart php8.3-fpm
```

Um rollback de código **não desfaz automaticamente uma migração de banco**. Por isso, migrações devem ser executadas somente depois do backup e devem ser tratadas como mudanças permanentes do schema.

## Configuração do nginx

Use `nginx.conf.example` como base e ajuste `server_name`, `root` e o socket do PHP-FPM. O arquivo bloqueia arquivos ocultos e diretórios internos, impede execução de PHP dentro de `uploads`, adiciona headers básicos de segurança e usa cache apenas para assets estáticos.

O HTTPS deve terminar no nginx ou em um proxy reverso confiável. Em produção, mantenha `config/database.php` fora do Git e com permissões restritas, por exemplo `640`, pertencendo ao usuário do deploy e ao grupo do PHP-FPM.

## Limitações deliberadas da primeira versão enterprise

O compartilhamento atual adiciona imediatamente uma conta já existente ao espaço. A tabela `convites_familia` está preparada para uma próxima etapa de convite por token e aceite por e-mail. Também continuam como evoluções futuras a linha do tempo de eventos, fontes históricas e importação/exportação GEDCOM pela interface web.
