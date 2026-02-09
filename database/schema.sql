-- ============================================
-- ESQUEMA DO BANCO DE DADOS
-- Sistema de Auto-Inspeção de Farmácias
-- RDC 67/2007 - ANVISA
-- ============================================

-- Tabela de usuários (já existe, mas incluída para referência)
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cpf` VARCHAR(14) NOT NULL COMMENT 'CPF do usuário (pode ter formatação)',
  `nome` VARCHAR(255) NOT NULL,
  `senha` VARCHAR(255) NOT NULL COMMENT 'Hash bcrypt da senha',
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cpf` (`cpf`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Usuários do sistema';

-- Tabela de inspeções
CREATE TABLE IF NOT EXISTS `inspecoes` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID do usuário que realizou a inspeção',
  `farmacia_nome` VARCHAR(255) NOT NULL COMMENT 'Razão Social ou Nome Fantasia da farmácia',
  `farmacia_cnpj` VARCHAR(18) NULL COMMENT 'CNPJ da farmácia',
  `responsavel_tecnico` VARCHAR(255) NULL COMMENT 'Nome do Responsável Técnico',
  `crf` VARCHAR(20) NULL COMMENT 'CRF/UF do Responsável Técnico',
  `data_inspecao` DATE NOT NULL COMMENT 'Data da inspeção',
  `assinatura_data_url` TEXT NULL COMMENT 'Imagem da assinatura em base64 (data URL)',
  `data_assinatura` DATETIME NULL COMMENT 'Data/hora da assinatura do responsável técnico',
  `total_score` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Pontuação total obtida',
  `max_score` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Pontuação máxima possível',
  `percentage` DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT 'Percentual de aprovação',
  `grade` VARCHAR(20) NOT NULL DEFAULT 'INSUFICIENTE' COMMENT 'Conceito: EXCELENTE, BOM, REGULAR, INSUFICIENTE, REPROVADO',
  `fatal_errors_count` INT(11) NOT NULL DEFAULT 0 COMMENT 'Quantidade de erros fatais',
  `status` ENUM('RASCUNHO', 'ASSINADO', 'FINALIZADO') NOT NULL DEFAULT 'RASCUNHO',
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_data_inspecao` (`data_inspecao`),
  KEY `idx_status` (`status`),
  KEY `idx_farmacia_cnpj` (`farmacia_cnpj`),
  CONSTRAINT `fk_inspecoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inspeções realizadas';

-- Tabela de respostas das questões
CREATE TABLE IF NOT EXISTS `inspecao_respostas` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `inspecao_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID da inspeção',
  `question_id` VARCHAR(50) NOT NULL COMMENT 'ID da questão (ex: 1.1, 2.3, etc)',
  `category` VARCHAR(50) NOT NULL COMMENT 'Categoria da questão',
  `question_type` VARCHAR(20) NOT NULL COMMENT 'Tipo: SIM_NAO, INF, R, I, N',
  `question_text` TEXT NOT NULL COMMENT 'Texto da questão',
  `weight` INT(11) NOT NULL DEFAULT 1 COMMENT 'Peso da questão (1-5)',
  `is_fatal` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Se é erro fatal',
  `answer` TEXT NULL COMMENT 'Resposta: true/false para boolean, texto para INF',
  `answer_boolean` TINYINT(1) NULL COMMENT 'Resposta como boolean (para facilitar consultas)',
  `suggestion` TEXT NULL COMMENT 'Sugestão caso seja erro fatal',
  `score` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Pontuação obtida nesta questão',
  `max_score` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Pontuação máxima desta questão',
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inspecao` (`inspecao_id`),
  KEY `idx_question_id` (`question_id`),
  KEY `idx_category` (`category`),
  KEY `idx_is_fatal` (`is_fatal`),
  CONSTRAINT `fk_respostas_inspecao` FOREIGN KEY (`inspecao_id`) REFERENCES `inspecoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Respostas das questões de cada inspeção';

-- Tabela de pontuações por categoria
CREATE TABLE IF NOT EXISTS `inspecao_categoria_scores` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `inspecao_id` INT(11) UNSIGNED NOT NULL COMMENT 'ID da inspeção',
  `category` VARCHAR(50) NOT NULL COMMENT 'Categoria da questão',
  `category_name` VARCHAR(255) NOT NULL COMMENT 'Nome legível da categoria',
  `score` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Pontuação obtida na categoria',
  `max_score` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Pontuação máxima da categoria',
  `percentage` DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT 'Percentual da categoria',
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inspecao` (`inspecao_id`),
  KEY `idx_category` (`category`),
  CONSTRAINT `fk_categoria_scores_inspecao` FOREIGN KEY (`inspecao_id`) REFERENCES `inspecoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pontuações por categoria de cada inspeção';

-- Índices adicionais para melhor performance
CREATE INDEX `idx_inspecoes_usuario_data` ON `inspecoes` (`usuario_id`, `data_inspecao` DESC);
CREATE INDEX `idx_respostas_inspecao_fatal` ON `inspecao_respostas` (`inspecao_id`, `is_fatal`);
