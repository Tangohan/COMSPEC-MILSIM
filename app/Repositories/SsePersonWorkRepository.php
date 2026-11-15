<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Lectures et écritures de la page « Personnes » (vue de travail) : lots de données jointes à la liste, classification,
 * verrouillage, clôture et dates d'ouverture. Toute requête est bornée à la communauté. Les colonnes viennent de l'étape 4700 :
 * sans elle, {@see self::ready()} est faux et la page reste en lecture seule (aucune écriture n'est tentée).
 */
final class SsePersonWorkRepository
{
    private const IN_CHUNK = 400;

    private Database $db;

    /** @var \WeakMap<PDO, array{ok: bool, at: int}>|null */
    private static ?\WeakMap $ready = null;

    /** @var \WeakMap<PDO, array{ok: bool, at: int}>|null */
    private static ?\WeakMap $viewsReady = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /** Les colonnes de classification, verrouillage et clôture existent-elles ? (étape 4700) */
    public static function ready(): bool
    {
        return self::probe(self::$ready, 'SELECT classification, locked_at, locked_by, lock_reason, lock_until, closed_at, closed_by, close_reason FROM sse_persons WHERE 1 = 0');
    }

    /** La table des dernières ouvertures existe-t-elle ? */
    public static function viewsReady(): bool
    {
        return self::probe(self::$viewsReady, 'SELECT id, tenant_id, user_id, person_id, last_opened_at, open_count FROM sse_person_views WHERE 1 = 0');
    }

    public static function forgetReady(): void
    {
        self::$ready = null;
        self::$viewsReady = null;
    }

    /** @param \WeakMap<PDO, array{ok: bool, at: int}>|null $cache */
    private static function probe(?\WeakMap &$cache, string $sql): bool
    {
        try {
            $pdo = Database::getPdo();
            $cache ??= new \WeakMap();
            $known = $cache[$pdo] ?? null;
            if (is_array($known) && ($known['ok'] || time() - $known['at'] < 20)) {
                return $known['ok'];
            }
            try {
                $pdo->query($sql);
                $cache[$pdo] = ['ok' => true, 'at' => time()];

                return true;
            } catch (\Throwable) {
                $cache[$pdo] = ['ok' => false, 'at' => time()];

                return false;
            }
        } catch (\Throwable) {
            return false;
        }
    }

    // ------------------------------------------------------------------ Lots joints à la liste

    /**
     * @param list<int> $photoIds identifiants des photos principales
     * @return array<int, string> personne => adresse publique de la photo principale
     */
    public function primaryPhotos(int $tenantId, array $photoIds): array
    {
        $out = [];
        foreach ($this->chunks($photoIds) as $chunk) {
            [$in, $params] = $this->in($chunk, 'p');
            foreach ($this->db->fetchAll(
                "SELECT id, person_id, image_path FROM sse_person_photos WHERE tenant_id = :t AND id IN ({$in})",
                ['t' => $tenantId] + $params
            ) as $row) {
                $path = (string) ($row['image_path'] ?? '');
                if ($path !== '') {
                    $out[(int) $row['person_id']] = (string) user_media_public_url($path);
                }
            }
        }

        return $out;
    }

    /**
     * Relevés biométriques (type et qualité) de chaque personne.
     *
     * @param list<int> $personIds
     * @return array<int, list<array{kind: string, quality: int|null}>>
     */
    public function biometricSamples(int $tenantId, array $personIds): array
    {
        $out = [];
        foreach ($this->chunks($personIds) as $chunk) {
            [$in, $params] = $this->in($chunk, 'p');
            try {
                $rows = $this->db->fetchAll(
                    "SELECT person_id, kind, quality FROM sse_biometric_samples WHERE tenant_id = :t AND person_id IN ({$in})",
                    ['t' => $tenantId] + $params
                );
            } catch (\Throwable) {
                return $out;
            }
            foreach ($rows as $row) {
                $out[(int) $row['person_id']][] = [
                    'kind' => (string) ($row['kind'] ?? 'empreintes'),
                    'quality' => isset($row['quality']) ? (int) $row['quality'] : null,
                ];
            }
        }

        return $out;
    }

    /**
     * Identités du registre rattachées aux personnes (avis de recherche, compte du membre).
     *
     * @param list<int> $personIds
     * @return array<int, list<array{id: int, kind: string, user_id: int|null}>>
     */
    public function identities(int $tenantId, array $personIds): array
    {
        $out = [];
        foreach ($this->chunks($personIds) as $chunk) {
            [$in, $params] = $this->in($chunk, 'p');
            try {
                $rows = $this->db->fetchAll(
                    "SELECT id, person_id, kind, user_id FROM sse_identities WHERE tenant_id = :t AND person_id IN ({$in}) ORDER BY id ASC",
                    ['t' => $tenantId] + $params
                );
            } catch (\Throwable) {
                return $out;
            }
            foreach ($rows as $row) {
                $out[(int) $row['person_id']][] = [
                    'id' => (int) $row['id'],
                    'kind' => (string) ($row['kind'] ?? 'civil'),
                    'user_id' => isset($row['user_id']) && (int) $row['user_id'] > 0 ? (int) $row['user_id'] : null,
                ];
            }
        }

        return $out;
    }

    /**
     * Nombre de dossiers où la personne est rattachée.
     *
     * @param list<int> $personIds
     * @return array<int, int>
     */
    public function caseLinkCounts(int $tenantId, array $personIds): array
    {
        $out = [];
        foreach ($this->chunks($personIds) as $chunk) {
            [$in, $params] = $this->in($chunk, 'p');
            try {
                $rows = $this->db->fetchAll(
                    "SELECT person_id, COUNT(*) AS n FROM sse_case_persons WHERE tenant_id = :t AND person_id IN ({$in}) GROUP BY person_id",
                    ['t' => $tenantId] + $params
                );
            } catch (\Throwable) {
                return $out;
            }
            foreach ($rows as $row) {
                $out[(int) $row['person_id']] = (int) $row['n'];
            }
        }

        return $out;
    }

    /**
     * Dernières ouvertures : une ligne par compte et par personne.
     *
     * @param list<int> $personIds
     * @return array<int, list<array{user_id: int, at: string, count: int}>>
     */
    public function views(int $tenantId, array $personIds): array
    {
        if (!self::viewsReady()) {
            return [];
        }
        $out = [];
        foreach ($this->chunks($personIds) as $chunk) {
            [$in, $params] = $this->in($chunk, 'p');
            foreach ($this->db->fetchAll(
                "SELECT person_id, user_id, last_opened_at, open_count FROM sse_person_views WHERE tenant_id = :t AND person_id IN ({$in})",
                ['t' => $tenantId] + $params
            ) as $row) {
                $out[(int) $row['person_id']][] = [
                    'user_id' => (int) $row['user_id'],
                    'at' => (string) $row['last_opened_at'],
                    'count' => (int) $row['open_count'],
                ];
            }
        }

        return $out;
    }

    /**
     * Enregistre l'ouverture d'une fiche par un compte. Une seule ligne par (communauté, compte, personne) : une réouverture
     * dans la fenêtre ne change rien (pas d'écriture à chaque rafraîchissement).
     *
     * @return bool true si la date a été mise à jour
     */
    public function touchView(int $tenantId, int $userId, int $personId, string $now, int $windowSeconds): bool
    {
        if ($tenantId < 1 || $userId < 1 || $personId < 1 || !self::viewsReady()) {
            return false;
        }
        try {
            $row = $this->db->fetchOne(
                'SELECT id, last_opened_at FROM sse_person_views WHERE tenant_id = :t AND user_id = :u AND person_id = :p',
                ['t' => $tenantId, 'u' => $userId, 'p' => $personId]
            );
            if ($row === null) {
                try {
                    $this->db->execute(
                        'INSERT INTO sse_person_views (tenant_id, user_id, person_id, last_opened_at, open_count) VALUES (:t, :u, :p, :at, 1)',
                        ['t' => $tenantId, 'u' => $userId, 'p' => $personId, 'at' => $now]
                    );

                    return true;
                } catch (\Throwable) {
                    // Deux ouvertures simultanées : l'unicité a refusé la seconde, on met à jour la ligne existante.
                    $row = ['id' => 0, 'last_opened_at' => ''];
                }
            }
            $last = strtotime((string) $row['last_opened_at'] . ' UTC');
            $current = strtotime($now . ' UTC');
            if ($last !== false && $current !== false && $current - $last < $windowSeconds) {
                return false;
            }

            return $this->db->execute(
                'UPDATE sse_person_views SET last_opened_at = :at, open_count = open_count + 1 WHERE tenant_id = :t AND user_id = :u AND person_id = :p',
                ['at' => $now, 't' => $tenantId, 'u' => $userId, 'p' => $personId]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Noms des comptes (indicatif et nom affiché).
     *
     * @param list<int> $userIds
     * @return array<int, string>
     */
    public function userLabels(int $tenantId, array $userIds): array
    {
        $out = [];
        foreach ($this->chunks($userIds) as $chunk) {
            [$in, $params] = $this->in($chunk, 'u');
            try {
                $rows = $this->db->fetchAll(
                    "SELECT id, display_name, callsign FROM users WHERE tenant_id = :t AND id IN ({$in})",
                    ['t' => $tenantId] + $params
                );
            } catch (\Throwable) {
                return $out;
            }
            foreach ($rows as $row) {
                $out[(int) $row['id']] = FileShareRepository::userLabel($row);
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ Écritures

    /** @return bool true si la fiche de la communauté a été modifiée */
    public function setClassification(int $tenantId, int $personId, ?string $classification): bool
    {
        return $this->update($tenantId, $personId, ['classification' => $classification]);
    }

    public function lock(int $tenantId, int $personId, int $userId, string $reason, ?string $until, string $now): bool
    {
        return $this->update($tenantId, $personId, [
            'locked_at' => $now, 'locked_by' => $userId, 'lock_reason' => $reason, 'lock_until' => $until,
        ]);
    }

    public function unlock(int $tenantId, int $personId): bool
    {
        return $this->update($tenantId, $personId, ['locked_at' => null, 'locked_by' => null, 'lock_reason' => null, 'lock_until' => null]);
    }

    public function close(int $tenantId, int $personId, int $userId, string $reasonCode, string $now): bool
    {
        return $this->update($tenantId, $personId, ['closed_at' => $now, 'closed_by' => $userId, 'close_reason' => $reasonCode]);
    }

    public function reopen(int $tenantId, int $personId): bool
    {
        return $this->update($tenantId, $personId, ['closed_at' => null, 'closed_by' => null, 'close_reason' => null]);
    }

    /**
     * @param array<string, mixed> $columns colonnes de la liste blanche ci-dessous, jamais un nom venu de l'extérieur
     */
    private function update(int $tenantId, int $personId, array $columns): bool
    {
        if ($tenantId < 1 || $personId < 1 || !self::ready()) {
            return false;
        }
        $allowed = ['classification', 'locked_at', 'locked_by', 'lock_reason', 'lock_until', 'closed_at', 'closed_by', 'close_reason'];
        $sets = [];
        $params = ['id' => $personId, 't' => $tenantId];
        foreach ($columns as $col => $value) {
            if (!in_array($col, $allowed, true)) {
                continue;
            }
            $sets[] = $col . ' = :c_' . $col;
            $params['c_' . $col] = $value;
        }
        if ($sets === []) {
            return false;
        }
        try {
            // « rowCount » vaut 0 quand rien ne change : on vérifie l'existence de la fiche de la communauté à part.
            $this->db->execute('UPDATE sse_persons SET ' . implode(', ', $sets) . ' WHERE id = :id AND tenant_id = :t', $params);
            $row = $this->db->fetchOne('SELECT id FROM sse_persons WHERE id = :id AND tenant_id = :t', ['id' => $personId, 't' => $tenantId]);

            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    // ------------------------------------------------------------------ Outils

    /**
     * @param list<int> $ids
     * @return list<list<int>>
     */
    private function chunks(array $ids): array
    {
        $clean = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));

        return $clean === [] ? [] : array_chunk($clean, self::IN_CHUNK);
    }

    /**
     * @param list<int> $ids
     * @return array{0: string, 1: array<string, int>}
     */
    private function in(array $ids, string $prefix): array
    {
        $marks = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $marks[] = ':' . $prefix . $i;
            $params[$prefix . $i] = $id;
        }

        return [implode(',', $marks), $params];
    }
}
