-- Árvore Familiar — referências de pessoas entre espaços familiares
-- Permite reutilizar um registro sem duplicá-lo. A família de origem permanece
-- proprietária dos dados; os demais espaços recebem uma associação somente leitura.

CREATE TABLE IF NOT EXISTS familia_pessoas (
    familia_id INT NOT NULL,
    pessoa_id INT NOT NULL,
    tipo ENUM('propria', 'referenciada') NOT NULL DEFAULT 'referenciada',
    referenciada_por INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (familia_id, pessoa_id),
    INDEX idx_familia_pessoas_pessoa (pessoa_id),
    INDEX idx_familia_pessoas_origem (familia_id, tipo),
    FOREIGN KEY (familia_id) REFERENCES familias(id) ON DELETE CASCADE,
    FOREIGN KEY (pessoa_id) REFERENCES pessoas(id) ON DELETE CASCADE,
    FOREIGN KEY (referenciada_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Todas as pessoas já existentes continuam pertencendo ao seu espaço original.
INSERT IGNORE INTO familia_pessoas (familia_id, pessoa_id, tipo)
SELECT p.familia_id, p.id, 'propria'
FROM pessoas p;

-- Os novos registros criados depois da migração devem ser associados pela aplicação.
-- A restrição lógica de que uma pessoa própria só pertence a pessoas.familia_id
-- permanece garantida na camada de domínio, evitando duplicação acidental.

INSERT INTO auditoria (familia_id, usuario_id, entidade, acao, detalhes)
SELECT f.id, f.criado_por, 'familia_pessoas', 'migracao_referencias',
       JSON_OBJECT('pessoas_associadas', COUNT(fp.pessoa_id))
FROM familias f
LEFT JOIN familia_pessoas fp ON fp.familia_id = f.id
GROUP BY f.id, f.criado_por;

-- Para rollback manual, remova apenas associações tipo referenciada; as pessoas
-- e as associações próprias não devem ser apagadas por esse rollback.
-- DELETE FROM familia_pessoas WHERE tipo = 'referenciada';
