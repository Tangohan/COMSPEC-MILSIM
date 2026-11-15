<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Support\SseImageStorage;

/**
 * Photo jointe à un signalement (page de signalement du site) : type vérifié par le CONTENU (jamais l'extension ni le type annoncé
 * par le navigateur), 4 Mo au plus, image ré-encodée par GD (EXIF, position GPS et commentaires disparaissent), rangée dans l'espace
 * privé « sse-alert-reports », jamais servie par une adresse publique : seule la route des signalements la rend, aux gestionnaires.
 */
final class SseAlertReportPhotos
{
    public const FOLDER = 'sse-alert-reports';

    public const MAX_BYTES = 4 * 1024 * 1024;

    public const MAX_SIDE = 1600;

    public function __construct(private ?string $privateDir = null)
    {
        $this->privateDir = SseImageStorage::privateRoot($this->privateDir);
    }

    /**
     * Range une photo reçue. Les messages sont ceux montrés au membre.
     *
     * @return array{ok: bool, path: string, error: string}
     */
    public function store(int $tenantId, string $bytes): array
    {
        $fail = static fn (string $error): array => ['ok' => false, 'path' => '', 'error' => $error];
        if ($tenantId < 1) {
            return $fail('Photo refusée.');
        }
        if ($bytes === '') {
            return $fail('La photo est vide.');
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            return $fail('La photo est trop lourde : 4 Mo au plus.');
        }
        $mime = SseIdImageService::sniff($bytes);
        if ($mime === null) {
            return $fail('La photo doit être une image JPEG, PNG ou WebP.');
        }
        $out = function_exists('imagecreatefromstring') ? SseIdImageService::reencode($bytes, 'alert', self::MAX_SIDE, 'jpg') : null;
        if ($out === null) {
            // Sans GD : seulement le retrait des blocs de métadonnées, l'image reste celle d'origine.
            $clean = SseIdImageService::stripMetadata($bytes, $mime);
            if ($clean === null) {
                return $fail('La photo doit être une image JPEG, PNG ou WebP.');
            }
            $out = [$clean, ['image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? 'jpg'];
        }
        [$data, $ext] = $out;
        $dir = (string) $this->privateDir . DIRECTORY_SEPARATOR . self::FOLDER;
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return $fail('Enregistrement de la photo impossible.');
        }
        $name = $tenantId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (@file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $data) === false) {
            return $fail('Enregistrement de la photo impossible.');
        }

        return ['ok' => true, 'path' => SseImageStorage::privatePath(self::FOLDER, $name), 'error' => ''];
    }

    /**
     * Fichier d'un chemin gardé en base, pour la route contrôlée : seulement ce dossier, seulement un vrai fichier image.
     *
     * @return array{file: string, mime: string}|null
     */
    public function resolve(string $path): ?array
    {
        if (!str_starts_with($path, SseImageStorage::PATH_PREFIX . self::FOLDER . '/')) {
            return null;
        }
        $abs = SseImageStorage::absolute($path, null, $this->privateDir);
        $mime = $abs !== null ? SseImageStorage::verifiedMime($abs) : null;

        return $abs !== null && $mime !== null ? ['file' => $abs, 'mime' => $mime] : null;
    }
}
