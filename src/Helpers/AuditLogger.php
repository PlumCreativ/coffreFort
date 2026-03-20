<?php

namespace App\Helpers;

use Medoo\Medoo;

class AuditLogger
{
    private Medoo $db;

    public function __construct(Medoo $db)
    {
        $this->db = $db;
    }

    /**
     * Log une opération INSERT/UPDATE/DELETE
     * 
     * @param string $tableName Nom de la table ('users', 'files', etc.)
     * @param string $operation 'INSERT', 'UPDATE', or 'DELETE'
     * @param int $userId ID de l'utilisateur qui effectue l'action
     * @param int|null $recordId ID du record affecté
     * @param array|null $oldValues Anciennes valeurs (pour UPDATE/DELETE)
     * @param array|null $newValues Nouvelles valeurs (pour INSERT/UPDATE)
     * @return bool true si succès
     */
    public function log(
        string $tableName,
        string $operation,
        int $userId,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): bool {
        try {
            $oldValuesJson = $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
            $newValuesJson = $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;

            // Appeler la procédure stockée
            $this->db->query(
                "CALL sp_audit_insert(?, ?, ?, ?, ?, ?)",
                [$tableName, $operation, $userId, $recordId, $oldValuesJson, $newValuesJson]
            );

            return true;
        } catch (\Exception $e) {
            error_log("Erreur audit logging: " . $e->getMessage());
            // Ne pas lever l'exception pour ne pas bloquer l'opération
            return false;
        }
    }

    /**
     * Log une lecture de données (SELECT)
     * Utile pour les opérations sensibles
     */
    public function logRead(
        string $tableName,
        int $userId,
        string $action,
        ?array $context = null
    ): bool {
        try {
            $contextJson = $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null;

            $this->db->query(
                "CALL sp_audit_insert(?, ?, ?, ?, ?, ?)",
                [$tableName, 'READ', $userId, null, null, $contextJson]
            );

            return true;
        } catch (\Exception $e) {
            error_log("Erreur audit read logging: " . $e->getMessage());
            return false;
        }
    }
}