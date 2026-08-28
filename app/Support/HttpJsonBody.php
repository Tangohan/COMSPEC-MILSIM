<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Corps JSON des routes terrain — ne jamais relire php://input sur un envoi de fichier.
 * (multipart + file_get_contents saturait la mémoire PHP sur une capture Arma.)
 */
final class HttpJsonBody
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    public static function isMultipart(): bool
    {
        $ct = (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        if (stripos($ct, 'multipart/') !== false) {
            return true;
        }

        return $_FILES !== [];
    }

    /**
     * Champs texte déjà parsés (envoi de fichier). Pas une copie du binaire.
     *
     * @return array<string, mixed>
     */
    public static function postFields(): array
    {
        return is_array($_POST) ? $_POST : [];
    }

    /**
     * JSON brut plafonné. Chaîne vide si multipart, trop gros, ou corps absent.
     */
    public static function rawJson(int $maxBytes = self::MAX_BYTES): string
    {
        if (self::isMultipart()) {
            return '';
        }
        $maxBytes = max(1024, $maxBytes);
        $declared = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($declared > $maxBytes) {
            return '';
        }

        $fh = @fopen('php://input', 'rb');
        if ($fh === false) {
            return '';
        }
        try {
            $raw = stream_get_contents($fh, $maxBytes + 1);
        } finally {
            fclose($fh);
        }
        if (!is_string($raw) || $raw === '') {
            return '';
        }
        if (strlen($raw) > $maxBytes) {
            return '';
        }

        return $raw;
    }
}
