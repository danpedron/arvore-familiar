-- Migração: permite vincular uma mídia (foto/documento) a mais de uma pessoa
-- (ex: certidão de casamento vinculada ao marido e à esposa).
-- Rode este script apenas se você já tinha uma instalação anterior.

USE arvore_familiar;

CREATE TABLE IF NOT EXISTS midia_pessoa (
    midia_id INT NOT NULL,
    pessoa_id INT NOT NULL,
    PRIMARY KEY (midia_id, pessoa_id),
    FOREIGN KEY (midia_id) REFERENCES midias(id) ON DELETE CASCADE,
    FOREIGN KEY (pessoa_id) REFERENCES pessoas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Migra os vínculos existentes (cada mídia já tinha uma pessoa_id única)
INSERT IGNORE INTO midia_pessoa (midia_id, pessoa_id)
SELECT id, pessoa_id FROM midias WHERE pessoa_id IS NOT NULL;

-- A coluna pessoa_id na tabela midias fica obsoleta (não é mais usada pelo código),
-- mas deixamos ela aqui em vez de apagar, para não arriscar perda de dados.
-- Se quiser removê-la manualmente depois de confirmar que está tudo certo:
--   ALTER TABLE midias DROP FOREIGN KEY midias_ibfk_1; -- confira o nome real da constraint antes
--   ALTER TABLE midias DROP COLUMN pessoa_id;
ALTER TABLE midias MODIFY pessoa_id INT NULL;
