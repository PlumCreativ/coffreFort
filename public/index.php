<?php
use Slim\Factory\AppFactory;
use Medoo\Medoo;
use App\Controller\FileController;
use App\Controller\UserController;
use App\Controller\ShareController;
use App\Controller\AdminController;
use Slim\Psr7\UploadedFile;

require __DIR__ . '/../vendor/autoload.php';


$database = new Medoo([
    // 'type'      => 'mysql',
    // 'host'      => 'mysql',
    // 'database'  => 'coffreFort',
    // 'username'  => 'coffreFort',
    // 'password'  => '5678_Juklau+147!',

    'type'      => 'mysql',
    'host'      => getenv('DB_HOST') ?: 'mysql',                //Utiliser getenv()
    'database'  => getenv('DB_NAME') ?: 'coffreFort',
    'username'  => getenv('DB_USER') ?: 'root',
    'password'  => getenv('DB_PASSWORD') ?: '',
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

$adminController = new AdminController($database);
$fileController = new FileController($database);
$userController = new UserController($database);
$shareController = new ShareController($database);

//JO => signifie requête dans Postman OK

//routes pour les admin
$app->get('/admin/users/quotas', [$adminController, 'listUsersWithQuota']);                 //JO         //Liste tous les utilisateurs avec leurs quotas QUE Admin
$app->put('/admin/users/{id}/quota', [$adminController, 'updateUserQuota']);                //JO         //modifier le quota d'un utilisateur QUE ADMIN
$app->delete('/admin/users/{id}', [$adminController, 'deleteUser']);   //à implementer      //JO         //Supprimer un utilisateur et TOUTES ses données (fichiers, dossiers, partages) QUE ADMIN 

// routes pour les fichiers
$app->get('/files', [$fileController, 'list']);                                             //JO         //Lister les fichiers = ok
$app->get('/files?folder={id}', [$fileController, 'list']);                                 //JO         //Lister les fichiers par dossier = ok
$app->get('/filesPaginated', [$fileController, 'listPaginated']);//=> je n'utilise pas cette route, c'est juste pour montrer un exemple de pagination simple sans filtrage par user ou folder (pas d'auth, pas de vérif de propriétaire) *OK
$app->get('/files/{id}', [$fileController, 'show']);                                        //JO         //Détails d'un fichier avec le dernier version = ok
$app->get('/files/{id}/download', [$fileController, 'download']);                           //JO         //(mainController) chiffré OK  //téléchargement direct (propriètaire)(version courante) = ok

$app->post('/files', [$fileController, 'upload']);                                          //JO         //(mainController) chiffré OK    // Uploader un fichier (crée la version 1 chiffrée) =  ok
$app->delete('/files/{id}', [$fileController, 'delete']);                                   //JO         //Supprime un fichier et TOUTES ses versions = ok
$app->put('/files/{id}', [$fileController, 'renameFile']);                                  //JO         //renommage
$app->post('/files/{id}/versions', [$fileController, 'uploadNewVersion']);                  //JO         //(java:FileDetailsController) déchiffré OK   //Ajouter une nouvelle version au fichier = à vérifier
$app->get('/files/{id}/versions', [$fileController, 'listVersions']);                       //JO         //liste complète paginée des versions = OK
$app->delete('/files/{file_id}/versions/{id}', [$fileController, 'deleteVersion']);         //JO         //Supprime une version d'un fichier
$app->get('/files/{id}/versions/{version}/download', [$fileController, 'downloadVersion']); //JO         //(FileDetailsController) déchiffré OK //téléchargement version (propriètaire)

//Stats / quota / activité
$app->get('/stats', [$fileController, 'stats']);                                            //JO
$app->put('/quota', [$fileController, 'setQuota']);                                         //JO         //pour modifier le quota de user connecté
$app->get('/me/quota', [$fileController, 'meQuota']);                                       //JO         //Récupérer le quota de user connecté= ok

$app->get('/me/activity', [$fileController, 'meActivity']);     //à implementer             //JO         //Derniers événements de user = à vérifier si je l'utilise!????!!! openapi 540

// routes pour les folders
$app->get('/folders', [$fileController, 'listFolders']);                                    //JO         //Lister les dossiers de user courant (racine par défaut) = ok
$app->post('/folders', [$fileController, 'createFolder']);                                  //JO         //Créer un dossier = modifier!! = ok
$app->delete('/folders/{id}', [$fileController, 'deleteFolder']);                           //JO         //Supprimer un dossier avec des contraintes = ok
$app->put('/folders/{id}', [$fileController, 'renameFolder']);                              //JO         //renommage = ok

// routes pour les users
$app->get('/users', [$userController, 'list']);                                             //JO
$app->get('/users/{id}', [$userController, 'show']);                                        //JO

$app->post('/auth/register', [$userController, 'register']);                                //JO         //Créer un compte utilisateur = ok
$app->post('/auth/login', [$userController, 'login']);                                      //JO         //Authentifier un utilisateur et obtenir un JWT = ok
$app->post('/logout', [$userController,'logout']); // il n'y a pas

//route pour les shares
$app->post('/shares', [$shareController, 'createShare']);                                   //JO         //Créer un lien de partage = à faire pour les folders + si je remplis pas maxuses ou date expiration
$app->get('/shares', [$shareController, 'listShares']);                                     //JO         //Liste des partages filtrables, triés et paginés = ok
$app->get('/shares/{id}', [$shareController, 'showShare']);                                 //JO         //Détails d'un partage (pour le propriétaire)
$app->delete('/shares/{id}', [$shareController, 'deleteShare']);                            //JO         //supprimer le lien de partage
$app->patch('/shares/{id}/revoke', [$shareController, 'revokeShare']);                      //JO         //Révoquer immédiatement un lien de partage = ok

$app->get('/s/{token}', [$shareController, 'publicShare']);                                 //JO file    //Infos publiques associées à un token de partage (page publique) -file
                                                                                            //JO folder  //Infos publiques associées à un token de partage (page publique) -folder
$app->get('/s/{token}/versions', [$shareController, 'publicShareVersions']);                //JO         //liste les versions disponibles (publique)
$app->get('/s/{token}/download', [$shareController, 'publicDownload']);                     //JO file    //(navigateur) déchiffré OK  //télécharger le fichier = ok
                                                                                            //JO folder  //(navigateur) déchiffré OK  //télécharger le folder en ZIP = ok


// $app->get('/s/{token}/download?v=2', [$shareController, '']);            //télécharger une version spécifique
//$app ->post('/s/{token}/download', [$shareController, ??????????]);       //Télécharger via un lien public signé = à faire!!!??? openapi 489  (Flux binaire via lien public)


// Route d'accueil (GET /)
$app->get('/', function ($request, $response) {
    $response->getBody()->write(json_encode([
        'message' => 'File Vault API',
        'endpoints' => [
            'GET /admin/users/quotas',
            'PUT /admin/users/{id}/quota',
            'DELETE /admin/users/{id}',

            'GET /files',
            'GET /files?folder={id}',
           
            'GET /files/{id}',
            'GET /files/{id}/download',

            'POST /files',
            'DELETE /files/{id}',
            'PUT /files/{id}',
            'POST /files/{id}/versions',
            'GET /files/{id}/versions',
            'DELETE/files/{file_id}/versions/{id}',

            'GET /s/{token}/versions',
            'GET /files/{id}/versions/{version}/download',

            'GET /stats',
            'PUT /quota',
            'GET /me/quota',
            'GET /me/activity',

            'GET /users',
            'GET /users/{id}',
            //'DELETE /users/{id}', que admin!
            'POST /auth/register',
            'POST /auth/login',
            'POST /logout',
            
            'GET /folders',
            'POST /folders',
            'DELETE /folders/{id}',
            'PUT /folders/{id}',

            'POST /shares',
            'GET /shares',
            'GET /shares/{id}',
            'DELETE /shares/{id}',
            'POST /shares/{id}/revoke',

            'GET /s/{token}',
            'GET /s/{token}/download',
            'GET /s/{token}/versions',

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
