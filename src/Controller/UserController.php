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

        $email = $body['email'] ?? null;
        $password = $body['password'] ?? null;

        // Validation des champs requis
        if (!isset($email) || !isset($password)) {
            $response->getBody()->write(json_encode([
                'error' => 'Email and password are required'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Validation de l'email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response->getBody()->write(json_encode([
                'error' => 'Invalid email format'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Validation du mot de passe (minimum 8 caractères)
        if (strlen($password) < 8) {
            $response->getBody()->write(json_encode([
                'error' => 'Password must be at least 8 characters long'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Vérifier si l'email existe déjà
        if ($this->users->findByEmail($email)) {
            $response->getBody()->write(json_encode([
                'error' => 'Email already exists'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }

        // Créer l'utilisateur
        $userData = [
            'email' => $email,
            'pass_hash' => password_hash($password, PASSWORD_DEFAULT),
            'quota_used' => 0,
            'quota_total' => isset($body['quota_total']) ? (int)$body['quota_total'] : 1073741824, // 1GB par défaut
            'is_admin' => isset($body['is_admin']) ? (bool)$body['is_admin'] : false,
            'created_at' => date('Y-m-d')
        ];
    
        $id = $this->users->create($userData);

        $user = $this->users->findByEmail($email);

        // Génération du JWT
        $payload = [
            'iss' => 'coffre-fort',          // émetteur
            'aud' => 'coffre-fort-users',    // audience
            'iat' => time(),                 // date d’émission
            'exp' => time() + 3600,          // expiration (1h)
            'user_id' => $user['id'],            // identifiant utilisateur
            'email' => $user['email'],
            'is_admin' => $user['is_admin']
        ];

        $jwt = JWT::encode($payload, $this->jwtSecret, 'HS256');
        

        // Réponse
        $response->getBody()->write(json_encode([
            "success" => true,
            "message" => "Register success",
            "redirect" => "/dashboard",
            'jwt' => $jwt
        ], JSON_PRETTY_PRINT));


        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }


    // POST /auth/login - Authentifie un utilisateur et retourne un JWT =>??????? à vérifier!!!!
    public function login(Request $request, Response $response): Response {

    // 1. Récupération données POST (Slim décode automatiquement)
    $data = $request->getParsedBody();
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    if (!$email || !$password) {
        $response->getBody()->write(json_encode([
            "success" => false,
            "error" => "Missing email or password"
        ], JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    // 2. Récupération utilisateur
    // $user = $data->get("users", "*", [ "email" => $email ]);
    $user = $this->users->findByEmail($data['email']);


    if (!$user) {
        $response->getBody()->write(json_encode([
            "success" => false,
            "error" => "User does not exist"
        ], JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }

    // 3. Vérification hash password
    if (!password_verify($password, $user['pass_hash'])) {
        $response->getBody()->write(json_encode([
            'error' => 'Invalid credentials'
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }

    // 4. Succès → stockage session
    // $_SESSION['user_id'] = $user['id'];
    // $_SESSION['email'] = $email;

    // 5. Réponse JSON propre (pas de header() !)

    // Génération du JWT
    $payload = [
        'iss' => 'coffre-fort',          // émetteur
        'aud' => 'coffre-fort-users',    // audience
        'iat' => time(),                 // date d’émission
        'exp' => time() + 3600,          // expiration (1h)
        'user_id' => $user['id'],            // identifiant utilisateur
        'email' => $user['email'],
        'is_admin' => $user['is_admin']
    ];

    $jwt = JWT::encode($payload, $this->jwtSecret, 'HS256');
    

    // Réponse
    $response->getBody()->write(json_encode([
        "success" => true,
        "message" => "Login success",
        "redirect" => "/dashboard",
        'jwt' => $jwt
    ], JSON_PRETTY_PRINT));


    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
}
// public function login(Request $request, Response $response): Response 
// {
//     // 1. Récupération du header Authorization
//     $authHeader = $request->getHeaderLine('Authorization');

//     if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
//         return $this->jsonError($response, "Missing or invalid Authorization header", 401);
//     }

//     $jwt = $matches[1];

//     // 2. Décodage du token
//     try {
//         $decoded = JWT::decode($jwt, new Key($this->jwtSecret, 'HS256'));
//     } catch (\Exception $e) {
//         return $this->jsonError($response, "Invalid token: " . $e->getMessage(), 401);
//     }

//     // 3. Succès → dashboard
//     $payload = (array)$decoded;

//     $response->getBody()->write(json_encode([
//         "success" => true,
//         "message" => "Token valid",
//         "user" => $payload,
//         "redirect" => "/dashboard"
//     ], JSON_PRETTY_PRINT));

//     return $response->withHeader('Content-Type', 'application/json');
// }


// Petite méthode utilitaire
private function jsonError(Response $response, string $msg, int $code): Response
{
    $response->getBody()->write(json_encode([
        "success" => false,
        "error" => $msg
    ], JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($code);
}


    // ROUTE DASHBOARD (protégée)
public function dashboard(Request $request, Response $response)
{
    // 1) Essayer de récupérer via Header
    $authHeader = $request->getHeaderLine('Authorization');
    $jwt = null;

    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $jwt = $matches[1];
    }

    // 2) Si pas trouvé → essayer GET
    if (!$jwt) {
        $params = $request->getQueryParams();
        $jwt = $params['jwt'] ?? null;
    }

    // 3) Si toujours rien → erreur
    if (!$jwt) {
        return $response->withStatus(401);
    }

    // 4) Décodage JWT
    try {
        $token = JWT::decode($jwt, new Key($this->jwtSecret, 'HS256'));
    } catch (\Exception $e) {
        return $response->withStatus(403);
    }

    // 5) Dashboard HTML
    $html = "
<!doctype html>
<html lang='fr'>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>CryptoVault</title>

    <!-- Bootstrap -->
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css'>

    <!-- Custom main -->
    <link rel='stylesheet' href='main.css'>
</head>

<body>

<header class='py-3 border-bottom bg-white'>
    <nav class='container d-flex justify-content-center'>
        <img src='img/logo.jpeg' width='80' class='logo' alt='CryptoVault logo'>
    </nav>
</header>

<!-- Hero -->
<section class='hero text-center py-5'>
    <h1 class='fw-bold'>Welcome to CryptoVault</h1>
    <p class='text-muted'>Your secure file storage solution</p>
</section>

<!-- Features -->
<section class='container my-5'>
    <div class='row g-4'>
        <div class='col-md-4'>
            <div class='cv-card text-center p-4'>
                <i class='bi bi-folder-fill feature-icon'></i>
                <h5 class='mt-3'>Organize Your Files</h5>
                <p class='text-muted'>Create folders and manage your files efficiently.</p>
            </div>
        </div>

        <div class='col-md-4'>
            <div class='cv-card text-center p-4'>
                <i class='bi bi-shield-lock-fill feature-icon'></i>
                <h5 class='mt-3'>Secure Storage</h5>
                <p class='text-muted'>Your files are encrypted and stored securely.</p>
            </div>
        </div>

        <div class='col-md-4'>
            <div class='cv-card text-center p-4'>
                <i class='bi bi-cloud-arrow-up-fill feature-icon'></i>
                <h5 class='mt-3'>Easy Uploads</h5>
                <p class='text-muted'>Upload files quickly and access them anywhere.</p>
            </div>
        </div>
    </div>
</section>

<!-- Table + Search -->
<section class='container my-5'>
    
    <div class='cv-table-header d-flex align-items-center gap-3 flex-wrap mb-3'>
        
        <!-- Search -->
        <div class='cv-search flex-grow-1'>
            <i class='bi bi-search'></i>
            <input type='text' placeholder='Rechercher un fichier ou dossier'>
        </div>

        <!-- Sort -->
        <div class='cv-select'>
            <select>
                <option selected>Trier</option>
                <option value='1'>Les plus récents</option>
                <option value='2'>Les plus anciens</option>
                <option value='3'>Modifiés récemment</option>
            </select>
            <i class='bi bi-chevron-down'></i>
        </div>

        <!-- Buttons -->
        <button class='btn btn-outline-dark'>
            <i class='bi bi-share'></i> Partager
        </button>

        <button class='btn btn-dark'>
            <i class='bi bi-cloud-arrow-up'></i> Téléverser
        </button>

    </div>

    <!-- Progress -->
    <div class='progress mb-4'>
        <div class='progress-bar progress-bar-striped progress-bar-animated bg-danger' style='width:75%''>540 MB</div>
    </div>

    <!-- Table -->
    <div class='table-responsive'>
        <table class='table table-hover align-middle'>
            <thead class='table-light'>
                <tr>
                    <th>Nom fichier/dossier</th>
                    <th>Taille</th>
                    <th>Compteur d’usages</th>
                    <th>Expiration</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>File Upload</td>
                    <td>Securely upload your files to the vault with encryption.</td>
                    <td>Securely upload your files to the vault with encryption.</td>
                    <td>Securely upload your files to the vault with encryption.</td>
                </tr>
                <tr>
                    <td>Folder Management</td>
                    <td>Create, delete, and organize folders for better file management.</td>
                    <td>Create, delete, and organize folders for better file management.</td>
                    <td>Create, delete, and organize folders for better file management.</td>
                </tr>
                <tr>
                    <td>User Authentication</td>
                    <td>Register and log in to access your secure file vault.</td>
                    <td>Register and log in to access your secure file vault.</td>
                    <td>Register and log in to access your secure file vault.</td>
                </tr>
            </tbody>
        </table>
    </div>

</section>

<!-- Footer -->
<footer class='text-center py-5 bg-light mt-5'>
    <h2>Your Files</h2>
    <p class='text-muted'>Manage and access your files securely</p>
</footer>

<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>

    ";

    
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
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