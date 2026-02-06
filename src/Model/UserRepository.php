<?php
namespace App\Model;

use Medoo\Medoo;

class UserRepository
{
    private Medoo $db;

    public function __construct(Medoo $db)
    {
        $this->db = $db;
    }

    //liste tous les users
    public function listUsers(): array
    {
        return $this->db->select('users', '*');
    }

    //retourne tous les caractéristiques d'un user donné
    public function find(int $id): ?array
    {
        return $this->db->get('users', '*', ['id' => $id]) ?: null;
    }

    //retourne tous les caractéristique d'un user qui a l'email donné en paramètre
    public function findByEmail(string $email): ?array
    {
        return $this->db->get('users', '*', ['email' => $email]) ?: null;
    }

    //crée un user
    public function create(array $data): int
    {
        $this->db->insert('users', $data);
        return (int)$this->db->id();
    }

    //supprime un user
    public function delete(int $id): void
    {
        $this->db->delete('users', ['id' => $id]);
    }

    //compte le nbre de users
    public function countUsers(): int
    {
        return (int)$this->db->count('users');
    }

    //update le quota d'un user
    public function updateQuota(int $targetUserId, int $newQuota): bool
    {
        $count = $this->db->update('users', [
            'quota_total' => $newQuota
        ], [
            'id'     => $targetUserId
        ])->rowCount();

        //vérif => le nombre de ligne modifié
        return $count > 0;
    }

    //update le quota d'un user
    public function updateUserQuotaUsed(int $targetUserId, int $newQuotaUsed): bool
    {
        $count = $this->db->update('users', [
            'quota_used' => $newQuotaUsed
        ], [
            'id'     => $targetUserId
        ])->rowCount();

        //vérif => le nombre de ligne modifié
        return $count > 0;
    }

}