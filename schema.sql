-- Schema for checklist POC
CREATE DATABASE IF NOT EXISTS checklist_vallourec DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE checklist_vallourec;

CREATE TABLE IF NOT EXISTS checklists (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  area VARCHAR(255),
  nome VARCHAR(255),
  matricula VARCHAR(100),
  setor VARCHAR(255),
  responsavel VARCHAR(255),
  obs_geral TEXT,
  signature_path VARCHAR(512),
  planilha_path VARCHAR(512),
  pdf_path VARCHAR(512),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS checklist_items (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  checklist_id BIGINT NOT NULL,
  description TEXT,
  status VARCHAR(50),
  obs TEXT,
  FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS checklist_item_photos (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT NOT NULL,
  path VARCHAR(512) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES checklist_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
