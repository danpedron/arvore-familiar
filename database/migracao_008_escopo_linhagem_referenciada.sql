-- Escopo de linhagem associado a cada referência comunitária.
-- A associação continua em familia_pessoas; esta tabela registra a causa
-- para que pais/ancestrais possam ser removidos apenas quando não forem mais
-- necessários por nenhuma referência do espaço.

CREATE TABLE IF NOT EXISTS familia_pessoa_escopos (
    familia_id INT NOT NULL,
    referencia_raiz_id INT NOT NULL,
    pessoa_id INT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (familia_id, referencia_raiz_id, pessoa_id),
    INDEX idx_escopos_pessoa (familia_id, pessoa_id),
    INDEX idx_escopos_raiz (familia_id, referencia_raiz_id),
    FOREIGN KEY (familia_id) REFERENCES familias(id) ON DELETE CASCADE,
    FOREIGN KEY (referencia_raiz_id) REFERENCES pessoas(id) ON DELETE CASCADE,
    FOREIGN KEY (pessoa_id) REFERENCES pessoas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Referências existentes antes desta migração continuam sendo raízes
-- explícitas. A expansão usa somente o componente de relações parentais
-- biológicas/adotivas e não altera pessoas nem relações originais.
INSERT IGNORE INTO familia_pessoa_escopos (familia_id, referencia_raiz_id, pessoa_id)
SELECT fp.familia_id, fp.pessoa_id, fp.pessoa_id
FROM familia_pessoas fp
WHERE fp.tipo = 'referenciada';

DROP TEMPORARY TABLE IF EXISTS tmp_familia_linhagem;
CREATE TEMPORARY TABLE tmp_familia_linhagem (
    familia_id INT NOT NULL,
    referencia_raiz_id INT NOT NULL,
    pessoa_id INT NOT NULL,
    PRIMARY KEY (familia_id, referencia_raiz_id, pessoa_id)
) ENGINE=InnoDB;

INSERT IGNORE INTO tmp_familia_linhagem (familia_id, referencia_raiz_id, pessoa_id)
WITH RECURSIVE linhagem AS (
    SELECT fp.familia_id, fp.pessoa_id AS referencia_raiz_id, fp.pessoa_id
    FROM familia_pessoas fp
    WHERE fp.tipo = 'referenciada'
    UNION DISTINCT
    SELECT l.familia_id, l.referencia_raiz_id,
           CASE WHEN rp.filho_id = l.pessoa_id THEN rp.pai_mae_id ELSE rp.filho_id END
    FROM linhagem l
    JOIN relacoes_parentais rp
      ON (rp.filho_id = l.pessoa_id OR rp.pai_mae_id = l.pessoa_id)
     AND rp.tipo IN ('biologico', 'adotivo')
)
SELECT familia_id, referencia_raiz_id, pessoa_id
FROM linhagem;

INSERT IGNORE INTO familia_pessoa_escopos (familia_id, referencia_raiz_id, pessoa_id)
SELECT familia_id, referencia_raiz_id, pessoa_id
FROM tmp_familia_linhagem;

INSERT IGNORE INTO familia_pessoas (familia_id, pessoa_id, tipo, referenciada_por)
SELECT DISTINCT l.familia_id, l.pessoa_id, 'referenciada', NULL
FROM tmp_familia_linhagem l
JOIN pessoas p ON p.id = l.pessoa_id
WHERE NOT EXISTS (
    SELECT 1 FROM familia_pessoas fp
    WHERE fp.familia_id = l.familia_id AND fp.pessoa_id = l.pessoa_id
);

DROP TEMPORARY TABLE tmp_familia_linhagem;

INSERT INTO auditoria (familia_id, usuario_id, entidade, acao, detalhes)
SELECT f.id, f.criado_por, 'familia_pessoa_escopos', 'migracao_escopo_linhagem',
       JSON_OBJECT('raizes_associadas', COUNT(DISTINCT e.referencia_raiz_id), 'pessoas_mapeadas', COUNT(*))
FROM familias f
LEFT JOIN familia_pessoa_escopos e ON e.familia_id = f.id
GROUP BY f.id, f.criado_por;
