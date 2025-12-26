<?php
namespace App\Controller;

use App\Model\FileRepository;
use App\Model\ShareRepository;
use App\Model\DownloadLogRepository;
use App\Security\ShareToken;
use App\Security\AuthService;

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

        $this->jwtSecret = $jwtSecret ?? ($_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?? '');
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


        //validations
        if(!in_array($kind, ['file', 'folder'], true)){
            return $this->json($response, ['error' => 'kind invalide (file|folder)'], 400);
        }

        if($targetId <= 0){
            return $this->json($response, ['error' => 'target_id invalide'], 400);
        }
 
        if($maxUses !== null && $maxUses < 1){
            return $this->json($response, ['error' => 'max_uses doit etre >= 1'], 400);
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
            'user_id'        => $userId,
            'kind'           => $kind,
            'target_id'      => $targetId,
            'token'          => $token,
            'token_sig'      => str_repeat('0', 64),
            'label'          => $label,
            'expires_at'     => $expiresAtSql,
            'max_uses'       => $maxUses,
            'remaining_uses' => $maxUses
        ]);

        $shareId = (int)$created['id'];
        $sig = ShareToken::sign($this->shareSecret, $token, $shareId);

        $this->db->update('shares', ['token_sig' => $sig], ['id' => $shareId]);

        $created['token_sig'] = $sig;

        // à modifier de '/s/' => '/share.php?token=' => pour pouvoir ouvrir!!
        $publicPath = '/share.php?token=' . $token; // URL publique sans la signature
        $url = $this->publicBaseUrl ? ($this->publicBaseUrl . $publicPath) : $publicPath;


        return $this->json($response, [
            'id'             => $shareId,
            'user_id'        => $userId,
            'kind'           => $kind,
            'target_id'      => $targetId,
            'label'          => $label,
            'expires_at'     => $expiresAtSql,
            'max_uses'       => $maxUses,
            'remaining_uses' => $maxUses,
            'is_revoked'     => 0,
            'created_at'     => $created['created_at'] ?? date('Y-m-d H:i:s'),
            'url'            => $url
        ], 201);
    }

    // GET /shares ->liste des partages filtrable, trié et paginé.
    public function listShares(Request $request, Response $response): Response
    {
        try{
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];
        }catch(\Exception $e){
            $code = (int)($e->getCode() ? : 401);
            return $this->json($response, ['error' => $e->getMessage()], $code);
        }

        $params = $request->getQueryParams();
        $targetId = isset($params['target_id']) ? (int)$params['target_id'] : null;
        $limit = isset($params['limit']) ? min(100, (int)$params['limit']) : 20;
        $offset = isset($params['offset']) ? max(0, (int)$params['offset']) : 0;

        $where = ['user_id' => $userId];
        if($targetId !== null && $targetId > 0){
            $where['target_id'] = $targetId;
        }

        $shares = $this->db->select('shares', '*', [
            'AND' => $where,
            'ORDER' => ['created_at' => 'DESC'],
            'LIMIT' => [$offset, $limit]
        ]);

        foreach($shares as &$share){
            if($share['kind'] === 'file'){

                //récuperer le nom original du fichier
                $file = $this->db->get('files', 'original_name', ['id' => (int)$share['target_id']]);
                
                $share['file_name'] = $file ?: 'Fichier supprime';
            }elseif($share['kind'] === 'folder'){

                //récuperer le nom du dossier
                $file = $this->db->get('folders', 'name', ['id' => (int)$share['target_id']]);
                $share['file_name'] = $file ?: 'Dossier supprime';
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

        return $this->json($response, ['shares' => $shares], 200);
    }

    // POST /shares/{id}/revoke -> révoquer un partage
    public function revokeShare(Request $request, Response $response, array $args):Response
    {
        $shareId = (int)$args['id'];

        try{
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $userId = (int)$user['id'];
        }catch(\Exception $e){
            $code = (int)($e->getCode() ? : 401);
            return $this->json($response, ['error' => $e->getMessage()], $code);
        }

        $share = $this->shares->findById($shareId);

        if(!$share || (int)$share['user_id'] !== $userId){
            return $this->json($response, ['error' => 'Partage introuvable ou non autorise'], 403);
        }

        $this->shares->revoke($shareId);
        return $this->json($response, ['message' => 'Partage revoque avec succes'], 200);
    }


    // GET /s/{token} ->consultation publique des métadonnées.
    public function publicShare(Request $request, Response $response, array $args): Response {

        $token = (string)$args['token'];
        if (!$token) return $this->json($response, ['error' => 'Token manquant'], 400);

        $share = $this->shares->findByToken($token);
        if (!$share) return $this->json($response, ['error' => 'Partage introuvable'], 404);

        if ((int)$share['is_revoked'] === 1) return $this->json($response, ['error' => 'Ce partage a ete revoque'], 403);
        if ($share['expires_at'] && strtotime($share['expires_at']) <= time()) return $this->json($response, ['error' => 'Ce partage a expire'], 403);
        if ($share['remaining_uses'] !== null && (int)$share['remaining_uses'] <= 0) return $this->json($response, ['error' => 'Nombre de telechargements atteint'], 403);

        $fileMeta = null;

        if($share['kind'] === 'file'){
            $fileId = (int)$share['target_id'];
            $file = $this->files->find($fileId);

            if(!$file){
                return $this->json($response, ['error', 'Fichier partage introuvable'], 404);
            }

            $fileMeta = [
                'id'            => (int)$file['id'],
                'user_id'       => (int)$file['user_id'],
                'folder_id'     => (int)$file['folder_id'],
                'original_name' => (string)($file['original_name'] ?? ''),
                'size'          => isset($file['size']) ? (int)$file['size'] : null,
                'created_at'    => (string)($file['created_at'] ?? ''),
                'mime'          => (string)($file['mime'] ?? ''),
            ];
        }else if($share['kind'] === 'folder'){
            $fileMeta = null; //=> à remplir plus tard!!!
        }

        return $this->json($response, [
            'id'                => (int)$share['id'],
            'kind'              => $share['kind'],
            'target_id'         => (int)$share['target_id'],
            'label'             => $share['label'],
            'expires_at'        => $share['expires_at'],
            'max_uses'          => $share['max_uses'],
            'remaining_uses'    => $share['remaining_uses'],
            'is_revoked'        => (int)$share['is_revoked'],

            'file'              => $fileMeta,
        ], 200);
    }

    //GET /s/{token}/download  =>téléchargement, journalisation et décrémentation atomique de remaining_uses.
    public function publicDownload(Request $request, Response $response, array $args):Response {

        $token = (string)$args['token'];
        if($token === ''){
            return $this->json($response, ['error' => 'Token manquant'], 400);
        }
        
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $request->getHeaderLine('User-Agent') ? : 'unknown';

        $success = false;
        $message = null;
        $shareId = 0;

        $share = $this->shares->findByToken($token);
        if($share === null || !$share){
            $message = 'Token de partage invalide';
            $this->logs->log(0, null, $ip, $userAgent, false, $message);
            return $this->json($response,['error' => $message], 404);
        }

        $shareId = (int)$share['id'];

      
        try{

            if((int)$share['is_revoked'] === 1){
                $message = 'Partage revoque';
                return $this->json($response, ['error' => $message], 403);
            }

            if(!empty($share['expires_at']) && strtotime($share['expires_at']) <= time()){
                $message = 'Partage expire';
                return $this->json($response, ['error' => $message], 403);
            }

            if($this->shareSecret === ''){
                $message = 'SHARE_SECRET manquant sur le serveur';
                return $this->json($response, ['error' => $message], 500);
            }

            $expectedSig = ShareToken::sign($this->shareSecret, $token, $shareId);
            if(!ShareToken::equals($expectedSig, (string)$share['token_sig'])){
                $message = 'Signature de partage invalide';
                return $this->json($response, ['error' => $message], 403);
            }

            //pour l'instant que pour le fichier => puis plus tard dossier
            if($share['kind'] !== 'file'){
                $message = 'Partage de dossier non supporte pour le moment';
                return $this->json($response, ['error' => $message], 501);
            }

            //décrémentation atomique => 
            // => garantit que le compteur de téléchargements ne peut jamais être contourné, même si 10 personnes cliquent en même temps.
            if ($share['remaining_uses'] !== null) {
                $ok = $this->shares->consumeUse($shareId);
                if (!$ok) {
                    $message = 'Nombre de telechargements atteint';
                    return $this->json($response, ['error' => $message], 403);
                }
            }

            //télécharger le fichier
            $fileId = (int)$share['target_id'];
            $file = $this->files->find($fileId);
            if(!$file){
                $message = 'Fichier partage introuvable';
                return $this->json($response, ['error' => $message], 404);
            }

            $path = $this->uploadDir . DIRECTORY_SEPARATOR . $file['stored_name'];
            if(!file_exists($path)){
                $message = 'Fichier partage manquant sur le serveur';
                return $this->json($response, ['error' => $message], 500);
            }

            $stream = fopen($path, 'rb');
            $body = $response->getBody();
            while(!feof($stream)){
                $body->write(fread($stream, 8192));
            }
            fclose($stream);

            $success = true;
            $message = 'Telechargement reussi';

            return $response
                ->withHeader('Content-Type', $file['mime'])
                ->withHeader('Content-Disposition', 'attachment; filename="' . $file['original_name'] . '"')
                ->withHeader('Content-Length', (string)filesize($path))
                ->withStatus(200);

        }finally{

            //log le téléchargement
            $this->logs->log($shareId, null, $ip, $userAgent, $success, $message);
        }

    }

}