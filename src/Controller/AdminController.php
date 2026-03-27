<?php

namespace App\Controller;

use App\Model\FileRepository;
use App\Model\UserRepository;
use App\Model\ShareRepository;
use App\Security\AuthService;
use App\Helper\AuditLogger;
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
    private AuditLogger $auditLogger;

    public function __construct(Medoo $db)
    {
        $this->users = new UserRepository($db);
        $this->files = new FileRepository($db);
        $this->shares = new ShareRepository($db);
        $this->uploadDir = __DIR__ . '/../../storage/uploads';
        $this->auditLogger = new AuditLogger($db);

        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?? '';
        $this->auth = new AuthService($db, $this->jwtSecret);
    }

    /**
     * GET /admin/users/quotas
     * Liste tous les utilisateurs avec leurs quotas (ADMIN uniquement)
     */
    public function listUsersWithQuota(Request $request, Response $response): Response
    {
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        if (!isset($user['is_admin']) || !(bool)$user['is_admin']) {
            return $this->json($response, ['error' => 'Accès refusé: administrateur requis.'], 403);
        }

        try {

            $allUsers = $this->users->listUsers();

            $result = [];
            foreach ($allUsers as $usr) {
                $userId = (int)$usr['id'];

                // Calcul de l'espace utilisé par l'utilisateur
                $used = $this->files->totalSizeByUser($userId);

                // Récupérer le quota max de user
                $max = isset($usr['quota_total']) ? (int)$usr['quota_total'] : 0;

                $result[] = [
                    'id' => $userId,
                    'email' => $usr['email'],
                    'used' => $used,
                    'max' => $max,
                    'is_admin' => (bool)$usr['is_admin']
                ];
            }

            return $this->json($response, ['users' => $result], 200);

        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => 'Erreur lors de la récupération des utilisateurs',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /admin/users/{id}/quota
     * Modifie le quota d'un utilisateur (ADMIN uniquement)
     */
    public function updateUserQuota(Request $request, Response $response, array $args): Response
    {
        $targetUserId = (int)($args['id'] ?? 0);

        if ($targetUserId <= 0) {
            return $this->json($response, ['error' => "Id utilisateur invalide"], 400);
        }

        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        if (!isset($user['is_admin']) || !(bool)$user['is_admin']) {
            return $this->json($response, ['error' => 'Accès refusé: administrateur requis.'], 403);
        }

        $data = $request->getParsedBody();
        $newQuota = isset($data['quota']) ? (int)$data['quota'] : null;

        if ($newQuota == null || $newQuota < 0) {
            return $this->json($response, ['error' => 'Quota invalide (doit être >=0)'], 400);
        }

        try {
            $targetUser = $this->users->find($targetUserId);
            if (!$targetUser) {
                return $this->json($response, ['error' => 'Utilisateur introuvable'], 404);
            }

            $usedSpace = $this->files->totalSizeByUser($targetUserId);

            if ($newQuota < $usedSpace) {
                return $this->json($response, [
                    'error' => "Le nouveau quota ne peut pas être inférieur à l'espace utilisé.",
                    'quota_requested' => $newQuota,
                    'space_used' => $usedSpace
                ], 400);
            }

            // Sauvegarder l'ancien quota avant modification
            $oldQuota = (int)($targetUser['quota_total'] ?? 0);

            // Effectuer la mise à jour
            $this->users->updateQuota($targetUserId, $newQuota);

            // Log l'opération de mise à jour du quota
            $this->auditLogger->insert(
                (int)$user['id'],
                'QUOTA_UPDATE',
                'users',
                $targetUserId,
                ['old_quota' => $oldQuota, 'new_quota' => $newQuota]
            );

            return $this->json($response, [
                'message' => "Quota modifié avec succès",
                'user_id' => $targetUserId,
                'new_quota' => $newQuota,
                'used' => $usedSpace
            ], 200);

        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => "Erreur lors de la modification du quota",
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /admin/users/{id}
     * Supprime un utilisateur (ADMIN uniquement)
     * Respecte le RGPD en supprimant toutes les données de l'utilisateur
     */
    public function deleteUser(Request $request, Response $response, array $args): Response
    {
        // Vérification d'authentification d'admin
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        if (!isset($user['is_admin']) || !(bool)$user['is_admin']) {
            return $this->json($response, ['error' => 'Accès refusé: administrateur requis.'], 403);
        }

        $targetUserId = (int)($args['id'] ?? 0);
        if ($targetUserId <= 0) {
            return $this->json($response, ['error' => 'Id utilisateur invalide'], 400);
        }

        // Vérification si user existe
        $targetUser = $this->users->find($targetUserId);
        if (!$targetUser) {
            return $this->json($response, ['error' => 'Utilisateur introuvable'], 404);
        }

        // Empêcher la suppression de son propre compte
        if ((int)$user['id'] === $targetUserId) {
            return $this->json($response, ['error' => 'Vous ne pouvez pas supprimer votre propre compte'], 400);
        }

        try {
            // Lister tous les fichiers physiques à supprimer
            $filesToDelete = [];

            // Récupérer tous les fichiers de l'utilisateur
            $allFiles = $this->files->listFilesByUser($targetUserId);

            foreach ($allFiles as $file) {
                $fileId = (int)$file['id'];

                // Récupérer toutes les versions d'un fichier
                $versions = $this->files->getAllVersions($fileId);

                // Supprimer tous les versions sur le disque
                foreach ($versions as $version) {
                    $storedName = $version['stored_name'] ?? null;
                    if ($storedName) {
                        $path = $this->uploadDir . DIRECTORY_SEPARATOR . $storedName;
                        $filesToDelete[] = $path;
                    }
                }

                // Fichier en clair => ancienne version
                $storedName = $file['stored_name'] ?? null;
                if ($storedName) {
                    $path = $this->uploadDir . DIRECTORY_SEPARATOR . $storedName;
                    if (!in_array($path, $filesToDelete)) {
                        $filesToDelete[] = $path;
                    }
                }
            }

            // Supprimer les fichiers physiques du disque
            $deletedFiles = 0;
            $failedFiles = [];

            foreach ($filesToDelete as $path) {
                if (file_exists($path)) {
                    if (@unlink($path)) {
                        $deletedFiles++;
                    } else {
                        $failedFiles[] = basename($path);
                        error_log("Impossible de supprimer le fichier : $path");
                    }
                }
            }

            // Supprimer les logs de téléchargement
            $this->shares->deleteDownloadLogsByUser($targetUserId);

            // Log l'opération de suppression
            $this->auditLogger->insert(
                (int)$user['id'],
                'USER_DELETE',
                'users',
                $targetUserId,
                [
                    'email'       => $targetUser['email'],
                    'is_admin'    => $targetUser['is_admin'],
                    'quota_total' => $targetUser['quota_total'] ?? 0,
                ]
            );

            // Supprimer user en BDD
            $deleted = $this->users->delete($targetUserId);

            if ($deleted === false) {
                return $this->json($response, ['error' => 'Suppression impossible en BDD'], 500);
            }

            // Retourner un résumé
            $summary = [
                'message' => "Utilisateur supprimé avec succès (BDD)",
                'user_id' => $targetUserId,
                'email' => $targetUser['email'],
                'deleted_files' => $deletedFiles
            ];

            if (!empty($failedFiles)) {
                $summary['warning'] = "Certains fichiers n'ont pas pu être supprimés du disque";
                $summary['failed_files'] = $failedFiles;
            }

            return $this->json($response, $summary, 200);

        } catch (\Exception $e) {
            error_log("Erreur lors de la suppression de l'utilisateur $targetUserId : " . $e->getMessage());
            return $this->json($response, [
                'error' => "Erreur lors de la suppression de l'utilisateur",
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /******************* Functions PRIVATE ***************************************************/

    private function json(Response $response, array $data, int $status): Response
    {
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}