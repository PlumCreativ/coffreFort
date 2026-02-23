<?php
// coffre-fort/src/Controller/UserController.php

namespace App\Controller;

use App\Model\FileRepository;
use App\Model\UserRepository;
use App\Model\ShareRepository;
use App\Security\AuthService;
use Medoo\Medoo;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AdminController
{


    private UserRepository $users;
    private FileRepository $files;
    private ShareRepository $shares;
    private string $uploadDir;
    private AuthService $auth;
    private string $jwtSecret;

    public function __construct(Medoo $db)
    {
        $this->users = new UserRepository($db);
        $this->files = new FileRepository($db);
        $this->shares = new ShareRepository($db);
        $this->uploadDir = __DIR__ . '/../../storage/uploads';

        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?? '';
        $this->auth = new AuthService($db, $this->jwtSecret);
    }

    /**
     * GET /admin/users/quotas *********************************************************************************************** OK
     * Liste tous les utilisateurs avec leurs quotas QUE Admin
     */
    public function listUsersWithQuota(Request $request, Response $response): Response
    {
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        if(!isset($user['is_admin']) || !(bool)$user['is_admin']){
            return $this->json($response, ['error' => 'Accès refusé: administrateur requis.'], 403);
        }

        try {
            $allUsers = $this->users->listUsers();

            $result = [];
            foreach($allUsers as $user){
                $userId = (int)$user['id'];

                //calcul de l'espace utilisé par l'utilisateur
                $used = $this->files->totalSizeByUser($userId);

                //récuperer le quota max de user
                $max = isset($user['quota_total']) ? (int)$user['quota_total'] : 0;

                $result[] = [
                    'id' => $userId,
                    'email' => $user['email'],
                    'used' => $used,
                    'max' => $max,
                    'is_admin' => (bool)$user['is_admin']
                ];
            }
            return $this->json($response, ['users' => $result], 200);

        }catch (\Exception $e) {
            return $this->json($response, ['error' => 'Erreur lors de la récupération des utilisateurs: ',
            'details' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * PUT /admin/users/{id}/quota *************************************************************************************** OK
     * Modifie le quota d'un utilisateur QUE ADMIN
     */
    public function updateUserQuota(Request $request, Response $response, array $args): Response
    {
        $targetUserId = (int)($args['id'] ?? 0);
        
        if($targetUserId <= 0){
            return $this->json($response, ['error' => "Id utilisateur invalide"], 400);
        }

        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        if(!isset($user['is_admin']) || !(bool)$user['is_admin']){
            return $this->json($response, ['error' => 'Accès refusé: administrateur requis.'], 403);
        }

        $data = $request->getParsedBody();
        $newQuota = isset($data['quota']) ? (int)$data['quota'] : null;

        if($newQuota == null || $newQuota < 0){
            return $this->json($response, ['error' => 'Quota invalide (doit être >=0)'], 400);
        }

        try{
            $targetUser = $this->users->find($targetUserId);
            if (!$targetUser) {
                return $this->json($response, ['error' => 'Utilisateur introuvable'], 404);
            }

            $usedSpace = $this->files->totalSizeByUser($targetUserId);

            if($newQuota < $usedSpace){
                return $this->json($response, [
                    'error'             => "Le nouveau quota ne peut pas être infèrieure à l\'espace utilisé.",
                    'quota_requested'   => $newQuota,
                    'space_used'        => $usedSpace
                ], 400);
            }

            $this->users->updateQuota($targetUserId, $newQuota);

            return $this->json($response, [
                'message'    => "Quota modifié avec succès",
                'user_id'   => $targetUserId,
                'new_quota' => $newQuota,
                'used'      =>$usedSpace
            ], 200);
        }catch(\Exception $e){
            return $this->json($response, [
                'error'    => "Erreur lors de la modification du quota",
                'details'   => $e->getMessage()
            ], 500);
        }
    }

  
    /**
     * DELETE /admin/users/{id} - Supprime un utilisateur (ADMIN uniquement) ****************************************************************************OK
     * Respecte le RGPD en supprimant toutes les données de l'utilisateur
     */
    public function deleteUser(Request $request, Response $response, array $args): Response
    {
        //vérif authentification d'admin
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        if(!isset($user['is_admin']) || !(bool)$user['is_admin']){
            return $this->json($response, ['error' => 'Accès refusé: administrateur requis.'], 403);
        }
        
        $targetUserId = (int)($args['id'] ?? 0);
        if ($targetUserId <= 0) {
            return $this->json($response, ['error' => 'Id utilisateur invalide'], 400); 
        }

         //vérif si user existe
        $targetUser = $this->users->find($targetUserId);
        if (!$targetUser) {
            return $this->json($response, ['error' => 'Utilisateur introuvable'], 404);
        }

        //emêcher la suppression de son propre compte
        if((int)$user['id'] === $targetUserId){
            return $this->json($response, ['error' => 'Vous ne pouvez pas supprimer votre propre compte'], 400);
        }

        try{
            //lister tous les fichiers physiques à supprimer
            $filesToDelete = [];

            //récup tous les fichier de user
            $allFiles = $this->files->listFilesByUser($targetUserId);

            foreach($allFiles as $file){
                $fileId = (int)$file['id'];

                //récupe toutes les versions d'un file
                $versions = $this->files->getAllVersions($fileId);

                //supprimer tous les versions sur le disque
                foreach ($versions as $version) {
                    $storedName = $version['stored_name'] ?? null;
                    if($storedName){
                        $path = $this->uploadDir . DIRECTORY_SEPARATOR . $storedName;
                        $filesToDelete[] = $path;
                    }
                }

                //fichier en clair => ancien version
                $storedName = $file['stored_name'] ?? null;
                if($storedName){
                    $path = $this->uploadDir . DIRECTORY_SEPARATOR . $storedName;
                    if(!in_array($path, $filesToDelete)){
                        $filesToDelete[] = $path;
                    }
                }
            }

            //supprimer les fichiers physiques du disque
            $deletedFiles = 0;
            $failedFiles = [];

            foreach($filesToDelete as $path){
                if(file_exists($path)){
                    if(@unlink($path)){
                        $deletedFiles++;
                    }else{
                        $failedFiles[] = basename($path);
                        error_log("Impossible de supprimer le fichier : $path");
                    }
                }
            }

            //supprimer les logs de téléchargement :
            //downloads_log.share_id -> shares.user_id = $targetUserId
            $this->shares->deleteDownloadLogsByUser($targetUserId);

            //supprimer user en BDD
            $deleted = $this->users->delete($targetUserId); // si possible, retourne true/false
            
            if ($deleted === false) {
                return $this->json($response, ['error' => 'Suppression impossible en BDD'], 500);
            }

            //retourner un résummé
            $summary = [
                'message' => "Utilisateur supprimé avec succès (BDD)",
                'user_id' => $targetUserId,
                'email' => $targetUser['email'],
                'deleted_files' => $deletedFiles
            ];

            if(!empty($failedFiles)){
                $summary['warning'] = 'Certains fichiers n\'ont pas pu être supprimés du disque';
                $summary['failed_files'] = $failedFiles;
            }

            return $this->json($response, $summary, 200);
        
        }catch(\Exception $e){
            error_log("Erreur lors de la suppression de l\'utilisateur $targetUserId. ");
            return $this->json($response, [
                'error' => 'Erreur lors de la suppression de l\'utilisateur',
                'details' => $e->getMessage()
            ], 500);
         }

        // suppression réussi => statut: 204 No Content
        return $response->withStatus(204);
    }



     /******************* Functions PRIVATE ***************************************************/

    private function json(Response $response, array $data, int $status): Response{
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
    








}