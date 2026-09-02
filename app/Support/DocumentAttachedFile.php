<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Pièce jointe d’une fiche documentaire — libellés métier et mise de côté sûre.
 * Un pointeur vide (NULL ou chaîne vide) est un état légitime : pas de fichier inventé.
 */
final class DocumentAttachedFile
{
    public static function hasPointer(mixed $filePath): bool
    {
        return trim((string) $filePath) !== '';
    }

    public static function humanKind(?string $mime): string
    {
        $mime = strtolower(trim((string) $mime));

        return match (true) {
            $mime === 'application/pdf' => 'Document PDF',
            str_starts_with($mime, 'image/') => 'Image',
            str_starts_with($mime, 'video/') => 'Vidéo',
            default => 'Fichier joint',
        };
    }

    public static function displayName(?string $originalName, ?string $mime): string
    {
        $name = basename(str_replace('\\', '/', trim((string) $originalName)));
        if ($name !== '' && $name !== '.' && $name !== '..') {
            return $name;
        }

        return self::humanKind($mime);
    }

    public static function humanSize(?int $bytes): string
    {
        if ($bytes === null || $bytes <= 0) {
            return '';
        }

        return number_format($bytes / 1024, 1, ',', ' ') . ' Ko';
    }

    /**
     * Chemin relatif sous storage/documents/ pour ranger un fichier retiré.
     * N’écrit rien : l’appelant ne doit créer ce fichier que s’il déplace un original existant.
     */
    public static function archiveRelativePath(int $tenantId, int $documentId, string $currentRelative, ?string $stamp = null): string
    {
        $base = basename(str_replace('\\', '/', $currentRelative));
        if ($base === '' || $base === '.' || $base === '..') {
            $base = 'piece-jointe';
        }
        $stamp = $stamp ?? date('YmdHis');

        return 'detached/' . $tenantId . '/' . $documentId . '/' . $stamp . '_' . $base;
    }

    /**
     * Déplace le fichier existant. Ne crée aucune pièce si la source est absente.
     */
    public static function moveAsideIfPresent(string $sourceFull, string $destFull): bool
    {
        if (!is_file($sourceFull)) {
            return false;
        }
        $dir = dirname($destFull);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Impossible de préparer l’archive du fichier.');
        }
        if (!@rename($sourceFull, $destFull)) {
            throw new \RuntimeException('Impossible de mettre de côté le fichier.');
        }

        return true;
    }
}
