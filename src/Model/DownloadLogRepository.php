<?php
namespace App\Model;

use Medoo\Medoo;

class DownloadLogRepository{

    private Medoo $db;

    public function __construct(Medoo $db){

        $this->db = $db;
    }

     public function log(int $shareId, ?int $versionId, string $ip, string $userAgent, bool $success): void{
        $this->db->insert('downloads_log', [
            'share_id'          => $shareId, 
            'version_id'        => $versionId, 
            'downloaded_at'     => date('Y-m-d H:i:s'), 
            'ip'                => $ip, 
            'user_agent'        => $userAgent, 
            'success'           => $success ? 1 :0
        ]);
    }


}