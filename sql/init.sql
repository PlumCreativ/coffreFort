
CREATE DATABASE IF NOT EXISTS `coffreFort` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `coffreFort`;

DROP TABLE IF EXISTS `files`;
CREATE TABLE `files`(
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT NOT NULL,
    `folder_id` BIGINT NOT NULL,
    `original_name` VARCHAR(50) NOT NULL,
    `stored_name` VARCHAR(150) NOT NULL, 
    `mime` VARCHAR(150) NOT NULL,
    `size` BIGINT NOT NULL,
    `created_at` DATE NOT NULL,
    CONSTRAINT `fk_files_user` FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT `fk_files_folder` FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL
);

DROP TABLE IF EXISTS `file_versions`;
CREATE TABLE `file_versions`(
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `file_id` BIGINT NOT NULL,
    `version` VARCHAR(255) NOT NULL,
    `stored_name` VARCHAR(150) NOT NULL,
    `id_last_version` BIGINT NOT NULL,
    `checksum` MEDIUMINT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_file_versions_file` FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    UNIQUE KEY `uniq_file_version` (file_id, version)
    
);

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users`(
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `quota_used` INT NOT NULL,
    `quota_total` BIGINT NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `pass_hash` VARCHAR(255) NOT NULL,
    `is_admin` BOOLEAN NOT NULL,
    `created_at` DATE NOT NULL
);

DROP TABLE IF EXISTS `folders`;
CREATE TABLE `folders`(
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `parent_id` INT UNSIGNED NULL,
    `name` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_folders_user` FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT `fk_folders_parent` FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE CASCADE
);

DROP TABLE IF EXISTS `shares`;
CREATE TABLE `shares`(
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT NOT NULL,
    `kind` ENUM('') NOT NULL,
    `target_id` BIGINT NOT NULL,
    `label` VARCHAR(255) NOT NULL,
    `expires_at` DATE NOT NULL,
    `max_uses` MEDIUMINT NOT NULL,
    `remaining_uses` MEDIUMINT NOT NULL,
    `is_revoked` BOOLEAN NOT NULL,
    `created_at` DATE NOT NULL,
    CONSTRAINT `fk_shares_user` FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

DROP TABLE IF EXISTS `downloads_log`;
CREATE TABLE `downloads_log`(
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `share_id` BIGINT NOT NULL,
    `version_id` BIGINT NOT NULL,
    `downloaded_at` DATE NOT NULL,
    `ip` TINYINT NOT NULL,
    `user_agent` VARCHAR(25) NOT NULL,
    `success` BOOLEAN NOT NULL,
    CONSTRAINT `fk_downloads_version` FOREIGN KEY (version_id) REFERENCES file_versions(id) ON DELETE SET NULL
);

-- Index utiles => ????
CREATE INDEX idx_folders_user ON folders(user_id);
CREATE INDEX idx_files_user_folder ON files(user_id, folder_id);
CREATE INDEX idx_shares_token ON shares(token);
CREATE INDEX idx_downloads_share ON downloads_log(share_id);

