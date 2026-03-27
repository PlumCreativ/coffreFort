<?php

namespace App\Helper;

use Medoo\Medoo;

/**
 * AuditLogger — insère dans audit_logs en suivant la même structure
 * que les triggers SQL (user_id, action, table_name, record_id, details).
 *
 * La colonne created_at est gérée par DEFAULT CURRENT_TIMESTAMP côté MySQL.
 * ip_address et user_agent sont capturés automatiquement depuis $_SERVER.
 */
class AuditLogger
{
    private Medoo $db;

    public function __construct(Medoo $db)
    {
        $this->db = $db;
    }

    /**
     * Insère un enregistrement d'audit — interface principale.
     *
     * @param int|null  $userId    ID de l'utilisateur concerné (null si non authentifié)
     * @param string    $action    Valeur ENUM : 'USER_LOGIN', 'FILE_UPLOAD', etc.
     * @param string    $tableName Nom de la table affectée ('users', 'files', …)
     * @param int|null  $recordId  ID du record affecté
     * @param array|null $details  Données contextuelles (sérialisées en JSON)
     */
    public function insert(
        ?int $userId,
        string $action,
        string $tableName,
        ?int $recordId = null,
        ?array $details = null
    ): void {
        try {
            $this->db->insert('audit_logs', [
                'user_id'    => $userId,
                'action'     => $action,
                'table_name' => $tableName,
                'record_id'  => $recordId,
                'details'    => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                'ip_address' => $this->getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (\Exception $e) {
            // Erreur silencieuse : l'audit ne bloque jamais l'opération métier
            error_log('[AuditLogger] ' . $e->getMessage());
        }
    }

    private function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
