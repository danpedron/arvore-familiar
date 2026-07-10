-- Migração: adiciona suporte a nomes adicionais (nome de casada, religioso etc.)
-- Rode este script apenas se você já tinha importado o schema.sql anterior
-- (sem a tabela nomes_pessoa). Se for uma instalação nova, ignore este arquivo
-- e use apenas o schema.sql.

USE arvore_familiar;

CREATE TABLE IF NOT EXISTS nomes_pessoa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pessoa_id INT NOT NULL,
    nome VARCHAR(200) NOT NULL,
    tipo ENUM('casamento', 'religioso', 'profissional', 'outro') DEFAULT 'casamento',
    uniao_id INT NULL,
    observacao VARCHAR(200) NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pessoa_id) REFERENCES pessoas(id) ON DELETE CASCADE,
    FOREIGN KEY (uniao_id) REFERENCES unioes(id) ON DELETE SET NULL
) ENGINE=InnoDB;
