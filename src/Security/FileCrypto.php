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
            $plain,         //=> contenu original non chiffré
            'aes-256-gcm',  //=> chiffrement symétrique - taille clé = 256 bits (= 32 octets) - mode fait chiffrement + garantie l'intégrité
            $fileKey,       //=> clé secrete -> chiffrer et déchiffrer
            OPENSSL_RAW_DATA, //=> retourne le résultat en binaire brut et pas en base64
            $iv,            //=> nonce/IV ->unique pour chaque chiffrement avec la même clé
            $tag,           //=> remplie par OpenSSL avec la tag d'authentification (16 octets)
            $aad,             //=> AAD -> Additional Authenticated Data = données non chiffrées mais protégées par le tag. (ex: fileId/version)
            16              //=>la longueur du tag à produire : 16 octets (128 bits) => valeur standard
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



}
?>