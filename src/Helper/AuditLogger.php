<?php

namespace App\Helper;

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

            // Mapper les opérations génériques aux actions ENUM spécifiques
            $actionEnum = $this->mapOperationToAction($tableName, $operation);

            // Insérer directement dans audit_logs
            $this->db->insert('audit_logs', [
                'user_id' => $userId,
                'action' => $actionEnum,
                'table_name' => $tableName,
                'record_id' => $recordId,
                'details' => $newValuesJson ?? $oldValuesJson, // Préférer les nouvelles valeurs
                'ip_address' => $this->getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            return true;
        } catch (\Exception $e) {
            error_log("Erreur audit logging: " . $e->getMessage());
            // Ne pas lever l'exception pour ne pas bloquer l'opération
            return false;
        }
    }

    /**
     * Mapper une opération générique à une action ENUM spécifique
     */
    private function mapOperationToAction(string $tableName, string $operation): string
    {
        $operation = strtoupper($operation);

        // Mapping basé sur la table et l'opération
        $mappings = [
            'users' => [
                'INSERT' => 'USER_REGISTER',
                'UPDATE' => 'QUOTA_UPDATE',
                'DELETE' => 'USER_DELETE'
            ],
            'files' => [
                'INSERT' => 'FILE_UPLOAD',
                'UPDATE' => 'FILE_RENAME',
                'DELETE' => 'FILE_DELETE'
            ],
            'folders' => [
                'INSERT' => 'FOLDER_CREATE',
                'UPDATE' => 'FOLDER_RENAME',
                'DELETE' => 'FOLDER_DELETE'
            ],
            'shares' => [
                'INSERT' => 'SHARE_CREATE',
                'UPDATE' => 'SHARE_REVOKE',
                'DELETE' => 'SHARE_DELETE'
            ],
            'file_versions' => [
                'INSERT' => 'FILE_VERSION_UPLOAD',
                'DELETE' => 'FILE_VERSION_DELETE'
            ]
        ];

        if (isset($mappings[$tableName][$operation])) {
            return $mappings[$tableName][$operation];
        }

        // Fallback à 'OTHER' pour les cas non couverts
        return 'OTHER';
    }

    /**
     * Récupérer l'adresse IP du client
     */
    private function getClientIp(): string
    {
        // Respecter les proxies et load balancers
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // En cas de proxy, prendre la première IP
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return '127.0.0.1';
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

            $this->db->insert('audit_logs', [
                'user_id' => $userId,
                'action' => $action,
                'table_name' => $tableName,
                'record_id' => null,
                'details' => $contextJson,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            return true;
        } catch (\Exception $e) {
            error_log("Erreur audit read logging: " . $e->getMessage());
            return false;
        }
    }
}
