<?php
namespace App\Security;

use Medoo\Medoo;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Model\UserRepository;

class AuthService
{
    private Medoo $db;
    private string $jwtSecret;

    public function __construct(Medoo $db, string $jwtSecret)
    {
        $this->db = $db;
        $this->jwtSecret = $jwtSecret;
    }

    //authentifier user du token récupéré
    public function getAuthenticatedUserFromToken(Request $request): array
    {
        //Récupérer le header Authorization
        $authHeader = $request->getHeaderLine('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new \Exception('Token manquant', 401);
        }

        $jwt = substr($authHeader, 7);

        //Vérifier le secret JWT
        if (empty($this->jwtSecret)) {
            throw new \Exception('JWT secret non configuré sur le serveur.', 500);
        }

        //Décoder le token JWT
        try {
            $decoded = JWT::decode($jwt, new Key($this->jwtSecret, 'HS256'));
        } catch (\Exception $e) {
            throw new \Exception('Token invalide: ' . $e->getMessage(), 401);
        }

        $email = $decoded->email ?? null;
        if (!$email) {
            throw new \Exception('Email manquant dans le token', 401);
        }

        //Récupérer l'utilisateur depuis la base de données
        $userRepo = new UserRepository($this->db);
        $user = $userRepo->findByEmail($email);
        if (!$user) {
            throw new \Exception('Utilisateur introuvable', 404);
        }

        return $user; // ex: ['id' => ..., 'email' => ...]
    }
}
