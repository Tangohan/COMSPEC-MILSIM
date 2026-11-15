<?php

declare(strict_types=1);

namespace App\Services\ContactMail;

/** Nom de fichier d'un téléchargement : sans chemin ni caractère de contrôle, avec repli ASCII et forme RFC 5987. */
final class DownloadName
{
    public static function clean(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        $name = basename($name);
        $name = preg_replace('/[\x00-\x1F\x7F"]+/u', '', $name) ?? '';
        $name = trim($name, " .\t");

        return $name !== '' ? mb_substr($name, 0, 150) : 'piece-jointe';
    }

    /** « filename="ascii.pdf"; filename*=UTF-8''nom%20complet.pdf » */
    public static function dispositionParams(string $name): string
    {
        $ascii = preg_replace('/[^A-Za-z0-9._\- ]+/', '_', $name) ?? 'piece-jointe';
        $ascii = $ascii !== '' ? $ascii : 'piece-jointe';

        return 'filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name);
    }
}
