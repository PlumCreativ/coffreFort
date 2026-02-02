<?php
namespace App\Security;

final class FileCrypto {



    /**
     * Chiffre un contenu (plaintext) avec AES-256-GCM.
     * Retourne ciphertext + iv + tag + fileKey.
     *
     * @return array{ciphertext:string, iv:string, tag:string, fileKey:string}
     */
    public static function encryptContent(string $plain, string $aad = ''): array
    {
        $fileKey = random_bytes(32);    //=> AES-256
        $iv = random_bytes(12);         //=> Initialization Vector => GCM recommandé 12 bytes
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plain,           //=> contenu original non chiffré
            'aes-256-gcm',    //=> chiffrement symétrique - taille clé = 256 bits (= 32 octets) - mode fait chiffrement + garantie l'intégrité
            $fileKey,         //=> clé secrete -> chiffrer et déchiffrer
            OPENSSL_RAW_DATA, //=> retourne le résultat en binaire brut et pas en base64
            $iv,              //=> nonce/IV ->unique pour chaque chiffrement avec la même clé
            $tag,             //=> remplie par OpenSSL avec la tag d'authentification (16 octets)
            $aad,             //=> AAD -> Additional Authenticated Data = données non chiffrées mais protégées par le tag. (ex: fileId/version)
            16                //=>la longueur du tag à produire : 16 octets (128 bits) => valeur standard
        );

        if($ciphertext === false || strlen($tag) !== 16){
            throw new \RuntimeException('Encryptage failed (ciphertext/tag invalid)');
        }

        return [
            'ciphertext'    => $ciphertext,
            'iv'            => $iv,
            'tag'           => $tag,
            'fileKey'       => $fileKey,
        ];

    }


    /**
     * Wrap (enveloppe) la fileKey avec la KEK via AES-256-GCM.
     * key_envelope = envIv || envTag || wrappedKey
     *
     * @return array{keyEnvelope:string, envIv:string, envTag:string, wrappedKey:string}
     */
    public static function wrapFileKey(string $fileKey, string $kek, string $aad = ''): array
    {

        $kek = self::normalizeKek($kek);

        $envIv = random_bytes(12);
        $envTag = '';

        $wrappedKey = openssl_encrypt(
            $fileKey,
            'aes-256-gcm',
            $kek,
            OPENSSL_RAW_DATA,
            $envIv,
            $envTag,
            $aad,
            16
        );

         if($wrappedKey === false || strlen($envTag) !== 16){

            //il n'y a pas retours HTTP!!! => c'est que dans les controllers !!!!
            throw new \RuntimeException('Key envelope failed (wrappedKey/tag invalid)');
        }

        // key_envelope = envIv || envTag || wrappedKey
        $keyEnvelope = $envIv . $envTag . $wrappedKey;

        return [
            'keyEnvelope'   => $keyEnvelope,
            'envIv'         => $envIv,
            'envTag'        => $envTag,
            'wrappedKey'    => $wrappedKey,
        ];

    }

    /**
     * Fonction "tout-en-un" pour upload :
     * - chiffre le plaintext
     * - wrap la clé
     * - calcule checksum binaire sha256(ciphertext)
     *
     * @return array{ciphertext:string, iv:string, tag:string, key_envelope:string, checksum:string, size_plain:int}

     */
    public static function encryptForStorage(string $plain, string $kek, string $aadContent = '', string $aadKey = ''): array
    {

        $enc = self::encryptContent($plain, $aadContent);
        $wrap = self::wrapFileKey($enc['fileKey'], $kek, $aadKey);

        $ciphertext = $enc['ciphertext'];
        $checksum = hash('sha256', $ciphertext, true);

        return [
            'ciphertext'    => $ciphertext,
            'iv'            => $enc['iv'],
            'tag'           => $enc['tag'],
            'key_envelope'  => $wrap['keyEnvelope'],
            'checksum'      => $checksum,
            'size_plain'    => strlen($plain),
        ];
    }

    /**
     * Vérifie/normalise la KEK : >= 32, tronque à 32.
     */
    public static function normalizeKek(string $kek): string
    {
        $kek = trim($kek);

        // if ($kek === '' || strlen($kek) < 32) {
        //     throw new \RuntimeException('Server KEK missing/misconfigured');
        // }

        if ($kek === '' || strlen($kek) < 32) {
            throw new \RuntimeException('Server KEK missing/misconfigured');
        }

        return substr($kek, 0, 32);
    }


     /**
     * Parse le key_envelope stocké en base: envIv(12) || envTag(16) || wrappedKey(n)
     * @return array{envIv:string, envTag:string, wrappedKey:string}
     */
    public static function parseKeyEnvelope(string $keyEnvelope): array
    {
        if(!is_string($keyEnvelope) || strlen($keyEnvelope) < 28){
            throw new \RuntimeException('Key envelope invalide (trop court)');
        }

        return [
            'envIv'         => substr($keyEnvelope, 0, 12),
            'envTag'        => substr($keyEnvelope, 12, 16),
            'wrappedKey'    => substr($keyEnvelope, 28),
        ];
    }


    /**
     * Unwrap / déchiffre la fileKey depuis wrappedKey avec la KEK, en AES-256-GCM.
     */
    public static function unwrapFileKey(string $wrappedKey, string $kek, string $envIv, string $envTag, string $aadKey = ''): string
    {
        $kek = self::normalizeKek($kek);

         $fileKey = openssl_decrypt(
            $wrappedKey,
            'aes-256-gcm',
            $kek,
            OPENSSL_RAW_DATA,
            $envIv,
            $envTag,
            $aadKey 
            // 16
        );

        if($fileKey === false){

            // openssl_error_string() peut être vide, mais utile en debug
            $err = openssl_error_string();
            throw new \RuntimeException('Impossible de dechiffrer la cle du fichier' . ($err ? " ($err)" : ""));
        }

        if(strlen($fileKey) !== 32) {

            //pour assurer que ça soit une clé AES-256 (32 bytes)
            throw new \RuntimeException('Cle fichier invalide (taille inattendue)');
        }

        return $fileKey;
    }


    /**
     * Déchiffre le contenu (ciphertext) avec fileKey, iv, tag, AAD.
     */
    public static function decryptContent(string $ciphertext, string $fileKey, string $iv, string $tag, string $aadContent = ''): string
    {
        if(!is_string($iv) || strlen($iv) !== 12){
             throw new \RuntimeException('IV invalide');
        }

        if(!is_string($tag) || strlen($tag) !== 16){
             throw new \RuntimeException('Auth tag invalide');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $fileKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aadContent
            // 16
        );

        if ($plaintext === false){
            throw new \RuntimeException('Dechiffrement du contenu echoue');
        }

        return $plaintext;
    }


    /**
     * JOUR 2 => Déchiffrement côté serveur pour simplicité
     * Déchiffrement "tout-en-un" à partir de la ligne file_versions.
     * - calcule AAD (clé + contenu) sur (fileId, version)
     * - unwrap fileKey depuis key_envelope
     * - decrypt ciphertext
     *
     * @param array $versionRow Doit contenir: version, iv, auth_tag, key_envelope, checksum (optionnel)
     * @return array{plaintext:string, servedVersion:int}
     */
    public static function decryptFromStorage(string $ciphertext, array $versionRow, string $kek, int $fileId): array
    {

        $servedVersion = (int)($versionRow['version'] ?? 0); // important
        if($servedVersion <= 0){
            throw new \RuntimeException('Version invalide (absente ou <= 0)');
        }

        $keyEnvelope = $versionRow['key_envelope'] ?? null;
        if(!is_string($keyEnvelope)){
            throw new \RuntimeException('Key envelope manquant ou invalide');
        }

        $iv = $versionRow['iv'] ?? null;
        $tag = $versionRow['auth_tag'] ?? null;

        if(!is_string($iv) || !is_string($tag)){
            throw new \RuntimeException('IV/auth_tag manquant(s)');
        }

         // AAD stable et déterministe => doit être IDENTIQUE entre encrypt/decrypt!!!!!
        $aadKey = "filekey:$fileId:v$servedVersion";
        $aadContent = "file:$fileId:v$servedVersion";

        $parts = self::parseKeyEnvelope($keyEnvelope);

        $fileKey = self::unwrapFileKey(
            $parts['wrappedKey'],
            $kek,
            $parts['envIv'],
            $parts['envTag'],
            $aadKey
        );

        $plaintext = self::decryptContent($ciphertext, $fileKey, $iv, $tag, $aadContent);

        // Vérifier le checksum
        if(isset($versionRow['checksum']) && is_string($versionRow['checksum'])){
            $computedChecksum = hash('sha256', $ciphertext, true);

            if (!hash_equals($versionRow['checksum'], $computedChecksum)){
                throw new \RuntimeException('Checksum invalide');
            }
        }

        return [
            'plaintext' => $plaintext,
            'servedVersion' => $servedVersion,
        ];
    }

}
?>