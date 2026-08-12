-- Migração 006 — explorador de árvore e referências externas
-- Executar uma única vez após migracao_005_enterprise.sql.

ALTER TABLE importacoes
    MODIFY tipo ENUM('gedcom', 'json') NOT NULL DEFAULT 'gedcom';

ALTER TABLE importacoes
    ADD COLUMN familia_id INT NULL AFTER id,
    ADD COLUMN usuario_id INT NULL AFTER familia_id;

UPDATE importacoes i
LEFT JOIN pessoas p ON p.importacao_id = i.id
SET i.familia_id = p.familia_id
WHERE i.familia_id IS NULL AND p.familia_id IS NOT NULL;

ALTER TABLE importacoes
    ADD INDEX idx_importacoes_familia (familia_id, iniciado_em),
    ADD CONSTRAINT fk_importacoes_familia FOREIGN KEY (familia_id) REFERENCES familias(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_importacoes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS arvores_seguidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    familia_id INT NOT NULL,
    usuario_id INT NOT NULL,
    nome VARCHAR(180) NOT NULL,
    url VARCHAR(1000) NOT NULL,
    observacao VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_arvore_seguida (familia_id, usuario_id, url(255)),
    INDEX idx_arvores_seguidas_familia (familia_id, usuario_id),
    FOREIGN KEY (familia_id) REFERENCES familias(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
