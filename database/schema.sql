-- ============================================
-- Árvore Familiar - Schema MariaDB
-- ============================================

CREATE DATABASE IF NOT EXISTS arvore_familiar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE arvore_familiar;

-- Usuários que podem acessar e editar o sistema
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Pessoas cadastradas na árvore (vivas ou falecidas)
-- nome_completo deve ser o NOME DE NASCIMENTO/BATISMO (não o nome de casada/casado),
-- para manter a identificação da pessoa estável independente de casamentos.
-- Nomes adotados por casamento ficam na tabela `nomes_pessoa`.
CREATE TABLE pessoas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_completo VARCHAR(200) NOT NULL,
    apelido VARCHAR(100) NULL,
    sexo ENUM('M', 'F', 'Outro', 'Desconhecido') DEFAULT 'Desconhecido',
    data_nascimento DATE NULL,
    local_nascimento VARCHAR(200) NULL,
    local_nascimento_lat DECIMAL(10,7) NULL,
    local_nascimento_lng DECIMAL(10,7) NULL,
    data_falecimento DATE NULL,
    local_falecimento VARCHAR(200) NULL,
    local_falecimento_lat DECIMAL(10,7) NULL,
    local_falecimento_lng DECIMAL(10,7) NULL,
    falecido TINYINT(1) DEFAULT 0,
    foto_perfil VARCHAR(255) NULL,
    biografia TEXT NULL,
    criado_por INT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_nome (nome_completo)
) ENGINE=InnoDB;

-- Relacionamentos de parentesco (pai/mãe -> filho)
-- Modelo flexível: cada linha é "pessoa_id é filho(a) de pai_mae_id"
CREATE TABLE relacoes_parentais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filho_id INT NOT NULL,
    pai_mae_id INT NOT NULL,
    tipo ENUM('biologico', 'adotivo', 'padrasto_madrasta') DEFAULT 'biologico',
    FOREIGN KEY (filho_id) REFERENCES pessoas(id) ON DELETE CASCADE,
    FOREIGN KEY (pai_mae_id) REFERENCES pessoas(id) ON DELETE CASCADE,
    UNIQUE KEY unico_relacao (filho_id, pai_mae_id)
) ENGINE=InnoDB;

-- Relacionamentos conjugais (casamentos, uniões)
CREATE TABLE unioes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pessoa1_id INT NOT NULL,
    pessoa2_id INT NOT NULL,
    tipo ENUM('casamento', 'uniao_estavel', 'namoro', 'outro') DEFAULT 'casamento',
    data_inicio DATE NULL,
    data_fim DATE NULL,
    status ENUM('ativo', 'divorciado', 'viuvo', 'encerrado') DEFAULT 'ativo',
    FOREIGN KEY (pessoa1_id) REFERENCES pessoas(id) ON DELETE CASCADE,
    FOREIGN KEY (pessoa2_id) REFERENCES pessoas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Nomes adicionais que a pessoa adotou ao longo da vida (nome de casada, nome religioso etc.)
-- Permite registrar mais de um, já que uma pessoa pode ter se casado mais de uma vez.
CREATE TABLE nomes_pessoa (
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

-- Documentos e fotos. Uma mídia pode estar vinculada a mais de uma pessoa
-- (ex: certidão de casamento vinculada ao marido E à esposa) via midia_pessoa.
CREATE TABLE midias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('foto', 'documento') DEFAULT 'foto',
    caminho_arquivo VARCHAR(255) NOT NULL,
    titulo VARCHAR(200) NULL,
    descricao TEXT NULL,
    enviado_por INT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enviado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE midia_pessoa (
    midia_id INT NOT NULL,
    pessoa_id INT NOT NULL,
    PRIMARY KEY (midia_id, pessoa_id),
    FOREIGN KEY (midia_id) REFERENCES midias(id) ON DELETE CASCADE,
    FOREIGN KEY (pessoa_id) REFERENCES pessoas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Eventos da vida (opcional, para linha do tempo futura)
CREATE TABLE eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pessoa_id INT NOT NULL,
    tipo_evento VARCHAR(100) NOT NULL,
    data_evento DATE NULL,
    local VARCHAR(200) NULL,
    descricao TEXT NULL,
    FOREIGN KEY (pessoa_id) REFERENCES pessoas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
