<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseCoverExportRepository;
use App\Repositories\SseIdCountryRepository;

/**
 * Couverture générée sur Athena, téléchargée en fichier, importée dans le jeu sans connexion (app Ma couverture).
 *
 * Fichier « comspec-cover » v1 (JSON UTF-8 sans BOM, 256 Kio au plus) :
 *   format, version, ref, generated_at, expires_at (UTC, ISO 8601), account_ref (empreinte du compte, 16 hex),
 *   cover (voir COVER_KEYS), content_sha256 (SHA-256 du texte exact de « cover » tel qu'écrit dans le fichier),
 *   sig (HMAC-SHA256 serveur de ref|generated_at|account_ref|content_sha256 : Athena seule peut le vérifier).
 * Le mod ne peut vérifier que le format et l'empreinte (détecte un fichier abîmé, pas un fichier retouché) : il ne
 * s'en sert que pour remplir sa fiche locale, jamais pour prouver quoi que ce soit.
 * Personnes fictives seulement (SseCoverGeneratorService). Le fichier ne contient ni identifiant de compte, ni communauté.
 */
final class SseCoverExportService
{
    public const FORMAT = 'comspec-cover';
    public const VERSION = 1;
    public const MAX_BYTES = 262144;
    public const MAX_PHOTO_B64 = 150000;
    /** Régénérations par 24 h et par personne : soi-même / par un gestionnaire. */
    public const LIMIT_SELF = 3;
    public const LIMIT_MANAGER = 20;
    public const VALID_DAYS = 90;

    private const TEXT = [
        'name' => 160, 'first_name' => 80, 'last_name' => 80, 'pob' => 120, 'country' => 80, 'address' => 255, 'city' => 120,
        'profession' => 120, 'employer' => 190, 'phone' => 60, 'travel' => 255, 'family' => 190,
    ];
    private const COVER_KEYS = [
        'seed', 'lang', 'name', 'first_name', 'last_name', 'sex', 'dob', 'pob', 'country', 'country_code', 'address', 'city',
        'profession', 'employer', 'phone', 'travel', 'family', 'history', 'contacts', 'docs', 'photo',
    ];
    private const TOP_KEYS = ['format', 'version', 'ref', 'generated_at', 'expires_at', 'account_ref', 'cover', 'content_sha256', 'sig'];

    /** @var callable(array<string, mixed>): void */
    private $audit;

    public function __construct(
        private ?SseCoverExportRepository $exports = null,
        private ?SseCoverGeneratorService $generator = null,
        private ?SseIdCountryRepository $countries = null,
        ?callable $audit = null,
        private ?string $appKey = null,
    ) {
        $this->exports ??= new SseCoverExportRepository();
        $this->generator ??= new SseCoverGeneratorService();
        $this->countries ??= new SseIdCountryRepository();
        $this->appKey ??= (string) (function_exists('env') ? env('APP_KEY', '') : '');
        $this->audit = $audit ?? static function (array $entry): void {
            try {
                (new \App\Repositories\SseAuditLogRepository())->write($entry);
            } catch (\Throwable $e) {
                error_log('Couverture : journal d’audit indisponible : ' . $e->getMessage());
            }
        };
    }

    /**
     * Génère, enregistre (historique) et rend l'identifiant du fichier.
     *
     * @param string|null $photoBytes image brute envoyée (portrait fictif), null = sans photo
     * @return array{ok: true, id: int, ref: string, name: string}|array{ok: false, error: string}
     */
    public function generate(int $tenantId, int $subjectId, ?int $actorId, string $countryCode, ?string $sex, string $lang, ?string $photoBytes, bool $manager, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $lang = in_array($lang, SseCoverGeneratorService::LANGS, true) ? $lang : 'fr';
        $limit = $manager && $actorId !== $subjectId ? self::LIMIT_MANAGER : self::LIMIT_SELF;
        if ($this->exports->countSince($tenantId, $subjectId, $now->modify('-24 hours')->format('Y-m-d H:i:s')) >= $limit) {
            return ['ok' => false, 'error' => 'limit'];
        }
        $country = $countryCode !== '' ? $this->countries->findByCode($tenantId, $countryCode) : null;
        if ($country === null || empty($country['active'])) {
            $active = $this->countries->listForTenant($tenantId, true);
            if ($countryCode !== '' || $active === []) {
                return ['ok' => false, 'error' => 'country'];
            }
            $country = $active[random_int(0, count($active) - 1)];
        }
        $photo = null;
        if ($photoBytes !== null && $photoBytes !== '') {
            $photo = self::preparePhoto($photoBytes);
            if ($photo === null) {
                return ['ok' => false, 'error' => 'photo'];
            }
        }
        $taken = $this->exports->taken($tenantId);
        $cover = $this->generator->generateUnique($country, $taken['names'], $taken['numbers'], null, in_array($sex, ['M', 'F'], true) ? $sex : null, $lang, 12, $now);
        if ($cover === null) {
            return ['ok' => false, 'error' => 'unique'];
        }
        $cover['photo'] = $photo;
        $ref = 'CVR-' . strtoupper(bin2hex(random_bytes(4)));
        $file = $this->buildFile($cover, $tenantId, $subjectId, $ref, $now);
        $id = $this->exports->insert($tenantId, $subjectId, $actorId, [
            'ref' => $ref, 'seed' => $cover['seed'], 'country_code' => (string) $cover['country_code'], 'cover_name' => $cover['name'],
            'doc_number' => $cover['docs'][0]['number'], 'content_sha' => $file['sha'], 'payload_json' => $file['json'],
        ], $now->format('Y-m-d H:i:s'));
        ($this->audit)([
            'tenant_id' => $tenantId, 'actor_user_id' => $actorId, 'actor_label' => 'Ma couverture', 'action' => 'id_cover.export_generate',
            'object_type' => 'sse_cover_export', 'object_id' => $id, 'after' => ['member_user_id' => $subjectId, 'ref' => $ref, 'country' => $cover['country_code']],
        ]);

        return ['ok' => true, 'id' => $id, 'ref' => $ref, 'name' => $cover['name']];
    }

    /**
     * Texte du fichier d'une couverture générée (à servir en téléchargement), null si elle n'est pas de cette communauté
     * ou, hors gestionnaire, pas de ce membre.
     *
     * @return array{row: array<string, mixed>, json: string}|null
     */
    public function download(int $tenantId, int $exportId, int $viewerId, bool $manager, ?\DateTimeImmutable $now = null, ?int $onlyUser = null): ?array
    {
        $row = $this->exports->find($tenantId, $exportId);
        if ($row === null || (!$manager && (int) $row['user_id'] !== $viewerId) || ($onlyUser !== null && (int) $row['user_id'] !== $onlyUser)) {
            return null;
        }
        $this->exports->markDownloaded($tenantId, $exportId, ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'));
        ($this->audit)([
            'tenant_id' => $tenantId, 'actor_user_id' => $viewerId, 'actor_label' => 'Ma couverture', 'action' => 'id_cover.export_download',
            'object_type' => 'sse_cover_export', 'object_id' => $exportId, 'after' => ['member_user_id' => (int) $row['user_id'], 'ref' => (string) $row['ref']],
        ]);

        return ['row' => $row, 'json' => (string) $row['payload_json']];
    }

    /**
     * Panneau de la page : historique et régénérations restantes.
     *
     * @return array{history: list<array<string, mixed>>, remaining: int, limit: int, countries: list<array<string, mixed>>}
     */
    public function panel(int $tenantId, int $subjectId, bool $manager, bool $self, ?\DateTimeImmutable $now = null): array
    {
        $limit = $manager && !$self ? self::LIMIT_MANAGER : self::LIMIT_SELF;
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $used = $this->exports->countSince($tenantId, $subjectId, $now->modify('-24 hours')->format('Y-m-d H:i:s'));

        return [
            'history' => $this->exports->listForUser($tenantId, $subjectId, 10),
            'remaining' => max(0, $limit - $used),
            'limit' => $limit,
            'countries' => $this->countries->listForTenant($tenantId, true),
        ];
    }

    /**
     * Fichier complet. « cover » est écrit une seule fois, son texte exact est celui haché.
     *
     * @param array<string, mixed> $cover
     * @return array{json: string, sha: string}
     */
    public function buildFile(array $cover, int $tenantId, int $userId, string $ref, \DateTimeImmutable $now): array
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
        $coverJson = json_encode(array_intersect_key($cover, array_flip(self::COVER_KEYS)), $flags);
        $sha = hash('sha256', $coverJson);
        $generated = $now->format('Y-m-d\TH:i:s\Z');
        $account = $this->accountRef($tenantId, $userId);
        $head = json_encode([
            'format' => self::FORMAT, 'version' => self::VERSION, 'ref' => $ref, 'generated_at' => $generated,
            'expires_at' => $now->modify('+' . self::VALID_DAYS . ' days')->format('Y-m-d\TH:i:s\Z'), 'account_ref' => $account,
        ], $flags);
        $sig = hash_hmac('sha256', $ref . '|' . $generated . '|' . $account . '|' . $sha, $this->key());
        $json = substr($head, 0, -1) . ',"cover":' . $coverJson . ',"content_sha256":"' . $sha . '","sig":"' . $sig . '"}';

        return ['json' => $json, 'sha' => $sha];
    }

    /** Le fichier a-t-il bien été signé par ce serveur (pour un renvoi ultérieur à Athena) ? */
    public function verifySignature(array $file): bool
    {
        if (!isset($file['ref'], $file['generated_at'], $file['account_ref'], $file['content_sha256'], $file['sig'])) {
            return false;
        }
        $expected = hash_hmac('sha256', $file['ref'] . '|' . $file['generated_at'] . '|' . $file['account_ref'] . '|' . $file['content_sha256'], $this->key());

        return is_string($file['sig']) && hash_equals($expected, $file['sig']);
    }

    public function accountRef(int $tenantId, int $userId): string
    {
        return substr(hash_hmac('sha256', 'cover-account|' . $tenantId . '|' . $userId, $this->key()), 0, 16);
    }

    /**
     * Lecture stricte d'un fichier (contrat repris à l'identique par le mod) : format, version, champs connus, types,
     * tailles, caractères de contrôle, empreinte, photo. Aucune exécution ni jamais d'interprétation des textes.
     *
     * @return array{ok: true, file: array<string, mixed>, cover: array<string, mixed>}|array{ok: false, error: string}
     */
    public static function parse(string $text, ?\DateTimeImmutable $now = null): array
    {
        $bad = static fn (string $why): array => ['ok' => false, 'error' => $why];
        if (strlen($text) === 0 || strlen($text) > self::MAX_BYTES) {
            return $bad('size');
        }
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        }
        try {
            $file = json_decode($text, true, 6, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $bad('json');
        }
        if (!is_array($file) || array_is_list($file) || array_diff(array_keys($file), self::TOP_KEYS) !== []) {
            return $bad('keys');
        }
        if (($file['format'] ?? null) !== self::FORMAT) {
            return $bad('format');
        }
        if (($file['version'] ?? null) !== self::VERSION) {
            return $bad('version');
        }
        if (!is_string($file['ref'] ?? null) || preg_match('/^CVR-[0-9A-F]{8}$/', $file['ref']) !== 1
            || !is_string($file['account_ref'] ?? null) || preg_match('/^[0-9a-f]{16}$/', $file['account_ref']) !== 1
            || !is_string($file['content_sha256'] ?? null) || preg_match('/^[0-9a-f]{64}$/', $file['content_sha256']) !== 1
            || !is_string($file['sig'] ?? null) || preg_match('/^[0-9a-f]{64}$/', $file['sig']) !== 1) {
            return $bad('header');
        }
        $iso = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/';
        foreach (['generated_at', 'expires_at'] as $k) {
            if (!is_string($file[$k] ?? null) || preg_match($iso, $file[$k]) !== 1) {
                return $bad('header');
            }
        }
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($file['expires_at'] < $now->format('Y-m-d\TH:i:s\Z')) {
            return $bad('expired');
        }
        $cover = $file['cover'] ?? null;
        if (!is_array($cover) || array_is_list($cover) || array_diff(array_keys($cover), self::COVER_KEYS) !== []) {
            return $bad('cover');
        }
        // Empreinte du texte exact de « cover » : retrouvé tel quel entre `"cover":` et `,"content_sha256"`.
        $a = strpos($text, '"cover":');
        $b = strrpos($text, ',"content_sha256":');
        if ($a === false || $b === false || $b < $a) {
            return $bad('hash');
        }
        if (!hash_equals($file['content_sha256'], hash('sha256', substr($text, $a + 8, $b - $a - 8)))) {
            return $bad('hash');
        }
        $out = self::cleanCover($cover);

        return $out === null ? $bad('cover') : ['ok' => true, 'file' => $file, 'cover' => $out];
    }

    /**
     * @param array<string, mixed> $c
     * @return array<string, mixed>|null
     */
    private static function cleanCover(array $c): ?array
    {
        $str = static function (mixed $v, int $max): ?string {
            if (!is_string($v) || mb_strlen($v) > $max || preg_match('/[\x00-\x1F\x7F]/u', $v) === 1 || !mb_check_encoding($v, 'UTF-8')) {
                return null;
            }

            return trim($v);
        };
        $out = [];
        foreach (self::TEXT as $k => $max) {
            $v = $str($c[$k] ?? '', $max);
            if ($v === null) {
                return null;
            }
            $out[$k] = $v;
        }
        if ($out['name'] === '') {
            return null;
        }
        $dob = $str($c['dob'] ?? '', 10);
        if ($dob === null || ($dob !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) !== 1)) {
            return null;
        }
        $out['dob'] = $dob;
        $out['sex'] = in_array($c['sex'] ?? '', ['M', 'F', 'X', ''], true) ? (string) ($c['sex'] ?? '') : null;
        $out['lang'] = in_array($c['lang'] ?? 'fr', SseCoverGeneratorService::LANGS, true) ? (string) ($c['lang'] ?? 'fr') : null;
        $cc = $str($c['country_code'] ?? '', 16);
        $out['country_code'] = $cc;
        $out['seed'] = is_int($c['seed'] ?? null) && $c['seed'] >= 0 ? $c['seed'] : null;
        if (in_array(null, [$out['sex'], $out['lang'], $out['country_code'], $out['seed']], true)) {
            return null;
        }
        $out['history'] = [];
        foreach (self::list($c['history'] ?? [], 8) ?? [null] as $line) {
            $v = $str($line, 255);
            if ($v === null) {
                return null;
            }
            $out['history'][] = $v;
        }
        $out['contacts'] = [];
        foreach (self::list($c['contacts'] ?? [], 10) ?? [null] as $row) {
            if (!is_array($row) || !array_is_list($row) || count($row) !== 3) {
                return null;
            }
            $r = [$str($row[0], 160), $str($row[1], 60), $str($row[2], 60)];
            if (in_array(null, $r, true)) {
                return null;
            }
            $out['contacts'][] = $r;
        }
        $out['docs'] = [];
        foreach (self::list($c['docs'] ?? [], 10) ?? [null] as $d) {
            if (!is_array($d) || array_diff(array_keys($d), ['type', 'country', 'number', 'issued', 'expires']) !== []) {
                return null;
            }
            $type = $str($d['type'] ?? '', 20);
            $number = $str($d['number'] ?? '', 40);
            $country = $str($d['country'] ?? '', 80);
            $issued = $str($d['issued'] ?? '', 10);
            $expires = $str($d['expires'] ?? '', 10);
            if ($type === null || !isset(\App\Support\SseIdentityCatalog::DOC_TYPES[$type]) || $number === null || $number === ''
                || $country === null || $issued === null || $expires === null
                || preg_match('/^\d{4}-\d{2}-\d{2}$/', $issued) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires) !== 1) {
                return null;
            }
            $out['docs'][] = ['type' => $type, 'country' => $country, 'number' => $number, 'issued' => $issued, 'expires' => $expires];
        }
        $out['photo'] = null;
        if (($c['photo'] ?? null) !== null) {
            $p = $c['photo'];
            if (!is_array($p) || array_diff(array_keys($p), ['mime', 'b64']) !== [] || ($p['mime'] ?? '') !== 'image/jpeg'
                || !is_string($p['b64'] ?? null) || strlen($p['b64']) > self::MAX_PHOTO_B64 || preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $p['b64']) !== 1) {
                return null;
            }
            $bin = base64_decode($p['b64'], true);
            if ($bin === false || !str_starts_with($bin, "\xFF\xD8\xFF")) {
                return null;
            }
            $out['photo'] = ['mime' => 'image/jpeg', 'b64' => $p['b64']];
        }

        return $out;
    }

    /** @return list<mixed>|null null = pas une liste ou trop longue */
    private static function list(mixed $v, int $max): ?array
    {
        return is_array($v) && array_is_list($v) && count($v) <= $max ? $v : null;
    }

    /**
     * Portrait fictif envoyé : vrai type lu dans le contenu, recadré 35 x 45, 210 x 270 px, JPEG sans métadonnées, trop
     * lourd = refusé. Rien n'est gardé sur disque ailleurs que dans le fichier de couverture.
     *
     * @return array{mime: string, b64: string}|null
     */
    public static function preparePhoto(string $bytes): ?array
    {
        if (strlen($bytes) > 4 * 1024 * 1024 || SseIdImageService::sniff($bytes) === null || !function_exists('imagecreatefromstring')) {
            return null;
        }
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return null;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 40 || $h < 40) {
            imagedestroy($src);

            return null;
        }
        $ratio = 35 / 45;
        $cw = (int) min($w, floor($h * $ratio));
        $ch = (int) min($h, floor($w / $ratio));
        $dst = imagecreatetruecolor(210, 270);
        imagecopyresampled($dst, $src, 0, 0, (int) (($w - $cw) / 2), (int) (($h - $ch) / 2), 210, 270, $cw, $ch);
        ob_start();
        imagejpeg($dst, null, 80);
        $jpeg = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);
        $b64 = base64_encode($jpeg);

        return strlen($b64) <= self::MAX_PHOTO_B64 ? ['mime' => 'image/jpeg', 'b64' => $b64] : null;
    }

    private function key(): string
    {
        return $this->appKey !== null && $this->appKey !== '' ? $this->appKey : 'dev-insecure-key';
    }
}
