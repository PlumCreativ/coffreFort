<?php
// src/Controller/FileController.php

namespace App\Controller;

use App\Helpers\RequestHelper;
use App\Helpers\StorageWriter;
use App\Model\FileRepository;
use App\Model\UserRepository;
use App\Model\DownloadLogRepository;
use App\Security\AuthService;
use App\Security\FileCrypto;
use Medoo\Medoo;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;


class FileController {

    private Medoo $db; 
    private FileRepository $files;
    private UserRepository $users;
    private AuthService $auth;
    private DownloadLogRepository $downloadLog;
    private string $uploadDir;
    private string $jwtSecret;
    private string $kek;

    private const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100 Mo
    
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'application/msword',  // .doc
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',  // .docx
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',  // .xlsx (bonus)
    ];
    
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xlsx'
    ];
    


    // public function __construct(Medoo $db)
    // {
    //     $this->files = new FileRepository($db);
    //     $this->uploadDir = __DIR__ . '/../../storage/uploads';
    // }

    public function __construct(Medoo $db, ?string $jwtSecret = null)
    {
        $this->db = $db;
        $this->files = new FileRepository($db);
        $this->users = new UserRepository($db);
        $this->uploadDir = __DIR__ . '/../../storage/uploads';
        $this->downloadLog = new DownloadLogRepository($db);

        // Init du secret JWT (env ou param)
        $this->jwtSecret = $jwtSecret ?? ($_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?? '');
        $this->auth = new AuthService($db, $this->jwtSecret);

        if ($this->jwtSecret === '') {
            // Tu peux aussi throw ici, mais je préfère debug clair
            error_log("JWT_SECRET manquant dans les variables d'environnement.");
        }

        $kekRow = $_ENV['KEY_ENCRYPTION_KEY'] ?? getenv('KEY_ENCRYPTION_KEY') ?? '';
        $this->kek = trim($kekRow);
        if ($this->kek === '' || strlen($this->kek) < 32) {
            error_log("KEY_ENCRYPTION_KEY manquante/mauvaise (len=" . strlen($this->kek) . ")");
        }
    }

    //=============================================================================================================
    //                                          FILES
    //=============================================================================================================

    /*
     * GET /files ou GET /files?folder={id}  ******************************************************************************** OK
     * Liste des fichiers de l'utilisateur avec pagination
     * GET /files?limit=20&offset=0 // GET /files?limit=20&offset=20
     * GET /files?folder=10&limit=10&offset=0 //GET /files?folder=10&limit=10&offset=10
     */
    public function list(Request $request, Response $response): Response
    {
        //authentification
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? min(100, max(1, (int)$queryParams['limit'])) : 20; // garantir: 1 < limit < 20
        $offset = isset($queryParams['offset']) ? max(0, (int)$queryParams['offset']) : 0;
        
        // Si un folder_id est fourni, filtrer par dossier
        if (isset($queryParams['folder'])) {
            $folderId = (int)$queryParams['folder'];

            if ($folderId <= 0) {
                return $this->json($response, ['error' => 'ID de dossier invalide'], 400);
            }
            
            // Vérifier si le dossier existe
            $folder = $this->files->findFolder($folderId);
            if (!$folder) {
                return $this->json($response, ['error' => 'Dossier introuvable'], 404);
            }

            //vérifier si le dossier appartient au user
            if ((int)$folder['user_id'] !== $userId) {
                return $this->json($response, ['error' => 'Accès interdit à ce dossier'], 403);
            }
            
            //Compter AVANT de récupérer (pour éviter de charger si 0 résultats)
            $total = $this->files->countFilesByFolderByUser($userId, $folderId);

            // Si aucun résultat, retourner directement
            if ($total === 0) {
                return $this->json($response, [
                    'files'    => [],
                    'total'     => 0,
                    'limit'     => $limit,
                    'offset'    => $offset
                ], 200);
            }
            $files = $this->files->listFilesByFolderPaginated($folderId, $userId, $limit, $offset);

        } else {
            // Sinon, retourner tous les fichiers
            //Compter AVANT de récupérer (pour éviter de charger si 0 résultats)
            $total = $this->files->countFilesByUser($userId);

            // Si aucun résultat, retourner directement
            if ($total === 0) {
                return $this->json($response, [
                    'files'    => [],
                    'total'     => 0,
                    'limit'     => $limit,
                    'offset'    => $offset
                ], 200);
            }
            $files = $this->files->listFilesByUserPaginated($userId, $limit, $offset);
        }

        return $this->json($response, [
            'files'  => $files,
            'total'  => $total,      
            'limit'  => $limit,      
            'offset' => $offset     
        ], 200);
    }


    // GET /filesPaginated => avec pagination  => je n'utilise pas cette route, c'est juste pour montrer un exemple de pagination simple sans filtrage par user ou folder (pas d'auth, pas de vérif de propriétaire) ******************************************************************************************
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


    // GET /files/{id}  => détails d'un fichier avec versions  ************************************************************************ OK
    //(FileDetails=> pour rafraîchir les métadonnées (latest_versions içi n'est pas utilisé)
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        //vérif authentification
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];

        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        $file = $this->files->find($id);

        if (!$file) {
            return $this->json($response, ['error' => 'Fichier introuvable'], 404);
        }

        //vérif si user est le propriétaire
        if((int)$file['user_id'] !== $userId){
            return $this->json($response, ['error' => 'Accès interdit'], 403);
        }

        //paramètre : N darab dernières versions à retourner
        $params = $request->getQueryParams();
        $limit = isset($params['latest_versions_limit']) ? (int)$params['latest_versions_limit'] : 5;

        //limit de sécurité
        if($limit <= 0) $limit = 5;
        if($limit > 50) $limit = 50;

        //récupérer les infos de versioning
        $currentVersion = $this->files->getMaxVersionForFile($id);
        $versionCount = $this->files->getVersionCount($id);
        $latestVersions = $this->files->getLatestVersions($id, $limit);

        // formatter les versions avec checksum tronqué => BINARY(32) => bin2hex puis truncate  
        $latestMapped = array_map(function ($row) {
            $checksumHex = bin2hex($row['checksum']);
            return [
                'version'       => (int)$row['version'],
                'size'          => (int)$row['size'],
                'created_at'    => $row['created_at'],
                'checksum'      => substr($checksumHex, 0, 12) . '...'  // 12 premiers caractères
            ];
        }, $latestVersions);

        // Construction la réponse
        $responseData = [
            'id'                 => (int)$file['id'],
            'user_id'            => (int)$file['user_id'], 
            'folder_id'          => (int)$file['folder_id'], 
            'original_name'      => $file['original_name'],
            'stored_name'        => $file['stored_name'],
            'mime'               => $file['mime'],
            'size'               => (int)$file['size'],
            'created_at'         => $file['created_at'], 
            'updated_at'         => $file['updated_at'],

            // nouvelles infos versionning
            'current_version'    => $currentVersion,
            'versions_count'     => $versionCount,
            'latest_versions'    => $latestMapped,
        ];
       
        return $this->json($response, $responseData, 200);
    }

        
    // GET /files/{id}/versions  => liste complète paginée des versions  ************************************************************************************
    public function listVersions(Request $request, Response $response, array $args): Response
    {        
        //vérif authentification
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];

        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        //vérif si fileId est présent et valide
        $fileId = (int)($args['id'] ?? 0);

        if($fileId <= 0){
            return $this->json($response, ['error' => 'id invalide'], 400);
        }

        //vérif fichier et owner
        $file = $this->files->find($fileId);
        if(!$file){
            return $this->json($response, ['error' => 'Fichier introuvable'], 404);
        }

        if((int)$file['user_id'] !== $userId){
            return $this->json($response, ['error' => 'Accès interdit'], 403);
        }

        // paramètre de pagination
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? min(100, max(1, (int)$queryParams['limit'])) : 20; // garantir: 1 < limit < 20
        $offset = isset($queryParams['offset']) ? max(0, (int)$queryParams['offset']) : 0;

        //limit de sécurité => à supprimer si ça marche
        // if($limit <= 0) $limit = 20;
        // if($limit > 100) $limit = 100;
        // if($offset < 0) $offset = 0;

        //Compter AVANT de récupérer (pour éviter de charger si 0 résultats)
        $total = $this->files->getVersionCount($fileId);

        // Si aucun résultat, retourner directement
        if ($total === 0) {
            return $this->json($response, [
                'versions'    => [],
                'total'     => 0,
                'limit'     => $limit,
                'offset'    => $offset
            ], 200);
        }

        // Récupérer les versions avec pagination
        $versions = $this->files->listVersionsPaginated($fileId, $limit, $offset);
        $currentVersion = (int)$versions['current_version'];

        // formatter les versions avec checksum tronqué => BINARY(32) => bin2hex puis truncate  
        $versionsMapped = array_map(function ($row) use ($currentVersion) {  //importer la variable => use
            $checksumHex = bin2hex($row['checksum']);
            return [
                'id'            => (int)$row['id'],
                'version'       => (int)$row['version'],
                'size'          => (int)$row['size'],
                'created_at'    => $row['created_at'],
                // 'checksum'      =>  substr($checksumHex, 0, 16) . '...', // Plus long pour la liste complète
                'checksum'      => $checksumHex,
                'is_current'    => (int)$row['version'] === $currentVersion
            ];
        },$versions['rows']);
        
        return $this->json($response, [
            'file_id'           => $fileId,
            'current_version'   => $currentVersion, //$result['current_version'],
            'total'             => $total, //$result['total'],
            'limit'             => $limit,
            'offset'            => $offset,
            'versions'          => $versionsMapped
        ], 200);
    }

    // POST /files  (upload via form-data)************************************************************************************** OK
    public function upload(Request $request, Response $response): Response
    {
        //vérif authentification => décoder le token JWT depuis le header Authorization
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];

        } catch (\Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        //vérif la présence du fichier
        $uploadedFiles = $request->getUploadedFiles();
        if(empty($uploadedFiles) || !isset($uploadedFiles['file'])){
             return $this->json($response, [
                'error' => "Aucun fichier fourni",
                'received_keys' => !empty($uploadedFiles) ? array_keys($uploadedFiles) : []
                ], 400);
        }
    
        $file = $uploadedFiles['file'];

        if ($file->getError() !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE     => 'Fichier trop volumineux (upload_max_filesize)',
                UPLOAD_ERR_FORM_SIZE    => 'Fichier trop volumineux (MAX_FILE_SIZE)',
                UPLOAD_ERR_PARTIAL      => 'Fichier partiellement uploadé',
                UPLOAD_ERR_NO_FILE      => 'Aucun fichier uploadé',
                UPLOAD_ERR_NO_TMP_DIR   => 'Dossier temporaire manquant',
                UPLOAD_ERR_CANT_WRITE   => 'Échec écriture sur disque',
                UPLOAD_ERR_EXTENSION    => 'Upload stoppé par extension PHP',
            ];
            
            return $this->json($response, [
                'error'         => 'Erreur upload',
                'error_code'    => $file->getError(),
                'error_message' => $errorMessages[$file->getError()] ?? 'Erreur inconnu'
            ], 400);
        }

        // validation du fichier => MIME, taille, extension
        try {
            $this->validateUploadedFile($file);
        } catch (\RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 400);
        }
            
        //Récupérer folder_id depuis form-data ou query !
        $parsedBody = $request->getParsedBody();
        //$folderId = 5; //=> pour le test avec postman;
        $folderId = (int)($parsedBody['folder_id'] ?? 0);

        if($folderId <= 0){
            return $this->json($response, ['error' => 'Dossier non spécifié'], 400);
        }

        //vérif dossier existe
        if(!$this->files->folderExists($folderId)) {
            return $this->json($response, ['error' => 'Dossier introuvable'], 404);
        }

        $folder = $this->files->findFolder($folderId);
        //vérif si le folder appartient à user
        if((int)$folder['user_id'] !== $userId){
            return $this->json($response, ['error' => 'Accès interdit à ce dossier'], 403);
        }

        //vérif quota
        $size = (int)$file->getsize();
        $totalSize = $this->files->totalSizeByUser($userId); //=> par utilisateur!!! 
        $quota = $this->files->userQuotaTotal($userId); //ancien quotaBytes 

        if ($quota > 0 && ($totalSize + $size) > $quota) {
            return $this->json($response, [
                'error'      => 'Quota dépassé',
                'quota_max'  => $quota,
                'quota_used' => $totalSize,
                'file_size'  => $size
                ], 413);
        }

        $originalName = $file->getClientFilename();
        $mimeType = $file->getClientMediaType();

        //lire le tmp => régi
        // $tmpPath = $file->getStream()->getMetaData('uri');
        // $plain = @file_get_contents($tmpPath);
        // if($plain === false){
        //     return $this->json($response, ['error' => 'Impossible d\'accéder au  fichier téléversé'], 500);
        // }

        // Utiliser le stream PSR-7 pour accéder au fichier temporaire
        $stream = $file->getStream();
        $tmpPath = $stream->getMetadata('uri');

        if (!$tmpPath || !file_exists($tmpPath)) {
            return $this->json($response, ['error' => 'Impossible d\'accéder au fichier téléversé'], 500);
        }

        try {
            $this->db->pdo->beginTransaction();

            // créer l'entrée files
            $fileId = $this->files->create([
                'user_id'       => $userId,               
                'folder_id'     => $folderId,               //récuperer le bon folder_id!!! 
                'original_name' => $originalName,
                // 'stored_name'   => $storedName,          //pointe vers la version courante
                'stored_name'   => 'PENDING',               //pointe vers la version courante
                'mime'          => $mimeType,
                'size'          => $size,
                'created_at'    => date('Y-m-d H:i:s'),     // => pour mettre l'heure, minutes..
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);

            $version = 1;
            $aadContent = "file:$fileId:v$version";
            $aadKey     = "filekey:$fileId:v$version";

             // Lire le fichier téléversé par stream
            try {
                $plain = StorageWriter::readBinary($tmpPath);
            } catch (\RuntimeException $e) {
                throw new \RuntimeException('Impossible de lire le fichier téléversé: ' . $e->getMessage());
            }

            // Chiffrement (pour l'instant en une fois, FileCrypto ne supporte pas le streaming)
            $crypto = FileCrypto::encryptForStorage($plain, $this->kek, $aadContent, $aadKey);

            // Libérer la mémoire
            unset($plain);

            StorageWriter::ensureDir($this->uploadDir);

            //stocké chiffré
            $storedName = uniqid('f_', true) . '_' . '_file_' . $fileId . '.bin';
           //$storedName = uniqid('f_', true) . '_file_' . $fileId . '.bin';
            $outPath = $this->uploadDir . DIRECTORY_SEPARATOR . $storedName;

            //Écriture avec stream 
            StorageWriter::writeBinary($outPath, $crypto['ciphertext']);

           // Libérer la mémoire
            unset($crypto['ciphertext']);
           
            // créer la version 1
            $versionId = $this->files->createFileVersion([
                'file_id'       => $fileId,
                'version'       => 1,
                'stored_name'   => $storedName,
                'iv'            => $crypto['iv'],
                'auth_tag'      => $crypto['tag'],
                'key_envelope'  => $crypto['key_envelope'],
                'checksum'      => $crypto['checksum'],
                'size'          => $size,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);

            $this->files->updateFileMeta($fileId, [
                'stored_name' => $storedName,
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);

            $this->db->pdo->commit();

            //recalculer le total depuis le BDD
            $newTotalSize = $this->files->totalSizeByUser($userId);
            $this->users->updateUserQuotaUsed($userId, $newTotalSize);

             $response->getBody()->write(json_encode([
                'message'       => 'Fichier uploadé avec succès (crypté)',
                'id'            => $fileId,
                'version_id'    => $versionId,
                'version'       => 1,
                'filename'      => $originalName,
                'stored_name'   => $storedName,
                'size'          => $size
            ], JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
            
        }catch (\Throwable $e){
            if($this->db->pdo->inTransaction()){
                $this->db->pdo->rollBack();
            }

            //cleanup fichier disque si Database fail
            if(isset($outPath) && file_exists($outPath)){
                @unlink($outPath);
            }

            return $this->json($response, [
                'error' => 'Erreur lors de l\'upload',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


     // POST /files/{id}/versions*************************************************************************************************** OK
    public function uploadNewVersion(Request $request, Response $response, array $args): Response
    {
        
        $fileId = (int)($args['id'] ?? 0);
        if($fileId <= 0){
            return $this->json($response, ['error' => 'Fileid invalide'], 400);
        }

        //vérif authentification
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];

        } catch (\Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }
        
        //vérif fichier appartient owner
        $file = $this->files->find($fileId);
        if(!$file){
            return $this->json($response, ['error' => 'Fichier introuvable'], 404);
        }

         if((int)$file['user_id'] !== $userId){
            return $this->json($response, ['error' => 'Accès interdit'], 403);
        }

        //récupération le fichier uploadé
        $uploadedFiles = $request->getUploadedFiles();

        if(!isset($uploadedFiles['file'])){
            return $this->json($response, ['error' => "Aucun fichier portant la clé «file» n'a été trouvé."], 400);
        }

        $newFile = $uploadedFiles['file'];
        if($newFile->getError() !== UPLOAD_ERR_OK){
            return $this->json($response, ['error' => 'Erreur lors de l\'upload', 'code' => $newFile->getError()], 400);
        }

        //vérif que l'extension correspond
        $currentFileName = $file['original_name'];
        $currentExtension = strtolower(pathinfo($currentFileName, PATHINFO_EXTENSION));

        $uploadFileName = $newFile->getClientFileName();
        $uploadedExtension = strtolower(pathinfo($uploadFileName, PATHINFO_EXTENSION));

        if($currentExtension !== $uploadedExtension){
            return $this->json($response, [
                'error' => 'Le type de fichier ne correspond pas',
                'expected' => $currentExtension,
                'received' => $uploadedExtension
             ], 400);
        }

        //vérif le type mime
        $uploadedMimeType = $newFile->getClientMediaType();
        $currentMimeType = $this->files->getMimeType($fileId);

        if(!$this->isMimeTypeCompatible($currentMimeType, $uploadedMimeType)){
            return $this->json($response, ['error' => 'Le type MIME ne correspond pas',
             'expected' => $currentMimeType,
             'received' => $uploadedMimeType
             ], 400);
        }

        try {
            $this->validateUploadedFile($newFile);
        }catch (\RuntimeException $e){
            return $this->json($response, ['error' => $e->getMessage()], 400);
        }

        //quota
        $size = (int)$newFile->getsize();
        $totalSize = $this->files->totalSizeByUser($userId);
        $quota = $this->files->userQuotaTotal($userId);

        if($quota > 0 && ($totalSize + $size) > $quota){
            return $this->json($response, ['error' => 'Quota exceeded'], 413);
        }

        //charger le contenu et chiffrer => pour l'instant max. 10Mo
        // ancien code
        // $temporairePath = $newFile->getStream()->getMetaData('uri');
        // $plain = @file_get_contents($temporairePath);
        // if($plain === false){
        //     return $this->json($response, ['error' => 'Impossible de lire le fichier téléversé'], 500);
        // }

        // Utiliser le stream PSR-7
        $stream = $newFile->getStream();
        $tmpPath = $stream->getMetadata('uri');

        if (!$tmpPath || !file_exists($tmpPath)) {
            return $this->json($response, ['error' => 'Impossible d\'accéder au fichier téléversé'], 500);
        }

        try {
            $this->db->pdo->beginTransaction();
            
            //calculer la prochaine version AVANT chiffrement => pour AAD correct
            $maxVersion = $this->files->getMaxVersionForFile($fileId);
            $nextVersion = $maxVersion + 1;

            //Construire AAD avec la version exact
            $aadContent = "file:$fileId:v$nextVersion";
            $aadKey = "filekey:$fileId:v$nextVersion";

            // Lire le fichier téléversé par stream
            try {
                $plain = StorageWriter::readBinary($tmpPath);
            } catch (\RuntimeException $e) {
                throw new \RuntimeException('Impossible de lire le fichier téléversé: ' . $e->getMessage());
            }       

            //chiffrer
            $crypto = FileCrypto::encryptForStorage($plain, $this->kek, $aadContent, $aadKey);

            // Libérer la mémoire
            unset($plain);

            //stockage disque
            StorageWriter::ensureDir($this->uploadDir);
          
            //nom stocké
            //$storedName = uniqid('fv_', true) . '_' . '_file_' . $fileId . '.bin';
            $storedName = uniqid('fv_', true) . '_file_' . $fileId . '.bin';
            $outPath = $this->uploadDir . DIRECTORY_SEPARATOR . $storedName;

            StorageWriter::writeBinary($outPath, $crypto['ciphertext']);

            // Libérer la mémoire
            unset($crypto['ciphertext']);

            $checksum = $crypto['checksum'];
            $totalSizeUsed = $this->files->totalSizeByUser($userId);

            //insérer file_versions
            $versionId = $this->files->createFileVersion([
                'file_id'       => $fileId,
                'version'       => $nextVersion,
                'stored_name'   => $storedName,
                'iv'            => $crypto['iv'],
                'auth_tag'      => $crypto['tag'],
                'key_envelope'  => $crypto['key_envelope'],
                'checksum'      => $checksum,
                'size'          => $size,
                'created_at'    => date('Y-m-d H:i:s')
            ]);

            //update meta fichier vers dernière version
            $newOriginalName = $newFile->getClientFilename();
            $newMime = $newFile->getClientMediaType();
            
            //faire pointer files vers la dernière version
            $this->files->updateFileMeta($fileId, [
                'stored_name'   => $storedName,
                'size'          => $size,
                'mime'          => $newMime,
                'original_name' => $newOriginalName,
                'updated_at'    => date('Y-m-d H:i:s')
            ]);

            $this->db->pdo->commit();

            //recalculer le total depuis le BDD
            $newTotalSize = $this->files->totalSizeByUser($userId);
            $this->users->updateUserQuotaUsed($userId, $newTotalSize);

            $response->getBody()->write(json_encode([
                'message'     => 'Version créée avec succès',
                'file_id'     => $fileId,
                'version_id'  => $versionId,
                'version'     => $nextVersion,
                'stored_name' => $storedName,
                'size'        => $size
            ], JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);

        }catch(\Throwable $e){

            if ($this->db->pdo->inTransaction()) {
                $this->db->pdo->rollBack();  //=> annuler tout ce qui a été fait dans la base depuis beginTransaction()
            }

            // si DB fail, supprimer le fichier écrit => éviter les orphelins sur disque (aucun réf dans BD)!!
            if ($outPath && file_exists($outPath)) {

                //@=> ne pas afficher de warning PHP si la suppression échoue => possible enlever 
                @unlink($outPath);  //=> supprimer le fichier
            }

            return $this->json($response, [
                'error'         => 'Erreur lors de la création de la version',
                'details'       => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifie si deux types Mime sont compatibles =>ok
     */
    private function isMimeTypeCompatible(String $expected, String $received): bool
    {
        //normaliser les types MIME
        $expected = strtolower(trim($expected));
        $received = strtolower(trim($received));

        // Types MIME équivalents
        $equivalents = [
            'image/jpeg'         => ['image/jpeg', 'image/jpg'],
            'image/jpg'          => ['image/jpeg', 'image/jpg'],
            'application/pdf'    => ['application/pdf'],
            'application/msword' => ['application/msword'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ],
        ];

        //vérif si les type sont équivalents
        foreach($equivalents as $base => $aliases){
            if(in_array($expected, $aliases) && in_array($received, $aliases)){
                return true;
            }
        }

        //sinon => vérifie l'égalité stricte
        return $expected === $received;
    }


    /**
     * Valide un fichier uploadé (taille, extension, MIME type) =>ok
     * 
     * @throws \RuntimeException Si le fichier n'est pas valide
     */
    private function validateUploadedFile(UploadedFileInterface $file): void
    {
        $size = (int)$file->getSize();
        $mimeType = $file->getClientMediaType();
        $filename = $file->getClientFilename();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        //vérif la taille
        if ($size > self::MAX_FILE_SIZE) {
            throw new \RuntimeException('Taille trop grande (max. ' . (self::MAX_FILE_SIZE / 1024 / 1024) . ' Mo)');
        }

        //vérif extension
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            throw new \RuntimeException("Extension '$extension' non autorisée");
        }

        //vérif le type MIME
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new \RuntimeException("Type MIME '$mimeType' non autorisé");
        }
    }


    // GET /files/{id}/download  => //téléchargement direct (propriètaire)(version courante) ***********************************************************************  OK
    public function download(Request $request, Response $response, array $args): Response
    {
        $fileId = (int)($args['id'] ?? 0);
        if ($fileId <= 0) {
            return $this->json($response, ['error' => 'FileId invalides'], 400);
        }

        // Récupérer IP et User-Agent pour les logs
        $ip = RequestHelper::getClientIp($request);
        $userAgent = RequestHelper::getUserAgent($request);
        $file = $this->files->find($fileId);
        $shareId = null; // =>  un déchiffrement direct

        if (!$file) {
              $this->downloadLog->log($shareId, null, $ip, $userAgent, false, 'Fichier introuvable');
            return $this->json($response, ['error' => 'Fichier introuvable'], 404);
        }

        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        if(!$this->files->isOwnedByUser($fileId, $userId)){
            //log échec => accès refusé
            if(isset($this->downloadLog)){
                $this->downloadLog->log($shareId, null, $ip, $userAgent, false, 'Accès refusé (403): utilisateur non propriétaire');
            }
            return $this->json($response, ['error' => "Accès refusé"], 403);
        }

        //fichier chiffré => il y a une version courante dans file_versions
        $versionRow = $this->files->getCurrentVersionRow($fileId);

        if(is_array($versionRow) && !empty($versionRow)){

            $versionId = (int)($versionRow['id'] ?? 0);

            //chemin chiffré
            $storedName = (string)($versionRow['stored_name'] ?? '');
            if ($storedName === '') {
                return $this->json($response, ['error' => 'stored_name manquant (file_versions)'], 500);
            }

            //vérif que le fichier chiffré existe sur le disque
            $path = $this->uploadDir . DIRECTORY_SEPARATOR . $storedName;
            if (!file_exists($path)) {
                return $this->json($response, ['error' => 'Fichier manquant sur le serveur'], 500);
            }

            //ancien codes
            // $ciphertext = file_get_contents($path);
            // if ($ciphertext === false) {
            //     return $this->json($response, ['error' => 'Impossible de lire le fichier chiffre'], 500);
            // }

            // Lecture par stream pour éviter de charger tout en mémoire
            //lire le fichier chiffré
            try {
                $ciphertext = StorageWriter::readBinary($path);
            } catch(\RuntimeException $e){
                 return $this->json($response, ['error' => 'Impossible de lire le fichier'], 500);
            }
           
            //déchiffrer le fichier
            try {
                $kek = FileCrypto::normalizeKek($_ENV['KEY_ENCRYPTION_KEY'] ?? getenv('KEY_ENCRYPTION_KEY') ?? '');
                $decrypte = FileCrypto::decryptFromStorage($ciphertext, $versionRow, $kek, $fileId);
                $plaintext = $decrypte['plaintext'];

                // Libérer la mémoire
                unset($ciphertext);

            } catch (\Throwable $e) {
                error_log('Decrypt failed (FileController::download): ' . $e->getMessage());

                //log de l'échec du déchiffrement
                if(isset($this->downloadLog)){
                    $this->downloadLog->log($shareId, $versionId, $ip, $userAgent, false, 'Échec déchiffrement: ' . $e->getMessage());
                }
                return $this->json($response, ['error' => $e->getMessage()], 500);
            }

            // préparer les headers de téléchargement
            $filename = (string)($file['original_name'] ?? 'download');
            $mime = (string)($file['mime'] ?? 'application/octet-stream');

            //log de succès
            if(isset($this->downloadLog)){
                $this->downloadLog->log($shareId, $versionId, $ip, $userAgent, true, 'Download direct réussi');
            }

            // renvoyer le PLAINTEXT (contenu déchiffré) et pas le fichier chiffré!!!!
            $response->getBody()->write($plaintext);

            return $response
                ->withHeader('Content-Type', $mime)
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withHeader('Content-Length', (string)strlen($plaintext))
                ->withStatus(200);
        }

        //ancien version "en claire"
        $storedName = (string)($file['stored_name'] ?? '');
        if ($storedName === '') {
            return $this->json($response, ['error' => 'stored_name manquant (files)'], 500);
        }

        //vérif que le fichier chiffré existe sur le disque
        $path = $this->uploadDir . DIRECTORY_SEPARATOR . $storedName;

        if (!file_exists($path)) {
            return $this->json($response, ['error' => 'Fichier manquant sur le serveur'], 500);
        }

        //ouvrir le fichier en lecture binaire
        $stream = fopen($path, 'rb');
        if ($stream === false) {

            //log de l'échec du déchiffrement
            if(isset($this->downloadLog)){
                $this->downloadLog->log($shareId, null, $ip, $userAgent, false, "Impossible d'ouvrir le fichier en clair");
            }
            return $this->json($response, ['error' => "Impossible d'ouvrir le fichier"], 500);
        }

        $body = $response->getBody();
        while (!feof($stream)) {
            $chunk = fread($stream, 8192);
            if ($chunk === false) break;
            $body->write($chunk);  //=> lecture par chunks de 8192 => environ 8Ko
        }
        // $response->getBody()->write(stream_get_contents($stream));
        fclose($stream);

        // Log le succès
        if (isset($this->downloadLog)) {
            $this->downloadLog->log($shareId, null, $ip, $userAgent, true, 'Download direct réussi (fichier en clair)');
        }

        return $response
            ->withHeader('Content-Type', (string)$file['mime'])
            ->withHeader('Content-Disposition', 'attachment; filename="' . (string)$file['original_name'] . '"')
            ->withHeader('Content-Length', (string)filesize($path))  //=> sans ça certain fichier ne se téléchargent pas correctement
            ->withStatus(200);
    }


    // GET /files/{id}/versions/{version}/download  => //téléchargement version (propriètaire) **************************************ok
    public function downloadVersion(Request $request, Response $response, array $args): Response
    {
        $fileId = (int)($args['id'] ?? 0);
        $version = (int)($args['version'] ?? 0);
        $shareId = null; //=>  un déchiffrement direct

        if($fileId <= 0){
            return $this->json($response, ['error' => 'id fichier invalide'], 400);
        }

        if($version <= 0){
            return $this->json($response, ['error' => 'id version invalide'], 400);
        }

        // Récupérer IP et User-Agent pour les logs
        $ip = RequestHelper::getClientIp($request);
        $userAgent = RequestHelper::getUserAgent($request);

        //vérif authentification
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        if(!$this->files->isOwnedByUser($fileId, $userId)){

            //log échec => accès refusé
            if(isset($this->downloadLog)){
                $this->downloadLog->log($shareId, null, $ip, $userAgent, false, 'Accès refusé (403): utilisateur non propriétaire');
            }
            return $this->json($response, ['error' => "Accès refusé"], 403);
        }

        //récup fichier
        $file = $this->files->find($fileId);
        if(!$file){
            $this->downloadLog->log($shareId, null, $ip, $userAgent, false, 'Fichier introuvable');
            return $this->json($response, ['error' => "Fichier introuvable"], 404);
        }

        //récup version demandé
        $versionRow = $this->files->getVersionRow($fileId, $version);
        $versionId = (int)($versionRow['id'] ?? 0);
        if(!$versionRow){
            $this->downloadLog->log($shareId, $versionId, $ip, $userAgent, false, 'Fichier introuvable');
            return $this->json($response, ['error' => "Version demandee introuvable"], 404);
        }

        $storedName = (string)($versionRow['stored_name'] ?? '');
        if($storedName === ''){
            return $this->json($response, ['error' => "stored_name manquant"], 500);
        }

        //vérif que le fichier chiffré existe sur le disque
        $path = $this->uploadDir . DIRECTORY_SEPARATOR . $storedName;
        if(!file_exists($path)){
            return $this->json($response, ['error' => 'Fichier manquant sur le serveur'], 500);
        }

        //Lire le fichier chiffré
        //ancien code
        // $ciphertext = file_get_contents($path);
        // if ($ciphertext === false) {
        //     return $this->json($response, ['error' => "Impossible de lire le fichier chiffre"], 500);
        // }

        // Lire le fichier chiffré par stream pour éviter de charger tout en mémoire
        try {
            $ciphertext = StorageWriter::readBinary($path);
        } catch(\RuntimeException $e){
            return $this->json($response, ['error' => 'Impossible de lire le fichier'], 500);
        }

        //déchiffrer le fichier
        try{
            $kek = FileCrypto::normalizeKek($_ENV['KEY_ENCRYPTION_KEY'] ?? getenv('KEY_ENCRYPTION_KEY') ?? '');
            $decrypte = FileCrypto::decryptFromStorage($ciphertext, $versionRow, $kek, $fileId);
            $plaintext = $decrypte['plaintext'];

            // Libérer mémoire
            unset($ciphertext);

        }catch (\Throwable $e){
            $message = $e->getMessage();
            error_log('Decrypt failed (downloadVersion): ' . $message);

            //log de l'échec du déchiffrement
            if(isset($this->downloadLog)){
                $this->downloadLog->log($shareId, $versionId, $ip, $userAgent, false, 'Échec déchiffrement: ' . $e->getMessage());
            }
            return $this->json($response, ['error' => $message], 500);
        }

        //préparer les headers de téléchargement
        $filename = (string)($file['original_name'] ?? 'download');
        $mime = (string)($file['mime'] ?? 'application/octet-stream');

        //Encoder le nom de fichier pour éviter les problèmes avec caractères spéciaux???
        //$safeFilename = rawurlencode($filename);

        // renvoyer le PLAINTEXT  et pas le fichier chiffré!!!!
        $response->getBody()->write($plaintext);

        // Log le succès
        if (isset($this->downloadLog)) {
            $this->downloadLog->log($shareId, $versionId, $ip, $userAgent, true, "Download version $version réussi (200)");
        }

        return $response
            ->withHeader('Content-Type', $mime)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string)strlen($plaintext))
            ->withStatus(200);
    }


 /***************************************** Functions PRIVATE ***************************************************************/

    private function json(Response $response, array $data, int $status): Response{
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

   

  /************************************************************************************************************************/     


    /**
     * DELETE /files/{id} ********************************************************************************************** OK
     * Supprime un fichier et TOUTES ses versions
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        //vérif authentification => décoder le token JWT depuis le header Authorization
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];

        } catch (\Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }
        
        $fileId = (int)$args['id'];
        if($fileId <= 0){
            return $this->json($response, ['error' => 'FileId invalide'], 400);
        }

        //vérif fichier et owner
        $file = $this->files->find($fileId);
        if(!$file){
            return $this->json($response, ['error' => 'Fichier introuvable'], 404);
        }

        if((int)$file['user_id'] !== $userId){
            return $this->json($response, ['error' => 'Accès interdit'], 403);
        }
        
        try{
            $versions = $this->files->getAllVersions($fileId);

            //supprimer tous les versions sur le disque
            foreach ($versions as $version) {
                $storedName = $version['stored_name'];
                $path = $this->uploadDir . DIRECTORY_SEPARATOR . $storedName;
                if (file_exists($path)) {
                    @unlink($path); //=>  @ pour éviter les warnings si le fichier n'existe plus
                }
            }
            
            //transaction SQL
            $this->db->pdo->beginTransaction();

             // Supprimer les versions en BDD
            $this->files->deleteAllVersions($fileId);

            // Supprimer le fichier en BDD
            $this->files->delete($fileId);

            $this->db->pdo->commit();

            //mise à jour le quota
            $newTotalSize = $this->files->totalSizeByUser($userId);
            $this->users->updateUserQuotaUsed($userId, $newTotalSize);

            return $this->json($response, [
                'message'           => 'Fichier supprimé avec succès',
                'file_id'           => $fileId,
                'versions_deleted'  => count($versions)
            ], 200);
        }catch(\Exception $e){
            return $this->json($response, [
                'error'     => 'Erreur lors de la suppression du fichier',
                'details'   => $e->getMessage()
            ], 500);
        } 
    }

    /**
     * DELETE /files/{file_id}/versions/{id} *********************************************************************************************************************** OK
     * Supprime une version d'un fichier
     */
     public function deleteVersion(Request $request, Response $response, array $args): Response
    {
        
        //vérif authentification
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];

        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }
    
        $fileId = (int)($args['file_id'] ?? 0);
        $versionId = (int)($args['id'] ?? 0);

        if($fileId <= 0){
            return $this->json($response, ['error' => 'id fichier invalide'], 400);
        }

        if($versionId <= 0){
            return $this->json($response, ['error' => 'id version invalide'], 400);
        }

        //vérif fichier et owner
        $file = $this->files->find($fileId);
        if(!$file){
            return $this->json($response, ['error' => 'Fichier introuvable'], 404);
        }

        if((int)$file['user_id'] !== $userId){
            return $this->json($response, ['error' => 'Accès interdit'], 403);
        }

        //vérifier que la version existe et appartienne au fichier
        $version = $this->files->findVersion($versionId);
        if(!$version){
            return $this->json($response, ['error' => 'Version introuvable'], 404);
        }

        if((int)$version['file_id'] !== $fileId){
             return $this->json($response, ['error' => 'Cette version n\'appartient pas à ce fichier'], 403);
        }

        //ne pas supprimer la version courante
        $currentVersion = (int)$file['current_version'];
        if((int)$version['version'] === $currentVersion){
            return $this->json($response, ['error' => 'Impossible de supprimer la version active du fichier'], 400);
        }

        //ne pas supprimer si c'est la seule version
        $totalVersions = $this->files->getVersionCount($fileId);
        if($totalVersions <= 1){
            return $this->json($response, ['error' => 'Impossible de supprimer la dernière version du fichier'], 409);
        }

        // Supprimer
        try {
            $path = $this->uploadDir . DIRECTORY_SEPARATOR . $version['stored_name'];
            if (file_exists($path)) {
                unlink($path);
            }

            $this->files->deleteVersion($versionId);

            //mise à jour le quota
            $newTotalSize = $this->files->totalSizeByUser($userId);
            $this->users->updateUserQuotaUsed($userId, $newTotalSize);

            return $this->json($response, [
                'message'           => 'Version supprimée avec succès',
                'file_id'           => $fileId,
                'version_id'        => $versionId,
                'version_number'    => (int)$version['version']
            ], 200);

        } catch (\Exception $e) {
            return $this->json($response, [
                'error'     => 'Erreur lors de la suppression de la version',
                'details'   => $e->getMessage()
            ], 500);
        }
    }


    //PUT /files/{id} => renommer un fichier ********************************************************************************* OK
    public function renameFile(Request $request, Response $response, array $args): Response
    {
        // récuperer id via JWT
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];

        } catch (\Exception $e) {
            $code = (int)($e->getCode() ?: 401);
            return $this->json($response, ['error' => $e->getMessage()], $code);
        }

        $id = (int)($args['id'] ?? 0);
        if($id <= 0){
            return $this->json($response, ['error' => 'id invalide'], 400);
        }

        $file = $this->files->find($id);
        if(!$file){
            return $this->json($response, ['error' => 'Fichier introuvable'], 404);
        }

        //est-ce que le user est le propriètaire
        if((int)$file['user_id'] !== $userId){
            return $this->json($response, ['error' => 'Acces interdit'], 403);
        }

        $body = $request->getParsedBody();
        if(!is_array($body)) $body = [];

        $newName = isset($body['name']) ? trim((string)$body['name']) : '';
        if($newName === ''){
            return $this->json($response, ['error' => 'Nom obligatoire'], 400);
        }

        // interdit quelques caractères => \,/,:,*,?,",<,>,|
        if (preg_match('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]/', $newName)) {
            return $this->json($response, ['error' => 'Nom invalide (caracteres interdits)'], 400);
        }

        //empêcher le doublon dans le même dossier
        $folderId = (int)$file['folder_id'];
        if($this->files->fileNameExistForUser($userId, $folderId, $newName, $id)){
            return $this->json($response, ['error' => 'Un fichier avec ce nom existe déjà ici'], 409);
        }

        $ok = $this->files->renameFile($id, $newName);
        if(!$ok){

            return $this->json($response, [
                'error'         => 'Aucun changement',
                'id'            => $id,
                'original_name' => $newName
            ], 200);
        }

        return $this->json($response, [
            'message'       => 'Fichier renomme',
            'id'            => $id,
            'original_name' => $newName
        ], 200);
    }


   


    // GET /stats
    public function stats(Request $request, Response $response): Response
    {
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];

        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        $totalSize = $this->files->totalSizeByUser($userId);
        $quota = $this->files->userQuotaTotal($userId); //ancien quotaBytes

        $count = $this->files->countFilesByUser($userId);

        $data = [
            'user_id'          => $userId,
            'total_size_bytes' => $totalSize,
            'quota_bytes'      => $quota,
            'file_count'       => $count,
        ];

        return $this->json($response, $data, 200);
    }


    // PUT /quota - Met à jour le quota d'un utilisateur (PAR USER) =>actuellement je ne l'utilise pas
    public function setQuota(Request $request, Response $response): Response
    {
        try {
            $admin = $this->auth->getAuthenticatedUserFromToken($request);
            
            if(!$admin['is_admin']){
                throw new \Exception("Accès interdit", 403);
            }

        } catch (\Exception $e) {
            $code = $e->getCode() ?:401;
            return $this->json($response, ['error' => $e->getMessage()], $code);
        }

        $body = $request->getParsedBody();

        // Validation du champ quota_total
        if (!isset($body['quota_total'])) {
            $error = ['error' => 'Le champ "quota_total" est obligatoire'];
            return $this->json($response, $error, 400);
        }

        // Validation que c'est un nombre positif
        $bytes = (int)$body['quota_total'];
        if ($bytes <= 0) {
            $error = ['error' => 'Le quota doit être un nombre positif'];
            return $this->json($response, $error, 400);
        }

        // ID de l'utilisateur => à remplacer par l'utilisateur connecté
        $userId = (int)$body['user_id'];

        // Vérifier que l'utilisateur existe
        $user = $this->files->getUser($userId);
        if (!$user) {
            $error = ['error' => 'Utilisateur non trouvé'];
            return $this->json($response, $error, 404);
        }

        // Mettre à jour le quota
        $this->files->updateUserQuota($userId, $bytes);

        // Récupérer les nouvelles données
        $updatedUser = $this->files->getUser($userId);

        $data = [
            'message'            => 'Quota mis à jour avec succès',
            'user_id'            => $userId,
            'quota_total'        => $updatedUser['quota_total'],
            'quota_used'         => $updatedUser['quota_used'],
            'quota_available'    => $updatedUser['quota_total'] - $updatedUser['quota_used']
        ];

        return $this->json($response, $data, 200);
    }


    // GET /me/quota — utilisé / total / % 
    public function meQuota(Request $request, Response $response): Response
    {
        // récuperer id via JWT
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];

        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

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
            'user_id'       => $userId,
            'used_bytes'    => $usedBytes,
            'total_bytes'   => $totalBytes,
            'percent_used'  => $percent
        ];

        return $this->json($response, $data, 200);
    }


   // GET /me/activity — derniers événements (uploads + downloads) ?????
    public function meActivity(Request $request, Response $response): Response
    {
        // récuperer id via JWT
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

         // paramètre de pagination
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? min(100, max(1, (int)$queryParams['limit'])) : 20; // garantir: 1 < limit < 20
        $offset = isset($queryParams['offset']) ? max(0, (int)$queryParams['offset']) : 0;


        $uploads = $this->files->recentUploads($userId, $limit);
        $downloads = $this->files->recentDownloads($userId, $limit, $offset);

        // Normaliser les events dans un même format
        $events = [];

        foreach($uploads as $upload){
            $events[] = [
                'type'      => 'upload',
                'id'        => (int)$upload['id'],
                'file_id'   => (int)$upload['id'],
                'file_name' => $upload['original_name'],
                'size'      => (int)$upload['size'],
                'at'        => $upload['created_at'],
            ];
        }

        foreach($downloads as $download){
            $events[] = [
                'type'          => 'download',
                'id'            => (int)$download['log_id'],
                'share_id'      => (int)$download['share_id'],
                'version_id'    => (int)$download['version_id'],
                'file_name'     => $download['original_name'] ?? null,
                'at'            => $download['downloaded_at'],
                'ip'            => $download['ip'],
                'user_agent'    => $download['user_agent'],
                'success'       => (bool)$download['success'],
                'message'       => $download['message'] ?? null
            ];
        }

        // Trier par date desc avec "usort"
        usort($events, function ($a, $b) {

            // strtotime => converti str en timestamp
            // b avant a => tri décroissant <=> (a avant b => tri croissant)
            return strtotime($b['at']) <=> strtotime($a['at']);
        });

        // Limiter après merge => $events il y a trop éléments 
        $events = array_slice($events, 0, $limit); //=> il renvoie de 0 à p.ex 20 éléménts..

         return $this->json($response, [
            'user_id'   => $userId,
            'count'     => count($events),
            'events'    => $events
        ], 200);
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


    //=========================================================================================================
    //                                      FOLDERS
    //=========================================================================================================

    // GET /folders — retourne uniquement les dossiers appartenant à l'utilisateur connecté ********************************** OK
    public function listFolders(Request $request, Response $response): Response
    {
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        // Récupérer uniquement les dossiers de ce user
        $data = $this->files->listFoldersByUser($userId);

        $payload = json_encode($data, JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
    
    // POST /folders - Crée un nouveau dossier **************************************************************************** OK
    public function createFolder(Request $request, Response $response): Response
    {
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        $body = $request->getParsedBody();
        
        // Validation
        if (!isset($body['user_id']) || !isset($body['name'])) {
            return $this->json($response, ['error' => 'user_id and name are required'], 400);
        }

        // Si parent_id n'est pas fourni ou est 0 => à mettre NULL pour un dossier racine
        $parentId = null;
        if (isset($body['parent_id']) && $body['parent_id'] > 0) {
            $parentId = (int)$body['parent_id'];
        }
        
        $folderData = [
            'user_id'       => (int)$body['user_id'],
            'parent_id'     => $parentId,
            'name'          => $body['name'],
            'created_at'    => date('Y-m-d H:i:s')
        ];
        
        $folderId = $this->files->createFolder($folderData);
        
        return $this->json($response, [
            'message'       => 'Dossier créé',
            'id'            => $folderId,
            'name'          => $body['name'],
            'parent_id'     => $parentId
        ], 201);
    }


    // DELETE /folders/{id}  => à mettre dedans  le vérif propriétaire *********************************************************** OK
    public function deleteFolder(Request $request, Response $response, array $args): Response
    {
        $folderId = (int)($args['id'] ?? 0);
        if($folderId <= 0){
            return $this->json($response, ['error' => 'ID de dossier invalide'], 400);
        }

        //authentification
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        //vérif si folder existe
        $folder = $this->files->findFolder($folderId);
        if (!$folder) {
            return $this->json($response, ['error' => 'Dossier introuvable'], 404);
        }

        //vérif si folder appartient au user
        if ((int)$folder['user_id'] !== $userId) {
            return $this->json($response, ['error' => 'Accès interdit à ce dossier'], 403);
        }

        //vérif s'il y a des files dans le dossier
        $filesInFolder = $this->files->countFilesByFolder($folderId, $userId);
        if ($filesInFolder > 0) {
            return $this->json($response, [
                'error' => 'Le dossier contient des fichiers et ne peut pas être supprimé',
                'files_count' => $filesInFolder
            ], 400);
        }

        // vérif s'il y a des sous-dossiers
        $subfolders = $this->files->countSubfolders($folderId);
        if ($subfolders > 0) {
            return $this->json($response, [
                'error' => 'Le dossier contient des sous-dossiers et ne peut pas être supprimé',
                'subfolders_count' => $subfolders
            ], 400);
        }

        try{
            // Supprimer en base de données
             $this->files->deleteFolder($folderId);

            // suppression réussi => statut: 204
            return $this->json($response, ['message' => 'Folder supprimé avec succès'], 204);
        }catch(\Exception $e){
            return $this->json($response, [
                'error' => 'Erreur lors de la suppression du dossier',
                'details' => $e->getMessage()
            ], 500);
        }
    }


    //PUT /folders/{id} => renommer un dossier ********************************************************************************* OK
    public function renameFolder(Request $request, Response $response, array $args): Response
    {
        // récuperer id via JWT
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];
        } catch (\Exception $e) {
            $code = (int)($e->getCode() ?: 401);
            return $this->json($response, ['error' => $e->getMessage()], $code);
        }

        $folderId = (int)($args['id'] ?? 0);
        if($folderId <= 0){
            return $this->json($response, ['error' => 'id invalide'], 400);
        }

        $folder = $this->files->findFolder($folderId);
        if(!$folder){
            return $this->json($response, ['error' => 'Dossier introuvable'], 404);
        }

        //est-ce que le user est le propriètaire
        if((int)$folder['user_id'] !== $userId){
            return $this->json($response, ['error' => 'Acces interdit'], 403);
        }

        $body = $request->getParsedBody();
        if(!is_array($body)) $body = [];

        $newName = isset($body['name']) ? trim((string)$body['name']) : '';
        if($newName === ''){
            return $this->json($response, ['error' => 'Nom obligatoire'], 400);
        }

        // interdit quelques caractères => \,/,:,*,?,",<,>,|
        if (preg_match('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]/', $newName)) {
            return $this->json($response, ['error' => 'Nom invalide (caracteres interdits)'], 400);
        }

        //empêcher le doublon dans le même parent
        $parentId = $folder['parent_id'] !== null ? (int)$folder['parent_id'] : null;
        if($this->files->folderNameExistForUser($userId, $parentId, $newName, $folderId)){
            return $this->json($response, ['error' => 'Un dossier avec ce nom existe deja ici'], 409);
        }

        $ok = $this->files->renameFolder($folderId, $newName);
        if(!$ok){
            return $this->json($response, ['error' => 'Renommage non applique...'], 404);
        }

        return $this->json($response, [
            'message'       => 'Dossier renomme',
            'id'            => $folderId,
            'name'          => $newName
        ], 200);
    }



}


?>