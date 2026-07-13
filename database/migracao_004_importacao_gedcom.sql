-- Migração: adiciona suporte a importação (ex: GEDCOM) rastreável e reversível.
-- Rode este script apenas se você já tinha uma instalação anterior.

USE arvore_familiar;

CREATE TABLE IF NOT EXISTS importacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('gedcom') DEFAULT 'gedcom',
    arquivo_original VARCHAR(255) NOT NULL,
    status ENUM('em_andamento', 'concluida', 'revertida', 'erro') DEFAULT 'em_andamento',
    backup_arquivo VARCHAR(255) NULL,
    pessoas_criadas INT DEFAULT 0,
    pessoas_atualizadas INT DEFAULT 0,
    relacoes_criadas INT DEFAULT 0,
    unioes_criadas INT DEFAULT 0,
    nomes_criados INT DEFAULT 0,
    observacoes TEXT NULL,
    iniciado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    finalizado_em DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS importacao_alteracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    importacao_id INT NOT NULL,
    pessoa_id INT NOT NULL,
    campo VARCHAR(50) NOT NULL,
    valor_anterior TEXT NULL,
    valor_novo TEXT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (importacao_id) REFERENCES importacoes(id) ON DELETE CASCADE,
    FOREIGN KEY (pessoa_id) REFERENCES pessoas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Nota: rode esta migração apenas UMA vez (ADD CONSTRAINT não tem IF NOT EXISTS
-- garantido em todas as versões do MariaDB, ao contrário de ADD COLUMN).

ALTER TABLE pessoas
    ADD COLUMN IF NOT EXISTS origem ENUM('manual', 'gedcom') NOT NULL DEFAULT 'manual' AFTER biografia,
    ADD COLUMN IF NOT EXISTS importacao_id INT NULL AFTER origem,
    ADD COLUMN IF NOT EXISTS gedcom_id VARCHAR(30) NULL AFTER importacao_id;

ALTER TABLE pessoas
    ADD CONSTRAINT fk_pessoas_importacao FOREIGN KEY (importacao_id) REFERENCES importacoes(id) ON DELETE SET NULL;

ALTER TABLE relacoes_parentais
    ADD COLUMN IF NOT EXISTS importacao_id INT NULL;
ALTER TABLE relacoes_parentais
    ADD CONSTRAINT fk_relacoes_importacao FOREIGN KEY (importacao_id) REFERENCES importacoes(id) ON DELETE SET NULL;

ALTER TABLE unioes
    ADD COLUMN IF NOT EXISTS importacao_id INT NULL;
ALTER TABLE unioes
    ADD CONSTRAINT fk_unioes_importacao FOREIGN KEY (importacao_id) REFERENCES importacoes(id) ON DELETE SET NULL;

ALTER TABLE nomes_pessoa
    ADD COLUMN IF NOT EXISTS importacao_id INT NULL;
ALTER TABLE nomes_pessoa
    ADD CONSTRAINT fk_nomes_importacao FOREIGN KEY (importacao_id) REFERENCES importacoes(id) ON DELETE SET NULL;
