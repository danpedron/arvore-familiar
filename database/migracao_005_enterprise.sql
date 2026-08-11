-- Árvore Familiar — migração enterprise / multi-família
-- Execute uma única vez após as migrações 001–004.
-- A migração preserva as pessoas existentes dentro de uma família padrão.

CREATE TABLE IF NOT EXISTS familias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(180) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    descricao VARCHAR(500) NULL,
    criado_por INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_familias_nome (nome),
    FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO familias (nome, slug, descricao)
SELECT 'Família principal', 'familia-principal', 'Espaço criado automaticamente durante a migração.'
WHERE NOT EXISTS (SELECT 1 FROM familias LIMIT 1);

ALTER TABLE pessoas ADD COLUMN familia_id INT NULL AFTER id;

SET @familia_padrao = (SELECT id FROM familias ORDER BY id LIMIT 1);
UPDATE pessoas SET familia_id = @familia_padrao WHERE familia_id IS NULL;
ALTER TABLE pessoas MODIFY familia_id INT NOT NULL;
ALTER TABLE pessoas ADD CONSTRAINT fk_pessoas_familia FOREIGN KEY (familia_id) REFERENCES familias(id) ON DELETE CASCADE;
ALTER TABLE pessoas ADD INDEX idx_pessoas_familia_nome (familia_id, nome_completo);

CREATE TABLE IF NOT EXISTS familia_usuarios (
    familia_id INT NOT NULL,
    usuario_id INT NOT NULL,
    papel ENUM('owner', 'editor', 'viewer') NOT NULL DEFAULT 'viewer',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (familia_id, usuario_id),
    INDEX idx_familia_usuarios_usuario (usuario_id),
    FOREIGN KEY (familia_id) REFERENCES familias(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO familia_usuarios (familia_id, usuario_id, papel)
SELECT @familia_padrao, id, 'owner' FROM usuarios;

CREATE TABLE IF NOT EXISTS convites_familia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    familia_id INT NOT NULL,
    email VARCHAR(150) NOT NULL,
    papel ENUM('editor', 'viewer') NOT NULL DEFAULT 'viewer',
    token_hash CHAR(64) NOT NULL UNIQUE,
    convidado_por INT NULL,
    aceito_em DATETIME NULL,
    expirado_em DATETIME NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_convites_email (email, aceito_em),
    FOREIGN KEY (familia_id) REFERENCES familias(id) ON DELETE CASCADE,
    FOREIGN KEY (convidado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auditoria (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    familia_id INT NOT NULL,
    usuario_id INT NULL,
    entidade VARCHAR(60) NOT NULL,
    entidade_id INT NULL,
    acao VARCHAR(60) NOT NULL,
    detalhes JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_auditoria_familia_data (familia_id, criado_em),
    INDEX idx_auditoria_entidade (entidade, entidade_id),
    FOREIGN KEY (familia_id) REFERENCES familias(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relações, uniões e mídias passam a ter o escopo derivado pelas pessoas vinculadas.
-- Os índices abaixo reduzem o custo dos principais caminhos da árvore.
ALTER TABLE relacoes_parentais ADD INDEX idx_relacoes_filho (filho_id), ADD INDEX idx_relacoes_pai (pai_mae_id);
ALTER TABLE unioes ADD INDEX idx_unioes_pessoa1 (pessoa1_id), ADD INDEX idx_unioes_pessoa2 (pessoa2_id);
ALTER TABLE midia_pessoa ADD INDEX idx_midia_pessoa_pessoa (pessoa_id);

-- Recomendado após a migração: revisar a família padrão e seus membros no painel.
