<?php
// src/Controller/FileController.php

namespace App\Controller;

use App\Model\FileRepository;
use App\Model\UserRepository;
use Medoo\Medoo;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class FileController
{
    private Medoo $db; 
    private FileRepository $files;
    private string $uploadDir;
    private string $jwtSecret;


    // public function __construct(Medoo $db)
    // {
    //     $this->files = new FileRepository($db);
    //     $this->uploadDir = __DIR__ . '/../../storage/uploads';
    // }

    public function __construct(Medoo $db, ?string $jwtSecret = null)
    {
        $this->db = $db;
        $this->files = new FileRepository($db);
        $this->uploadDir = __DIR__ . '/../../storage/uploads';

        // Init du secret JWT (env ou param)
        $this->jwtSecret = $jwtSecret ?? ($_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?? '');

        if ($this->jwtSecret === '') {
            // Tu peux aussi throw ici, mais je préfère debug clair
            error_log("JWT_SECRET manquant dans les variables d'environnement.");
        }
    }


    /** HELPER
     * Récupère l'utilisateur authentifié à partir du header Authorization: Bearer <jwt>.
     * 
     * @throws \Exception avec code HTTP (401, 404, ...) en cas de problème.
     */
    private function getAuthenticatedUserFromToken(Request $request): array
    {
        // 1) Récupérer le header Authorization
        $authHeader = $request->getHeaderLine('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new \Exception('Token manquant', 401);
        }

        $jwt = substr($authHeader, 7); // enlever "Bearer "

        // 2) Vérifier le secret
        if (empty($this->jwtSecret)) {
            throw new \Exception('JWT secret non configuré sur le serveur.', 500);
        }

        // 3) Décoder le token
        try {
            $decoded = JWT::decode($jwt, new Key($this->jwtSecret, 'HS256'));
        } catch (\Exception $e) {
            throw new \Exception('Token invalide: ' . $e->getMessage(), 401);
        }

        $email = $decoded->email ?? null;
        error_log("EMAIL DU TOKEN = " . ($email ?? 'NULL'));

        if (!$email) {
            throw new \Exception('Email manquant dans le token', 401);
        }

        // 4) Récupérer l'utilisateur
        $userRepo = new \App\Model\UserRepository($this->db);
        $user = $userRepo->findByEmail($email);

        if (!$user) {
            throw new \Exception('Utilisateur introuvable', 404);
        }

        // OK
        return $user; // ex: ['id' => ..., 'email' => ...]
    }



    // GET /files ou GET /files?folder={id}
    public function list(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        
        // Si un folder_id est fourni, filtrer par dossier
        if (isset($queryParams['folder'])) {
            $folderId = (int)$queryParams['folder'];
            
            // Vérifier si le dossier existe
            if (!$this->files->folderExists($folderId)) {
                $response->getBody()->write(json_encode([
                    'error' => 'Folder not found',
                    'folder_id' => $folderId
                ]));
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(404);
            }
            
            $data = $this->files->listFilesByFolder($folderId);
        } else {
            // Sinon, retourner tous les fichiers
            $data = $this->files->listFiles();
        }

        $payload = json_encode($data, JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }


    // GET /filesPaginated => avec pagination
    public function listPaginated(Request $request, Response $response): Response
    {
        $nbFiles = $this->files->countfiles();
        
        // il faut mettre dans url => files?page=3  (par exemple)
        $page = (int)($request->getQueryParams()['page'] ?? 1);
        $limit = (int)($request->getQueryParams()['limit'] ?? 3);
      

        $offset = ($page -1) * $limit;
        
        $data = $this->files->listFiles();

        $dataSliced = array_slice($data, $offset, $limit);

        $payload = json_encode($dataSliced, JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }


    // GET /files/{id}
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $file = $this->files->find($id);

        if (!$file) {
            $response->getBody()->write(json_encode(['error' => 'File not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($file, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }


    // POST /files  (upload via form-data)
    public function upload(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();

        // DEBUG => Afficher ce qui est reçu
        if (empty($uploadedFiles)) {
            $response->getBody()->write(json_encode([
                'error' => 'No file uploaded',
                'debug' => [
                    'uploaded_files' => $uploadedFiles,
                    'content_type' => $request->getHeaderLine('Content-Type'),
                    'method' => $request->getMethod()
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (!isset($uploadedFiles['file'])) {
            $response->getBody()->write(json_encode([
                'error' => 'No file with key "file" found',
                'debug' => [
                    'received_keys' => array_keys($uploadedFiles)
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $file = $uploadedFiles['file'];

        if ($file->getError() !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
            ];
            
            $response->getBody()->write(json_encode([
                'error' => 'Upload error',
                'error_code' => $file->getError(),
                'error_message' => $errorMessages[$file->getError()] ?? 'Unknown error'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Validation du fichier
        $maxSize = 2 * 1024 * 1024; // 2 Mo
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg', 'application/doc', 'application/docx', 'application/pdf'];
        
        $size = $file->getSize();
        $mimeType = $file->getClientMediaType();
        
        // Vérification de la taille
        if ($size > $maxSize) {
            $response->getBody()->write(json_encode(['error' => 'Taille trop grande (max. 2 Mo)']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // Vérification du type MIME
        if (!in_array($mimeType, $allowedTypes)) {
            $response->getBody()->write(json_encode([
                'error' => 'Type de fichier non autorisé.',
                'received_type' => $mimeType,
                'allowed_types' => $allowedTypes
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
            
        // Décoder le token JWT depuis le header Authorization
        try {
            $user = $this->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 401;
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($code);
        }

        // Récupérer folder_id depuis form-data ou query !!!! => remettre plus tard quand on lie avec le folder
        // $folderId = (int)($request->getParsedBody()['folder_id'] ?? 0);
        // if ($folderId <= 0 || !$this->files->folderExists($folderId)) {
        //     $response->getBody()->write(json_encode(['error' => 'Dossier introuvable']));
        //     return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        // }


        $totalSize = $this->files->totalSize();
        $quota = $this->files->userQuotaTotal($userId); //ancien quotaBytes

        if ($quota > 0 && ($totalSize + $size) > $quota) {
            $response->getBody()->write(json_encode(['error' => 'Quota exceeded']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(413);
        }

        $originalName = $file->getClientFilename();
        $storedName = uniqid('f_', true) . '_' . $originalName;

        // Créer le répertoire s'il n'existe pas
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }

        $file->moveTo($this->uploadDir . DIRECTORY_SEPARATOR . $storedName);

        $id = $this->files->create([
            'user_id'       => $userId,               
            'folder_id'     => 2,               //à changer en récupérant le bon folder_id!!! 
            'original_name' => $originalName,
            'stored_name'   => $storedName,
            'mime'          => $mimeType,
            'size'          => $size,
            'created_at'    => date('Y-m-d H:i:s') // => pour mettre l'heure, minutes..
        ]);

        $response->getBody()->write(json_encode([
            'message'       => 'File uploaded successfully',
            'id'            => $id, 
            'filename'      => $originalName,
            'stored_name'   => $storedName,
            'size'          => $size
        ], JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }


    // GET /files/{id}/download
    public function download(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $file = $this->files->find($id);

        if (!$file) {
            $response->getBody()->write('File not found');
            return $response->withStatus(404);
        }

        $path = $this->uploadDir . DIRECTORY_SEPARATOR . $file['stored_name'];

        if (!file_exists($path)) {
            $response->getBody()->write('File missing on disk');
            return $response->withStatus(500);
        }

        $stream = fopen($path, 'rb');
        $response->getBody()->write(stream_get_contents($stream));
        fclose($stream);

        return $response
            ->withHeader('Content-Type', $file['mime'])
            ->withHeader('Content-Disposition', 'attachment; filename="' . $file['original_name'] . '"')
            ->withStatus(200);
    }


    // DELETE /files/{id}
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $file = $this->files->find($id);

        if (!$file) {
            $response->getBody()->write(json_encode(['error' => 'File not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Supprimer le fichier sur le disque
        $path = $this->uploadDir . DIRECTORY_SEPARATOR . $file['stored_name'];
        if (file_exists($path)) {
            unlink($path);
        }

        // Supprimer en base
        $this->files->delete($id);

        $response->getBody()->write(json_encode(['message' => 'File deleted']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }


    // GET /stats
    public function stats(Request $request, Response $response): Response
    {
        $userId = 1; 
        $totalSize = $this->files->totalSize();
        $quota = $this->files->userQuotaTotal($userId); //ancien quotaBytes

        // Exercice 1: utiliser countFiles() ici si l'étudiant l’a codée
        $count = $this->files->countFiles();

        $data = [
            'total_size_bytes' => $totalSize,
            'quota_bytes'      => $quota,
            'file_count'       => $count,
        ];

        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }


    // PUT /quota - Met à jour le quota d'un utilisateur
    public function setQuota(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        // Validation du champ quota_total
        if (!isset($body['quota_total'])) {
            $error = ['error' => 'Le champ "quota_total" est obligatoire'];
            $response->getBody()->write(json_encode($error, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Validation que c'est un nombre positif
        $bytes = (int)$body['quota_total'];
        if ($bytes <= 0) {
            $error = ['error' => 'Le quota doit être un nombre positif'];
            $response->getBody()->write(json_encode($error, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // ID de l'utilisateur => à remplacer par l'utilisateur connecté
        $userId = isset($body['user_id']) ? (int)$body['user_id'] : 1;

        // Vérifier que l'utilisateur existe
        $user = $this->files->getUser($userId);
        if (!$user) {
            $error = ['error' => 'Utilisateur non trouvé'];
            $response->getBody()->write(json_encode($error, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Mettre à jour le quota
        $this->files->updateUserQuota($userId, $bytes);

        // Récupérer les nouvelles données
        $updatedUser = $this->files->getUser($userId);

        $data = [
            'message' => 'Quota mis à jour avec succès',
            'user_id' => $userId,
            'quota_total' => $updatedUser['quota_total'],
            'quota_used' => $updatedUser['quota_used'],
            'quota_available' => $updatedUser['quota_total'] - $updatedUser['quota_used']
        ];

        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }


    // GET /me/quota — utilisé / total / %
    public function meQuota(Request $request, Response $response): Response
    {
        // récuperer id via JWT
        $userId = 1;

        // utilisé => somme des fichiers du user
        $usedBytes = $this->files->totalSizeByUser($userId);

        // total => quota_total depuis la table user
        $totalBytes = $this->files->userQuotaTotal($userId);

        if ($totalBytes <= 0) {
            $percent = 0;
        } else {
            $percent = round(($usedBytes / $totalBytes) * 100, 2);
        }

        $data = [
            'user_id' => $userId,
            'used_bytes' => $usedBytes,
            'total_bytes' => $totalBytes,
            'percent_used' => $percent
        ];

        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }


   // GET /me/activity — derniers événements (uploads + downloads)
    public function meActivity(Request $request, Response $response): Response
    {
        // récuperer id via JWT
        $userId = 1;

        $limit = (int)($request->getQueryParams()['limit'] ?? 20);

        $uploads = $this->files->recentUploads($userId, $limit);
        $downloads = $this->files->recentDownloads($userId, $limit);

        // Normaliser les events dans un même format
        $events = [];

        foreach($uploads as $upload){
            $events[] = [
                'type' => 'upload',
                'id' => (int)$upload['id'],
                'file_id' => (int)$upload['id'],
                'file_name' => $upload['original_name'],
                'size' => (int)$upload['size'],
                'at' => $upload['created_at'],
            ];
        }

        foreach($downloads as $download){
            $events[] = [
                'type' => 'download',
                'id' => (int)$download['log_id'],
                'share_id' => (int)$download['share_id'],
                'version_id' => (int)$download['version_id'],
                'file_name' => $download['original_name'] ?? null,
                'at' => $download['downloaded_at'],
                'ip' => $download['ip'],
                'user_agent' => $download['user_agent'],
                'success' => (bool)$download['success'],
            ];
        }

        // Trier par date desc avec "usort"
        usort($events, function ($a, $b) {

            // strtotime => converti str en timestamp
            // b avant a => tri décroissant (a avant b => tri croissant)
            return strtotime($b['at']) <=> strtotime($a['at']);
        });

        // Limiter après merge => $events il y a trop éléments 
        $events = array_slice($events, 0, $limit); //=> il renvoie de 0 à p.ex 20 éléménts..

        $response->getBody()->write(json_encode([
            'user_id' => $userId,
            'count' => count($events),
            'events' => $events
        ], JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    }


//====================== Folders ================================================

    // GET /folders
    // public function listFolders(Request $request, Response $response): Response
    // {
    //     $data = $this->files->listFolders();

    //     $payload = json_encode($data, JSON_PRETTY_PRINT);
    //     $response->getBody()->write($payload);
    //     return $response
    //         ->withHeader('Content-Type', 'application/json')
    //         ->withStatus(200);
    // }

    // GET /folders — retourne uniquement les dossiers appartenant à l'utilisateur connecté
    public function listFolders(Request $request, Response $response): Response
    {
        try {
            $user = $this->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];
        } catch (\Exception $e) {
            $code = $e->getCode();
            if ($code < 100 || $code > 599) {
                $code = 401; // fallback
            }

            $response->getBody()->write(json_encode([
                'error' => $e->getMessage()
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($code);
        }

        // Récupérer uniquement les dossiers de ce user
        $data = $this->files->listFoldersByUser($userId);

        $payload = json_encode($data, JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
    
    // POST /folders - Crée un nouveau dossier
    public function createFolder(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        
        // Validation
        if (!isset($body['user_id']) || !isset($body['name'])) {
            $response->getBody()->write(json_encode([
                'error' => 'user_id and name are required'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Si parent_id n'est pas fourni ou est 0 => à mettre NULL pour un dossier racine
        $parentId = null;
        if (isset($body['parent_id']) && $body['parent_id'] > 0) {
            $parentId = (int)$body['parent_id'];
        }
        
        $folderData = [
            'user_id' => (int)$body['user_id'],
            'parent_id' => $parentId,
            'name' => $body['name'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $folderId = $this->files->createFolder($folderData);
        
        $response->getBody()->write(json_encode([
            'message' => 'Folder created',
            'id' => $folderId,
            'name' => $body['name'],
            'parent_id' => $parentId
        ], JSON_PRETTY_PRINT));
        
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    // DELETE /folders/{id}
    public function deleteFolder(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $folder = $this->files->findFolder($id);

        if (!$folder) {
            $response->getBody()->write(json_encode(['error' => 'Folder not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Supprimer le fichier sur le disque
        $path = $this->uploadDir . DIRECTORY_SEPARATOR . $folder['name'];
        if (file_exists($path)) {
            unlink($path);
        }

        // Supprimer en base
        $this->files->deleteFolder($id);

        $response->getBody()->write(json_encode(['message' => 'Folder deleted']));
        // suppression réussi => statut: 204
        return $response->withHeader('Content-Type', 'application/json')->withStatus(204);
    }



}


?>