-- ============================================================================
-- SYSTÈME D'AUDIT DE LA BASE DE DONNÉES
-- ============================================================================
-- 
-- Ce système capture automatiquement toutes les modifications importantes
-- (INSERT, UPDATE, DELETE) sur les tables critiques de l'application.
--
-- Architecture:
-- 1. Procédure stockée générique sp_audit_insert pour centraliser la logique
-- 2. Gestion d'erreur douce (CONTINUE HANDLER) pour éviter de bloquer les transactions
-- 3. Triggers simples qui appellent la procédure
--
-- Sécurité des transactions:
-- - Les erreurs d'audit NE BLOQUENT JAMAIS la transaction métier
-- - Les données critiques sont toujours sauvegardées
-- - L'audit est un complément, jamais un blocage
--
-- ============================================================================

-- ============================================================================
-- PROCÉDURE STOCKÉE GÉNÉRIQUE POUR L'AUDIT
-- ============================================================================
-- Paramètres:
--   p_user_id: ID de l'utilisateur qui a déclenché l'action (peut être NULL)
--   p_action: Type d'action ('FILE_UPLOAD', etc.)
--   p_table_name: Nom de la table affectée
--   p_record_id: ID du record modifié
--   p_details: Détails JSON de l'action
--
-- Fonctionnement:
--   - Insère un enregistrement dans audit_logs
--   - Gère les erreurs silencieusement (CONTINUE HANDLER)
--   - Permet aux transactions métier de continuer même en cas de problème d'audit
-- ============================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_audit_insert`$$

CREATE PROCEDURE `sp_audit_insert`(
    IN p_user_id BIGINT UNSIGNED,
    IN p_action VARCHAR(100),
    IN p_table_name VARCHAR(50),
    IN p_record_id BIGINT UNSIGNED,
    IN p_details JSON
)
COMMENT 'Procédure générique pour enregistrer les audits. Gère les erreurs silencieusement.'
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        -- Les erreurs d\'audit sont ignorées silencieusement
        -- Les transactions métier ne sont JAMAIS bloquées par l\'audit
        -- Optionnel: on pourrait insérer dans une table audit_errors pour monitoring
    END;
    
    -- Insérer l\'enregistrement d\'audit avec gestion d\'erreur
    INSERT INTO audit_logs (user_id, action, table_name, record_id, details)
    VALUES (p_user_id, p_action, p_table_name, p_record_id, p_details);
END$$

DELIMITER ;


-- ============================================================================
-- TRIGGERS POUR LES FICHIERS
-- ============================================================================

DELIMITER $$

DROP TRIGGER IF EXISTS `trg_files_after_insert`$$
CREATE TRIGGER `trg_files_after_insert`
AFTER INSERT ON `files`
FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        -- Erreur silencieuse pour éviter de bloquer l'upload de fichier
    END;
    
    INSERT INTO audit_logs (user_id, action, table_name, record_id, details)
    VALUES (
        NEW.user_id,
        'FILE_UPLOAD',
        'files',
        NEW.id,
        JSON_OBJECT(
            'file_id', NEW.id,
            'original_name', NEW.original_name,
            'size', NEW.size,
            'mime', NEW.mime,
            'folder_id', NEW.folder_id
        )
    );
END$$

DELIMITER ;


DELIMITER $$

DROP TRIGGER IF EXISTS `trg_files_after_rename`$$
CREATE TRIGGER `trg_files_after_rename`
AFTER UPDATE ON `files`
FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        -- Erreur silencieuse pour éviter de bloquer la renommage
    END;
    
    -- N'auditer que si le nom a réellement changé
	IF OLD.original_name != NEW.original_name && LENGTH(TRIM(NEW.original_name)) != 0 THEN
        INSERT INTO audit_logs (user_id, action, table_name, record_id, details)
        VALUES (
            NEW.user_id,
            'FILE_RENAME',
            'files',
            NEW.id,
            JSON_OBJECT(
                'file_id', NEW.id,
                'before', JSON_OBJECT(
                    'name', OLD.original_name,
                    'size', OLD.size
                ),
                'after', JSON_OBJECT(
                    'name', NEW.original_name,
                    'size', NEW.size
                )
            )
        );
    END IF;
END$$

DELIMITER ;


DELIMITER $$

DROP TRIGGER IF EXISTS `trg_files_before_delete`$$
CREATE TRIGGER `trg_files_before_delete`
BEFORE DELETE ON `files`
FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        -- Erreur silencieuse pour éviter de bloquer la suppression
    END;
    
    -- BEFORE DELETE: capture les données AVANT suppression
    INSERT INTO audit_logs (user_id, action, table_name, record_id, details)
    VALUES (
        OLD.user_id,
        'FILE_DELETE',
        'files',
        OLD.id,
        JSON_OBJECT(
            'file_id', OLD.id,
            'original_name', OLD.original_name,
            'size', OLD.size,
            'stored_name', OLD.stored_name,
            'folder_id', OLD.folder_id
        )
    );
END$$

DELIMITER ;

-- ============================================================================
-- TRIGGERS POUR LES DOSSIERS
-- ============================================================================

DELIMITER $$

DROP TRIGGER IF EXISTS `trg_folders_after_insert`$$
CREATE TRIGGER `trg_folders_after_insert`
AFTER INSERT ON `folders`
FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        -- Erreur silencieuse
    END;
    
    INSERT INTO audit_logs (user_id, action, table_name, record_id, details)
    VALUES (
        NEW.user_id,
        'FOLDER_CREATE',
        'folders',
        NEW.id,
        JSON_OBJECT(
            'folder_id', NEW.id,
            'name', NEW.name,
            'parent_id', NEW.parent_id
        )
    );
END$$

DELIMITER ;


DELIMITER $$

DROP TRIGGER IF EXISTS `trg_folders_after_rename`$$
CREATE TRIGGER `trg_folders_after_rename`
AFTER UPDATE ON `folders`
FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        -- Erreur silencieuse
    END;
    
    -- N'auditer que si le nom a réellement changé
	IF OLD.name != NEW.name && LENGTH(TRIM(NEW.name)) != 0 THEN
        INSERT INTO audit_logs (user_id, action, table_name, record_id, details)
        VALUES (
            NEW.user_id,
            'FOLDER_RENAME',
            'folders',
            NEW.id,
            JSON_OBJECT(
                'folder_id', NEW.id,
                'old_name', OLD.name,
                'new_name', NEW.name
            )
        );
    END IF;
END$$

DELIMITER ;


DELIMITER $$

DROP TRIGGER IF EXISTS `trg_folders_before_delete`$$
CREATE TRIGGER `trg_folders_before_delete`
BEFORE DELETE ON `folders`
FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        -- Erreur silencieuse
    END;
    
    -- BEFORE DELETE: capture les données AVANT suppression
    INSERT INTO audit_logs (user_id, action, table_name, record_id, details)
    VALUES (
        OLD.user_id,
        'FOLDER_DELETE',
        'folders',
        OLD.id,
        JSON_OBJECT(
            'folder_id', OLD.id,
            'name', OLD.name,
            'parent_id', OLD.parent_id
        )
    );
END$$

DELIMITER ;

-- ============================================================================
-- TRIGGERS POUR LES PARTAGES
-- ============================================================================

DELIMITER $$

DROP TRIGGER IF EXISTS `trg_shares_after_insert`$$
CREATE TRIGGER `trg_shares_after_insert`
AFTER INSERT ON `shares`
FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        -- Erreur silencieuse
    END;
    
    INSERT INTO audit_logs (user_id, action, table_name, record_id, details)
    VALUES (
        NEW.user_id,
        'SHARE_CREATE',
        'shares',
        NEW.id,
        JSON_OBJECT(
            'shares_id', NEW.id,
            'kind', NEW.kind,
            'target_id', NEW.target_id,
            'label', NEW.label,
            'expires_at', NEW.expires_at,
            'max_uses', NEW.max_uses
        )
    );
END$$

DELIMITER ;

DELIMITER $$

DROP TRIGGER IF EXISTS `trg_shares_after_revoke`$$
CREATE TRIGGER `trg_shares_after_revoke`
AFTER UPDATE ON `shares`
FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        -- Erreur silencieuse
    END;
    
    INSERT INTO audit_logs (user_id, action, table_name, record_id, details)
    VALUES (
        NEW.user_id,
        'SHARE_REVOKE',
        'shares',
        NEW.id,
        JSON_OBJECT(
            'shares_id', NEW.id,
            'kind', NEW.kind,
            'target_id', NEW.target_id
        )
    );
END$$

DELIMITER ;



DELIMITER $$

DROP TRIGGER IF EXISTS `trg_shares_before_delete`$$
CREATE TRIGGER `trg_shares_before_delete`
BEFORE DELETE ON `shares`
FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        -- Erreur silencieuse
    END;
    
    -- BEFORE DELETE: capture les données AVANT suppression
    INSERT INTO audit_logs (user_id, action, table_name, record_id, details)
    VALUES (
        OLD.user_id,
        'SHARE_DELETE',
        'shares',
        OLD.id,
        JSON_OBJECT(
            'shares_id', OLD.id,
            'kind', OLD.kind,
            'label', OLD.label,
            'target_id', OLD.target_id,
            'was_revoked', OLD.is_revoked
        )
    );
END$$

DELIMITER ;

-- ============================================================================
-- TRIGGERS POUR LES VERSIONS DE FICHIERS
-- ============================================================================


DELIMITER $$

DROP TRIGGER IF EXISTS `trg_file_versions_after_insert`$$
CREATE TRIGGER `trg_file_versions_after_insert`
AFTER INSERT ON `file_versions`
FOR EACH ROW
BEGIN
	DECLARE v_user_id BIGINT UNSIGNED;
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        -- Erreur silencieuse
    END;
    
    -- Récupérer le user_id du fichier parent
    SELECT user_id INTO v_user_id
    FROM files
    WHERE id = NEW.file_id
    LIMIT 1;

    INSERT INTO audit_logs (user_id, action, table_name, record_id, details)
    VALUES (
        v_user_id,
        'FILE_VERSION_UPLOAD',
        'file_versions',
        NEW.id,
        JSON_OBJECT(
            'version_id', NEW.id,
            'file_id', NEW.file_id,
            'version', NEW.version,
            'size', NEW.size
        )
    );
END$$

DELIMITER ;


DELIMITER $$

DROP TRIGGER IF EXISTS `trg_file_versions_before_delete`$$
CREATE TRIGGER `trg_file_versions_before_delete`
BEFORE DELETE ON `file_versions`
FOR EACH ROW
BEGIN
	DECLARE v_user_id BIGINT UNSIGNED;
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        -- Erreur silencieuse
    END;
    
    -- Récupérer le user_id du fichier parent
    SELECT user_id INTO v_user_id
    FROM files
    WHERE id = OLD.file_id
    LIMIT 1;

    INSERT INTO audit_logs (user_id, action, table_name, record_id, details)
    VALUES (
        v_user_id,
        'FILE_VERSION_DELETE',
        'file_versions',
        OLD.id,
        JSON_OBJECT(
            'version_id', OLD.id,
            'file_id', OLD.file_id,
            'version', OLD.version,
            'size', OLD.size
        )
    );
END$$

DELIMITER ;


-- ============================================================================
-- VUES POUR FACILITER AFFICHAGE L'ACTIVITÉ DE ADMIN
-- ============================================================================

-- Vue générale : toutes les actions d'administration sur les utilisateurs
-- Couvre : USER_DELETE, QUOTA_UPDATE
-- Colonnes :
--   performed_by_id/email → admin qui a exécuté l'action (user_id + JOIN users + fallback JSON $.admin_email)
--   target_user_id/email  → user ciblé (record_id + JSON $.target_email)
--   old_quota / new_quota → valeurs avant/après pour QUOTA_UPDATE
--   ip_address / user_agent → contexte réseau de l'action
DROP VIEW IF EXISTS `admin_user_activity_view`;
CREATE VIEW `admin_user_activity_view` AS
SELECT
    al.id                                                       AS log_id,
    al.created_at,

    -- Admin qui a effectué l'action
    al.user_id                                                  AS performed_by_id,
    COALESCE(
        u.email,
        JSON_UNQUOTE(JSON_EXTRACT(al.details, '$.admin_email'))
    )                                                           AS performed_by_email,

    -- Action effectuée
    al.action,

    -- User ciblé par l'action
    al.record_id                                                AS target_user_id,
    JSON_UNQUOTE(JSON_EXTRACT(al.details, '$.target_email'))    AS target_user_email,

    -- Détails quota (QUOTA_UPDATE)
    JSON_UNQUOTE(JSON_EXTRACT(al.details, '$.old_quota'))       AS old_quota,
    JSON_UNQUOTE(JSON_EXTRACT(al.details, '$.new_quota'))       AS new_quota,

    -- Contexte réseau
    al.ip_address,
    al.user_agent,

    -- Données brutes complètes
    al.details

FROM audit_logs al
LEFT JOIN users u ON al.user_id = u.id
WHERE al.action IN (
    'USER_DELETE',
    'QUOTA_UPDATE'
)
ORDER BY al.created_at DESC;


-- ===========================================================================
-- VUES SPÉCIFIQUES POUR LES ACTIVITÉS LIÉES AUX FICHIERS, PARTAGES, DOSSIERS
-- ===========================================================================

DROP VIEW IF EXISTS `user_files_activity_view`;
CREATE VIEW `user_files_activity_view` AS
SELECT
    al.id AS log_id,
    al.user_id,
    u.email AS user_email,
    al.action,
    al.table_name,
    al.record_id,
    al.details,
    al.created_at
FROM audit_logs al
LEFT JOIN users u ON al.user_id = u.id
WHERE al.action IN ('FILE_UPLOAD', 'FILE_RENAME', 'FILE_DELETE', 'FILE_VERSION_UPLOAD', 'FILE_VERSION_DELETE')
ORDER BY al.created_at DESC;

DROP VIEW IF EXISTS `user_shares_activity_view`;
CREATE VIEW `user_shares_activity_view` AS
SELECT
    al.id AS log_id,
    al.user_id,
    u.email AS user_email,
    al.action,
    al.table_name,
    al.record_id,
    al.details,
    al.created_at
FROM audit_logs al
LEFT JOIN users u ON al.user_id = u.id
WHERE al.action IN ('SHARE_CREATE', 'SHARE_REVOKE', 'SHARE_DELETE')
ORDER BY al.created_at DESC;    

DROP VIEW IF EXISTS `user_folders_activity_view`;
CREATE VIEW `user_folders_activity_view` AS
SELECT
    al.id AS log_id,
    al.user_id,
    u.email AS user_email,
    al.action,
    al.table_name,
    al.record_id,
    al.details,
    al.created_at
FROM audit_logs al
LEFT JOIN users u ON al.user_id = u.id
WHERE al.action IN ('FOLDER_CREATE', 'FOLDER_RENAME', 'FOLDER_DELETE')
ORDER BY al.created_at DESC;

-- ============================================================================
-- VUE POUR FACILITER L'AFFICHAGE DES LOGS DANS L'ADMIN
-- ============================================================================

DROP VIEW IF EXISTS `audit_logs_view`;
CREATE VIEW `audit_logs_view` AS
SELECT
    al.id,
    al.user_id,
    u.email AS user_email,
    al.action,
    al.table_name,
    al.record_id,
    al.details,
    al.created_at
FROM audit_logs al
LEFT JOIN users u ON al.user_id = u.id
ORDER BY al.created_at DESC;

DROP VIEW IF EXISTS `downloads_log_view`;
CREATE VIEW `downloads_log_view` AS
SELECT
    dl.id,
    dl.share_id,
    s.kind AS share_kind,
    dl.version_id,
    fv.file_id,
    f.original_name AS file_name,
    dl.downloaded_at,
    dl.ip,
    dl.user_agent,
    dl.success,
    dl.message
FROM downloads_log dl
LEFT JOIN shares s ON dl.share_id = s.id
LEFT JOIN file_versions fv ON dl.version_id = fv.id
LEFT JOIN files f ON fv.file_id = f.id
ORDER BY dl.downloaded_at DESC;

-- ============================================================================
-- FIN DU FICHIER
-- ============================================================================
