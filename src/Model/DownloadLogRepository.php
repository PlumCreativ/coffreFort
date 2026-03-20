<?php


namespace App\Model;

use Medoo\Medoo;
use DateTime;
use DateTimeZone;

class DownloadLogRepository{

    private Medoo $db;
    private DateTime $now;

    public function __construct(Medoo $db){

        $this->db = $db;
        $this->now = new DateTime('now', new DateTimeZone('UTC'));
    }

    /***
    * Log une tentative de téléchargement
     * @param int $shareId
     * @param int|null $versionId
     * @param string $ip
     * @param string $userAgent
     * @param bool $success
     * @param string|null $message
    */

    // Utiliser DateTime avec timezone explicite
    
    public function log(?int $shareId, ?int $versionId, string $ip, string $userAgent, bool $success, ?string $message = null): void{
        $this->db->insert('downloads_log', [
            'share_id'          => $shareId,            //peut être null => download direct
            'version_id'        => $versionId, 
            'downloaded_at'     => $this->now->format('Y-m-d H:i:s'),
            'ip'                => $ip, 
            'user_agent'        => $userAgent, 
            'success'           => $success ? 1 :0, 
            'message'           => $message ? substr($message, 0, 255) : null
        ]);
    }

    
    /**
     * Récupère les derniers logs liés aux partages d'un utilisateur
    */
    public function getByUserId(int $userId, int $limit = 50, int $offset = 0): array{
        return $this->db->select('downloads_log dl', [
            '[>]shares' => ['dl.share_id' => 'id'], 
        ], [
            'dl.id AS log_id',
            'dl.share_id',
            'dl.version_id',
            'dl.downloaded_at',
            'dl.ip',
            'dl.user_agent',
            'dl.success',
            'dl.message',
            'shares.user_id',
        ],[
            'shares.user_id' => $userId,
            'ORDER'          => ['dl.downloaded_at' => 'DESC'],
            'LIMIT'          => [$offset, $limit]
        ]);
    }


}