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
--   p_action: Type d'action ('FILE_UPLOAD', 'USER_DELETE', etc.)
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
-- TRIGGERS POUR LES UTILISATEURS
-- ============================================================================ 


DELIMITER $$

DROP TRIGGER IF EXISTS `trg_users_before_delete`$$
CREATE TRIGGER `trg_users_before_delete`
BEFORE DELETE ON `users`
FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        -- Erreur silencieuse
    END;
    
    -- BEFORE DELETE: capture les données AVANT suppression
    -- Critère RGPD: enregistrer la suppression de compte
    INSERT INTO audit_logs (user_id, action, table_name, record_id, details)
    VALUES (
        OLD.id,
        'USER_DELETE',
        'users',
        OLD.id,
        JSON_OBJECT(
            'email', OLD.email,
            'quota_total', OLD.quota_total,
            'quota_used', OLD.quota_used,
            'was_admin', OLD.is_admin,
            'created_at', OLD.created_at,
            'reason', "RGPD - Droit à l'effacement"
        )
    );
END$$

DELIMITER ;

-- ============================================================================
-- DÉCLENCHEURS ADDITIONNELS RECOMMANDÉS
-- ============================================================================
-- 
-- Trigger pour les inscriptions d'utilisateurs:
-- ============================================================================

DELIMITER $$

DROP TRIGGER IF EXISTS `trg_users_after_insert`$$
CREATE TRIGGER `trg_users_after_insert`
AFTER INSERT ON `users`
FOR EACH ROW
BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        -- Erreur silencieuse
    END;
    
    -- Enregistrer l'inscription d'un nouvel utilisateur
    INSERT INTO audit_logs (user_id, action, table_name, record_id, details)
    VALUES (
        NEW.id,
        'USER_REGISTER',
        'users',
        NEW.id,
        JSON_OBJECT(
            'email', NEW.email,
            'is_admin', NEW.is_admin,
            'quota_total', NEW.quota_total
        )
    );
END$$

DELIMITER ;

-- ============================================================================
-- FIN DU SYSTÈME D'AUDIT
-- ============================================================================




