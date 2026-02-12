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


