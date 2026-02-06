<?php
// coffre-fort/src/Controller/UserController.php

namespace App\Controller;

use App\Model\FileRepository;
use App\Model\UserRepository;
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
    private AuthService $auth;
    private string $jwtSecret;

    public function __construct(Medoo $db)
    {
        $this->users = new UserRepository($db);
        $this->files = new FileRepository($db);

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





     /******************* Functions PRIVATE ***************************************************/

    private function json(Response $response, array $data, int $status): Response{
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
    








}