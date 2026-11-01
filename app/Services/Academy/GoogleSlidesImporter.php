<?php

declare(strict_types=1);

namespace App\Services\Academy;

use App\Controllers\Api\AtakApiController;
use App\Services\Atak\GoogleSlidesAccessCheck;

/**
 * Importe une présentation Google Slides dans un cours Academy, côté serveur.
 *
 * Même source que les diapositives de briefing (GoogleSlidesAccessCheck) : l'export public de Google,
 * sans clé ni service payant. On télécharge l'export PDF d'un seul tenant (ordre des diapositives
 * conservé), borné en durée et en taille, et on le range avec les fichiers privés de la communauté.
 */
final class GoogleSlidesImporter
{
    public const FILE_PATTERN = '/\A[a-f0-9]{32}\.pdf\z/';
    public const MAX_BYTES = 30 * 1024 * 1024;
    public const TIMEOUT = 30;

    /** Codes d'erreur traduits dans lang/{fr,en}/academy.php (clé « slides_<code> »). */
    public const INVALID = 'invalid';
    public const PUBLISH_LINK = 'publish_link';
    public const PRIVATE = 'private';
    public const TOO_LARGE = 'too_large';
    public const UNAVAILABLE = 'unavailable';

    /** @var \Closure(string): array{status:int, type:string, body:string, disposition:string, host:string} */
    private \Closure $fetch;

    /** @param (callable(string): array{status:int, type:string, body:string, disposition:string, host:string})|null $fetch injecté par les tests */
    public function __construct(?callable $fetch = null, private ?string $root = null)
    {
        $this->fetch = $fetch !== null ? \Closure::fromCallable($fetch) : self::download(...);
    }

    /** Identifiant de la présentation, ou code d'erreur : seule l'adresse docs.google.com/presentation/d/<id>/… est acceptée. */
    public static function deckId(string $url): string
    {
        $url = trim($url);
        if (!AtakApiController::isValidGoogleSlidesUrl($url)) {
            return self::INVALID;
        }
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_PORT) !== null
            || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
            return self::INVALID;
        }
        if (GoogleSlidesAccessCheck::isPublishLink($url)) {
            // Le lien « Publier sur le Web » (/d/e/2PACX…) ne donne pas accès à l'export : il faut l'adresse de la présentation.
            return self::PUBLISH_LINK;
        }
        $id = GoogleSlidesAccessCheck::deckId($url);
        return $id !== null && strlen($id) <= 128 ? $id : self::INVALID;
    }

    public function directory(int $tenantId): string
    {
        return rtrim($this->root ?? base_path('storage/private/academy'), '/\\') . '/' . $tenantId;
    }

    public function path(int $tenantId, string $file): ?string
    {
        if ($tenantId < 1 || !preg_match(self::FILE_PATTERN, $file)) {
            return null;
        }
        $path = $this->directory($tenantId) . '/' . $file;
        return is_file($path) ? $path : null;
    }

    /**
     * @return array{type:string, title:array{fr:string,en:string}, file:string, pages:int, deck:string, source:string, imported_at:int}
     * @throws \DomainException code d'erreur lisible (constantes ci-dessus)
     */
    public function import(int $tenantId, string $url): array
    {
        $deck = self::deckId($url);
        if (in_array($deck, [self::INVALID, self::PUBLISH_LINK], true) || $tenantId < 1) {
            throw new \DomainException($tenantId < 1 ? self::INVALID : $deck);
        }
        try {
            $response = ($this->fetch)('https://docs.google.com/presentation/d/' . rawurlencode($deck) . '/export/pdf');
        } catch (\LengthException) {
            throw new \DomainException(self::TOO_LARGE);
        } catch (\Throwable) {
            throw new \DomainException(self::UNAVAILABLE);
        }
        $host = strtolower($response['host']);
        if (!in_array($response['status'], [200, 401, 403, 404], true)
            || ($host !== 'docs.google.com' && !str_ends_with($host, '.googleusercontent.com'))) {
            throw new \DomainException(self::UNAVAILABLE);
        }
        // Présentation non partagée : Google répond par sa page de connexion, jamais par un PDF.
        if ($response['status'] !== 200 || !str_starts_with($response['body'], '%PDF-')) {
            throw new \DomainException(self::PRIVATE);
        }
        if (strlen($response['body']) > self::MAX_BYTES) {
            throw new \DomainException(self::TOO_LARGE);
        }
        $pages = preg_match_all('#/Type\s*/Page(?![A-Za-z])#', $response['body']);
        $file = bin2hex(random_bytes(16)) . '.pdf';
        $directory = $this->directory($tenantId);
        if ((!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory))
            || file_put_contents($directory . '/' . $file, $response['body'], LOCK_EX) === false) {
            throw new \DomainException(self::UNAVAILABLE);
        }
        $title = self::title($response['disposition']);
        return ['type' => 'slides', 'title' => ['fr' => $title, 'en' => $title], 'file' => $file, 'pages' => max(1, (int) $pages),
            'deck' => $deck, 'source' => 'https://docs.google.com/presentation/d/' . $deck . '/edit', 'imported_at' => time()];
    }

    public function delete(int $tenantId, string $file): void
    {
        $path = $this->path($tenantId, $file);
        if ($path !== null) {
            @unlink($path);
        }
    }

    /** Titre de la présentation tel que Google le renvoie dans le nom du fichier exporté. */
    public static function title(string $disposition): string
    {
        $name = '';
        if (preg_match("/filename\\*=UTF-8''([^;]+)/i", $disposition, $m)) {
            $name = rawurldecode(trim($m[1], " \""));
        } elseif (preg_match('/filename="([^"]+)"/i', $disposition, $m)) {
            $name = $m[1];
        }
        $name = trim((string) preg_replace('/\.pdf\z/i', '', $name));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name));
        return $name !== '' && mb_check_encoding($name, 'UTF-8') ? mb_substr($name, 0, 200) : 'Google Slides';
    }

    /** @return array{status:int, type:string, body:string, disposition:string, host:string} */
    private static function download(string $url): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('curl');
        }
        $body = '';
        $disposition = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_HTTPHEADER => ['Accept: application/pdf'],
            CURLOPT_HEADERFUNCTION => static function ($h, string $line) use (&$disposition): int {
                if (stripos($line, 'content-disposition:') === 0) {
                    $disposition = trim(substr($line, 20));
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($h, string $chunk) use (&$body): int {
                $body .= $chunk;
                return strlen($body) > self::MAX_BYTES ? -1 : strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $host = (string) parse_url((string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL), PHP_URL_HOST);
        curl_close($ch);
        if (strlen($body) > self::MAX_BYTES) {
            throw new \LengthException('too_large');
        }
        if ($ok === false) {
            throw new \RuntimeException('network');
        }
        return ['status' => $status, 'type' => $type, 'body' => $body, 'disposition' => $disposition, 'host' => $host];
    }
}
