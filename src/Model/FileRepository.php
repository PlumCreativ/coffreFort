<?php
namespace App\Model;

use Medoo\Medoo;

class FileRepository
{
    private Medoo $db;

    public function __construct(Medoo $db)
    {
        $this->db = $db;
    }

    public function listFiles(): array
    {
        return $this->db->select('files', '*');
    }

    public function listFilesByFolder(int $folderId): array
    {
        return $this->db->select('files', '*', ['folder_id' => $folderId]);
    }

    public function listFoldersByUser(int $userId): array
    {
        return $this->db->select('folders', [
            'id',
            'user_id',
            'parent_id',
            'name',
            'created_at'
        ], [
            'user_id'   => $userId,
            'ORDER'     => ['name' => 'ASC']
        ]);
    }

    public function find(int $id): ?array
    {
        return $this->db->get('files', '*', ['id' => $id]) ?: null;
    }

    public function create(array $data): int
    {
        $this->db->insert('files', $data);
        return (int)$this->db->id();
    }

    public function delete(int $id): void
    {
        $this->db->delete('files', ['id' => $id]);
    }

    public function countFiles(): int
    {
        return (int)$this->db->count('files');
    }

    public function countFilesByUser(int $userId): int
    {
        return (int)($this->db->count('files', ['user_id' => $userId]) ?: 0);
    }

     public function totalSize(): int
    {
        return (int)$this->db->sum('files', 'size') ?: 0;
    }

    public function totalSizeByUser(int $userId): int 
    {
        return (int)($this->db->sum('files', 'size', ['user_id' => $userId]) ?: 0);
    }

    
    // public function quotaBytes(int $userId): int ??? à supprimer??
    // {
    //     return (int)$this->db->get('users', 'quota_total', ['id' => $userId]);
    // }

    public function userQuotaTotal(int $userId): int 
    {
        // évite des erreurs en cas d'absence du mise à jour de "quota_used"
        return (int)($this->db->get('users', 'quota_total', ['id' => $userId]) ?: 0);
    }

    // Met à jour le quota_total d'un utilisateur
    public function updateUserQuota(int $userId, int $quotaTotal): void
    {
        $this->db->update('users', [
            'quota_total'   => $quotaTotal
        ], [
            'id'            => $userId
        ]);
    }

    // Met à jour le quota_used d'un utilisateur
    public function updateQuotaUsed(int $userId, int $quotaUsed): void
    {
        $this->db->update('users', [
            'quota_used' => $quotaUsed
        ], [
            'id'         => $userId
        ]);
    }

    // Récupère les infos complètes d'un utilisateur
    public function getUser(int $userId): ?array
    {
        return $this->db->get('users', [
            'id',
            'email',
            'quota_total',
            'quota_used',
            'is_admin',
            'created_at'
        ], ['id' => $userId]) ?: null;
    }

    // derniers uploads de user
    public function recentUploads(int $userId, int $limit = 20): array
    {
         return $this->db->select('files', '*', [
            'user_id'   => $userId,
            'ORDER'     => ['created_at' => 'DESC', 'id' => 'DESC'], 
            'LIMIT'     => $limit
        ]);
    }

    // Derniers downloads des shares du user
    public function recentDownloads(int $userId, int $limit = 20): array
    {
        // downloads_log -> shares (pour filtrer sur owner) -> file_versions -> files (nom du fichier)
        // jointure utilisé par Medoo!!!!! => en SQL "FROM downloads_log AS dl
        // [>] => LEFT JOIN
        return $this->db->select('downloads_log (dl)', [
            '[>]shares (s)'         => ['dl.share_id' => 'id'],
            '[>]file_versions (fv)' => ['dl.version_id' => 'id'],
            '[>]files (f)'          => ['fv.file_id' => 'id']
        ], [ //les colonnes séléctionnés
            'dl.id (log_id)',
            'dl.share_id',
            'dl.version_id',
            'dl.downloaded_at',
            'dl.ip',
            'dl.user_agent',
            'dl.success',
            'f.original_name'
        ], [ //les conditions
            's.user_id' => $userId,
            'ORDER'     => ['dl.downloaded_at' => 'DESC', 'dl.id' => 'DESC'],
            'LIMIT'     => $limit
        ]);
    }

    public function renameFile(int $id, string $newName): bool
    {
        $count = $this->db->update('files', [
            'original_name' => $newName
        ], [
            'id'            => $id
        ])->rowCount();

        //vérif => le nombre de ligne modifié
        return $count > 0;
    }

    /**
     * Vérifie si un fichier du même user, dans le même dossier, a déjà ce nom
     * en excluant le fichier courant via $excludeId
     */
    public function fileNameExistForUser(int $userId, int $folderId, string $name, int $excludeId = 0): bool
    {
        $where = [
            'user_id'       => $userId,
            'folder_id'     => $folderId,
            'original_name' => $name
        ];

        if($excludeId > 0){
            $where['id[!]'] = $excludeId;  // =>AND id != :excludeId
        }

        $count = (int)$this->db->count('files', $where);
        return $count > 0;
    }


    // ======================= Folders ========================================
    
    public function listFolders(): array
    {
        return $this->db->select('folders', '*');
    }

    public function findFolder(int $id): ?array
    {
        return $this->db->get('folders', '*', ['id' => $id]) ?: null;
    }


     public function createFolder(array $data): int
    {
        $this->db->insert('folders', $data);
        return (int)$this->db->id();
    }

    public function deleteFolder(int $id): void
    {
        $this->db->delete('folders', ['id' => $id]);
    }

    public function folderExists(int $folderId): bool
    {
        return (bool)$this->db->get('folders', 'id', ['id' => $folderId]);
    }


    public function renameFolder(int $id, string $newName): bool
    {
        $count = $this->db->update('folders', [
            'name'  => $newName
        ], [
            'id'    => $id
        ])->rowCount();

        //vérif => le nombre de ligne modifié
        return $count > 0;
    }

    /**
     * vérification si un dossier du même user, même parent_id, a déjà ce nom
     *  => en excluant le dossier en cours via $excludeId
     * true => le nom n'est pas disponible, il y a déjà au moins 1 dossier avec les mêmes user, parent_id, nom
     */
    public function folderNameExistForUser(int $userId, ?int $parentId, string $name, int $excludeId = 0): bool
    {
        $where = [
            'user_id'   => $userId,
            'name'      => $name
        ];

        if($parentId === null){
            $where['parent_id'] = null;
        }else{
            $where['parent_id'] = $parentId;
        }

        if($excludeId > 0){
            $where['id[!]'] = $excludeId;  // =>AND id != :excludeId
        }

        $count = (int)$this->db->count('folders', $where);
        return $count > 0;

    }


     // ======================= pour le shares ========================================

    public function isOwnedByUser(int $fileId, int $userId): bool
    {
        $count = $this->db->count('files', [
            'id'        => $fileId, 
            'user_id'   => $userId
        ]);
        return $count > 0;
    }

    public function folderOwnedByUser(int $folderId, int $userId): bool
    {
        $count = $this->db->count('folders', [
            'id'        => $folderId,
            'user_id'   => $userId
        ]);
        return $count > 0;
    }
}
