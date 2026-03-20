<?php
require 'vendor/autoload.php';

// Test de connexion à la base et génération de SQL
$db = new Medoo\Medoo([
    'type' => 'mysql',
    'host' => 'mysql',
    'database' => 'coffreFort',
    'username' => 'root',
    'password' => '5678_Juklau+147!_29132'
]);

// Test la requête qui cause l'erreur
try {
    $result = $db->get('users', ['join' => '*'], ['email' => 'test@test.com']);
    echo "Success: " . json_encode($result) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
