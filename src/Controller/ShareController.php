<?php
namespace App\Controller;

use App\Helpers\RequestHelper;
use App\Helpers\StorageWriter;
use App\Model\FileRepository;
use App\Model\ShareRepository;
use App\Model\DownloadLogRepository;
use App\Security\ShareToken;
use App\Security\AuthService;
use App\Security\FileCrypto;


use Medoo\Medoo;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class ShareController{
    private Medoo $db;
    private FileRepository $files;
    private ShareRepository $shares;
    private DownloadLogRepository $logs;
    private AuthService $auth;

    private string $uploadDir;
    private string $jwtSecret;
    private string $shareSecret;
    private string $publicBaseUrl;


    public function __construct(Medoo $db, ?string $jwtSecret = null){
        $this->db = $db;
        $this->files = new FileRepository($db);
        $this->shares = new ShareRepository($db);
        $this->logs= new DownloadLogRepository($db);

        $this->uploadDir = __DIR__ . '/../../storage/uploads';

        $this->jwtSecret   = $jwtSecret ?? ($_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?? '');
        $this->shareSecret = $_ENV['SHARE_SECRET'] ?? getenv('SHARE_SECRET') ?? '';
        $this->publicBaseUrl = rtrim(($_ENV['APP_PUBLIC_BASE_URL'] ?? getenv('APP_PUBLIC_BASE_URL') ?? ''), '/');

        $this->auth = new AuthService($db, $this->jwtSecret);
    }

    private function json(Response $response, array $data, int $status): Response{
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }


    // POST /shares -> création d’un partage avec validations.
    public function createShare(Request $request, Response $response): Response
    {
        try{
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];
        }catch(\Exception $e){
            $code = (int)($e->getCode() ? : 401);
            return $this->json($response, ['error' => $e->getMessage()], $code);
        }

        if($this->shareSecret === ''){
            return $this->json($response, ['error' => 'SHARE_SECRET manquant sur le serveur'], 500);
        }

        $body = $request->getParsedBody();
        if(!is_array($body)){
            $body = [];
        }

        $kind = $body['kind'] ?? null;
        $targetId = isset($body['target_id']) ? (int)$body['target_id'] : null;
        $label = isset($body['label']) ? trim((string)$body['label']) : null;
        $maxUses = array_key_exists('max_uses', $body) ? (int)$body['max_uses'] : null;
        $expiresAtRaw = $body['expires_at'] ?? null;
        $allowFixedVersions = !empty($body['allow_fixed_versions']) ? 1 : 0;

        //validations
        if(!in_array($kind, ['file', 'folder'], true)){
            return $this->json($response, ['error' => 'kind invalide (file|folder)'], 400);
        }

        if($targetId <= 0){
            return $this->json($response, ['error' => 'target_id invalide'], 400);
        }
 
        if($maxUses !== null && $maxUses < 1){
            return $this->json($response, ['error' => 'max_uses doit être >= 1 ou null (illimité)'], 400);
        }

        //valider le owner
        if($kind === 'file'){
            if(!$this->files->isOwnedByUser($targetId, $userId)){
                return $this->json($response, ['error' => "Vous n'êtes pas proprietaire de ce fichier"], 403);
            }
        }else{
            if(!$this->files->folderOwnedByUser($targetId, $userId)){ //??????????????
                return $this->json($response, ['error' => "Vous n'êtes pas proprietaire de ce dossier"], 403);
            }
        }

        //validation expires_at futur (supporte ISO Z: 2025-12-31T23:59:59Z)
        $expiresAtSql = null;
        
        if($expiresAtRaw !== null && $expiresAtRaw !== ''){
            $ts = strtotime((string)$expiresAtRaw);
            if($ts === false){
                return $this->json($response, ['error' => 'expires_at invalide'], 400);
            }
            
            if($ts <= time()){
                return $this->json($response, ['error' => 'expires_at doit etre dans le futur'], 400);
            }

            $expiresAtSql = gmdate('Y-m-d H:i:s', $ts);  // stocke en UTC!!
        }

        //token opaque + signature HMAC(token || id ) stockée en BDD
        $token = ShareToken::randomToken(32);

        //il faut l'id pour signer => insert puis update la signature
        // => à mettre le token_sig temporairement à une valeur bidon => puis l'update
        $created = $this->shares->create([
            'user_id'                => $userId,
            'kind'                   => $kind,
            'target_id'              => $targetId,
            'token'                  => $token,
            'token_sig'              => str_repeat('0', 64), //temporaire
            'label'                  => $label,
            'expires_at'             => $expiresAtSql,
            'max_uses'               => $maxUses,
            'remaining_uses'         => $maxUses,           //initialiser à max_uses
            'allow_fixed_versions'   => $allowFixedVersions,
        ]);

        $shareId = (int)$created['id'];
        $sig = ShareToken::sign($this->shareSecret, $token, $shareId);

        $this->db->update('shares', ['token_sig' => $sig], ['id' => $shareId]);

        $created['token_sig'] = $sig;

        // à modifier de '/s/' => '/share.php?token=' => pour pouvoir ouvrir!!
        $publicPath = '/share.php?token=' . $token; // URL publique sans la signature
        $url = $this->publicBaseUrl ? ($this->publicBaseUrl . $publicPath) : $publicPath;

        return $this->json($response, [
            'id'                    => $shareId,
            'user_id'               => $userId,
            'kind'                  => $kind,
            'target_id'             => $targetId,
            'label'                 => $label,
            'token'                 => $token,
            'expires_at'            => $expiresAtSql,
            'max_uses'              => $maxUses,
            'remaining_uses'        => $maxUses,
            'is_revoked'            => 0,
            'allow_fixed_versions'  => $allowFixedVersions,
            'created_at'            => $created['created_at'] ?? date('Y-m-d H:i:s'),
            'url'                   => $url
        ], 201);
    }


    /**
     * GET /shares ******************************************************************************* OK
     * Liste des partages filtrables, triés et paginés
     */
    public function listShares(Request $request, Response $response): Response
    {
        try{
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];
        }catch(\Exception $e){
            $code = (int)($e->getCode() ? : 401);
            return $this->json($response, ['error' => $e->getMessage()], $code);
        }

        //paramètres de pagination et filtrages
        $params = $request->getQueryParams();
        $targetId = isset($params['target_id']) ? (int)$params['target_id'] : null;
        // $limit = isset($params['limit']) ? min(100, (int)$params['limit']) : 10;
        $limit = isset($params['limit']) ? min(100, max(1, (int)$params['limit'])) : 20; // garantir: 1 < limit < 20
        $offset = isset($params['offset']) ? max(0, (int)$params['offset']) : 0;

        //Compter AVANT de récupérer (pour éviter de charger si 0 résultats)
        $total = $this->shares->countSharesByUser($userId, $targetId);

        // Si aucun résultat, retourner directement
        if ($total === 0) {
            return $this->json($response, [
                'shares'    => [],
                'total'     => 0,
                'limit'     => $limit,
                'offset'    => $offset
            ], 200);
        }

        //récup des partages
        $shares = $this->shares->listSharesByUser($userId, $targetId, $limit, $offset);

        //enrichir avec les noms de fichiers/dossiers
        foreach($shares as &$share){
            if($share['kind'] === 'file'){

                //récuperer le nom original du fichier
                $file = $this->db->get('files', 'original_name', ['id' => (int)$share['target_id']]);
                
                $share['file_name'] = $file ?: 'Fichier supprimé';

            }elseif($share['kind'] === 'folder'){

                //récuperer le nom du dossier
                $folder = $this->db->get('folders', 'name', ['id' => (int)$share['target_id']]);
                $share['folder_name'] = $folder ?: 'Dossier supprimé';
            }else{
                $share['file_name'] = 'Inconnu';
            } 

            //url publique reconstruite => pour afficher dans "mes partages"
            $token = (string)($share['token'] ?? '');
            // $publicPath = '/s/' . $token; 
            $publicPath = '/share.php?token=' . $token;
            $share['url'] = $this->publicBaseUrl ? ($this->publicBaseUrl . $publicPath) : $publicPath;

        }
        unset($share);

        return $this->json($response, [
            'shares' => $shares,
            'total'  => $total,      
            'limit'  => $limit,      
            'offset' => $offset     
        ], 200);
    }


    //GET /shares/{id} - Détails d'un partage (pour le propriétaire)
    public function showShare(Request $request, Response $response, array $args): Response
    {
        $shareId = (int)($args['id'] ?? 0);

        if ($shareId <= 0) {
            return $this->json($response, ['error' => 'ID invalide'], 400);
        }

        // authentification
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        // Récupérer le partage
        $share = $this->shares->findById($shareId);

        if (!$share) {
            return $this->json($response, ['error' => 'Partage introuvable'], 404);
        }

        // Vérifier que l'utilisateur est propriétaire
        if ((int)$share['user_id'] !== $userId) {
            return $this->json($response, ['error' => 'Accès interdit'], 403);
        }

        // Enrichir avec l'URL
        $token = (string)($share['token'] ?? '');
        // $publicPath = '/s/' . $token; 
        $publicPath = '/share.php?token=' . $token;
        $share['url'] = $this->publicBaseUrl ? ($this->publicBaseUrl . $publicPath) : $publicPath;

        return $this->json($response, $share, 200);
    }


    // PATCH /shares/{id}/revoke -> révoquer un partage
    public function revokeShare(Request $request, Response $response, array $args):Response
    {
        $shareId = (int)($args['id'] ?? 0);

        if ($shareId <= 0) {
            return $this->json($response, ['error' => 'ID invalide'], 400);
        }

        try{
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];
        }catch(\Exception $e){
            $code = (int)($e->getCode() ? : 401);
            return $this->json($response, ['error' => $e->getMessage()], $code);
        }

        $share = $this->shares->findById($shareId);

        if (!$share) {
            return $this->json($response, ['error' => 'Partage introuvable'], 404);
        }

        // vérifier que l'utilisateur est propriétaire
        if ((int)$share['user_id'] !== $userId) {
            return $this->json($response, ['error' => 'Accès interdit'], 403);
        }

        //vérifier s'il est déjà révoqué
        if((int)$share['is_revoked'] === 1){
            return $this->json($response, ['message' => 'Ce partage est déjà révoqué'], 200);
        }

        //révoquer
        try {
            $this->shares->revoke($shareId);

            return $this->json($response, [
                'message' => 'Partage révoqué avec succès',
                'id'      => $shareId
            ], 200);

        } catch (\Exception $e) {
            return $this->json($response, [
                'error'     => 'Erreur lors de la révocation',
                'details'   => $e->getMessage()
            ], 500);
        }
        
        return $this->json($response, ['message' => 'Partage revoque avec succes'], 200);
    }


    // GET /s/{token} ->affiche les infos du partage => consultation publique des métadonnées. *************************************************************** OK
    public function publicShare(Request $request, Response $response, array $args): Response {

        $token = (string)($args['token'] ?? '');
        if ($token === ''){
            return $this->json($response, ['error' => 'Token manquant'], 400);
        } 

        $share = $this->shares->findByToken($token);
        if (!$share) {
            return $this->json($response, ['error' => 'Partage introuvable'], 404);
        } 

        // Validation du partage (révoqué, expiré, quota)
        $validationError = $this->validateShare($share);
        if($validationError){
            return $this->json($response, ['error' => $validationError['error']], $validationError['code']);
        }

        $fileMeta = null;

        if($share['kind'] === 'file'){
            $fileId = (int)$share['target_id'];
            $file = $this->files->find($fileId);

            if(!$file){
                return $this->json($response, ['error' => 'Fichier partage introuvable'], 404);
            }

            $versionsCount = $this->files->getVersionCount($fileId);
            $currentVersion = $this->files->getCurrentVersionMeta($fileId);

            $fileMeta = [
                'id'                => (int)$file['id'],
                'original_name'     => (string)($file['original_name'] ?? ''),
                'size'              => (int)$file['size'],
                'mime'              => (string)($file['mime'] ?? ''),
                'created_at'        => (string)($file['created_at'] ?? ''),

                'versions_count'    => (int)$versionsCount,
                'current_version'   => $currentVersion ?[
                    'id'            => (int)$currentVersion['id'],
                    'version'       => (int)$currentVersion['version'],
                    // meta.file.current_version.created_at
                    'created_at'    => (string)$currentVersion['created_at'],
                ] : null,
            ];
        }else if($share['kind'] === 'folder'){
            $fileMeta = null; //=> à remplir plus tard!!!
        }

        return $this->json($response, [
            'id'                    => (int)$share['id'],
            'kind'                  => $share['kind'],
            'target_id'             => (int)$share['target_id'],
            'label'                 => $share['label'],
            'expires_at'            => $share['expires_at'],
            'max_uses'              => $share['max_uses'] !== null ? (int)$share['max_uses'] : null,
            'remaining_uses'        => $share['remaining_uses'] !== null ? (int)$share['remaining_uses'] : null,
            'is_revoked'            => (bool)$share['is_revoked'],
            'allow_fixed_versions'  => (bool)$share['allow_fixed_versions'],
            'created_at'            => $share['created_at'],

            'file'                  => $fileMeta,

            // URLs de téléchargement
            'download_url'          => '/s/' . $token . '/download',
            'versions_url'          => (bool)$share['allow_fixed_versions'] ? '/s/' . $token . '/versions' : null,
        ], 200);
    }

    //DELETE /shares/{id}
    public function deleteShare(Request $request, Response $response, array $args): Response {

        $shareId = (int)($args['id'] ?? 0);

        if ($shareId <= 0) {
            return $this->json($response, ['error' => 'ID invalide'], 400);
        }

        try{
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];
        }catch(\Exception $e){
            $code = (int)($e->getCode() ? : 401);
            return $this->json($response, ['error' => $e->getMessage()], $code);
        }

        // récuperer le partage
        $share = $this->shares->findById($shareId);

        if (!$share) {
            return $this->json($response, ['error' => 'Partage introuvable'], 404);
        }

        // vérifier que l'utilisateur est propriétaire
        if ((int)$share['user_id'] !== $userId) {
            return $this->json($response, ['error' => 'Accès interdit'], 403);
        }

        // Supprimer
        try {
            $this->shares->delete($shareId);

            return $this->json($response, [
                'message' => 'Partage supprimé avec succès',
                'id'      => $shareId
            ], 200);

        } catch (\Exception $e) {
            return $this->json($response, [
                'error'   => 'Erreur lors de la suppression',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /*************************** function private ***********************************/

    private function validateShare(array $share): ?array
    {
        if ((int)$share['is_revoked'] === 1){
            return ['error' => 'Ce partage a été révoqué', 'code' => 403];
        } 
        if ($share['expires_at'] && strtotime($share['expires_at']) <= time()) {
            return ['error' => 'Ce partage a expiré', 'code' => 410];
        }
            
        if ($share['remaining_uses'] !== null && (int)$share['remaining_uses'] <= 0) {
            return ['error' => 'Nombre de téléchargements atteint', 'code' => 429];
        } 
        return null;
    }

    /********************************************************************************/


    //GET /s/{token}/download  =>téléchargement, journalisation et décrémentation atomique de remaining_uses.   ********************************************************* OK
    // télécharger la version courante (par défaut)
    // télécharger une version spécifique (si autorisé)
    public function publicDownload(Request $request, Response $response, array $args):Response {

        $token = (string)($args['token'] ?? '');
        if($token === ''){
            return $this->json($response, ['error' => 'Token manquant'], 400);
        }
        
        $ip = RequestHelper::getClientIp($request);
        $userAgent = RequestHelper::getUserAgent($request);

        $message = null;
        $shareId = 0;

        $versionId = null; // pas de versionnage en clair => on loggue NULL

        //charger le share
        $share = $this->shares->findByToken($token);
        if($share === null || !$share){
            $message = 'Token de partage invalide';
            $this->logs->log($shareId, null, $ip, $userAgent, false, $message);
            return $this->json($response,['error' => $message], 404);
        }

        $shareId = (int)$share['id'];
        $versionRow = null;

        try{

            $validationError = $this->validateShare($share);
            if($validationError){
                $this->logs->log($shareId, null, $ip, $userAgent, false, $validationError['error'] . ' ( ' . $validationError['code'] . ' )');
                 return $this->json($response, ['error' => $validationError['error']], $validationError['code']);
            }

            //vérif : share secret
            if($this->shareSecret === ''){
                $message = 'SHARE_SECRET manquant sur le serveur';
                $this->logs->log($shareId, null, $ip, $userAgent, false, $message);
                return $this->json($response, ['error' => $message], 500);
            }

            //vérif: signature
            $expectedSig = ShareToken::sign($this->shareSecret, $token, $shareId);
            if(!ShareToken::equals($expectedSig, (string)$share['token_sig'])){
                $message = 'Signature de partage invalide';
                $this->logs->log($shareId, null, $ip, $userAgent, false, $message);
                return $this->json($response, ['error' => $message], 403);
            }

            //pour l'instant que pour le fichier => puis plus tard dossier
            if($share['kind'] !== 'file'){
                $message = 'Partage de dossier non supporte pour le moment';
                return $this->json($response, ['error' => $message], 501);
            }

            //télécharger le fichier
            $fileId = (int)$share['target_id'];
            $file = $this->files->find($fileId);
            if(!$file){
                $message = 'Fichier partage introuvable';
                $this->logs->log($shareId, null, $ip, $userAgent, false, $message);
                return $this->json($response, ['error' => $message], 404);
            }

            //pour supporter '?v=3'....
            $params = $request->getQueryParams();
            $requestedVersion = isset($params['v']) ? (int)$params['v'] : null;
            
            // choisir la version à servir
            if($requestedVersion !== null && $requestedVersion > 0){

                // interdire si le share n'autorise pas
                if((int)($share['allow_fixed_versions'] ?? 0) !== 1){
                    $message = 'Les versions figées (?v=) ne sont pas autorisées pour ce lien';
                    $this->logs->log($shareId, null, $ip, $userAgent, false, $message);
                    return $this->json($response, ['error' => $message], 403);
                }
                
                $versionRow = $this->files->getVersionRow($fileId, $requestedVersion);
                
                if(!$versionRow){
                    $message = "Version demandée introuvable";
                    $this->logs->log($shareId, $versionId, $ip, $userAgent, false, $message);
                    return $this->json($response, ['error' => $message], 404);
                }
                    
                $versionId = (int)($versionRow['id'] ?? 0);
            } else {

                //version courant par défaut
                $versionRow = $this->files->getCurrentVersionRow($fileId);
                if($versionRow){
                    $versionId = (int)($versionRow['id'] ?? 0);
                }
            }

            //Fichier CHIFFRÉ (a des versions dans file_versions)
            if($versionRow !== null){
                error_log("Download Chiffré pour file_id = $fileId (partage via token");

                //version courante
                $versionId = (int)$versionRow['id'];
                $storedName = (string)$versionRow['stored_name'];

                // $storedName = (string)($file['stored_name'] ?? '');
                if($storedName === ''){
                    $message = 'stored_name manquant en base';
                    return $this->json($response, ['error' => $message], 500);
                }

                $path = $this->uploadDir . DIRECTORY_SEPARATOR . $storedName;
                if(!file_exists($path)){
                    $message = 'Fichier partage manquant sur le serveur';
                    error_log("Fichier manquant: $path");
                    return $this->json($response, ['error' => $message], 500);
                }

                //Lire le fichier chiffré
                //ancien code
                // $ciphertext = file_get_contents($path);
                // if ($ciphertext === false) {
                //     $message = "Impossible de lire le fichier chiffre";
                //     return $this->json($response, ['error' => $message], 500);
                // }

                // Lire le fichier chiffré par stream
                try{
                    $ciphertext = StorageWriter::readBinary($path);
                }catch (\RuntimeException $e){
                    $this->logs->log($shareId, $versionId, $ip, $userAgent, false, 'Impossible de lire le fichier chiffré (500)');
                    return $this->json($response, ['error' => 'Impossible de lire le fichier chiffré'], 500);
                }

              
                try{
                    $kek = FileCrypto::normalizeKek($_ENV['KEY_ENCRYPTION_KEY'] ?? getenv('KEY_ENCRYPTION_KEY') ?? '');
                    $decrypte = FileCrypto::decryptFromStorage($ciphertext, $versionRow, $kek, $fileId);
                    $plaintext = $decrypte['plaintext'];

                    // Libérer la mémoire
                    unset($ciphertext);
                }catch (\Throwable $e){
                    $message = $e->getMessage();
                    error_log('Decrypt failed (publicDownload): ' . $message);
                    $this->logs->log($shareId, $versionId, $ip, $userAgent, false, 'Échec déchiffrement (500): ' . $message);
                    return $this->json($response, ['error' => $message], 500);
                }

                //décrémentation atomique => 
                // => garantit que le compteur de téléchargements ne peut jamais être contourné, même si 10 personnes cliquent en même temps.
                if ($share['remaining_uses'] !== null) {
                    $ok = $this->shares->consumeUse($shareId);
                    if (!$ok) {
                        $message = 'Nombre de téléchargements atteint';
                        $this->logs->log($shareId, $versionId, $ip, $userAgent, false, $message);
                        return $this->json($response, ['error' => $message], 429); //(too many request)
                    }
                }

                // renvoyer le PLAINTEXT  et pas le fichier chiffré!!!!
                $response->getBody()->write($plaintext);

                $message = 'Telechargement reussi';
                $this->logs->log($shareId, $versionId, $ip, $userAgent, true, $message);

                return $response
                    ->withHeader('Content-Type', $file['mime'])
                    ->withHeader('Content-Disposition', 'attachment; filename="' . $file['original_name'] . '"')
                    ->withHeader('Content-Length', (string)strlen($plaintext))
                    ->withStatus(200);
            }

            //Fichier EN CLAIR (ancien système, pas de file_versions)
            error_log("Download En Clair pour file_id=$fileId (partage via token)");
            
            $storedName = (string)($file['stored_name'] ?? '');
            if($storedName === ''){
                $message = 'stored_name manquant dans files';
                return $this->json($response, ['error' => $message], 500);
            }

            $path = $this->uploadDir . DIRECTORY_SEPARATOR . $storedName;
            if(!file_exists($path)){
                $message = 'Fichier en clair manquant sur le serveur';
                error_log("Fichier manquant: $path");
                return $this->json($response, ['error' => $message], 500);
            }

            //décrémentation atomique => 
            // => garantit que le compteur de téléchargements ne peut jamais être contourné, même si 10 personnes cliquent en même temps.
            if ($share['remaining_uses'] !== null) {
                $ok = $this->shares->consumeUse($shareId);
                if (!$ok) {
                    $message = 'Nombre de telechargements atteint';
                    $this->logs->log($shareId, $versionId, $ip, $userAgent, false, $message);
                    return $this->json($response, ['error' => $message], 403); //ou 429
                }
            }

            //stream
            $stream = fopen($path, 'rb');
            if ($stream === false) {
                $message = "Impossible d'ouvrir le fichier";
                $this->logs->log($shareId, null, $ip, $userAgent, false, $message);
                return $this->json($response, ['error' => $message], 500);
            }

            $body = $response->getBody();
            while(!feof($stream)){
                $chunk = fread($stream, 8192);
                if ($chunk === false) break;
                $body->write($chunk);
            }
            fclose($stream);
            
            $message = 'Telechargement reussi';
            $this->logs->log($shareId, null, $ip, $userAgent, true, $message);

            return $response
                ->withHeader('Content-Type', $file['mime'])
                ->withHeader('Content-Disposition', 'attachment; filename="' . $file['original_name'] . '"')
                ->withHeader('Content-Length', (string)filesize($path))
                ->withStatus(200);
        
        }catch(\Throwable $e){
            error_log('Exception dans publicdownload: ' . $e->getMessage());
            $message = 'Erreur serveur: ' . $e->getMessage();
            return $this->json($response, ['error' => $message], 500);
        }
    }


    // GET /s/{token}/versions  => liste les versions disponibles: liste publique des versions si c'est autorisé
    public function publicShareVersions(Request $request, Response $response, array $args):Response 
    {
        $token = (string)($args['token'] ?? '');
        if($token === ''){
            return $this->json($response, ['error' => 'Token manquant'], 400);
        }

        $share = $this->shares->findByToken($token);
        if(!$share){
            return $this->json($response, ['error' => 'Partage introuvable'], 404);
        }

        $validationError = $this->validateShare($share);
        if($validationError){
                return $this->json($response, ['error' => $validationError['error']], $validationError['code']);
        }

        //supporte que le file
        if($share['kind'] !== 'file'){
            return $this->json($response, ['error' => 'Partage de dossier non supporte pour le moment'], 501);
        }

        //autorisation d'exposer les versions
        $allow = (int)($share['allow_fixed_versions'] ?? 0) === 1;
        if(!$allow){
            return $this->json($response, ['error' => 'Les versions ne sont pas exposées pour ce lien'], 403);
        }

        //télécharger le fichier
        $fileId = (int)$share['target_id'];
        $file = $this->files->find($fileId);
        if(!$file){
            return $this->json($response, ['error' => 'Fichier partage introuvable'], 404);
        }

        $params = $request->getQueryParams();
        $limit = isset($params['limit']) ? (int)$params['limit'] : 20;
        $offset = isset($params['offset']) ? (int)$params['offset'] : 0;

        $res = $this->files->listVersionsForShare($fileId, $limit, $offset);

        return $this->json($response, [
            'file_id'       => $fileId,
            'versions'      => array_map(function($r){
                return [
                    'id'            => (int)$r['id'],
                    'version'       => (int)$r['version'],
                    'size'          => isset($r['size']) ? (int)$r['size'] : null,
                    'created_at'    => (string)$r['created_at'],
                ];
            }, 
            $res['rows']),
            'total'         => (int)$res['total'],
            'limit'         => (int)$res['limit'],
            'offset'        => (int)$res['offset'],
        ], 200);
    }
}