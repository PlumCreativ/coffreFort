<?php
use Slim\Factory\AppFactory;
use Medoo\Medoo;
use App\Controller\FileController;
use App\Controller\UserController;
use Slim\Psr7\Request;
use Slim\Psr7\UploadedFile;

require __DIR__ . '/../vendor/autoload.php';

$database = new Medoo([
    'type' => 'mysql',
    'host' => 'mysql',
    'database' => 'coffreFort',
    'username' => 'coffreFort',
    'password' => '5678_Juklau+147!',
]);

$app = AppFactory::create();

$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);
$app->addBodyParsingMiddleware();

// Auto‑détection du base path quand l'app est servie depuis un sous‑dossier
// (ex.: /coffre-fort ou /coffre-fort/public)
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = rtrim(str_ireplace('index.php', '', $scriptName), '/');
if ($basePath !== '') {
    $app->setBasePath($basePath);
}

$fileController = new FileController($database);
$userController = new UserController($database);

// routes pour les fichiers
$app->get('/files', [$fileController, 'list']);
$app->get('/filesPaginated', [$fileController, 'listPaginated']);

$app->get('/files/{id}', [$fileController, 'show']);
$app->get('/files/{id}/download', [$fileController, 'download']);
$app->post('/files', [$fileController, 'upload']);
$app->delete('/files/{id}', [$fileController, 'delete']);
$app->get('/stats', [$fileController, 'stats']);
$app->put('/quota', [$fileController, 'setQuota']); //pour modifier le quota
$app->get('/me/quota', [$fileController, 'meQuota']);
$app->get('/me/activity', [$fileController, 'meActivity']);

// routes pour les folders
$app->get('/folders', [$fileController, 'listFolders']);
$app->post('/folders', [$fileController, 'createFolder']);
$app->delete('/folders/{id}', [$fileController, 'deleteFolder']);

// routes pour les users
$app->get('/users', [$userController, 'list']);
$app->get('/users/{id}', [$userController, 'show']);
$app->delete('/users/{id}', [$userController, 'delete']);

// route d'authentification et login
$app->post('/auth/register', [$userController, 'register']);
// $app->post('/auth/login', [$userController, 'login']);
$app->post('/logout', [$userController,'logout']);
// ROUTE LOGIN
$app->post('/auth/login', [$userController, 'login']);

// ROUTE DASHBOARD (protégée)
$app->get('/dashboard', [$userController,'dashboard']);

$app->get('/main', function ($request, $response){
    $response->getBody()->write('
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CryptoVault</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom main -->
    <link rel="stylesheet" href="main.css">
</head>

<body>

<header class="py-3 border-bottom bg-white">
    <nav class="container d-flex justify-content-center">
        <img src="img/logo.jpeg" width="80" class="logo" alt="CryptoVault logo">
    </nav>
</header>

<!-- Hero -->
<section class="hero text-center py-5">
    <h1 class="fw-bold">Welcome to CryptoVault</h1>
    <p class="text-muted">Your secure file storage solution</p>
</section>

<!-- Features -->
<section class="container my-5">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="cv-card text-center p-4">
                <i class="bi bi-folder-fill feature-icon"></i>
                <h5 class="mt-3">Organize Your Files</h5>
                <p class="text-muted">Create folders and manage your files efficiently.</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="cv-card text-center p-4">
                <i class="bi bi-shield-lock-fill feature-icon"></i>
                <h5 class="mt-3">Secure Storage</h5>
                <p class="text-muted">Your files are encrypted and stored securely.</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="cv-card text-center p-4">
                <i class="bi bi-cloud-arrow-up-fill feature-icon"></i>
                <h5 class="mt-3">Easy Uploads</h5>
                <p class="text-muted">Upload files quickly and access them anywhere.</p>
            </div>
        </div>
    </div>
</section>

<!-- Table + Search -->
<section class="container my-5">
    
    <div class="cv-table-header d-flex align-items-center gap-3 flex-wrap mb-3">
        
        <!-- Search -->
        <div class="cv-search flex-grow-1">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Rechercher un fichier ou dossier">
        </div>

        <!-- Sort -->
        <div class="cv-select">
            <select>
                <option selected>Trier</option>
                <option value="1">Les plus récents</option>
                <option value="2">Les plus anciens</option>
                <option value="3">Modifiés récemment</option>
            </select>
            <i class="bi bi-chevron-down"></i>
        </div>

        <!-- Buttons -->
        <button class="btn btn-outline-dark">
            <i class="bi bi-share"></i> Partager
        </button>

        <button class="btn btn-dark">
            <i class="bi bi-cloud-arrow-up"></i> Téléverser
        </button>

    </div>

    <!-- Progress -->
    <div class="progress mb-4">
        <div class="progress-bar progress-bar-striped progress-bar-animated bg-danger" style="width:75%">540 MB</div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
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
<footer class="text-center py-5 bg-light mt-5">
    <h2>Your Files</h2>
    <p class="text-muted">Manage and access your files securely</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

    ');
});

// Route d'accueil (GET /)
$app->get('/', function ($request, $response) {
    $response->getBody()->write(json_encode([
        'message' => 'File Vault API',
        'endpoints' => [
            'GET /files',
            'GET /filesPaginated',
            'GET /files/{id}',
            'GET /files/{id}/download',
            'POST /files',
            'DELETE /files/{id}',
            'GET /stats',
            'PUT /quota',
            'GET /me/quota',
            'GET /me/activity',

            'GET /users',
            'GET /users/{id}',
            'DELETE /users/{id}',

            'POST /auth/register',
            'POST /auth/login',
            
            'GET /folders',
            'POST /folders',
            'DELETE /folders/{id}',
        ]
    ], JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// Route de debug pour vérifier PHP
$app->get('/debug-upload', function ($request, $response) {
    $info = [
        'file_uploads' => ini_get('file_uploads'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_file_uploads' => ini_get('max_file_uploads'),
        'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
    ];
    $response->getBody()->write(json_encode($info, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
