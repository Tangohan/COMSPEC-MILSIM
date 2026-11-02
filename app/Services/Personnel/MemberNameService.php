<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Support\MemberName;
use PDO;

/**
 * Noms affichés et trigrammes des membres d’une communauté.
 *
 * Trigramme : un par membre et par communauté (table member_trigrams, unique par communauté). Attribué
 * automatiquement depuis le prénom et le nom du personnage ({@see MemberName::pickTrigram()}), puis stable :
 * un changement de nom ne le modifie pas, seule une demande (saisie à la main, « recalculer ») le change.
 */
final class MemberNameService
{
    public const TABLE = 'member_trigrams';

    /** @var array<string, bool> */
    private static array $schema = [];

    public function __construct(private ?PDO $pdo = null)
    {
    }

    public function schemaReady(): bool
    {
        return $this->tableExists(self::TABLE);
    }

    /**
     * @param list<int> $userIds
     * @return array<int, string> user_id => trigramme
     */
    public function trigramsFor(int $tenantId, array $userIds): array
    {
        $userIds = self::ids($userIds);
        if ($tenantId < 1 || $userIds === [] || !$this->schemaReady()) {
            return [];
        }
        $out = [];
        foreach (array_chunk($userIds, 500) as $chunk) {
            $st = $this->db()->prepare(
                'SELECT user_id, trigram FROM ' . self::TABLE . ' WHERE tenant_id = ? AND user_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')'
            );
            $st->execute(array_merge([$tenantId], $chunk));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $out[(int) $r['user_id']] = (string) $r['trigram'];
            }
        }

        return $out;
    }

    public function trigramFor(int $tenantId, int $userId): string
    {
        return $this->trigramsFor($tenantId, [$userId])[$userId] ?? '';
    }

    /**
     * Identité affichable (prénom, nom, pseudo, indicatif, trigramme) des membres demandés, dans cette communauté.
     *
     * @param list<int> $userIds
     * @return array<int, array<string, mixed>>
     */
    public function identitiesFor(int $tenantId, array $userIds): array
    {
        $userIds = self::ids($userIds);
        if ($tenantId < 1 || $userIds === [] || !$this->tableExists('users')) {
            return [];
        }
        $hasUp = $this->tableExists('user_profiles');
        $hasUcp = $this->tableExists('user_community_profiles');
        $select = 'u.id'
            . ($this->columnExists('users', 'display_name') ? ', u.display_name AS u_display_name' : '')
            . ($this->columnExists('users', 'callsign') ? ', u.callsign AS u_callsign' : '');
        $join = '';
        if ($hasUp) {
            $select .= ', up.first_name, up.last_name';
            $join .= ' LEFT JOIN user_profiles up ON up.user_id = u.id';
        }
        if ($hasUcp) {
            $select .= ', ucp.display_name AS ucp_display_name, ucp.callsign AS ucp_callsign';
            $join .= ' LEFT JOIN user_community_profiles ucp ON ucp.user_id = u.id AND ucp.tenant_id = ' . (int) $tenantId;
        }
        $out = [];
        foreach (array_chunk($userIds, 500) as $chunk) {
            $st = $this->db()->prepare(
                'SELECT ' . $select . ' FROM users u' . $join . ' WHERE u.id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')'
            );
            $st->execute($chunk);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $id = (int) $r['id'];
                $dn = trim((string) ($r['ucp_display_name'] ?? ''));
                $cs = trim((string) ($r['ucp_callsign'] ?? ''));
                $out[$id] = [
                    'id' => $id,
                    'first_name' => trim((string) ($r['first_name'] ?? '')),
                    'last_name' => trim((string) ($r['last_name'] ?? '')),
                    'display_name' => $dn !== '' ? $dn : trim((string) ($r['u_display_name'] ?? '')),
                    'callsign' => $cs !== '' ? $cs : trim((string) ($r['u_callsign'] ?? '')),
                    'trigram' => '',
                ];
            }
        }
        foreach ($this->trigramsFor($tenantId, array_keys($out)) as $id => $t) {
            $out[$id]['trigram'] = $t;
        }

        return $out;
    }

    /**
     * Libellé de chaque membre au format de la communauté (« list » ou « card »), grade devant si demandé.
     *
     * @param list<int> $userIds
     * @param array<int, string> $gradeShortByUser grade abrégé par membre, si l’appelant l’a déjà
     * @return array<int, string>
     */
    public function labelsFor(int $tenantId, array $userIds, string $context = 'list', array $gradeShortByUser = []): array
    {
        $settings = MemberNameSettings::forTenant($tenantId);
        $format = MemberNameSettings::formatFor($settings, $context);
        $out = [];
        foreach ($this->identitiesFor($tenantId, $userIds) as $id => $row) {
            $row['grade_short'] = $gradeShortByUser[$id] ?? '';
            if (!$settings['trigrams_enabled']) {
                $row['trigram'] = '';
            }
            $out[$id] = MemberName::format($row, $format, $settings['show_grade']);
        }

        return $out;
    }

    /**
     * Attribue un trigramme au membre s’il n’en a pas (jamais de remplacement).
     */
    public function ensureTrigram(int $tenantId, int $userId): ?string
    {
        if ($tenantId < 1 || $userId < 1 || !$this->schemaReady()) {
            return null;
        }
        $current = $this->trigramFor($tenantId, $userId);
        if ($current !== '') {
            return $current;
        }
        $identity = $this->identitiesFor($tenantId, [$userId])[$userId] ?? null;
        if ($identity === null) {
            return null;
        }

        return $this->insertFirstFree($tenantId, $userId, $identity, $this->takenFor($tenantId));
    }

    /**
     * Attribue un trigramme à chaque membre de la communauté qui n’en a pas (les plus anciens d’abord).
     *
     * @return array{assigned: int, failed: int}
     */
    public function assignMissing(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return ['assigned' => 0, 'failed' => 0];
        }
        $have = $this->takenFor($tenantId, true);
        $taken = $this->takenFor($tenantId);
        $ids = array_values(array_filter($this->memberIds($tenantId), static fn (int $id): bool => !isset($have[$id])));
        $assigned = 0;
        $failed = 0;
        $identities = $this->identitiesFor($tenantId, $ids);
        foreach ($ids as $id) {
            $identity = $identities[$id] ?? null;
            $t = $identity !== null ? $this->insertFirstFree($tenantId, $id, $identity, $taken) : null;
            if ($t !== null) {
                $taken[$t] = true;
                $assigned++;
            } else {
                $failed++;
            }
        }

        return ['assigned' => $assigned, 'failed' => $failed];
    }

    /**
     * Trigramme choisi à la main (membre ou responsable) : trois lettres, libre dans la communauté.
     *
     * @return array{ok: bool, trigram?: string, error?: string}
     */
    public function setTrigram(int $tenantId, int $userId, string $raw): array
    {
        if ($tenantId < 1 || $userId < 1 || !$this->schemaReady()) {
            return ['ok' => false, 'error' => 'Les trigrammes ne sont pas encore disponibles sur cette communauté.'];
        }
        $t = MemberName::normalizeTrigram($raw);
        if ($t === null) {
            return ['ok' => false, 'error' => 'Un trigramme compte exactement trois lettres (A à Z).'];
        }
        $owner = $this->ownerOf($tenantId, $t);
        if ($owner !== null && $owner !== $userId) {
            return ['ok' => false, 'error' => 'Ce trigramme est déjà attribué à un autre membre de la communauté.'];
        }
        if ($owner === $userId) {
            return ['ok' => true, 'trigram' => $t];
        }
        try {
            $this->upsert($tenantId, $userId, $t, 'manual');
        } catch (\PDOException) {
            return ['ok' => false, 'error' => 'Ce trigramme vient d’être attribué à un autre membre. Choisissez-en un autre.'];
        }

        return ['ok' => true, 'trigram' => $t];
    }

    /**
     * Recalcule le trigramme depuis le nom actuel (sur demande) : le premier libre, l’actuel compris.
     */
    public function regenerate(int $tenantId, int $userId): ?string
    {
        if ($tenantId < 1 || $userId < 1 || !$this->schemaReady()) {
            return null;
        }
        $identity = $this->identitiesFor($tenantId, [$userId])[$userId] ?? null;
        if ($identity === null) {
            return null;
        }
        $taken = $this->takenFor($tenantId);
        $current = $this->trigramFor($tenantId, $userId);
        unset($taken[$current]);
        $t = MemberName::pickTrigram($identity, $taken);
        if ($t === null) {
            return $current !== '' ? $current : null;
        }
        if ($t !== $current) {
            $this->upsert($tenantId, $userId, $t, 'auto');
        }

        return $t;
    }

    /** Suggestion pour un membre sans trigramme (sans rien enregistrer). */
    public function suggest(int $tenantId, int $userId): ?string
    {
        $identity = $this->identitiesFor($tenantId, [$userId])[$userId] ?? null;

        return $identity !== null ? MemberName::pickTrigram($identity, $this->takenFor($tenantId)) : null;
    }

    /**
     * Membres de la communauté avec nom et trigramme, pour la page de gestion (triés par nom).
     *
     * @return list<array<string, mixed>>
     */
    public function registry(int $tenantId, int $limit = 1000): array
    {
        $ids = array_slice($this->memberIds($tenantId), 0, max(1, $limit));
        $rows = array_values($this->identitiesFor($tenantId, $ids));
        usort($rows, static fn (array $a, array $b): int => strnatcasecmp(MemberName::full($a), MemberName::full($b)));

        return $rows;
    }

    /**
     * Remplissage initial (migration) : toutes les communautés, sans jamais remplacer un trigramme existant.
     *
     * @return array{tenants: int, assigned: int, failed: int}
     */
    public function backfillAll(): array
    {
        $sum = ['tenants' => 0, 'assigned' => 0, 'failed' => 0];
        if (!$this->schemaReady() || !$this->tableExists('tenants')) {
            return $sum;
        }
        $ids = $this->db()->query('SELECT id FROM tenants ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($ids as $tid) {
            $r = $this->assignMissing((int) $tid);
            $sum['tenants']++;
            $sum['assigned'] += $r['assigned'];
            $sum['failed'] += $r['failed'];
        }

        return $sum;
    }

    /**
     * Identifiants des membres de la communauté (compte d’origine ou adhésion active), du plus ancien au plus récent.
     *
     * @return list<int>
     */
    public function memberIds(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->tableExists('users')) {
            return [];
        }
        $member = [];
        if ($this->columnExists('users', 'tenant_id')) {
            $member[] = 'u.tenant_id = ' . (int) $tenantId;
        }
        if ($this->tableExists('user_community_memberships')) {
            $member[] = 'EXISTS (SELECT 1 FROM user_community_memberships m WHERE m.user_id = u.id AND m.tenant_id = '
                . (int) $tenantId . " AND m.status = 'active')";
        }
        if ($member === []) {
            return [];
        }
        $where = '(' . implode(' OR ', $member) . ')';
        if ($this->columnExists('users', 'deleted_at')) {
            $where .= ' AND u.deleted_at IS NULL';
        }
        if ($this->columnExists('users', 'display_name')) {
            $where .= " AND (u.display_name IS NULL OR u.display_name <> 'Compte supprimé')";
        }
        $st = $this->db()->query('SELECT u.id FROM users u WHERE ' . $where . ' ORDER BY u.id');

        return array_map('intval', $st !== false ? ($st->fetchAll(PDO::FETCH_COLUMN) ?: []) : []);
    }

    /**
     * @param array<string, mixed> $identity
     * @param array<string, bool> $taken
     */
    private function insertFirstFree(int $tenantId, int $userId, array $identity, array $taken): ?string
    {
        // Deux enregistrements simultanés peuvent viser le même trigramme : l’index unique tranche, on passe au suivant.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $t = MemberName::pickTrigram($identity, $taken);
            if ($t === null) {
                return null;
            }
            try {
                $st = $this->db()->prepare(
                    'INSERT INTO ' . self::TABLE . ' (tenant_id, user_id, trigram, source, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $now = date('Y-m-d H:i:s');
                $st->execute([$tenantId, $userId, $t, 'auto', $now, $now]);

                return $t;
            } catch (\PDOException) {
                $existing = $this->trigramFor($tenantId, $userId);
                if ($existing !== '') {
                    return $existing;
                }
                $taken[$t] = true;
            }
        }

        return null;
    }

    private function upsert(int $tenantId, int $userId, string $trigram, string $source): void
    {
        $now = date('Y-m-d H:i:s');
        $upd = $this->db()->prepare('UPDATE ' . self::TABLE . ' SET trigram = ?, source = ?, updated_at = ? WHERE tenant_id = ? AND user_id = ?');
        $upd->execute([$trigram, $source, $now, $tenantId, $userId]);
        if ($upd->rowCount() > 0 || $this->trigramFor($tenantId, $userId) === $trigram) {
            return;
        }
        $this->db()->prepare(
            'INSERT INTO ' . self::TABLE . ' (tenant_id, user_id, trigram, source, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$tenantId, $userId, $trigram, $source, $now, $now]);
    }

    private function ownerOf(int $tenantId, string $trigram): ?int
    {
        $st = $this->db()->prepare('SELECT user_id FROM ' . self::TABLE . ' WHERE tenant_id = ? AND trigram = ? LIMIT 1');
        $st->execute([$tenantId, $trigram]);
        $v = $st->fetchColumn();

        return $v === false ? null : (int) $v;
    }

    /**
     * @return array<string|int, bool> trigramme => true (ou user_id => true avec $byUser)
     */
    private function takenFor(int $tenantId, bool $byUser = false): array
    {
        $st = $this->db()->prepare('SELECT user_id, trigram FROM ' . self::TABLE . ' WHERE tenant_id = ?');
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[$byUser ? (int) $r['user_id'] : (string) $r['trigram']] = true;
        }

        return $out;
    }

    private function tableExists(string $table): bool
    {
        $key = spl_object_id($this->db()) . ':t:' . $table;
        if (!isset(self::$schema[$key])) {
            try {
                self::$schema[$key] = $this->db()->query('SELECT 1 FROM ' . $table . ' LIMIT 1') !== false;
            } catch (\Throwable) {
                self::$schema[$key] = false;
            }
        }

        return self::$schema[$key];
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = spl_object_id($this->db()) . ':c:' . $table . '.' . $column;
        if (!isset(self::$schema[$key])) {
            try {
                self::$schema[$key] = $this->db()->query('SELECT ' . $column . ' FROM ' . $table . ' LIMIT 1') !== false;
            } catch (\Throwable) {
                self::$schema[$key] = false;
            }
        }

        return self::$schema[$key];
    }

    /** Oublie la structure mémorisée (après une migration, en test). */
    public static function forgetSchema(): void
    {
        self::$schema = [];
    }

    private function db(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /**
     * @param list<int|string> $ids
     * @return list<int>
     */
    private static function ids(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $v): bool => $v > 0)));
    }
}
