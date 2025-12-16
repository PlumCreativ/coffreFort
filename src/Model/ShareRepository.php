<?php
namespace App\Model;

use Medoo\Medoo;

class ShareRepository{

    private Medoo $db;

    public function __construct(Medoo $db)
    {
        $this->db = $db;
    }

    public function create(array $data): array{

        $this->db->insert('shares', [
            'user_id'           => $data['user_id'],
            'kind'              => $data['kind'], //file or folder
            'target_id'         => $data['target_id'],
            'token'             => $data['token'],
            'token_sig'         => $data['token_sig'], //signature to verify token integrity
            'label'             => $data['label'] ?? null,
            'expires_at'        => $data['expires_at'] ?? null,
            'max_uses'          => $data['max_uses'] ?? null,
            'remaining_uses'    => $data['max_uses'] ?? null,
            'is_revoked'        => 0
        ]);


        $id = (int)$this->db->id();
        return $this->findById($id);
    }

    public function findById(int $id): ?array{
        $row = $this->db->get('shares', '*', ['id' => $id]);
        return $row ?: null;
    }

    public function findByToken(string $token): ?array{
         $row = $this->db->get('shares', '*', ['token' => $token]);
        return $row ?: null;
    }

    public function revoke(int $id): void{
        $this->db->update('shares', [
            'is_revoked' => 1
        ], ['id' => $id]);
        
    }

    public function decrementRemainingUses($id):bool {
        $this->db->update('shares',[
            'remaining_uses[-]' => 1
        ], [
            'AND' => [
                'id' => $id,
                'remaining_uses[!]' => null
            ]
        ]);
        return $this->db->rowCount() > 0;
    }


 





}