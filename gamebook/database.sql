-- GameBook : structure de la base de données
-- À importer dans phpMyAdmin (onglet "Importer") ou à coller dans l'onglet "SQL".

CREATE DATABASE IF NOT EXISTS gamebook CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gamebook;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS games (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  igdb_id INT UNSIGNED NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  description TEXT,
  release_date DATE NULL,
  platforms VARCHAR(500),
  genres VARCHAR(255),
  developer VARCHAR(255),
  rating DECIMAL(3,1) NULL,
  reviews INT UNSIGNED NOT NULL DEFAULT 0,
  cover_url VARCHAR(500),
  images TEXT NULL,
  modes VARCHAR(255) NULL,
  perspectives VARCHAR(255) NULL,
  themes VARCHAR(255) NULL,
  publisher VARCHAR(255) NULL,
  franchise VARCHAR(255) NULL,
  details MEDIUMTEXT NULL,
  INDEX idx_title (title),
  INDEX idx_reviews (reviews),
  INDEX idx_franchise (franchise)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS favorites (
  user_id INT UNSIGNED NOT NULL,
  game_id INT UNSIGNED NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, game_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
