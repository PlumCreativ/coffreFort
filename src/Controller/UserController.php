<?php
// coffre-fort/src/Controller/UserController.php

namespace App\Controller;

use App\Model\UserRepository;
use Medoo\Medoo;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class UserController
{
    private UserRepository $users;
    private string $jwtSecret;

    public function __construct(Medoo $db)
    {
        $this->users = new UserRepository($db);
        $this->jwtSecret = getenv('JWT_SECRET') ?: 'default-secret'; //=> à mettre dans env!!!
    }


    // POST /auth/register - Inscription d'un nouvel utilisateur
    public function register(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        // Validation des champs requis
        if (!isset($body['email']) || !isset($body['password'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Email and password are required'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Validation de l'email
        if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            $response->getBody()->write(json_encode([
                'error' => 'Invalid email format'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Validation du mot de passe (minimum 8 caractères)
        if (strlen($body['password']) < 8) {
            $response->getBody()->write(json_encode([
                'error' => 'Password must be at least 8 characters long'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Vérifier si l'email existe déjà
        if ($this->users->findByEmail($body['email'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Email already exists'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }

        // Créer l'utilisateur
        $userData = [
            'email' => $body['email'],
            'pass_hash' => password_hash($body['password'], PASSWORD_DEFAULT),
            'quota_used' => 0,
            'quota_total' => isset($body['quota_total']) ? (int)$body['quota_total'] : 1073741824, // 1GB par défaut
            'is_admin' => isset($body['is_admin']) ? (bool)$body['is_admin'] : false,
            'created_at' => date('Y-m-d')
        ];

        $id = $this->users->create($userData);

        $response->getBody()->write(json_encode([
            'message' => 'User created successfully',
            'id' => $id,
            'email' => $body['email']
        ], JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }


    // POST /auth/login - Authentifie un utilisateur et retourne un JWT =>??????? à vérifier!!!!
    public function login(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        // Vérification des champs requis
        if (!isset($body['email']) || !isset($body['password'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Email and password are required'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Recherche de l'utilisateur par email
        $user = $this->users->findByEmail($body['email']);
        if (!$user) {
            $response->getBody()->write(json_encode([
                'error' => 'Invalid credentials'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // Vérification du mot de passe
        if (!password_verify($body['password'], $user['pass_hash'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Invalid credentials'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // Génération du JWT
        $payload = [
            'iss' => 'coffre-fort',          // émetteur
            'aud' => 'coffre-fort-users',    // audience
            'iat' => time(),                 // date d’émission
            'exp' => time() + 3600,          // expiration (1h)
            'sub' => $user['id'],            // identifiant utilisateur
            'email' => $user['email'],
            'is_admin' => $user['is_admin']
        ];

        $jwt = JWT::encode($payload, $this->jwtSecret, 'HS256');

        // Réponse
        $response->getBody()->write(json_encode([
            'jwt' => $jwt
        ], JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }


    // GET /users - Liste tous les utilisateurs
    public function list(Request $request, Response $response): Response
    {
        $data = $this->users->listUsers();

        $payload = json_encode($data, JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    // GET /users/{id} - Affiche un utilisateur
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $user = $this->users->find($id);

        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($user, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }


    // DELETE /users/{id} - Supprime un utilisateur
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $user = $this->users->find($id);

        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $this->users->delete($id);

        $response->getBody()->write(json_encode(['message' => 'User deleted successfully']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}

?>