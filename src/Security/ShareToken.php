<?php
namespace App\Security;

use Medoo\Medoo;

//Utilitaires pour la gestion des tokens
final class ShareToken{

    public static function base64url(string $data): string{
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function randomToken(int $bytes = 32): string{

        //base64url encode un token binaire aléatoire
        return self::base64url(random_bytes($bytes));  //=> envrion 43 chars
    }

    public static function sign(string $secret, string $token, int $shareId): string{

        //HMAC-SHA256 signature
        return hash_hmac('sha256', $token . $shareId, $secret);

    }

    public static function equals(string $a, string $b): bool {
        return hash_equals($a, $b);
    }
}