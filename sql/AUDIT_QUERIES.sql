-- ============================================================================
-- REQUÊTES UTILES POUR CONSULTER LES AUDITS
-- ============================================================================
-- 
-- Ce fichier contient des exemples de requêtes pour exploiter les données
-- d'audit enregistrées dans la table audit_logs.
--
-- ============================================================================

-- ============================================================================
-- 1. HISTORIQUE D'UN FICHIER SPÉCIFIQUE
-- ============================================================================
-- Voir toutes les actions sur un fichier particulier

SELECT 
    DATE_FORMAT(al.created_at, '%d/%m/%Y %H:%i:%s') AS 'Date',
    al.action AS 'Action',
    COALESCE(u.email, '[Système]') AS 'Utilisateur',
    al.details AS 'Détails'
FROM audit_logs al
LEFT JOIN users u ON al.user_id = u.id
WHERE al.table_name = 'files' 
  AND al.record_id = ? -- Remplacer par l'ID du fichier
ORDER BY al.created_at DESC;


-- ============================================================================
-- 2. HISTORIQUE D'UN DOSSIER SPÉCIFIQUE
-- ============================================================================

SELECT 
    DATE_FORMAT(al.created_at, '%d/%m/%Y %H:%i:%s') AS 'Date',
    al.action AS 'Action',
    COALESCE(u.email, '[Système]') AS 'Utilisateur',
    al.details AS 'Détails'
FROM audit_logs al
LEFT JOIN users u ON al.user_id = u.id
WHERE al.table_name = 'folders' 
  AND al.record_id = ? -- Remplacer par l'ID du dossier
ORDER BY al.created_at DESC;


-- ============================================================================
-- 3. TOUS LES AUDITS D'UN UTILISATEUR (DERNIÈRES 24H)
-- ============================================================================
-- Voir toutes les actions effectuées par un utilisateur spécifique

SELECT 
    DATE_FORMAT(al.created_at, '%d/%m/%Y %H:%i:%s') AS 'Date',
    al.action AS 'Action',
    al.table_name AS 'Table',
    al.record_id AS 'ID Record',
    al.details AS 'Détails'
FROM audit_logs al
WHERE al.user_id = ? -- Remplacer par l'ID utilisateur
  AND al.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY al.created_at DESC;


-- ============================================================================
-- 4. TOUS LES AUDITS D'UN UTILISATEUR (DERNIER MOIS)
-- ============================================================================

SELECT 
    DATE_FORMAT(al.created_at, '%d/%m/%Y %H:%i:%s') AS 'Date',
    al.action AS 'Action',
    al.table_name AS 'Table',
    al.details AS 'Détails'
FROM audit_logs al
WHERE al.user_id = ? -- Remplacer par l'ID utilisateur
  AND al.created_at > DATE_SUB(NOW(), INTERVAL 1 MONTH)
ORDER BY al.created_at DESC;


-- ============================================================================
-- 5. UPLOADS DE FICHIERS (DERNIÈRE SEMAINE)
-- ============================================================================

SELECT 
    DATE_FORMAT(al.created_at, '%d/%m/%Y %H:%i:%s') AS 'Date',
    u.email AS 'Utilisateur',
    JSON_EXTRACT(al.details, '$.original_name') AS 'Nom du fichier',
    JSON_EXTRACT(al.details, '$.size') AS 'Taille (bytes)',
    JSON_EXTRACT(al.details, '$.mime') AS 'Type MIME'
FROM audit_logs al
LEFT JOIN users u ON al.user_id = u.id
WHERE al.action = 'FILE_UPLOAD'
  AND al.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY al.created_at DESC;


-- ============================================================================
-- 6. SUPPRESSIONS DE FICHIERS (AUDIT COMPLET)
-- ============================================================================

SELECT 
    DATE_FORMAT(al.created_at, '%d/%m/%Y %H:%i:%s') AS 'Date de suppression',
    COALESCE(u.email, '[Suppression en cascade]') AS 'Supprimé par',
    JSON_EXTRACT(al.details, '$.original_name') AS 'Nom du fichier',
    JSON_EXTRACT(al.details, '$.size') AS 'Taille (bytes)',
    JSON_EXTRACT(al.details, '$.stored_name') AS 'Chemin de stockage'
FROM audit_logs al
LEFT JOIN users u ON al.user_id = u.id
WHERE al.action = 'FILE_DELETE'
ORDER BY al.created_at DESC;


-- ============================================================================
-- 7. PARTAGES CRÉÉS (AVEC DESTINATAIRES)
-- ============================================================================

SELECT 
    DATE_FORMAT(al.created_at, '%d/%m/%Y %H:%i:%s') AS 'Date du partage',
    u.email AS 'Créateur',
    JSON_EXTRACT(al.details, '$.kind') AS 'Type de partage',
    JSON_EXTRACT(al.details, '$.label') AS 'Étiquette',
    JSON_EXTRACT(al.details, '$.expires_at') AS 'Expiration',
    JSON_EXTRACT(al.details, '$.max_uses') AS 'Nombre d\'accès max'
FROM audit_logs al
LEFT JOIN users u ON al.user_id = u.id
WHERE al.action = 'SHARE_CREATE'
ORDER BY al.created_at DESC;


-- ============================================================================
-- 8. PARTAGES RÉVOQUÉS
-- ============================================================================

SELECT 
    DATE_FORMAT(al.created_at, '%d/%m/%Y %H:%i:%s') AS 'Date de révocation',
    u.email AS 'Révoqué par',
    JSON_EXTRACT(al.details, '$.kind') AS 'Type de partage',
    al.record_id AS 'ID du partage'
FROM audit_logs al
LEFT JOIN users u ON al.user_id = u.id
WHERE al.action = 'SHARE_REVOKE'
ORDER BY al.created_at DESC;


-- ============================================================================
-- 9. ENREGISTREMENTS/SUPPRESSIONS DE COMPTE (RGPD)
-- ============================================================================

SELECT 
    DATE_FORMAT(al.created_at, '%d/%m/%Y %H:%i:%s') AS 'Date',
    al.action AS 'Action',
    JSON_EXTRACT(al.details, '$.email') AS 'Email',
    JSON_EXTRACT(al.details, '$.quota_used') AS 'Quota utilisé (bytes)',
    JSON_EXTRACT(al.details, '$.was_admin') AS 'Était admin',
    JSON_EXTRACT(al.details, '$.reason') AS 'Raison'
FROM audit_logs al
WHERE al.action IN ('USER_REGISTER', 'USER_DELETE')
ORDER BY al.created_at DESC;


-- ============================================================================
-- 10. STATISTIQUES D'AUDIT (DERNIERS 30 JOURS)
-- ============================================================================

SELECT 
    al.action AS 'Action',
    COUNT(*) AS 'Nombre d\'occurrences',
    COUNT(DISTINCT al.user_id) AS 'Utilisateurs uniques',
    DATE_FORMAT(MIN(al.created_at), '%d/%m/%Y %H:%i') AS 'Première occurrence',
    DATE_FORMAT(MAX(al.created_at), '%d/%m/%Y %H:%i') AS 'Dernière occurrence'
FROM audit_logs al
WHERE al.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY al.action
ORDER BY COUNT(*) DESC;


-- ============================================================================
-- 11. DÉTECTION D'ANOMALIES: TÉLÉCHARGEMENTS MASSIFS
-- ============================================================================
-- Trouver les utilisateurs avec beaucoup d'accès aux téléchargements

SELECT 
    DATE_FORMAT(DATE(al.created_at), '%d/%m/%Y') AS 'Date',
    u.email AS 'Utilisateur',
    COUNT(*) AS 'Nombre d\'actions',
    GROUP_CONCAT(DISTINCT al.action) AS 'Actions uniques'
FROM audit_logs al
LEFT JOIN users u ON al.user_id = u.id
WHERE al.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY DATE(al.created_at), al.user_id
HAVING COUNT(*) > 100
ORDER BY COUNT(*) DESC;


-- ============================================================================
-- 12. MODIFICATIONS DE FICHIERS (RENOMMAGES, VERSIONS)
-- ============================================================================

SELECT 
    DATE_FORMAT(al.created_at, '%d/%m/%Y %H:%i:%s') AS 'Date',
    al.action AS 'Action',
    u.email AS 'Utilisateur',
    CASE 
        WHEN al.action = 'FILE_RENAME' THEN 
            CONCAT(
                JSON_EXTRACT(al.details, '$.before.name'), 
                ' → ', 
                JSON_EXTRACT(al.details, '$.after.name')
            )
        WHEN al.action = 'FILE_VERSION_UPLOAD' THEN 
            CONCAT(
                'Fichier: ', 
                JSON_EXTRACT(al.details, '$.file_id'),
                ' (v', 
                JSON_EXTRACT(al.details, '$.version'),
                ')'
            )
        ELSE al.details
    END AS 'Détail'
FROM audit_logs al
LEFT JOIN users u ON al.user_id = u.id
WHERE al.action IN ('FILE_RENAME', 'FILE_VERSION_UPLOAD', 'FILE_VERSION_DELETE')
ORDER BY al.created_at DESC
LIMIT 100;


-- ============================================================================
-- 13. DOSSIERS CRÉÉS ET SUPPRIMÉS (ARBORESCENCE)
-- ============================================================================

SELECT 
    DATE_FORMAT(al.created_at, '%d/%m/%Y %H:%i:%s') AS 'Date',
    al.action AS 'Action',
    u.email AS 'Utilisateur',
    JSON_EXTRACT(al.details, '$.name') AS 'Nom du dossier',
    JSON_EXTRACT(al.details, '$.parent_id') AS 'Dossier parent',
    al.record_id AS 'ID du dossier'
FROM audit_logs al
LEFT JOIN users u ON al.user_id = u.id
WHERE al.action IN ('FOLDER_CREATE', 'FOLDER_DELETE', 'FOLDER_RENAME')
ORDER BY al.created_at DESC
LIMIT 100;


-- ============================================================================
-- 14. RAPPORT QUOTIDIEN D'ACTIVITÉ
-- ============================================================================

SELECT 
    DATE_FORMAT(al.created_at, '%d/%m/%Y') AS 'Date',
    COUNT(*) AS 'Total d\'actions',
    COUNT(DISTINCT al.user_id) AS 'Utilisateurs actifs',
    SUM(CASE WHEN al.action = 'FILE_UPLOAD' THEN 1 ELSE 0 END) AS 'Uploads',
    SUM(CASE WHEN al.action LIKE 'SHARE_%' THEN 1 ELSE 0 END) AS 'Partages',
    SUM(CASE WHEN al.action LIKE 'FOLDER_%' THEN 1 ELSE 0 END) AS 'Opérations dossiers'
FROM audit_logs al
WHERE al.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(al.created_at)
ORDER BY al.created_at DESC;


-- ============================================================================
-- 15. RECHERCHE AVANCÉE: PAR PLAGE DE DATE ET UTILISATEUR
-- ============================================================================
-- Adapter les paramètres selon les besoins

SELECT 
    DATE_FORMAT(al.created_at, '%d/%m/%Y %H:%i:%s') AS 'Date',
    al.action AS 'Action',
    al.table_name AS 'Table',
    al.record_id AS 'Record ID',
    al.details AS 'Détails (JSON)'
FROM audit_logs al
WHERE al.user_id = ? -- ID utilisateur
  AND al.created_at BETWEEN '2024-01-01' AND '2024-12-31' -- Dates
  AND al.action IN ('FILE_UPLOAD', 'FILE_DELETE', 'SHARE_CREATE') -- Actions spécifiques
ORDER BY al.created_at DESC;


-- ============================================================================
-- 16. EXPORT POUR RAPPORT RGPD
-- ============================================================================
-- Exporter tous les audits concernant un utilisateur pour conformité RGPD

SELECT 
    al.id AS 'ID Audit',
    DATE_FORMAT(al.created_at, '%d/%m/%Y %H:%i:%s') AS 'Date et Heure',
    al.action AS 'Action',
    al.table_name AS 'Table affectée',
    al.record_id AS 'ID Record',
    al.details AS 'Données complètes (JSON)',
    al.ip_address AS 'Adresse IP',
    al.user_agent AS 'User Agent'
FROM audit_logs al
WHERE al.user_id = ? -- ID utilisateur pour RGPD
ORDER BY al.created_at ASC;


-- ============================================================================
-- 17. MAINTENANCE: NETTOYAGE DES ANCIENS AUDITS (ARCHIVAGE)
-- ============================================================================
-- À exécuter régulièrement (exemple: archiver les audits de plus de 2 ans)

-- D'abord, créer une table d'archivage si elle n'existe pas:
-- CREATE TABLE audit_logs_archive LIKE audit_logs;

-- Puis, archiver les anciens enregistrements:
-- INSERT INTO audit_logs_archive 
-- SELECT * FROM audit_logs 
-- WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR);

-- Enfin, supprimer de la table principale:
-- DELETE FROM audit_logs 
-- WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR);


-- ============================================================================
-- 18. INTÉGRITÉ DES AUDITS: VÉRIFIER LES DONNÉES MANQUANTES
-- ============================================================================

SELECT 
    COUNT(*) AS 'Total audit_logs',
    SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) AS 'Audits sans utilisateur',
    SUM(CASE WHEN details IS NULL THEN 1 ELSE 0 END) AS 'Audits sans détails',
    SUM(CASE WHEN record_id IS NULL THEN 1 ELSE 0 END) AS 'Audits sans record_id',
    MIN(created_at) AS 'Premier audit',
    MAX(created_at) AS 'Dernier audit',
    DATEDIFF(NOW(), MAX(created_at)) AS 'Jours depuis dernier audit'
FROM audit_logs;


-- ============================================================================
-- FIN DU FICHIER DE REQUÊTES D'AUDIT
-- ============================================================================
