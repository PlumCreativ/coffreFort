<?php
use Medoo\Medoo;
use Slim\Factory\AppFactory;
use App\Controller\FileController;
use App\Controller\UserController;
use Slim\Psr7\UploadedFile;
require __DIR__ . '/../vendor/autoload.php';

$db = new Medoo([
    'type' => 'mysql',
    'host' => 'mysql',
    'database' => 'coffreFort',
    'username' => 'coffreFort',
    'password' => '5678_Juklau+147!',
]);


// Récupération des données du formulaire
$userId = $_POST['email'];
$password = $_POST['pass_hash'];

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);
$app->addBodyParsingMiddleware();

// Auto‑détection du base path quand l'app est servie depuis un sous‑dossier
// (ex.: /coffre-fort ou /coffre-fort/public)
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = rtrim(str_ireplace('login.php', '', $scriptName), '/');
if ($basePath !== '') {
    $app->setBasePath($basePath);
}

$userController = new UserController($db);

if( isset( $userId ) && isset( $password ) ) {
    $passHash =  hex2bin($acces['password']);
    //password_verify($password, $passHash) 
    if( sodium_crypto_pwhash_str_verify($passHash, $password  )) {
        $_SESSION['pass_hash'] = $userId;
        header('Location:index.php?error=0');
        die;
    } else {
        $_SESSION['pass_hash'] = $userId;
        header('Location:login.php?error=1&passerror=1&nom='.$userId);
        die;
    }
} else {
    header('Location:login.php?error=1&emailrror=1');
    die;

}

header('Content-Type: application/json');
$response = [
    "email" => $userId,
    "pass_hash" => $password
];
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
header( "Location:index.php?error=0");


?>