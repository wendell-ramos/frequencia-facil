-- Executado automaticamente pelo MySQL na PRIMEIRA vez que o volume do banco é criado
-- (pasta montada em /docker-entrypoint-initdb.d no docker-compose.yml).
-- Mudou o schema? Crie um novo arquivo numerado (ex.: 003_add_coluna.sql) e
-- avise o grupo: em quem já tem o volume, rode à mão ou `docker compose down -v`.

CREATE TABLE IF NOT EXISTS alunos (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome             VARCHAR(120) NOT NULL,
    telefone         VARCHAR(20)  NOT NULL,
    data_nascimento  DATE NULL,
    criado_em        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_alunos_nome_telefone UNIQUE (nome, telefone)          -- US01: sem duplicados
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS presencas (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aluno_id   INT UNSIGNED NOT NULL,
    data_aula  DATE NOT NULL,
    presente   BOOLEAN NOT NULL,
    criado_em  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_presencas_aluno FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    CONSTRAINT uq_presencas_aluno_data UNIQUE (aluno_id, data_aula)     -- US02: 1 registro por dia
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
