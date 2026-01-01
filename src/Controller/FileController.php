<?php
// src/Controller/FileController.php

namespace App\Controller;

use App\Model\FileRepository;
use App\Model\UserRepository;
use App\Security\AuthService;
use App\Security\FileCrypto;
use App\Security\StorageWriter;
use Medoo\Medoo;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;


class FileController
{
    private Medoo $db; 
    private FileRepository $files;
    private AuthService $auth;
    private string $uploadDir;
    private string $jwtSecret;
    private string $kek;


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
                    'error'     => 'Folder not found',
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

        //paramètre : N darab dernières versions
        $limit = (int)($request->getQueryParams()['latest_versions_limit'] ?? 5);
        if($limit <= 0) $limit = 5;
        if($limit > 50) $limit = 50;

        $currentVersion = $this->files->getMaxVersionForFile($id);
        $versionCount = $this->files->getVersionCount($id);
        $latest = $this->files->getLatestVersions($id, $limit);

        // checksum = BINARY(32) => bin2hex puis truncate
        $latestMapped = array_map(function ($row) {
            $checksumHex = bin2hex($row['checksum']);
            return [
                'version'       => (int)$row['version'],
                'size'          => (int)$row['size'],
                'created_at'    => $row['created_at'],
                'checksum'      => substr($checksumHex, 0, 12) . '...'
            ];
        }, $latest);

        // fusion dans la réponse
        $file['current_version'] = $currentVersion;
        $file['versions_count'] = $versionCount;
        $file['latest_versions'] = $latestMapped;

        $response->getBody()->write(json_encode($file, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    // GET /files/{id}/versions paginée
    public function listVersions(Request $request, Response $response, array $args): Response
    {        
        //vérif authentification
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];

        } catch (\Exception $e) {
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(401)
                ->withBody($response->getBody()->write(json_encode([
                    'error' => $e->getMessage()
                ])));
        }

        $fileId = (int)($args['id'] ?? 0);
        if($fileId <= 0){
            $response->getBody()->write(json_encode(['error' => 'id invalide'], JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        //vérif fichier et owner
        $file = $this->files->find($fileId);
        if(!$file){
            $response->getBody()->write(json_encode(['error' => 'Fichier introuvable'], JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        if((int)$file['user_id'] !== $userId){
            $response->getBody()->write(json_encode(['error' => 'Acces interdit'], JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $page  = (int)($request->getQueryParams()['page'] ?? 1);
        $limit = (int)($request->getQueryParams()['limit'] ?? 20);

        $result = $this->files->listVersionsPaginated($fileId, $page, $limit);

        $items = array_map(function ($row) {
            return [
                'id'            => (int)$row['id'],
                'version'       => (int)$row['version'],
                'size'          => (int)$row['size'],
                'created_at'    => $row['created_at'],
                'checksum'      =>  bin2hex($row['checksum'])
            ];
        }, $result['rows']);

        $payload = [
            'file_id'   => $fileId,
            'page'      => $result['page'],
            'limit'     => $result['limit'],
            'total'     => $result['total'],
            'items'     => $items
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));
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
                UPLOAD_ERR_INI_SIZE     => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE    => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL      => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE      => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR   => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE   => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION    => 'File upload stopped by extension',
            ];
            
            $response->getBody()->write(json_encode([
                'error'         => 'Upload error',
                'error_code'    => $file->getError(),
                'error_message' => $errorMessages[$file->getError()] ?? 'Unknown error'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Validation du fichier
        $maxSize = 10 * 1024 * 1024; // 10 Mo
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg', 'application/doc', 'application/docx', 'application/pdf'];
        
        $size = (int)$file->getSize();
        $mimeType = $file->getClientMediaType();
        
        // Vérification de la taille
        if ($size > $maxSize) {
            $response->getBody()->write(json_encode(['error' => 'Taille trop grande (max. 10 Mo)']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // Vérification du type MIME
        if (!in_array($mimeType, $allowedTypes)) {
            $response->getBody()->write(json_encode([
                'error'         => 'Type de fichier non autorisé.',
                'received_type' => $mimeType,
                'allowed_types' => $allowedTypes
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
            
        // Décoder le token JWT depuis le header Authorization
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 401;
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus($code);
        }

        //Récupérer folder_id depuis form-data ou query !!!! => remettre plus tard quand on lie avec le folder
        $parsedBody = $request->getParsedBody();
        //$folderId = 5; //=> pour le test avec postman;
        $folderId = (int)($parsedBody['folder_id'] ?? 0);
        if ($folderId <= 0 || !$this->files->folderExists($folderId)) {
            $response->getBody()->write(json_encode(['error' => 'Dossier introuvable']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }


        $totalSize = $this->files->totalSizeByUser($userId); //=> par utilisateur!!! 
        $quota = $this->files->userQuotaTotal($userId); //ancien quotaBytes

        if ($quota > 0 && ($totalSize + $size) > $quota) {
            $response->getBody()->write(json_encode(['error' => 'Quota exceeded']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(413);
        }

        $originalName = $file->getClientFilename();
        // $storedName = uniqid('f_', true) . '_' . $originalName;

        // // Créer le répertoire s'il n'existe pas
        // if (!is_dir($this->uploadDir)) {
        //     mkdir($this->uploadDir, 0777, true);
        // }

        // $file->moveTo($this->uploadDir . DIRECTORY_SEPARATOR . $storedName);

        //lire le tmp
        $tmpPath = $file->getStream()->getMetaData('uri');
        $plain = @file_get_contents($tmpPath);
        if($plain === false){
            $response->getBody()->write(json_encode(['error' => 'Impossible de lire le fichier téléchargé'], JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        try {
            $this->db->pdo->beginTransaction();

            // créer l'entrée files
            $fileId = $this->files->create([
                'user_id'       => $userId,               
                'folder_id'     => $folderId,               //récuperer le bon folder_id!!! 
                'original_name' => $originalName,
                // 'stored_name'   => $storedName,             //pointe vers la version courante
                'stored_name'   => 'PENDING',             //pointe vers la version courante
                'mime'          => $mimeType,
                'size'          => $size,
                'created_at'    => date('Y-m-d H:i:s'), // => pour mettre l'heure, minutes..
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);

            $version = 1;
            $aadContent = "file:$fileId:v$version";
            $aadKey     = "filekey:$fileId:v$version";

            $crypto = FileCrypto::encryptForStorage($plain, $this->kek, $aadContent, $aadKey);

            StorageWriter::ensureDir($this->uploadDir);

            //stocké chiffré
            $storedName = uniqid('f_', true) . '_' . '_file_$fileId.bin';
            $outPath = $this->uploadDir . DIRECTORY_SEPARATOR . $storedName;

            StorageWriter::writeBinary($outPath, $crypto['ciphertext']);

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

             $response->getBody()->write(json_encode([
                'message'       => 'File uploaded successfully (encrypted)',
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

            $response->getBody()->write(json_encode([
                'error' => 'Database error',
                'details' => $e->getMessage(),
            ], JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
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

        //ouvrir le fichier en lecture binaire
        $stream = fopen($path, 'rb');

        $body = $response->getBody();
        while (!feof($stream)) {
            $body->write(fread($stream, 8192)); //=> lecture par chunks de 8192 => environ 8Ko
        }
        // $response->getBody()->write(stream_get_contents($stream));
        fclose($stream);

        return $response
            ->withHeader('Content-Type', $file['mime'])
            ->withHeader('Content-Disposition', 'attachment; filename="' . $file['original_name'] . '"')
            ->withHeader('Content-Length', filesize($path))  //=> sans ça certain fichier ne se téléchargent pas correctement
            ->withStatus(200);
    }

     // GET /files/{id}/versions/{version}/download
     public function downloadVersion(Request $request, Response $response, array $args): Response
     {
        $fileId = (int)($args['id'] ?? 0);
        $version = (int)($args['version'] ?? 0);

        if($fileId <= 0 || $version <= 0){
            return $this->json($response, ['error' => 'Paramètres invalides'], 400);
        }

        //vérif authentification
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];

        } catch (\Exception $e) {
            return $this->json($response, [
                    'error' => $e->getMessage()
                ], 401);
        }

        if(!$this->files->isOwnedByUser($fileId, $userId)){
            return $this->json($response, ['error' => "Acces refuse"], 403);
        }

        $file = $this->files->find($fileId);
        if(!$file){
            return $this->json($response, ['error' => "Fichier introuvable"], 404);
        }

        $versionRow = $this->files->getVersionRow($fileId, $version);
        if(!$versionRow){
            return $this->json($response, ['error' => "Version demandee introuvable"], 404);
        }

        $storedName = (string)($versionRow['stored_name'] ?? '');
        if($storedName === ''){
            return $this->json($response, ['error' => "stored_name manquant"], 500);
        }

        $path = $this->uploadDir . DIRECTORY_SEPARATOR . $storedName;
        if(!file_exists($path)){
            return $this->json($response, ['error' => 'Fichier manquant sur le serveur'], 500);
        }

        //Lire le fichier chiffré
        $ciphertext = file_get_contents($path);
        if ($ciphertext === false) {
            return $this->json($response, ['error' => "Impossible de lire le fichier chiffre"], 500);
        }

        // Charger KEK (32 bytes)
        $kekRaw = $_ENV['KEY_ENCRYPTION_KEY'] ?? getenv('KEY_ENCRYPTION_KEY') ?? '';
        $kek = trim($kekRaw);
        
        if ($kek === '' || strlen($kek) < 32) {
            return $this->json($response, ['error' => 'KEK non configurée sur le serveur'], 500);
        }
        
        $kek = substr($kek, 0, 32);

        // Extraire key_envelope (envIv || envTag || wrappedKey)
        $keyEnvelope = $versionRow['key_envelope'];
        
        if (!is_string($keyEnvelope) || strlen($keyEnvelope) < 28) {
            $message = 'Key envelope invalide (trop court)';
            return $this->json($response, ['error' => $message], 500);
        }
        
        $envIv = substr($keyEnvelope, 0, 12);
        $envTag = substr($keyEnvelope, 12, 16);
        $wrappedKey = substr($keyEnvelope, 28);

        // Déchiffrer la clé du fichier
        $fileKey = openssl_decrypt(
            $wrappedKey,
            'aes-256-gcm',
            $kek,
            OPENSSL_RAW_DATA,
            $envIv,
            $envTag,
            ""
            // 16
        );

        if ($fileKey === false) {
            $message = 'Impossible de dechiffrer la cle du fichier';
            error_log('openssl_decrypt fileKey failed: ' . openssl_error_string());
            return $this->json($response, ['error' => $message], 500);
        }

        // Déchiffrer le contenu
        $iv = $versionRow['iv'];
        $tag = $versionRow['auth_tag'];

        if (!is_string($iv) || strlen($iv) < 12) {
        return $this->json($response, ['error' => 'IV invalide'], 500);
        }
        if (!is_string($tag) || strlen($tag) < 16) {
            return $this->json($response, ['error' => 'Auth tag invalide'], 500);
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $fileKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            "" // AAD si tu n’en utilises pas
        );

        if ($plaintext === false) {
            $message = 'Dechiffrement du contenu echoue';
            error_log('openssl_decrypt plaintext failed: ' . openssl_error_string());
            return $this->json($response, ['error' => $message], 500);
        }

        // Vérifier le checksum
        $computedChecksum = hash('sha256', $ciphertext, true);
        if (!hash_equals($versionRow['checksum'], $computedChecksum)) {
            error_log('AVERTISSEMENT : Checksum ne correspond pas pour file_id=' . $fileId);
        }

        $filename = (string)($file['original_name'] ?? 'download');
        $mime = (string)($file['mime'] ?? 'application/octet-stream');

        // renvoyer le PLAINTEXT  et pas le fichier chiffré!!!!
        $response->getBody()->write($plaintext);

        return $response
            ->withHeader('Content-Type', $mime)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string)strlen($plaintext))
            ->withStatus(200);

    }

    private function json(Response $response, array $data, int $status): Response{
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }



    // DELETE /files/{id}
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $file = $this->files->find($id);

        if (!$file) {
            return $this->json($response, ['error' => 'Fichier introuvable'], 404);
        }

        // Supprimer le fichier sur le disque
        $path = $this->uploadDir . DIRECTORY_SEPARATOR . $file['stored_name'];
        if (file_exists($path)) {
            unlink($path);
        }

        // Supprimer en base
        $this->files->delete($id);
        return $this->json($response, ['message' => 'File deleted'], 200);
    }


    // POST /files/{id}/versions
    public function uploadNewVersion(Request $request, Response $response, array $args): Response
    {
        //vérif authentification
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];

        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        
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
            return $this->json($response, ['error' => 'Acces interdit'], 403);
        }

        //récupération le fichier uploadé
        $uploadedFiles = $request->getUploadedFiles();

        if(!isset($uploadedFiles['file'])){
            return $this->json($response, ['error' => "Aucun fichier portant la cle «file» n'a ete trouve."], 400);
        }

        $newFile = $uploadedFiles['file'];
        if($newFile->getError() !== UPLOAD_ERR_OK){
            return $this->json($response, ['error' => 'Upload error', 'code' => $newFile->getError()], 400);
        }

        $size = (int)$newFile->getSize();

        //quota
        $totalSize = $this->files->totalSizeByUser($userId);
        $quota = $this->files->userQuotaTotal($userId);

        if($quota > 0 && ($totalSize + $size) > $quota){
            return $this->json($response, ['error' => 'Quota exceeded'], 413);
        }

        //charger le contenu et chiffrer => pour l'instant max. 10Mo
        $temporairePath = $newFile->getStream()->getMetaData('uri');
        $plain = @file_get_contents($temporairePath);

        if($plain === false){
            return $this->json($response, ['error' => 'Impossible de lire le fichier téléchargé'], 500);
        }

        // $fileKey = random_bytes(32);    //=> AES-256
        // $iv = random_bytes(12);         //=> Initialization Vector => GCM recommandé 12 bytes
        // $tag = '';

        // $ciphertext = openssl_encrypt(
        //     $plain, //=> contenu original non chiffré
        //     'aes-256-gcm', //=> chiffrement symétrique - taille clé = 256 bits (= 32 octets) - mode fait chiffrement + garantie l'intégrité
        //     $fileKey, //=> clé secrete -> chiffrer et déchiffrer
        //     OPENSSL_RAW_DATA, //=> retourne le résultat en binaire brut et pas en base64
        //     $iv, //=> nonce/IV ->unique pour chaque chiffrement avec la même clé
        //     $tag, //=> remplie par OpenSSL avec la tag d'authentification (16 octets)
        //     "",   //=> AAD -> Additional Authenticated Data = données non chiffrées mais protégées par le tag. (ex: fileId/version)
        //     16 //=>la longueur du tag à produire : 16 octets (128 bits) => valeur standard
        // );

        // if($ciphertext === false || strlen($tag) !== 16){
        //     $response->getBody()->write(json_encode(['error' => 'Encryptage failed'], JSON_PRETTY_PRINT));
        //     return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        // }

       

        // $kek = $this->kek;

        // if ($kek === '' || strlen($kek) < 32) {
        //     error_log("CRITICAL: KEK non disponible au moment de l'upload");

        //     $response->getBody()->write(json_encode([
        //         'error' => 'Server KEK missing/misconfigured'
        //     ], JSON_PRETTY_PRINT));
        //     return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        // }

        // $kek = substr($kek, 0, 32);

        // $envIv = random_bytes(12);
        // $envTag = '';
        // $wrappedKey = openssl_encrypt(
        //     $fileKey,
        //     'aes-256-gcm',
        //     $kek,
        //     OPENSSL_RAW_DATA,
        //     $envIv,
        //     $envTag,
        //     "",
        //     16
        // );

        // if($wrappedKey === false || strlen($envTag) !== 16){
        //     $response->getBody()->write(json_encode(['error' => 'Key envelope failed'], JSON_PRETTY_PRINT));
        //     return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        // }

        // // key_envelope = envIv || envTag || wrappedKey
        // $keyEnvelope = $envIv . $envTag . $wrappedKey;

        //stockage disque
        // if(!is_dir($this->uploadDir)){
        //     mkdir($this->uploadDir, 0777, true);
        // }

        try {
            $aadContent = "file:$fileId";
            $aadKey = "filekey:$fileId";

            $crypto = FileCrypto::encryptForStorage($plain, $this->kek, $aadContent, $aadKey);

            //stockage disque
            StorageWriter::ensureDir($this->uploadDir);

            //nom stocké
            $storedName = uniqid('fv_', true) . '_file_' . $fileId .'.bin';
            $outPath = $this->uploadDir . DIRECTORY_SEPARATOR . $storedName;

            StorageWriter::writeBinary($outPath, $crypto['ciphertext']);

            $checksum = $crypto['checksum'];

        }catch(\Throwable $e){
            $response->getBody()->write(json_encode([
                'error' => 'Encryption/storage failed',
                'details' => $e->getMessage(),
            ], JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }


        // $bytesWritten = @file_put_contents($outPath, $ciphertext);
        // if($bytesWritten === false){
        //     $response->getBody()->write(json_encode(['error' => 'Cannot write encrypted file'], JSON_PRETTY_PRINT));
        //     return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        // }

        // $checksum = hash('sha256', $ciphertext, true); //=> BINARY(32)

        //nextversion + insert => transaction pour éviter les collisions
        try{
            $this->db->pdo->beginTransaction();

            //lire maxversion dans la transaction
            $maxVersion = $this->files->getMaxVersionForFile($fileId);
            $nextVersion = $maxVersion + 1;

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

            $response->getBody()->write(json_encode([
                'message'     => 'New version created',
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
            if (file_exists($outPath)) {

                //@=> ne pas afficher de warning PHP si la suppression échoue => possible enlever 
                @unlink($outPath);  //=> supprimer le fichier
            }

            $response->getBody()->write(json_encode([
                'error'         => 'Database error',
                'details'       => $e->getMessage()
            ], JSON_PRETTY_PRINT));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

    }

    // GET /stats
    public function stats(Request $request, Response $response): Response
    {

        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];

        } catch (\Exception $e) {
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(401)
                ->withBody($response->getBody()->write(json_encode([
                    'error' => $e->getMessage()
                ])));
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

        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }


// PUT /quota - Met à jour le quota d'un utilisateur
    public function setQuota(Request $request, Response $response): Response
    {

        try {
            $admin = $this->auth->getAuthenticatedUserFromToken($request);
            
            if(!$admin['is_admin']){
                throw new \Exception("Accès interdit", 403);
            }

        } catch (\Exception $e) {
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($e->getCode() ?:401)
                ->withBody($response->getBody()->write(json_encode([
                    'error' => $e->getMessage()
                ])));
        }

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
        $userId = (int)$body['user_id'];

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
            'message'            => 'Quota mis à jour avec succès',
            'user_id'            => $userId,
            'quota_total'        => $updatedUser['quota_total'],
            'quota_used'         => $updatedUser['quota_used'],
            'quota_available'    => $updatedUser['quota_total'] - $updatedUser['quota_used']
        ];

        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }


    // GET /me/quota — utilisé / total / %
    public function meQuota(Request $request, Response $response): Response
    {
        // récuperer id via JWT
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];

        } catch (\Exception $e) {
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(401)
                ->withBody($response->getBody()->write(json_encode([
                    'error' => $e->getMessage()
                ])));
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

        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }


   // GET /me/activity — derniers événements (uploads + downloads)
    public function meActivity(Request $request, Response $response): Response
    {
        // récuperer id via JWT
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];

        } catch (\Exception $e) {
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(401)
                ->withBody($response->getBody()->write(json_encode([
                    'error' => $e->getMessage()
                ])));
        }

        $limit = min(100, (int)($request->getQueryParams()['limit'] ?? 20));
        $offset = max(0, (int)($request->getQueryParams()['offset'] ?? 0));

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
            // b avant a => tri décroissant (a avant b => tri croissant)
            return strtotime($b['at']) <=> strtotime($a['at']);
        });

        // Limiter après merge => $events il y a trop éléments 
        $events = array_slice($events, 0, $limit); //=> il renvoie de 0 à p.ex 20 éléménts..

        $response->getBody()->write(json_encode([
            'user_id'   => $userId,
            'count'     => count($events),
            'events'    => $events
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
            $user = $this->auth->getAuthenticatedUserFromToken($request);
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
            'message'       => 'Folder created',
            'id'            => $folderId,
            'name'          => $body['name'],
            'parent_id'     => $parentId
        ], 201);
    }

    // DELETE /folders/{id}
    public function deleteFolder(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $folder = $this->files->findFolder($id);

        if (!$folder) {
            return $this->json($response, ['error' => 'Folder not found'], 404);
        }

        // Supprimer le fichier sur le disque
        $path = $this->uploadDir . DIRECTORY_SEPARATOR . $folder['name'];
        if (file_exists($path)) {
            unlink($path);
        }

        // Supprimer en base
        $this->files->deleteFolder($id);

        // suppression réussi => statut: 204
        return $this->json($response, ['message' => 'Folder deleted'], 204);
    }


    //PUT /folders/{id} => renommer un dossier
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

        $id = (int)($args['id'] ?? 0);
        if($id <= 0){
            return $this->json($response, ['error' => 'id invalide'], 400);
        }

        $folder = $this->files->findFolder($id);
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
        if($this->files->folderNameExistForUser($userId, $parentId, $newName, $id)){
            return $this->json($response, ['error' => 'Un dossier avec ce nom existe deja ici'], 409);
        }

        $ok = $this->files->renameFolder($id, $newName);
        if(!$ok){
            return $this->json($response, ['error' => 'Renommage non applique...'], 404);
        }

        return $this->json($response, [
            'message'       => 'Dossier renomme',
            'id'            => $id,
            'name'          => $newName
        ], 200);

    }

    //PUT /files/{id} => renommer un dossier
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

}


?>