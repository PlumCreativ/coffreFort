<?php
// src/Security/StorageWriter.php

namespace App\Security;


//pour centraliser l'écriture disque
final class StorageWriter

{
    public static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException("Cannot create upload directory: $dir");
            }
        }
    }


    public static function writeBinary(string $path, string $data): int
    {
        $bytes = @file_put_contents($path, $data);
        if ($bytes === false) {
            throw new \RuntimeException("Cannot write file: $path");
        }
        return $bytes;
    }
}
?>