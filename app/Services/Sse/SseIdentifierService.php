<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use PDO;
use PDOException;

/**
 * Identifiants SSE d'une donnée (fiche) : numéro d'identification, nom de code, nom de fiche, numéro DSE, IA reliée.
 *
 * Table sse_data_identifiers, une ligne par donnée (type + identifiant). Unicité par communauté, garantie par la base
 * (index uniques) et non par le code : numéro d'identification, nom de code, DSE. Aucun identifiant n'est réutilisé :
 * la séquence ne recule jamais (compteur sse_identifier_counters), les lignes ne sont jamais supprimées avec la donnée,
 * et un nom de code abandonné reste réservé (sse_identifier_history). Le DSE et le numéro ne changent jamais.
 */
final class SseIdentifierService
{
    /**
     * Types de données : table, colonne du titre saisi par l'auteur (ou null), adresse de la fiche dans le portail.
     *
     * @var array<string, array{table: string, title: ?string, href: string, label: string}>
     */
    public const TYPES = [
        'person' => ['table' => 'sse_persons', 'title' => null, 'href' => 'atak/sse/identites/%d', 'label' => 'Personne'],
        'identity' => ['table' => 'sse_identities', 'title' => null, 'href' => 'atak/sse/papiers/identites/%d', 'label' => 'Identité du registre'],
        'field_note' => ['table' => 'sse_field_notes', 'title' => 'title', 'href' => 'atak/sse/fiches/%d', 'label' => 'Fiche de renseignement'],
        'report' => ['table' => 'atak_tactical_reports', 'title' => null, 'href' => 'atak/sse/remontees/cr/%d', 'label' => 'Compte rendu'],
        'document' => ['table' => 'sse_documents', 'title' => 'title', 'href' => 'atak/sse/documents/%d', 'label' => 'Document'],
    ];

    private const MAX_ATTEMPTS = 12;

    private ?PDO $pdo;
    /** @var callable|null */
    private $audit;

    /** @param callable|null $audit fn(array $entry): void ; par défaut le journal SSE */
    public function __construct(?PDO $pdo = null, ?callable $audit = null)
    {
        $this->pdo = $pdo;
        $this->audit = $audit;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /** Création sûre depuis un dépôt : n'échoue jamais (table absente avant l'étape de migration, base indisponible). */
    public static function onCreated(int $tenantId, string $type, int $entityId, ?string $title = null): void
    {
        if ($tenantId < 1 || $entityId < 1) {
            return;
        }
        try {
            (new self())->assign($tenantId, $type, $entityId, $title);
        } catch (\Throwable $e) {
            error_log('[sse.identifiants] attribution impossible : ' . $e->getMessage());
        }
    }

    /**
     * Attribue les identifiants à une donnée ; sans effet (et sans rien écraser) si elle en a déjà.
     *
     * @return array<string, mixed>
     */
    public function assign(int $tenantId, string $type, int $entityId, ?string $authorTitle = null): array
    {
        if (!isset(self::TYPES[$type])) {
            throw new \InvalidArgumentException('Type de donnée SSE inconnu.');
        }
        $existing = $this->forEntity($tenantId, $type, $entityId);
        if ($existing !== null) {
            return $existing;
        }
        $title = trim((string) $authorTitle);
        $title = $title !== '' ? mb_substr($title, 0, 160) : null;

        $lastError = null;
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            [$year, $seq] = $this->nextSequence($tenantId);
            $idNo = SseIdentifierGenerator::idNumber($year, $seq);
            $name = $title ?? SseIdentifierGenerator::genericName($type, $idNo);
            $now = gmdate('Y-m-d H:i:s');
            try {
                $st = $this->pdo()->prepare(
                    'INSERT INTO sse_data_identifiers (tenant_id, entity_type, entity_id, ident_no, ident_year, ident_seq, codename, sheet_name, sheet_name_auto, dse, created_at, updated_at)
                     VALUES (:t, :type, :id, :no, :y, :s, :code, :name, :auto, :dse, :now, :now2)'
                );
                $st->execute([
                    't' => $tenantId, 'type' => $type, 'id' => $entityId, 'no' => $idNo, 'y' => $year, 's' => $seq,
                    'code' => $this->freeCodename($tenantId, $attempt), 'name' => $name, 'auto' => $title === null ? 1 : 0,
                    'dse' => $this->freeDse($tenantId, $attempt), 'now' => $now, 'now2' => $now,
                ]);

                return $this->forEntity($tenantId, $type, $entityId) ?? [];
            } catch (PDOException $e) {
                $lastError = $e;
                if (!self::isDuplicate($e)) {
                    throw $e;
                }
                // Une autre requête a attribué la même donnée entre-temps : on rend la sienne.
                $raced = $this->forEntity($tenantId, $type, $entityId);
                if ($raced !== null) {
                    return $raced;
                }
            }
        }

        throw new \RuntimeException('Attribution des identifiants SSE impossible après plusieurs essais.', 0, $lastError);
    }

    /** @return array<string, mixed>|null */
    public function forEntity(int $tenantId, string $type, int $entityId): ?array
    {
        return $this->one('SELECT * FROM sse_data_identifiers WHERE tenant_id = :t AND entity_type = :ty AND entity_id = :id', ['t' => $tenantId, 'ty' => $type, 'id' => $entityId]);
    }

    /** @return array<string, mixed>|null */
    public function findByDse(int $tenantId, string $input): ?array
    {
        $dse = SseIdentifierGenerator::normalizeDse($input);
        if ($dse === null) {
            return null;
        }

        return $this->one('SELECT * FROM sse_data_identifiers WHERE tenant_id = :t AND dse = :d', ['t' => $tenantId, 'd' => $dse]);
    }

    /**
     * Recherche par n'importe quel identifiant : numéro, nom de code, DSE, nom de fiche. Lignes brutes : l'appelant filtre
     * ensuite selon les droits de lecture de chaque donnée.
     *
     * @return list<array<string, mixed>>
     */
    public function search(int $tenantId, string $q, int $limit = 20): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $upper = strtoupper($q);
        $esc = static fn (string $s): string => str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s);
        $like = '%' . $esc($upper) . '%';
        $params = ['t' => $tenantId, 'exact' => $upper, 'exact2' => $upper, 'like' => $like, 'likeName' => '%' . $esc($q) . '%'];
        $dse = SseIdentifierGenerator::normalizeDse($q);
        $params['dse'] = $dse ?? '-';
        $sql = "SELECT * FROM sse_data_identifiers WHERE tenant_id = :t AND (
                    dse = :dse OR ident_no = :exact OR codename = :exact2 OR ident_no LIKE :like ESCAPE '!' OR codename LIKE :like2 ESCAPE '!' OR sheet_name LIKE :likeName ESCAPE '!'
                ) ORDER BY id DESC LIMIT " . max(1, min(60, $limit));
        $params['like2'] = $like;

        return $this->all($sql, $params);
    }

    /**
     * Modifie le nom de code et/ou le nom de fiche (journalisé). Le DSE et le numéro ne sont jamais modifiables.
     *
     * @return array{ok: bool, error?: string, row?: array<string, mixed>}
     */
    public function rename(int $tenantId, string $type, int $entityId, ?string $codename, ?string $sheetName, ?int $actorId, string $actorLabel): array
    {
        $row = $this->forEntity($tenantId, $type, $entityId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $sets = [];
        $params = ['id' => (int) $row['id'], 't' => $tenantId, 'now' => gmdate('Y-m-d H:i:s')];
        $changes = [];
        if ($codename !== null && trim($codename) !== '') {
            $new = SseIdentifierGenerator::normalizeCodename($codename);
            if ($new === null) {
                return ['ok' => false, 'error' => 'invalid_codename'];
            }
            if ($new !== $row['codename']) {
                if ($this->codenameReserved($tenantId, $new)) {
                    return ['ok' => false, 'error' => 'codename_taken'];
                }
                $sets[] = 'codename = :code';
                $params['code'] = $new;
                $changes[] = ['codename', (string) $row['codename'], $new];
            }
        }
        if ($sheetName !== null) {
            $name = mb_substr(trim($sheetName), 0, 160);
            if ($name === '') {
                $name = SseIdentifierGenerator::genericName($type, (string) $row['ident_no']);
                $auto = 1;
            } else {
                $auto = 0;
            }
            if ($name !== $row['sheet_name']) {
                $sets[] = 'sheet_name = :name, sheet_name_auto = :auto';
                $params['name'] = $name;
                $params['auto'] = $auto;
                $changes[] = ['sheet_name', (string) $row['sheet_name'], $name];
            }
        }
        if ($sets === []) {
            return ['ok' => true, 'row' => $row];
        }
        try {
            $this->exec('UPDATE sse_data_identifiers SET ' . implode(', ', $sets) . ', updated_at = :now WHERE id = :id AND tenant_id = :t', $params);
        } catch (PDOException $e) {
            if (self::isDuplicate($e)) {
                return ['ok' => false, 'error' => 'codename_taken'];
            }
            throw $e;
        }
        foreach ($changes as [$field, $old, $new]) {
            $this->history($tenantId, $type, $entityId, $field, $old, $new, $actorId, $actorLabel);
        }

        return ['ok' => true, 'row' => $this->forEntity($tenantId, $type, $entityId) ?? $row];
    }

    /**
     * Relie (ou délie, avec null) un profil d'IA existant de la communauté.
     *
     * @return array{ok: bool, error?: string, row?: array<string, mixed>}
     */
    public function setAiProfile(int $tenantId, string $type, int $entityId, ?int $profileId, ?int $actorId, string $actorLabel): array
    {
        $row = $this->forEntity($tenantId, $type, $entityId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        if ($profileId !== null && $profileId > 0) {
            if ($this->aiProfile($tenantId, $profileId) === null) {
                return ['ok' => false, 'error' => 'ai_profile_not_found'];
            }
        } else {
            $profileId = null;
        }
        $old = $row['ai_profile_id'] !== null ? (int) $row['ai_profile_id'] : null;
        if ($old !== $profileId) {
            $this->exec('UPDATE sse_data_identifiers SET ai_profile_id = :p, updated_at = :now WHERE id = :id AND tenant_id = :t', [
                'p' => $profileId, 'now' => gmdate('Y-m-d H:i:s'), 'id' => (int) $row['id'], 't' => $tenantId,
            ]);
            $this->history($tenantId, $type, $entityId, 'ai_profile_id', (string) ($old ?? ''), (string) ($profileId ?? ''), $actorId, $actorLabel);
        }

        return ['ok' => true, 'row' => $this->forEntity($tenantId, $type, $entityId) ?? $row];
    }

    /** @return array<string, mixed>|null */
    public function aiProfile(int $tenantId, int $profileId): ?array
    {
        try {
            return $this->one('SELECT id, label, side, category, identity_id FROM sse_ai_profiles WHERE id = :id AND tenant_id = :t AND archived_at IS NULL', ['id' => $profileId, 't' => $tenantId]);
        } catch (PDOException) {
            return null;
        }
    }

    /** @return list<array<string, mixed>> */
    public function aiProfiles(int $tenantId): array
    {
        try {
            return $this->all('SELECT id, label FROM sse_ai_profiles WHERE tenant_id = :t AND archived_at IS NULL ORDER BY label LIMIT 300', ['t' => $tenantId]);
        } catch (PDOException) {
            return [];
        }
    }

    /** La donnée existe-t-elle encore dans la communauté ? */
    public function entityExists(int $tenantId, string $type, int $entityId): bool
    {
        if (!isset(self::TYPES[$type])) {
            return false;
        }

        return $this->one('SELECT id FROM ' . self::TYPES[$type]['table'] . ' WHERE id = :id AND tenant_id = :t', ['id' => $entityId, 't' => $tenantId]) !== null;
    }

    /**
     * Rattrapage : attribue les identifiants aux données qui n'en ont pas, dans l'ordre de création. Ne touche jamais une
     * donnée déjà pourvue ; relançable. Retourne le nombre attribué par type.
     *
     * @return array<string, int>
     */
    public function backfill(?int $tenantId = null, bool $dryRun = false, int $batch = 500): array
    {
        $done = [];
        foreach (self::TYPES as $type => $def) {
            $done[$type] = 0;
            if (!$this->tableExists($def['table'])) {
                continue;
            }
            $titleCol = $def['title'] !== null ? ', e.' . $def['title'] . ' AS title' : ', NULL AS title';
            $lastId = 0;
            while (true) {
                $rows = $this->all(
                    'SELECT e.id, e.tenant_id' . $titleCol . ' FROM ' . $def['table'] . ' e
                     LEFT JOIN sse_data_identifiers i ON i.tenant_id = e.tenant_id AND i.entity_type = :ty AND i.entity_id = e.id
                     WHERE i.id IS NULL AND e.id > :last AND e.tenant_id IS NOT NULL' . ($tenantId !== null ? ' AND e.tenant_id = ' . (int) $tenantId : '') . '
                     ORDER BY e.id LIMIT ' . max(1, $batch),
                    ['ty' => $type, 'last' => $lastId]
                );
                if ($rows === []) {
                    break;
                }
                foreach ($rows as $r) {
                    $lastId = (int) $r['id'];
                    if ((int) $r['tenant_id'] < 1) {
                        continue;
                    }
                    if (!$dryRun) {
                        $title = isset($r['title']) ? (string) $r['title'] : null;
                        $this->assign((int) $r['tenant_id'], $type, (int) $r['id'], $title === 'Sans titre' ? null : $title);
                    }
                    $done[$type]++;
                }
            }
        }

        return $done;
    }

    /** @param array<string, mixed> $entry */
    public function journal(array $entry): void
    {
        try {
            if ($this->audit !== null) {
                ($this->audit)($entry);

                return;
            }
            (new \App\Repositories\SseAuditLogRepository())->write($entry);
        } catch (\Throwable $e) {
            error_log('[sse.identifiants] journal : ' . $e->getMessage());
        }
    }

    /** Schéma (utilisé par l'étape 5020 et les tests), compatible SQLite et MySQL / MariaDB. */
    public static function schema(string $driver): array
    {
        $mysql = $driver === 'mysql';
        $pk = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $text = static fn (int $n): string => 'VARCHAR(' . $n . ')';

        return [
            'sse_data_identifiers' => "CREATE TABLE sse_data_identifiers (
                id $pk,
                tenant_id INTEGER NOT NULL,
                entity_type {$text(24)} NOT NULL,
                entity_id INTEGER NOT NULL,
                ident_no {$text(24)} NOT NULL,
                ident_year INTEGER NOT NULL,
                ident_seq INTEGER NOT NULL,
                codename {$text(48)} NOT NULL,
                sheet_name {$text(190)} NOT NULL,
                sheet_name_auto INTEGER NOT NULL DEFAULT 1,
                dse {$text(16)} NOT NULL,
                ai_profile_id INTEGER NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE (tenant_id, entity_type, entity_id),
                UNIQUE (tenant_id, ident_no),
                UNIQUE (tenant_id, codename),
                UNIQUE (tenant_id, dse)
            )$tail",
            'sse_identifier_counters' => "CREATE TABLE sse_identifier_counters (
                tenant_id INTEGER NOT NULL,
                ident_year INTEGER NOT NULL,
                last_seq INTEGER NOT NULL,
                PRIMARY KEY (tenant_id, ident_year)
            )$tail",
            'sse_identifier_history' => "CREATE TABLE sse_identifier_history (
                id $pk,
                tenant_id INTEGER NOT NULL,
                entity_type {$text(24)} NOT NULL,
                entity_id INTEGER NOT NULL,
                field {$text(24)} NOT NULL,
                old_value {$text(190)} NULL,
                new_value {$text(190)} NULL,
                actor_user_id INTEGER NULL,
                actor_label {$text(120)} NULL,
                created_at DATETIME NOT NULL
            )$tail",
        ];
    }

    // --- interne ---------------------------------------------------------------------------------------------------

    /** @return array{0: int, 1: int} année et séquence réservées ; le compteur ne recule jamais, même si la suite échoue. */
    private function nextSequence(int $tenantId): array
    {
        $year = (int) gmdate('Y');
        $pdo = $this->pdo();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }
        try {
            $up = $pdo->prepare('UPDATE sse_identifier_counters SET last_seq = last_seq + 1 WHERE tenant_id = :t AND ident_year = :y');
            $up->execute(['t' => $tenantId, 'y' => $year]);
            if ($up->rowCount() === 0) {
                try {
                    $pdo->prepare('INSERT INTO sse_identifier_counters (tenant_id, ident_year, last_seq) VALUES (:t, :y, 1)')->execute(['t' => $tenantId, 'y' => $year]);
                } catch (PDOException $e) {
                    if (!self::isDuplicate($e)) {
                        throw $e;
                    }
                    $up->execute(['t' => $tenantId, 'y' => $year]);
                }
            }
            $st = $pdo->prepare('SELECT last_seq FROM sse_identifier_counters WHERE tenant_id = :t AND ident_year = :y');
            $st->execute(['t' => $tenantId, 'y' => $year]);
            $seq = (int) $st->fetchColumn();
            if ($own) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [$year, $seq];
    }

    private function freeCodename(int $tenantId, int $attempt): string
    {
        for ($i = 0; $i < 40; $i++) {
            $c = SseIdentifierGenerator::codename($attempt + intdiv($i, 4) + intdiv($attempt * 10, 6));
            if (!$this->codenameReserved($tenantId, $c)) {
                return $c;
            }
        }

        return SseIdentifierGenerator::codename(240 + $attempt);
    }

    private function freeDse(int $tenantId, int $attempt): string
    {
        for ($i = 0; $i < 20; $i++) {
            $d = SseIdentifierGenerator::dse(SseIdentifierGenerator::DSE_DEFAULT + ($attempt > 4 ? 1 : 0));
            if ($this->one('SELECT id FROM sse_data_identifiers WHERE tenant_id = :t AND dse = :d', ['t' => $tenantId, 'd' => $d]) === null) {
                return $d;
            }
        }

        return SseIdentifierGenerator::dse(SseIdentifierGenerator::DSE_MAX);
    }

    /** Nom de code déjà porté, ou porté un jour (jamais réutilisé). */
    private function codenameReserved(int $tenantId, string $codename): bool
    {
        return $this->one('SELECT id FROM sse_data_identifiers WHERE tenant_id = :t AND codename = :c', ['t' => $tenantId, 'c' => $codename]) !== null
            || $this->one("SELECT id FROM sse_identifier_history WHERE tenant_id = :t AND field = 'codename' AND old_value = :c", ['t' => $tenantId, 'c' => $codename]) !== null;
    }

    private function history(int $tenantId, string $type, int $entityId, string $field, string $old, string $new, ?int $actorId, string $actorLabel): void
    {
        $this->exec(
            'INSERT INTO sse_identifier_history (tenant_id, entity_type, entity_id, field, old_value, new_value, actor_user_id, actor_label, created_at)
             VALUES (:t, :ty, :id, :f, :o, :n, :u, :l, :now)',
            ['t' => $tenantId, 'ty' => $type, 'id' => $entityId, 'f' => $field, 'o' => $old, 'n' => $new, 'u' => $actorId, 'l' => mb_substr($actorLabel, 0, 120), 'now' => gmdate('Y-m-d H:i:s')]
        );
        $this->journal([
            'tenant_id' => $tenantId, 'actor_user_id' => $actorId, 'actor_label' => $actorLabel, 'action' => 'identifiant.' . $field,
            'object_type' => $type, 'object_id' => $entityId, 'before' => ['value' => $old], 'after' => ['value' => $new],
        ]);
    }

    private function tableExists(string $table): bool
    {
        try {
            $this->pdo()->query('SELECT 1 FROM ' . $table . ' LIMIT 1');

            return true;
        } catch (PDOException) {
            return false;
        }
    }

    private static function isDuplicate(PDOException $e): bool
    {
        $info = $e->errorInfo;

        return ($e->getCode() === '23000') || (is_array($info) && ((int) ($info[1] ?? 0) === 1062 || (int) ($info[1] ?? 0) === 19));
    }

    /** @param array<string, mixed> $params */
    private function exec(string $sql, array $params): int
    {
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);

        return $st->rowCount();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function one(string $sql, array $params): ?array
    {
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($r) ? $r : null;
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function all(string $sql, array $params): array
    {
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
