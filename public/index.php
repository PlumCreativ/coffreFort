<?php
use Slim\Factory\AppFactory;
use Medoo\Medoo;
use App\Controller\FileController;
use App\Controller\UserController;
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
$app->post('/auth/login', [$userController, 'login']);

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
