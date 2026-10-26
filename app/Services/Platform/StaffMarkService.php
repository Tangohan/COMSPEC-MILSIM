<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Core\Database;
use PDO;

/**
 * Marques « équipe Athena » sur tout le site : les administrateurs de la plateforme (users.is_platform_admin)
 * ont un aigle rouge et leur nom en rouge, le support (rôle contributeur « support » validé dans
 * Admin > Contributeurs) un casque vert et leur nom en vert.
 *
 * Une personne est reconnue par son e-mail : toutes ses lignes users (une par communauté) portent la marque.
 * Le résultat est gardé dans storage/cache/staff-marks.json (10 minutes, vidé à chaque changement d'accès ou
 * de rôle) pour que l'affichage ne coûte aucune requête. Rendu côté serveur par mark() / nameClass() /
 * name(), et pour le reste du site par public/assets/js/staff-marks.js (liens vers une fiche, data-user-id).
 */
final class StaffMarkService
{
    public const ADMIN = 'admin';
    public const SUPPORT = 'support';

    private const TTL = 600;

    /** @var array{users: array<int, string>, slugs: array<int, array<string, string>>, at: int}|null */
    private static ?array $cache = null;

    /** Marque du compte (id de ligne users, quelle que soit la communauté) : admin | support | null. */
    public static function kindOf(int $userId): ?string
    {
        if ($userId < 1) {
            return null;
        }
        $k = self::cached()['users'][$userId] ?? null;

        return is_string($k) && $k !== '' ? $k : null;
    }

    public static function label(string $kind): string
    {
        return $kind === self::ADMIN ? 'Administrateur Athena' : 'Support Athena';
    }

    /** Classes à ajouter sur l'élément qui porte le nom (couleur rouge ou verte). */
    public static function nameClass(int $userId): string
    {
        $k = self::kindOf($userId);

        return $k !== null ? ' is-staff is-staff--' . $k : '';
    }

    /**
     * Icône (aigle rouge pour l'administration, casque vert pour le support) à placer juste après un nom ;
     * $large pour un titre (fiche, en-tête de profil).
     */
    public static function mark(int $userId, bool $large = false): string
    {
        $k = self::kindOf($userId);
        if ($k === null) {
            return '';
        }
        $label = self::label($k);

        return '<span class="ath-staff-mark ath-staff-mark--' . $k . ($large ? ' ath-staff-mark--lg' : '') . '" title="' . $label . '" role="img" aria-label="' . $label . '">'
            . self::icon($k)
            . '</span>';
    }

    /** Nom échappé, coloré et suivi du badge si la personne fait partie de l'équipe Athena. */
    public static function name(string $name, int $userId, bool $large = false): string
    {
        $safe = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $k = self::kindOf($userId);
        if ($k === null) {
            return $safe;
        }

        return '<span class="ath-staff-name is-staff is-staff--' . $k . '" data-staff-done>' . $safe . '</span>' . self::mark($userId, $large);
    }

    public static function icon(string $kind): string
    {
        if ($kind === self::ADMIN) {
            // Aigle aux ailes déployées.
            return '<svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true" focusable="false"><path fill="currentColor" d="M10.6 9.6L3.4 2.6L4 5.4L1.6 5L3.4 7.5L1.2 7.8L3.8 9.8L2.2 10.6L5.6 11.8L4.6 12.8L9.9 13.4Z"/><path fill="currentColor" d="M13.4 9.6 L20.6 2.6 L20 5.4 L22.4 5 L20.6 7.5 L22.8 7.8 L20.2 9.8 L21.8 10.6 L18.4 11.8 L19.4 12.8 L14.1 13.4 Z"/><path fill="currentColor" d="M9.7 11a2.3 3.9 0 1 0 4.6 0a2.3 3.9 0 1 0-4.6 0Z"/><path fill="currentColor" d="M10.5 14L8.8 19l1.8-.9.6 2.4L12 19l.8 1.5.6-2.4 1.8.9L13.5 14Z"/><path fill="currentColor" d="M10.6 6a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0-3 0Z"/><path fill="currentColor" d="M13.1 5.3L15.4 5.9L14.6 6.9L13.4 6.6Z"/></svg>';
        }

        // Casque-micro du support.
        return '<svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2.5a8.5 8.5 0 0 0-8.5 8.5v5.2c0 1.3 1 2.3 2.3 2.3h1.4c.6 0 1-.4 1-1v-5.4c0-.6-.4-1-1-1H5.6V11a6.4 6.4 0 0 1 12.8 0v.1h-1.6c-.6 0-1 .4-1 1v5.4c0 .6.4 1 1 1h1.5c-.4 1.1-1.5 1.8-2.7 1.8h-2c-.2-.6-.8-1-1.4-1h-.7a1.5 1.5 0 0 0 0 3h.7c.6 0 1.2-.4 1.4-1h2a5 5 0 0 0 4.9-4.1c.6-.4 1-1.1 1-1.9V11A8.5 8.5 0 0 0 12 2.5Z"/></svg>';
    }

    /**
     * Correspondances pour staff-marks.js : u = id de compte -> marque, s = slug de fiche (communauté courante) -> marque.
     *
     * @return array{u: array<string, string>, s: array<string, string>}
     */
    public static function clientMap(int $tenantId = 0): array
    {
        $c = self::cached();
        $u = [];
        foreach ($c['users'] as $id => $k) {
            $u[(string) $id] = $k;
        }

        return ['u' => $u, 's' => $tenantId > 0 ? ($c['slugs'][$tenantId] ?? []) : []];
    }

    /**
     * Fixe les correspondances pour la requête en cours (tests, aperçus) sans base ni fichier.
     *
     * @param array<int, string> $users
     * @param array<int, array<string, string>> $slugs
     */
    public static function seed(array $users, array $slugs = []): void
    {
        self::$cache = ['users' => $users, 'slugs' => $slugs, 'at' => time()];
    }

    /** À appeler après un changement d'accès administrateur ou de rôle support. */
    public static function forget(): void
    {
        self::$cache = null;
        @unlink(self::cachePath());
    }

    /** @return array{users: array<int, string>, slugs: array<int, array<string, string>>, at: int} */
    private static function cached(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $path = self::cachePath();
        if (is_file($path) && (time() - (int) @filemtime($path)) < self::TTL) {
            $raw = @file_get_contents($path);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($data) && is_array($data['users'] ?? null)) {
                $users = [];
                foreach ($data['users'] as $id => $k) {
                    $users[(int) $id] = (string) $k;
                }
                $slugs = [];
                foreach (is_array($data['slugs'] ?? null) ? $data['slugs'] : [] as $tid => $list) {
                    $slugs[(int) $tid] = array_map('strval', is_array($list) ? $list : []);
                }

                return self::$cache = ['users' => $users, 'slugs' => $slugs, 'at' => (int) ($data['at'] ?? 0)];
            }
        }
        try {
            self::$cache = self::build(Database::getPdo());
        } catch (\Throwable $e) {
            error_log('[staff-marks] ' . $e->getMessage());

            return self::$cache = ['users' => [], 'slugs' => [], 'at' => time()];
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($path, json_encode(self::$cache), LOCK_EX);

        return self::$cache;
    }

    /** @return array{users: array<int, string>, slugs: array<int, array<string, string>>, at: int} */
    public static function build(PDO $pdo): array
    {
        $cols = [];
        $st = $pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
               AND COLUMN_NAME IN ('is_platform_admin', 'is_super_admin', 'profile_slug')"
        );
        foreach ($st !== false ? ($st->fetchAll(PDO::FETCH_COLUMN) ?: []) : [] as $c) {
            $cols[(string) $c] = true;
        }

        $kindByEmail = [];
        $flags = array_values(array_filter(['is_platform_admin', 'is_super_admin'], static fn (string $c): bool => isset($cols[$c])));
        if ($flags !== []) {
            $where = implode(' OR ', array_map(static fn (string $c): string => $c . ' = 1', $flags));
            $st = $pdo->query('SELECT DISTINCT LOWER(TRIM(email)) FROM users WHERE (' . $where . ") AND email IS NOT NULL AND email <> '' AND email NOT LIKE '%@deleted.invalid'");
            foreach ($st !== false ? ($st->fetchAll(PDO::FETCH_COLUMN) ?: []) : [] as $em) {
                $kindByEmail[(string) $em] = self::ADMIN;
            }
        }
        try {
            $st = $pdo->query(
                "SELECT DISTINCT LOWER(TRIM(u.email)) FROM site_contributor_roles r JOIN users u ON u.id = r.user_id
                  WHERE r.role = 'support' AND r.status = 'active' AND u.email IS NOT NULL AND u.email <> '' AND u.email NOT LIKE '%@deleted.invalid'"
            );
            foreach ($st !== false ? ($st->fetchAll(PDO::FETCH_COLUMN) ?: []) : [] as $em) {
                $kindByEmail[(string) $em] ??= self::SUPPORT;
            }
        } catch (\Throwable) {
            // Table des contributeurs pas encore créée.
        }

        $users = [];
        $slugs = [];
        foreach (array_chunk(array_keys($kindByEmail), 200) as $chunk) {
            $chunk = array_map('strval', $chunk);
            $st = $pdo->prepare(
                'SELECT id, tenant_id, LOWER(TRIM(email)) AS em' . (isset($cols['profile_slug']) ? ', profile_slug' : ', NULL AS profile_slug')
                . ' FROM users WHERE LOWER(TRIM(email)) IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')'
            );
            $st->execute($chunk);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $k = $kindByEmail[(string) $r['em']] ?? null;
                if ($k === null) {
                    continue;
                }
                $users[(int) $r['id']] = $k;
                $slug = trim((string) ($r['profile_slug'] ?? ''));
                if ($slug !== '' && (int) $r['tenant_id'] > 0) {
                    $slugs[(int) $r['tenant_id']][$slug] = $k;
                }
            }
        }

        return ['users' => $users, 'slugs' => $slugs, 'at' => time()];
    }

    private static function cachePath(): string
    {
        return base_path('storage/cache/staff-marks.json');
    }
}
