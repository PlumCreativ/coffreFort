<?php
// coffre-fort/src/Controller/UserController.php

namespace App\Controller;

use App\Model\UserRepository;
use App\Security\AuthService;
use Medoo\Medoo;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class UserController
{
    private UserRepository $users;
    private string $jwtSecret;
    private AuthService $auth;

    public function __construct(Medoo $db)
    {
        $this->users = new UserRepository($db);
        //$this->jwtSecret = getenv('JWT_SECRET') ?: 'default-secret'; //=> à mettre dans env!!!

        // Init du secret JWT (env ou param)
        $this->jwtSecret = $jwtSecret ?? ($_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?? '');
        $this->auth = new AuthService($db, $this->jwtSecret);

        if ($this->jwtSecret === '') {
            // possible de faire throw ici, mais je préfère debug clair
            error_log("JWT_SECRET manquant dans les variables d'environnement.");
        }
    }

    private function json(Response $response, array $data, int $status): Response{
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    

    // POST /auth/register - Inscription d'un nouvel utilisateur ********************************************************** OK
    public function register(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        // Validation des champs requis
        if (!isset($body['email']) || !isset($body['password'])) {
            return $this->json($response, ['error' => 'Email et un mot de passe sont requis'], 400);
        }

        // Validation de l'email
        if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json($response, ['error' => "Format d'e-mail invalide"], 400);
        }

        // Validation du mot de passe (minimum 8 caractères)
        if (strlen($body['password']) < 8) {
            return $this->json($response, ['error' => 'Le mot de passe doit comporter au moins 8 caractères'], 400);
        }

        // Vérifier si l'email existe déjà
        if ($this->users->findByEmail($body['email'])) {
            return $this->json($response, ['error' => 'Email existe deja'], 409);
        }

        $isFirstUser = ($this->users->countUsers() === 0);
        $isAdmin = $isFirstUser; //=> true pour le premier utilisateur

        // Créer l'utilisateur
        $userData = [
            'email'         => $body['email'],
            'pass_hash'     => password_hash($body['password'], PASSWORD_DEFAULT),
            'quota_used'    => 0,
            'quota_total'   => isset($body['quota_total']) ? (int)$body['quota_total'] : 1073741824, // 1GB par défaut
            // 'quota_total'   => isset($body['quota_total']) ? (int)$body['quota_total'] : 31457280, // 30 Mo par défaut pour tests
            'is_admin'      => $isAdmin,
            'created_at'    => date('Y-m-d')
        ];

        $id = $this->users->create($userData);

        $response->getBody()->write(json_encode([
            'message'   => 'Utilisateur créé',
            'id'        => $id,
            'email'     => $body['email'],
            'is_admin'  => $isAdmin
        ], JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }


    // POST /auth/login - Authentifie un utilisateur et retourne un JWT ***************************************************** OK
    public function login(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        // Vérification des champs requis
        if (!isset($body['email']) || !isset($body['password'])) {
            return $this->json($response, ['error' => 'Email et un mot de passe sont requis'], 400);
        }

        // Validation basique (anti XSS)
        $email = filter_var(trim($body['email']), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            return $this->json($response, ['error' => "Format d'email invalide"], 400);
        }

        // Recherche de l'utilisateur par email
        $user = $this->users->findByEmail($body['email']);
        if (!$user) {
            return $this->json($response, ['error' => 'Utilisateur avec cet email n\'existe pas'], 401);
        }

        // Vérification du mot de passe
        if (!password_verify($body['password'], $user['pass_hash'])) {
            return $this->json($response, ['error' => 'Mot de passe incorrect'], 401);
        }

        //call pour le procédure stocké de BDD à mettre ici pour faire des logs pour toutes les connexions???

        // Génération du JWT
        $payload = [
            'iss'       => 'coffre-fort',          // émetteur
            'aud'       => 'coffre-fort-users',    // audience
            'iat'       => time(),                 // date d’émission
            'exp'       => time() + 3600,          // expiration (1h)
            //'exp'       => time() + 300,          // expiration (5min pour tests)
            'user_id'   => $user['id'],            // identifiant utilisateur
            'email'     => $user['email'],
            'is_admin'  => $user['is_admin']
        ];

        // format de token: opaque signé (HMAC SHA‑256 sur payload)
        $jwt = JWT::encode($payload, $this->jwtSecret, 'HS256');

        // Réponse
        return $this->json($response, ['jwt' => $jwt], 200);
    }

    // GET /users - Liste tous les utilisateurs que pour admin ********************************************************************** OK
    public function list(Request $request, Response $response): Response
    {
        //vérif authentification d'admin
        try {
            $user = $this->auth->getAuthenticatedUserFromToken($request);
            $isAdmin = (int)$user['is_admin'];

            if($isAdmin === 0){
                return $this->json($response, ['error' => "Accès interdit"], 403);
            }

        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        $data = $this->users->listUsers();

        return $this->json($response, $data, 200);
    }


    // GET /users/{id} - Affiche un utilisateur que pour admin ********************************************************************** OK
    public function show(Request $request, Response $response, array $args): Response
    {
        //vérif authentification d'admin
        try {
            $authUser = $this->auth->getAuthenticatedUserFromToken($request);
            $isAdmin = (int)$authUser['is_admin'];

            if($isAdmin === 0){
                return $this->json($response, ['error' => "Accès interdit"], 403);
            }

        } catch (\Exception $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }

        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->json($response, ['error' => 'ID invalide'], 400);
        }

        $targetUser = $this->users->find($id);

        if (!$targetUser) {
            return $this->json($response, ['error' => 'Utilisateur introuvable'], 404);
        }

        return $this->json($response, $targetUser, 200);
    }


    // ROUTE DASHBOARD (protégée)
    public function dashboard(Request $request, Response $response)
    {
        // Essayer de récupérer via Header
        $authHeader = $request->getHeaderLine('Authorization');
        $jwt = null;

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $jwt = $matches[1];
        }

        // Si pas trouvé → essayer GET
        if (!$jwt) {
            $params = $request->getQueryParams();
            $jwt = $params['jwt'] ?? null;
        }

        // Si toujours rien → erreur
        if (!$jwt) {
            return $response->withStatus(401);
        }

        // Décodage JWT
        try {
            $jwt = JWT::decode($jwt, new Key($this->jwtSecret, 'HS256'));
        } catch (\Exception $e) {
            return $response->withStatus(403);
        }

        // OK → retourne les données nécessaires
        $response->getBody()->write(json_encode([
            "success"   => true,
            "message"   => "Bienvenue sur la page Main",
            "email"     => $jwt->email
        ]));
        
        return $response->withHeader("Content-Type", "application/json");
    }

}

?>
