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

    //liste tous les fichiers
    public function listFiles(): array
    {
        return $this->db->select('files', '*');
    }

    //liste tous les fichiers
    public function listFilesByFolder(int $folderId): array
    {
        return $this->db->select('files', [
            'id',
            'original_name',
            'mime',
            'size',
            'created_at',
            'updated_at'
        ], [
            'folder_id' => $folderId
        ]);
    }

    // liste les fichiers d'un dossier avec pagination
    public function listFilesByFolderPaginated(int $folderId, int $userId, int $limit = 20, int $offset = 0): array
    {
        return $this->db->select('files', '*', [
            'AND' => [
                'folder_id' => $folderId,
                'user_id'   => $userId
            ],
            'ORDER'     => ['updated_at' => 'DESC'],
            'LIMIT'     => [$offset, $limit]   //pour la pagination
        ]);
    }

    //liste tous les fichiers d'un user
    public function listFilesByUser(int $userId){
        return $this->db->select('files', '*', [
            'user_id'   => $userId,
        ]);
    }

    // liste tous les fichiers d'un utilisateur avec pagination
     public function listFilesByUserPaginated(int $userId, int $limit = 20, int $offset = 0): array
    {
        return $this->db->select('files', '*', [
            'user_id'   => $userId,
            'ORDER'     => ['updated_at' => 'DESC'],
            'LIMIT'     => [$offset, $limit]   //pour la pagination
        ]);
    }

    //retourne tous les caractéristiques d'un fichierspécifique
    public function find(int $id): ?array
    {
        return $this->db->get('files', '*', ['id' => $id]) ?: null;
    }

    //crée un fichier
    public function create(array $data): int
    {
        $this->db->insert('files', $data);
        return (int)$this->db->id();
    }

    //supprime un fichier
    public function delete(int $id): void
    {
        $this->db->delete('files', ['id' => $id]);
    }

    //compte le nbre des fichiers
    public function countFiles(): int
    {
        return (int)$this->db->count('files');
    }

    //compte le nbre de fichier d'un user
    public function countFilesByUser(int $userId): int
    {
        return (int)($this->db->count('files', ['user_id' => $userId]) ?: 0);
    }

    // compte le nombre de fichiers dans un dossier pour un utilisateur
    public function countFilesByFolderByUser(int $userId, int $folderId): int
    {
        return (int)($this->db->count('files', [
            'user_id'   => $userId,
            'folder_id' => $folderId
            ]) ?: 0);
    }

    //retourne le totel size des fichiers
    public function totalSize(): int
    {
        return (int)$this->db->sum('files', 'size') ?: 0;
    }

    //retourne le totel size des fichiers d'un user
    public function totalSizeByUser(int $userId): int 
    {
        
        $total = $this->db->sum('file_versions',[
            '[>]files' => ['file_versions.file_id' => 'id'],
        ], 'file_versions.size',
        [
            'files.user_id' => $userId
        ]);
      
        return (int)($total ?? 0);
    }

    // derniers uploads d'un user
    public function recentUploads(int $userId, int $limit = 20): array
    {
         return $this->db->select('files', '*', [
            'user_id'   => $userId,
            'ORDER'     => ['created_at' => 'DESC', 'id' => 'DESC'], 
            'LIMIT'     => $limit
        ]);
    }

    // Derniers downloads des shares d'un user
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

    //renommer un fichier
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


    //============================= pour le versionnage ========================================

    //récuperer le max de version => le "current version"
    public function getMaxVersionForFile(int $fileId): int
    {
        $max = $this->db->max('file_versions', 'version', ['file_id' => $fileId]);
        return (int)($max ?: 0);
    }

    //crée une nouvelle versions
    public function createFileVersion(array $data): int
    {
        $this->db->insert('file_versions', $data);
        return (int)$this->db->id();
    }

    // mise à jour les données d'un fichier donné
    public function updateFileMeta(int $fileId, array $data): void
    {
        $this->db->update('files', $data, ['id' => $fileId]);
    }

    //compte le nbre de version d'un file
    public function getVersionCount(int $fileId): int
    {
        $count = $this->db->count('file_versions', ['file_id' => $fileId]) ?: 0;
        return (int)$count;
    }

    // n darab dernières versions
    public function getLatestVersions(int $fileId, int $limit = 5): array
    {
        return $this->db->select('file_versions', [
            'version', 
            'size', 
            'created_at', 
            'checksum'
        ], [
            'file_id' => $fileId, 
            'ORDER'   => ['version' => 'DESC'], 
            'LIMIT'   => $limit
        ]) ?: [];
    }

    //retourne les version d'un fichier en pagination
    public function listVersionsPaginated(int $fileId, int $limit = 20, $offset = 0): array
    {
        // compter le total
        $total = $this->getVersionCount($fileId);

        //récupérer la version courante
        $currentVersion = $this->getMaxVersionForFile($fileId);

        //récuperer les versions paginées      
        $rows = $this->db->select('file_versions', [
            'id',
            'version', 
            'size', 
            'created_at',
            'checksum'
        ], [
            'file_id' => $fileId, 
            'ORDER'   => ['version' => 'DESC'], 
            'LIMIT'   => [$offset, $limit]
        ]) ?: [];

        return [
            'rows'              => $rows, 
            'total'             => $total, 
            'current_version'   => $currentVersion,
            'limit'             => $limit, 
            'offset'            => $offset
        ];
    }

    //retourne les version d'un fichier en pagination pour partage
    public function listVersionsForShare(int $fileId, int $limit = 20, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $rows = $this->db->select('file_versions', [
            'id',
            'version',
            'size',
            'created_at'
        ], [
            'file_id' => $fileId,
            'ORDER'   => ['version' => 'DESC'],
            'LIMIT'   => [$offset, $limit]
        ]) ?: [];

        $total = (int)($this->db->count('file_versions', ['file_id' => $fileId]) ?: 0);

        return ['rows'   => $rows, 
                'total'  => $total, 
                'limit'  => $limit, 
                'offset' => $offset];
    }

    //dernier version d'un fichier => version courante
    public function getCurrentVersionRow(int $fileId): ?array
    {
        $row = $this->db->get('file_versions', [
            'id', 
            'file_id',
            'version',
            'stored_name',
            'size',
            'created_at',
            'iv',
            'auth_tag',
            'key_envelope',
            'checksum'
        ], [
            'file_id'   => $fileId,
            'ORDER'     => ['version' => 'DESC'],
            'LIMIT'     => 1
        ]);

        return $row ?: null;
    }
    

    //version précise
    public function getVersionRow(int $fileId, int $version): ?array
    {
        return $this->db->get('file_versions', [
            'id', 
            'file_id',
            'version',
            'stored_name',
            'size',
            'created_at',
            'iv',                //pour pouvoir déchiffrer une version précise
            'auth_tag',          //pour pouvoir déchiffrer une version précise
            'key_envelope',      //pour pouvoir déchiffrer une version précise
            'checksum',
        ], [
            'file_id'   => $fileId,
            'version'   => $version
        ]) ?: null;
    }


    //pour affichage côté client web
     public function getCurrentVersionMeta(int $fileId): ?array
    {
        $row = $this->db->get('file_versions', [
            'id', 
            'file_id',
            'version',
            'size',
            'created_at',
        ], [
            'file_id'   => $fileId,
            'ORDER'     => ['version' => 'DESC'],
            'LIMIT'     => 1
        ]);

        return $row ?: null;
    }

    //retourne les caractéristiques d'un version précise
    public function findVersion(int $versionId): ?array
    {
        $version = $this->db->get('file_versions', '*', ['id' => $versionId]);
        return $version ?: null;
    }

    //suppression la version de la bdd
    public function deleteVersion(int $versionId): bool
    {
        $result = $this->db->delete('file_versions', ['id' => $versionId]);
        return $result->rowCount() > 0;
    }

    //récupère toutes les version d'un fichier
    public function getAllVersions(int $fileId){
        return $this->db->select('file_versions', '*', ['file_id' => $fileId]);
    }

    //supprime toutes les versions 
    public function deleteAllVersions(int $fileId): bool
    {
        $result = $this->db->delete('file_versions', ['file_id' => $fileId]);
        return $result->rowCount() > 0;
    }

    public function getMimeType(int $fileId){
        $mimeTypeFile = $this->db->get('files', 'mime', ['id' => $fileId]);
        return $mimeTypeFile ?: null;
    }

    //************************ il faut pour les files **************************

    // public function quotaBytes(int $userId): int ??? à supprimer??
    // {
    //     return (int)$this->db->get('users', 'quota_total', ['id' => $userId]);
    // }

    //retourne le quota_total d'un user
    public function userQuotaTotal(int $userId): int 
    {
        // évite des erreurs en cas d'absence du mise à jour de "quota_used"
        return (int)($this->db->get('users', 'quota_total', ['id' => $userId]) ?: 0);
    }

    // mise à jour le quota_total d'un user
    public function updateUserQuota(int $userId, int $quotaTotal): void
    {
        $this->db->update('users', [
            'quota_total'   => $quotaTotal
        ], [
            'id'            => $userId
        ]);
    }

    // mise à jour le quota_used d'un user
    public function updateQuotaUsed(int $userId, int $quotaUsed): void
    {
        $this->db->update('users', [
            'quota_used' => $quotaUsed
        ], [
            'id'         => $userId
        ]);
    }


    // Récupère les infos complètes d'un user
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
   

    // ============================ Folders ========================================
    
    //liste tous les folders
    public function listFolders(): array
    {
        return $this->db->select('folders', '*');
    }

    //retourne les folders d'un user
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

    //retourne toutes les caractéristiques d'un folder
    public function findFolder(int $id): ?array
    {
        return $this->db->get('folders', '*', ['id' => $id]) ?: null;
    }

    //crée un folder
    public function createFolder(array $data): int
    {
        $this->db->insert('folders', $data);
        return (int)$this->db->id();
    }

    //supprime un folder
    public function deleteFolder(int $id): void
    {
        $this->db->delete('folders', ['id' => $id]);
    }

    //vérif si un folder existe
    public function folderExists(int $folderId): bool
    {
        return (bool)$this->db->get('folders', 'id', ['id' => $folderId]);
    }

    //renomme un folder
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

    //compte le nbre de fichiers dans un dossier pour un user donné
    public function countFilesByFolder(int $folderId, int $userId){
        return $this->db->count('files', [
            'folder_id' => $folderId,
            'user_id'   => $userId
        ]);
    }

    //compte le nbre de sous-dossier d'un dossier
    public function countSubfolders(int $parentId){
        return $this->db->count('folders', [
            'parent_id' => $parentId
        ]);
    }


    // ========================== pour le shares ========================================

    //vérifie si le fichier appartient à un user donné
    public function isOwnedByUser(int $fileId, int $userId): bool
    {
        $count = $this->db->count('files', [
            'id'        => $fileId, 
            'user_id'   => $userId
        ]);
        return $count > 0;
    }

    //vérifie si le dossier appartient à un user donné
    public function folderOwnedByUser(int $folderId, int $userId): bool
    {
        $count = $this->db->count('folders', [
            'id'        => $folderId,
            'user_id'   => $userId
        ]);
        return $count > 0;
    }
}
