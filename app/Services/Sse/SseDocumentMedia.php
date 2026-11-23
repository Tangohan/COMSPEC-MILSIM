<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Support\SseImageStorage;
use App\Support\SseRichText;

/**
 * Images et logos insérés dans un document SSE.
 *
 * Envoi : PNG, JPEG ou WebP seulement (type lu dans le contenu, jamais SVG), 3 Mo au plus, réencodé par GD (métadonnées
 * perdues), nom aléatoire préfixé par la communauté, rangé dans storage/private/sse-images/sse-doc-media. Le corps du document ne
 * garde qu'un jeton « <communauté>_<aléa> » (bloc vide div.sse-media) ; l'image est ajoutée à l'affichage et dans le PDF, et
 * n'est servie que par la route contrôlée /atak/sse/documents/medias/{jeton} : un jeton d'une autre communauté ne donne rien.
 */
final class SseDocumentMedia
{
    public const FOLDER = 'sse-doc-media';

    private const EXTENSIONS = ['png', 'jpg', 'webp'];

    public function __construct(private ?SseIdImageService $images = null)
    {
        $this->images ??= new SseIdImageService();
    }

    /**
     * @param mixed $file entrée de $_FILES
     * @return array{ok: bool, token?: string, error?: string, none?: bool}
     */
    public function storeUpload(mixed $file, int $tenantId): array
    {
        $r = ($this->images ?? new SseIdImageService())->storeUpload($file, 'doc_media', $tenantId);

        return $this->tokenOf($r);
    }

    /** @return array{ok: bool, token?: string, error?: string} */
    public function storeBytes(string $bytes, int $tenantId): array
    {
        return $this->tokenOf(($this->images ?? new SseIdImageService())->storeBytes($bytes, 'doc_media', $tenantId));
    }

    /**
     * @param array<string,mixed> $r
     * @return array{ok: bool, token?: string, error?: string, none?: bool}
     */
    private function tokenOf(array $r): array
    {
        if (empty($r['ok']) || !isset($r['path'])) {
            return $r + ['ok' => false];
        }
        if (preg_match('~^' . preg_quote(SseImageStorage::PATH_PREFIX . self::FOLDER . '/', '~') . '([0-9]{1,10}_[a-f0-9]{16})\.(?:png|jpg|webp)$~', (string) $r['path'], $m) !== 1) {
            return ['ok' => false, 'error' => 'Image refusée.'];
        }

        return ['ok' => true, 'token' => $m[1]];
    }

    /** Fichier d'un jeton, pour la communauté donnée seulement (null : jeton invalide, autre communauté ou fichier absent). */
    public static function fileFor(string $token, int $tenantId, ?string $privateDir = null): ?string
    {
        if (preg_match(SseRichText::MEDIA_TOKEN, $token) !== 1 || (int) explode('_', $token)[0] !== $tenantId || $tenantId < 1) {
            return null;
        }
        foreach (self::EXTENSIONS as $ext) {
            $abs = SseImageStorage::absolute(SseImageStorage::privatePath(self::FOLDER, $token . '.' . $ext), null, $privateDir);
            if ($abs !== null) {
                return $abs;
            }
        }

        return null;
    }

    /**
     * Remplace chaque bloc d'image jointe par l'image : balise <img> vers la route contrôlée (web) ou vers le fichier (PDF).
     * Un jeton d'une autre communauté ou sans fichier disparaît.
     *
     * @param 'web'|'pdf' $mode
     */
    public static function expand(string $html, int $tenantId, string $mode, ?string $privateDir = null): string
    {
        if (!str_contains($html, 'sse-media')) {
            return $html;
        }

        return (string) preg_replace_callback(
            '~<div class="sse-media" data-media="([0-9]{1,10}_[a-f0-9]{16})"></div>~',
            static function (array $m) use ($tenantId, $mode, $privateDir): string {
                $abs = self::fileFor($m[1], $tenantId, $privateDir);
                if ($abs === null) {
                    return '';
                }
                if ($mode === 'pdf') {
                    $size = @getimagesize($abs);
                    $w = is_array($size) ? min(480, max(40, (int) $size[0])) : 240;

                    return '<div style="text-align:center"><img src="' . htmlspecialchars($abs, ENT_QUOTES, 'UTF-8') . '" width="' . $w . '"></div>';
                }
                $src = function_exists('url') ? (string) url('atak/sse/documents/medias/' . $m[1]) : '';

                return '<figure class="sse-media"><img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="Image jointe" loading="lazy"></figure>';
            },
            $html
        );
    }
}
