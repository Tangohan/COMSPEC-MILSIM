<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseCoverExportRepository;

/**
 * Couvertures d'Athena récupérées depuis le téléphone en jeu (app Ma couverture › Synchroniser avec Athena).
 *
 * Le compte est toujours celui du jeton de jeu (jamais un identifiant envoyé par le jeu) : un membre ne voit et ne
 * télécharge que les couvertures générées pour lui (par lui ou par un gestionnaire, colonne user_id), dans sa communauté.
 *
 *  - listFor()  : couvertures du membre, sans leur contenu (nom, résumé, pays, état, photo, dates, taille, empreinte),
 *                 avec « active » : celle que le membre a choisie sur le site (Outils opérateur), en tête de liste.
 *  - filesFor() : fichiers « comspec-cover » v1 choisis, tels que générés (SseCoverExportService, aucun second format),
 *                 relus par SseCoverExportService::parse ; seule la photo est retirée (« cover.photo » à null) : le jeu ne
 *                 peut pas l'afficher et elle pèse jusqu'à 150 000 caractères. Chaque fichier servi est marqué téléchargé
 *                 et journalisé comme un téléchargement depuis le site.
 * Version du protocole : API_VERSION (le mod envoie la sienne ; un mod plus ancien n'appelle jamais ces routes).
 */
final class SseCoverSyncService
{
    public const API_VERSION = 1;
    public const LIST_LIMIT = 20;
    public const MAX_PER_REQUEST = 5;
    /** Opérations du paquet d'identité relayées par COMSPEC Link (GetIdentityPack, DLL inchangée). */
    public const OP_LIST = '~covers';
    public const OP_FILES = '~cover:';
    private const REF = '/\ACVR-[0-9A-F]{8}\z/';

    public function __construct(
        private ?SseCoverExportRepository $exports = null,
        private ?SseCoverExportService $service = null,
    ) {
        $this->exports ??= new SseCoverExportRepository();
        $this->service ??= new SseCoverExportService($this->exports);
    }

    /**
     * Couvertures du membre : celle qu'il a marquée active d'abord, puis la plus récente d'abord.
     *
     * @return list<array<string, mixed>>
     */
    public function listFor(int $tenantId, int $userId, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $nowIso = $now->format('Y-m-d\TH:i:s\Z');
        $out = [];
        // Couvertures révoquées par le membre (Outils opérateur) : absentes de la liste du téléphone.
        $revoked = \App\Repositories\OperatorHubRepository::revokedCoverIds($tenantId, $userId);
        // Couverture marquée active par le membre : « active » à vrai sur elle seule (champ ajouté, ignoré d'un mod ancien).
        $activeId = \App\Repositories\OperatorHubRepository::activeCoverId($tenantId, $userId);
        foreach ($this->exports->listFilesForUser($tenantId, $userId, self::LIST_LIMIT) as $row) {
            if (isset($revoked[(int) $row['id']])) {
                continue;
            }
            $json = (string) ($row['payload_json'] ?? '');
            // Lecture sans contrôle de date (epoch) : une couverture expirée reste listée, marquée « expired ».
            $parsed = SseCoverExportService::parse($json, new \DateTimeImmutable('@0'));
            $file = $parsed['ok'] ? $parsed['file'] : [];
            $cover = $parsed['ok'] ? $parsed['cover'] : [];
            $expires = (string) ($file['expires_at'] ?? '');
            $status = !$parsed['ok'] ? 'invalid' : ($expires < $nowIso ? 'expired' : 'valid');
            $summary = implode(' · ', array_filter([(string) ($cover['profession'] ?? ''), (string) ($cover['city'] ?? '')], static fn (string $v): bool => $v !== ''));
            $out[] = [
                'ref' => (string) $row['ref'],
                'name' => (string) ($cover['name'] ?? $row['cover_name']),
                'summary' => mb_substr($summary, 0, 160),
                'country' => (string) ($cover['country'] ?? ''),
                'country_code' => (string) ($cover['country_code'] ?? $row['country_code']),
                'status' => $status,
                'has_photo' => is_array($cover['photo'] ?? null),
                'generated_at' => (string) ($file['generated_at'] ?? ''),
                'expires_at' => $expires,
                'updated_at' => str_replace(' ', 'T', substr((string) $row['created_at'], 0, 19)) . 'Z',
                'size' => strlen($json),
                'sha' => (string) $row['content_sha'],
                'by_manager' => $row['created_by_user_id'] !== null && (int) $row['created_by_user_id'] !== $userId,
                'downloaded' => !empty($row['downloaded_at']),
                'active' => $activeId > 0 && (int) $row['id'] === $activeId,
            ];
        }
        // Tri stable (PHP 8) : l'active passe en tête, le reste garde l'ordre du plus récent au plus ancien.
        usort($out, static fn (array $a, array $b): int => (int) $b['active'] <=> (int) $a['active']);

        return $out;
    }

    /**
     * Références demandées par le jeu : « CVR-XXXXXXXX », uniques, 1 à MAX_PER_REQUEST. null : demande refusée.
     *
     * @return list<string>|null
     */
    public static function refsFrom(string $raw): ?array
    {
        $refs = array_values(array_unique(array_filter(array_map('trim', explode(',', $raw)), static fn (string $r): bool => $r !== '')));
        if ($refs === [] || count($refs) > self::MAX_PER_REQUEST) {
            return null;
        }
        foreach ($refs as $r) {
            if (preg_match(self::REF, $r) !== 1) {
                return null;
            }
        }

        return $refs;
    }

    /**
     * Fichiers choisis. Une référence inconnue, d'un autre membre ou d'une autre communauté répond « not_found » (rien
     * ne dit qu'elle existe ailleurs) ; une couverture expirée « expired » ; un fichier illisible « invalid ».
     *
     * @param list<string> $refs
     * @return array{files: list<array<string, mixed>>, errors: list<array{ref: string, error: string}>}
     */
    public function filesFor(int $tenantId, int $userId, array $refs, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $files = [];
        $errors = [];
        foreach ($refs as $ref) {
            $row = $this->exports->findForUserByRef($tenantId, $userId, $ref);
            if ($row === null) {
                $errors[] = ['ref' => $ref, 'error' => 'not_found'];
                continue;
            }
            $parsed = SseCoverExportService::parse((string) $row['payload_json'], $now);
            if (!$parsed['ok']) {
                $errors[] = ['ref' => $ref, 'error' => $parsed['error'] === 'expired' ? 'expired' : 'invalid'];
                continue;
            }
            // Même chemin que le bouton Télécharger du site : propriété revérifiée, date de téléchargement, journal.
            $dl = $this->service->download($tenantId, (int) $row['id'], $userId, false, $now, $userId);
            if ($dl === null) {
                $errors[] = ['ref' => $ref, 'error' => 'not_found'];
                continue;
            }
            $file = $parsed['file'];
            if (is_array($file['cover'] ?? null) && ($file['cover']['photo'] ?? null) !== null) {
                $file['cover']['photo'] = null;
            }
            $files[] = $file;
        }

        return ['files' => $files, 'errors' => $errors];
    }

    /** Version du protocole envoyée par le mod (« v=1 », « 1 ») ; 1 si absente ou illisible. */
    public static function clientVersion(string $raw): int
    {
        return preg_match('/(?:^|v=)(\d{1,3})\b/', trim($raw), $m) === 1 ? max(1, (int) $m[1]) : 1;
    }
}
