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

    

    // POST /shares
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
            return $this->json($response, ['error' => 'max_uses doit être >= 1'], 400);
        }

        //valider le owner
        if($kind === 'file'){
            if(!$this->files->isOwnedByUser($targetId, $userId)){
                return $this->json($response, ['error' => "Vous n'êtes pas propriétaire de ce fichier"], 403);
            }

        }else{
            if(!$this->files->folderOwnedByUser($targetId, $userId)){
                return $this->json($response, ['error' => "Vous n'êtes pas propriétaire de ce dossier"], 403);
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
                return $this->json($response, ['error' => 'expires_at doit être dans le futur'], 400);
            }

            $expiresAtSql = gmdate('Y-m-d H:i:s', $ts);  // stocke en UTC
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

        $this->db->update('shares', [
            'token_sig' => $sig
        ], [
            'id' => $shareId
        ]);

        $created['token_sig'] = $sig;

        $publicPath = '/s/' . $token; // URL publique sans la signature
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


    //GET /s/{token}  =>public link
    public function publicDownload(Request $request, Response $response, array $args):Response {

        $token = (string)$args['token'];
        if($token === ''){
            return $this->json($response, ['error' => 'Token manquant'], 400);
        }

        $share = $this->shares->findByToken($token);
        if($share === null){
            return $this->json($response,['error' => 'Partage introuvable'], 404);
        }

        $shareId = (int)$share['id'];

        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $request->getHeaderLine('User-Agent') ? : 'unknown';

        $success = false;

        try{

            if((int)$share['is_revoked'] === 1){
                return $this->json($response, ['error' => 'Ce partage a été révoqué'], 403);
            }

            if(!empty($share['expires_at']) && strtotime($share['expires_at']) <= time()){
                return $this->json($response, ['error' => 'Ce partage a expiré'], 403);
            }

            if($share['remaining_uses'] !== null && (int)$share['remaining_uses'] <= 0){
                return $this->json($response, ['error' => 'Ce partage a atteint son nombre maximum de telechargements'], 403);
            }

            if($this->shareSecret === ''){
                return $this->json($response, ['error' => 'SHARE_SECRET manquant sur le serveur'], 500);
            }

            $expectedSig = ShareToken::sign($this->shareSecret, $token, $shareId);
            if(!ShareToken::equals($expectedSig, (string)$share['token_sig'])){
                return $this->json($response, ['error' => 'Signature de partage invalide'], 403);
            }

            //pour l'instant que pour le fichier => puis plus tard dossier
            if($share['kind'] !== 'file'){
                return $this->json($response, ['error' => 'Partage de dossier non supporté pour le moment'], 501);
            }

            //décrémenter le remaining_uses s'il est limité
            if($share['remaining_uses'] !== null){
                $this->shares->decrementRemainingUses($shareId);
            }

            //télécharger le fichier
            $fileId = (int)$share['target_id'];
            $file = $this->files->find($fileId);
            if(!$file){
                return $this->json($response, ['error' => 'Fichier partagé introuvable'], 404);
            }

            $path = $this->uploadDir . DIRECTORY_SEPARATOR . $file['stored_name'];
            if(!file_exists($path)){
                return $this->json($response, ['error' => 'Fichier partage manquant sur le serveur'], 500);
            }

            $stream = fopen($path, 'rb');
            $body = $response->getBody();
            while(!feof($stream)){
                $body->write(fread($stream, 8192));
            }
            fclose($stream);

            $success = true;

            return $response
                ->withHeader('Content-Type', $file['mime'])
                ->withHeader('Content-Disposition', 'attachment; filename="' . $file['original_name'] . '"')
                ->withHeader('Content-Length', (string)filesize($path))
                ->withStatus(200);

        }finally{

            //log le téléchargement
            $this->logs->log($shareId, null, $ip, $userAgent, $success);
        }

    }

}