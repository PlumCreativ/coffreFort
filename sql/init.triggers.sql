-- Triggers pour les fichiers
DELIMITER $$

DROP TRIGGER IF EXISTS `trg_files_after_insert`$$
CREATE TRIGGER `trg_files_after_insert`
AFTER INSERT ON `files`
FOR EACH ROW
BEGIN
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


DELIMITER $$

DROP TRIGGER IF EXISTS `trg_files_before_delete`$$
CREATE TRIGGER `trg_files_before_delete`
BEFORE DELETE ON `files`
FOR EACH ROW
BEGIN
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

-- Triggers pour les dossiers 

DELIMITER $$

DROP TRIGGER IF EXISTS `trg_folders_after_insert`$$
CREATE TRIGGER `trg_folders_after_insert`
AFTER INSERT ON `folders`
FOR EACH ROW
BEGIN
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



-- Triggers pour les shares

DELIMITER $$

DROP TRIGGER IF EXISTS `trg_shares_after_insert`$$
CREATE TRIGGER `trg_shares_after_insert`
AFTER INSERT ON `shares`
FOR EACH ROW
BEGIN
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



DELIMITER $$

DROP TRIGGER IF EXISTS `trg_shares_after_revoke`$$
CREATE TRIGGER `trg_shares_after_revoke`
AFTER UPDATE ON `shares`
FOR EACH ROW
BEGIN
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




 -- gestion des versions de fichiers


DELIMITER $$

DROP TRIGGER IF EXISTS `trg_file_versions_after_insert`$$
CREATE TRIGGER `trg_file_versions_after_insert`
AFTER INSERT ON `file_versions`
FOR EACH ROW
BEGIN
	DECLARE v_user_id BIGINT UNSIGNED;
    
    SELECT user_id INTO v_user_id
    FROM files
    WHERE id= NEW.file_id
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
    
    SELECT user_id INTO v_user_id
    FROM files
    WHERE id= OLD.file_id
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


 -- gestion du compte 


DELIMITER $$

DROP TRIGGER IF EXISTS `trg_users_before_delete`$$
CREATE TRIGGER `trg_users_before_delete`
BEFORE DELETE ON `users`
FOR EACH ROW
BEGIN
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
            'reason', "RGPD - Droit à l\'effacement"
        )
    );
END$$

DELIMITER ;




