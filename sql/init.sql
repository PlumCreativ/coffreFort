-- Base de données créée automatiquement par Docker via MYSQL_DATABASE
-- USE `coffreFort`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `pass_hash` VARCHAR(255) NOT NULL,
  `quota_total` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `quota_used` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `folders`(
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED,
    `parent_id` BIGINT UNSIGNED,
    `name` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_folders_user` FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT `fk_folders_parent` FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `files`(
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED,
    `folder_id` BIGINT UNSIGNED,
    `original_name` VARCHAR(50) NOT NULL,
    `stored_name` VARCHAR(150) NOT NULL, 
    `mime` VARCHAR(150) NOT NULL,
    `size` BIGINT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_files_user` FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT `fk_files_folder` FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS `file_versions`(
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `file_id` BIGINT UNSIGNED,
    `version` INT UNSIGNED NOT NULL,
    `stored_name` VARCHAR(150) NOT NULL,
    `iv` VARBINARY(12) NOT NULL,
    `auth_tag` VARBINARY(16) NOT NULL,
    `key_envelope` BLOB NOT NULL,
    `checksum` BINARY(32) NOT NULL,
    `size` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_file_versions_file` FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    UNIQUE KEY `uniq_file_version` (file_id, version)
);

CREATE TABLE IF NOT EXISTS `shares` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `kind` ENUM('file', 'folder') NOT NULL,
  `target_id` BIGINT UNSIGNED NOT NULL,
  `token` CHAR(64) NOT NULL UNIQUE,
  `token_sig` CHAR(64) NOT NULL,
  `label` VARCHAR(255) NULL,
  `expires_at` DATETIME NULL,
  `max_uses` INT UNSIGNED NULL,
  `remaining_uses` INT UNSIGNED NULL,
  `is_revoked` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_shares_user` FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_shares_token (token)
);


DROP TABLE IF EXISTS `downloads_log`;
CREATE TABLE `downloads_log` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `share_id` BIGINT UNSIGNED NOT NULL,
  `version_id` BIGINT UNSIGNED NULL,
  `downloaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) NOT NULL,
  `success` TINYINT(1) NOT NULL,
  `message` VARCHAR(255) NULL,
  CONSTRAINT `fk_downloads_share` FOREIGN KEY (share_id) REFERENCES shares(id) ON DELETE CASCADE,
  CONSTRAINT `fk_downloads_version` FOREIGN KEY (version_id) REFERENCES file_versions(id) ON DELETE SET NULL
);

-- Index utiles
CREATE INDEX idx_folders_user ON folders(user_id);
CREATE INDEX idx_files_user_folder ON files(user_id, folder_id);
CREATE INDEX idx_downloads_share ON downloads_log(share_id);
CREATE INDEX idx_file_versions_created_at ON file_versions(created_at);
