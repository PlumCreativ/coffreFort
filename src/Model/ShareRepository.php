<?php
namespace App\Model;

use Medoo\Medoo;

class ShareRepository{

    private Medoo $db;

    public function __construct(Medoo $db)
    {
        $this->db = $db;
    }


    //crée une partage
    public function create(array $data): array
    {
        $this->db->insert('shares', [
            'user_id'               => (int)$data['user_id'],
            'kind'                  => (string)$data['kind'], //file or folder
            'target_id'             => (int)$data['target_id'],
            'token'                 => (string)$data['token'],
            'token_sig'             => (string)$data['token_sig'], //signature to verify token integrity
            'label'                 => $data['label'] ?? null,
            'expires_at'            => $data['expires_at'] ?? null,
            'max_uses'              => $data['max_uses'] ?? null,
            'remaining_uses'        => $data['max_uses'] ?? null,
            'is_revoked'            => 0, 
            'allow_fixed_versions'  => (int)($data['allow_fixed_versions'] ?? 0),
        ]);

        $id = (int)$this->db->id();
        return $this->findById($id);
    }

    //retourne tous les caractéristiques d'un partage
    public function findById(int $id): ?array
    {
        $row = $this->db->get('shares', '*', ['id' => $id]);
        return $row ?: null;
    }

    //trouve le partage par le token (donné)
    public function findByToken(string $token): ?array
    {
         $row = $this->db->get('shares', '*', ['token' => $token]);
        return $row ?: null;
    }

    //révoquer un partage
    public function revoke(int $id): void
    {
        $this->db->update('shares', [
            'is_revoked' => 1
        ], [
            'id'         => $id
        ]);  
    }

    //supprimer un partage
    public function delete(int $id): void 
    {
        $this->db->delete('shares', [
            'id' => $id
        ]);
    }

    //supprimer tous les partages lié à un user
    public function deleteSharesByUser(int $UserId): void 
    {
        $this->db->delete('shares', [
            'user_id' => $UserId
        ]);
    }


    //pas utilisé
    public function decrementRemainingUses(int $id): bool 
    {
        $stmt = $this->db->update('shares',[ 
            'remaining_uses[-]'     => 1
        ], [
            'AND' => [
                'id'                => $id,
                'remaining_uses[!]' => null
            ]
        ]);
        return $stmt->rowCount() > 0;
    }

    // décrémente seulement si remaining_uses est > 0
    // retourne true si décrément OK, false sinon
    public function consumeUse(int $shareId): bool 
    {
        $count = $this->db->update('shares', [
            'remaining_uses[-]' => 1
        ], [
            'id'                => $shareId,
            'remaining_uses[>]' => 0
        ])->rowCount();

        return $count > 0;
    }

    public function countSharesByUser(int $userId, ?int $targetId = null): int
    {
        $where = ['user_id' => $userId];

        if($targetId !== null && $targetId > 0){
            $where['target_id'] = $targetId;
        }
        
        $total = $this->db->count('shares', ['AND' => $where]);
        return (int)($total ?: 0);
    }

    //Liste les partages d'un utilisateur avec pagination => filtrer par target_id (file ou folder) optionnellement
    public function listSharesByUser(int $userId, ?int $targetId = null, int $limit = 20, int $offset = 0): array
    {
        //construction dynamique du WHERE
        $where = ['user_id' => $userId];

        if($targetId !== null && $targetId > 0){
            $where['target_id'] = $targetId;
        }

         $shares = $this->db->select('shares', '*', [
            'AND' => $where,
            'ORDER' => ['created_at' => 'DESC'],
            'LIMIT' => [$offset, $limit]
        ]);

        return $shares;   
    }

    //supprimer tous les logs de téléchargement liés aux partages d'un utilisateur
    public function deleteDownloadLogsByUser(int $userId) : void
    {
        //récup tous les ids de shares de user
        $shareIds = $this->db->select('shares', 'id', ['user_id' => $userId]);

        if(!empty($shareIds)){

            //supprimer les logs de téléchargement
            $this->db->delete('downloads_log', [
                'share_id' => $shareIds
            ]);
        }
    }


}