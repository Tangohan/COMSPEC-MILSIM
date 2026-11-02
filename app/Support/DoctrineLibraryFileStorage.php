<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Response;

/**
 * Fichier PDF joint à une référence ajoutée par une communauté (Doctrine › Référentiel) : déposé hors du dossier public
 * (storage/uploads/doctrine-library/<communauté>/), lu uniquement par le portail, pour les membres de cette communauté.
 */
final class DoctrineLibraryFileStorage
{
    public const MAX_BYTES = 15 * 1024 * 1024;

    public const PREFIX = 'doctrine-library/';

    public static function isStoredPath(?string $path, int $tenantId): bool
    {
        $path = str_replace('\\', '/', trim((string) $path));

        return $path !== '' && !str_contains($path, '..') && str_starts_with($path, self::PREFIX . $tenantId . '/');
    }

    /**
     * @param mixed $file entrée de $_FILES
     * @return array{path: ?string, name: ?string, error: ?string} path null et error null : aucun fichier envoyé
     */
    public static function storeFromUpload(int $tenantId, mixed $file): array
    {
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'name' => null, 'error' => null];
        }
        if ((int) ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            return ['path' => null, 'name' => null, 'error' => 'Le dépôt du fichier a échoué. Réessayez avec un fichier plus léger.'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['path' => null, 'name' => null, 'error' => 'Fichier invalide.'];
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['path' => null, 'name' => null, 'error' => 'Fichier trop volumineux (15 Mo au plus).'];
        }
        $head = (string) @file_get_contents($tmp, false, null, 0, 5);
        if ($head !== '%PDF-') {
            return ['path' => null, 'name' => null, 'error' => 'Seuls les fichiers PDF sont acceptés.'];
        }
        $relDir = self::PREFIX . $tenantId;
        $absDir = base_path('storage/uploads/' . $relDir);
        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            return ['path' => null, 'name' => null, 'error' => 'Impossible d’enregistrer le fichier pour le moment.'];
        }
        $name = bin2hex(random_bytes(16)) . '.pdf';
        if (!@move_uploaded_file($tmp, $absDir . DIRECTORY_SEPARATOR . $name)) {
            return ['path' => null, 'name' => null, 'error' => 'Impossible d’enregistrer le fichier pour le moment.'];
        }
        $original = trim(basename(str_replace('\\', '/', (string) ($file['name'] ?? ''))));

        return ['path' => $relDir . '/' . $name, 'name' => mb_substr($original !== '' ? $original : 'document.pdf', 0, 255), 'error' => null];
    }

    public static function downloadResponse(int $tenantId, string $path, string $name): Response
    {
        $abs = self::isStoredPath($path, $tenantId) ? base_path('storage/uploads/' . $path) : '';
        if ($abs === '' || !is_file($abs)) {
            return (new Response())->setStatusCode(404)->setBody('Fichier introuvable.');
        }
        $name = str_replace(['"', "\r", "\n", '/', '\\'], '', $name !== '' ? $name : 'document.pdf');
        $response = new Response();
        $response->header('Content-Type', 'application/pdf');
        $response->header('Content-Disposition', 'inline; filename="' . $name . '"');
        $response->header('Content-Length', (string) filesize($abs));
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('Cache-Control', 'private, no-store');
        $response->setBodyStream(static function () use ($abs): void {
            $h = fopen($abs, 'rb');
            if ($h) {
                fpassthru($h);
                fclose($h);
            }
        });

        return $response;
    }
}
