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

    public function listUsers(): array
    {
        return $this->db->select('users', '*');
    }

    public function find(int $id): ?array
    {
        return $this->db->get('users', '*', ['id' => $id]) ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->get('users', '*', ['email' => $email]) ?: null;
    }

    public function create(array $data): int
    {
        $this->db->insert('users', $data);
        return (int)$this->db->id();
    }

    public function delete(int $id): void
    {
        $this->db->delete('users', ['id' => $id]);
    }

    public function countUsers(): int
    {
        return (int)$this->db->count('users');
    }
}