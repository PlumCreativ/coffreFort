
CREATE DATABASE IF NOT EXISTS `coffreFort` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `coffreFort`;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `pass_hash` VARCHAR(255) NOT NULL,
  `quota_total` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `quota_used` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

DROP TABLE IF EXISTS `folders`;
CREATE TABLE `folders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `parent_id` INT UNSIGNED NULL,
  `name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_folders_user` FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT `fk_folders_parent` FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE CASCADE
);

DROP TABLE IF EXISTS `files`;
CREATE TABLE `files` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `folder_id` INT UNSIGNED NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(150) NOT NULL,
  -- type => mime
  `mime` VARCHAR(255) NOT NULL,
  `size` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_files_user` FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT `fk_files_folder` FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL
);

DROP TABLE IF EXISTS `file_versions`;
CREATE TABLE `file_versions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `file_id` INT UNSIGNED NOT NULL,
  `version` INT UNSIGNED NOT NULL,
  `stored_name` VARCHAR(255) NOT NULL,
  `id_last_version` VARBINARY(32) NOT NULL,
  `checksum` CHAR(64) NOT NULL, 
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_file_versions_file` FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
  UNIQUE KEY `uniq_file_version` (file_id, version)
);

DROP TABLE IF EXISTS `shares`;
CREATE TABLE `shares` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `kind` ENUM('file', 'folder') NOT NULL,
  `target_id` INT UNSIGNED NOT NULL,
  `token` CHAR(64) NOT NULL UNIQUE,
  `label` VARCHAR(255) NULL,
  `expires_at` DATETIME NULL,
  `max_uses` INT UNSIGNED NULL,
  `remaining_uses` INT UNSIGNED NULL,
  `is_revoked` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_shares_user` FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

DROP TABLE IF EXISTS `downloads_log`;
CREATE TABLE `downloads_log` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `share_id` INT UNSIGNED NOT NULL,
  `version_id` INT UNSIGNED NULL,
  `downloaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) NOT NULL,
  `success` TINYINT(1) NOT NULL,
  CONSTRAINT `fk_downloads_share` FOREIGN KEY (share_id) REFERENCES shares(id) ON DELETE CASCADE,
  CONSTRAINT `fk_downloads_version` FOREIGN KEY (version_id) REFERENCES file_versions(id) ON DELETE SET NULL
);

-- Index utiles =????
CREATE INDEX idx_folders_user ON folders(user_id);
CREATE INDEX idx_files_user_folder ON files(user_id, folder_id);
CREATE INDEX idx_shares_token ON shares(token);
CREATE INDEX idx_downloads_share ON downloads_log(share_id);
