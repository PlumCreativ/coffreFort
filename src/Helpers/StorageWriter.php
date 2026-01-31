<?php
// src/Security/StorageWriter.php

namespace App\Helpers;


final class StorageWriter
{
    /**
     * S'assure qu'un répertoire existe
     */
    public static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException("Cannot create directory: $dir");
            }
        }
    }

    /**
     * Écrit des données binaires dans un fichier (par stream)
     */
    public static function writeBinary(string $path, string $data): int
    {

        $handle = @fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException("Impossible de créer le fichier de destination: $path");
        }

        $chunkSize = 8192; // 8 Ko
        $offset = 0;
        $length = strlen($data);
        $totalWritten = 0;

        while ($offset < $length) {
            $chunk = substr($data, $offset, $chunkSize);
            $written = fwrite($handle, $chunk);
            
            if ($written === false) {
                fclose($handle);
                throw new \RuntimeException("Erreur lors de l\'écriture du fichier");
            }
            
            $totalWritten += $written;
            $offset += $written;
        }

        fclose($handle);
        return $totalWritten;
    }


     /**
     * Lit un fichier binaire par stream (pour éviter de charger tout en mémoire)
     * 
     * @param string $path Chemin du fichier à lire
     * @return string Contenu du fichier
     * @throws \RuntimeException Si impossible de lire le fichier
     */
    public static function readBinary(string $path): string
    {
        $fileHandle = @fopen($path, 'rb');
        if ($fileHandle === false) {
            throw new \RuntimeException("Impossible de lire le fichier chiffré: $path");
        }

        $content = '';
        while (!feof($fileHandle)) {
            $chunk = fread($fileHandle, 8192); // 8 Ko par bloc
            if ($chunk === false) {
                fclose($fileHandle);
                throw new \RuntimeException("Erreur lors de la lecture du fichier: $path");
            }
            $content .= $chunk;
        }
        
        fclose($fileHandle);
        return $content;
    }

}
?>