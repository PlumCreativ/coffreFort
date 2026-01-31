<?php
use Slim\Factory\AppFactory;
use Medoo\Medoo;
use App\Controller\FileController;
use App\Controller\UserController;
use App\Controller\ShareController;
use Slim\Psr7\UploadedFile;

require __DIR__ . '/../vendor/autoload.php';

$database = new Medoo([
    'type'      => 'mysql',
    'host'      => 'mysql',
    'database'  => 'coffreFort',
    'username'  => 'coffreFort',
    'password'  => '5678_Juklau+147!',
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
$shareController = new ShareController($database);

// routes pour les fichiers
$app->get('/files', [$fileController, 'list']);                                     //Lister les fichiers par dossier = ok
$app->get('/filesPaginated', [$fileController, 'listPaginated']);
$app->get('/files/{id}', [$fileController, 'show']);                                //Détails d'un fichier avec versions = ok
$app->get('/files/{id}/download', [$fileController, 'download']);                   //=> (mainController) chiffré OK  //téléchargement direct (propriètaire)(version courante) = ok
// GET /files?folder={id}  => à vérifier comment je fais 

$app->post('/files', [$fileController, 'upload']);  //=> (mainController) chiffré OK    // Uploader un fichier (crée la version 1 chiffrée) =  ok
$app->delete('/files/{id}', [$fileController, 'delete']);                               //Supprimer un fichier (logique ou totale selon politique) = ok
$app->put('/files/{id}', [$fileController, 'renameFile']);  //renommage
$app->post('/files/{id}/versions', [$fileController, 'uploadNewVersion']); //=> (java:FileDetailsController) déchiffré OK   //Ajouter une nouvelle version au fichier = à vérifier
$app->get('/files/{id}/versions', [$fileController, 'listVersions']);            //liste complète paginée des versions = OK
$app->get('/files/{id}/versions/{version}/download', [$fileController, 'downloadVersion']); //=> (FileDetailsController) déchiffré OK //téléchargement version (propriètaire)

//Stats / quota / activité
$app->get('/stats', [$fileController, 'stats']);
$app->put('/quota', [$fileController, 'setQuota']);                          //pour modifier le quota
$app->get('/me/quota', [$fileController, 'meQuota']);                       //Récupérer le quota de l'utilisateur = ok
$app->get('/me/activity', [$fileController, 'meActivity']);                 //Derniers événements de l'utilisateur = à vérifier si je l'utilise!????!!! openapi 540

// routes pour les folders
$app->get('/folders', [$fileController, 'listFolders']);                    //Lister les dossiers de l'utilisateur courant (racine par défaut) = ok
$app->post('/folders', [$fileController, 'createFolder']);                  //Créer un dossier = modifier!! => à mettre dedans  le vérif propriétaire
$app->delete('/folders/{id}', [$fileController, 'deleteFolder']);           //Supprimer un dossier = modifier => à mettre dedans  le vérif propriétaire
$app->put('/folders/{id}', [$fileController, 'renameFolder']);              //renommage

// routes pour les users
$app->get('/users', [$userController, 'list']);
$app->get('/users/{id}', [$userController, 'show']);
$app->delete('/users/{id}', [$userController, 'delete']);
$app->post('/auth/register', [$userController, 'register']);                //Créer un compte utilisateur = ok
$app->post('/auth/login', [$userController, 'login']);                      //Authentifier un utilisateur et obtenir un JWT = ok
$app->post('/logout', [$userController,'logout']);

//route pour les shares
$app->post('/shares', [$shareController, 'createShare']);                   //Créer un lien de partage = à faire pour les folders + si je remplis pas maxuses ou date expiration
$app->get('/shares', [$shareController, 'listShares']);                     //Lister les liens de partage de l'utilisateur = ok
$app->post('/shares/{id}/revoke', [$shareController, 'revokeShare']);       //Révoquer immédiatement un lien de partage = ok
$app->get('/s/{token}', [$shareController, 'publicShare']);                 // Infos publiques associées à un token de partage (page publique) = à faire pour les folders
$app->get('/s/{token}/download', [$shareController, 'publicDownload']);     //=> (navigateur) déchiffré OK   //télécharger le fichier = ok
// $app->get('/s/{token}/download?v=2', [$shareController, '']);            //télécharger une version spécifique
//$app ->post('/s/{token}/download', [$shareController, ??????????]);        //Télécharger via un lien public signé = à faire!!!! openapi 489  (Flux binaire via lien public)

$app->get('/s/{token}/versions', [$shareController, 'publicShareVersions']); //liste les versions disponibles (publique)

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
            'PUT /files/{id}',
            'POST /files/{id}/versions',
            'GET /files/{id}/versions',
            'GET /s/{token}/versions',
            'GET /files/{id}/versions/{version}/download',

            'GET /stats',
            'PUT /quota',
            'GET /me/quota',
            'GET /me/activity',

            'GET /users',
            'GET /users/{id}',
            'DELETE /users/{id}',
            'POST /auth/register',
            'POST /auth/login',
            'POST /logout',
            
            'GET /folders',
            'POST /folders',
            'DELETE /folders/{id}',
            'PUT /folders/{id}',

            'POST /shares',
            'GET /shares',
            'POST /shares/{id}/revoke',
            'GET /s/{token}',
            'GET /s/{token}/download',

        ]
    ], JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// Route de debug pour vérifier PHP
$app->get('/debug-upload', function ($request, $response) {
    $info = [
        'file_uploads'          => ini_get('file_uploads'),
        'upload_max_filesize'   => ini_get('upload_max_filesize'),
        'post_max_size'         => ini_get('post_max_size'),
        'max_file_uploads'      => ini_get('max_file_uploads'),
        'upload_tmp_dir'        => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
    ];
    $response->getBody()->write(json_encode($info, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();

?>
