<?php

declare(strict_types=1);

namespace App\Services\AtakToc\Purge;

use App\Services\Atak\AtakMapClearService;
use App\Support\ShortCache;
use PDO;

/**
 * Accès aux éléments de la carte du poste TOC web (marqueurs, tracés, pings, pistes) pour les supprimer, les annuler et
 * les purger. SQL portable (MySQL en production, SQLite en test) : les instants sont passés par PHP, en UTC.
 *
 * Un « élément » est un tableau : ref (« marker:12 »), table (marker|shape|ping|track), kind (marker|form|route|zone|ping|
 * track), id, x, y (mètres Arma, null si inconnu), label, type, author, unit, owner (membre du site, 0 si inconnu), at
 * (dernière modification, secondes Unix), pinned, game (marqueur venu du jeu), deletedAt, deletedBy, batch.
 *
 * Toute requête porte le tenant_id ET la carte : un identifiant d'une autre communauté ou d'une autre carte n'est jamais
 * trouvé (anti-IDOR). Les marqueurs et tracés supprimés « doucement » restent en base (corbeille 15 minutes) ; un marqueur
 * du jeu reste 48 h de plus en pierre tombale, pour que le jeu le retire de sa carte native.
 *
 * @phpstan-type Element array{ref: string, table: string, kind: string, id: int, x: ?float, y: ?float, label: string, type: string, author: string, unit: string, owner: int, at: int, pinned: bool, game: bool, deletedAt: int, deletedBy: int, batch: string}
 * @phpstan-type Ref array{table: string, id: int}
 */
final class TocElementStore
{
    public const TABLES = ['marker', 'shape', 'ping', 'track'];
    public const MAX_SCAN = 20000;
    private const CHUNK = 400;

    public function __construct(private PDO $pdo, private ?bool $soft = null)
    {
    }

    public function softReady(): bool
    {
        return $this->soft ?? TocSoftDelete::available($this->pdo);
    }

    public static function stamp(int $ts): string
    {
        return gmdate('Y-m-d H:i:s', $ts);
    }

    public static function ts(mixed $utc): int
    {
        $t = is_string($utc) && $utc !== '' ? strtotime($utc . ' UTC') : false;

        return $t === false ? 0 : $t;
    }

    /** @return list<Ref> références valides (table connue, identifiant positif), sans doublon */
    public static function cleanRefs(mixed $refs, int $max): array
    {
        $out = [];
        $seen = [];
        foreach (is_array($refs) ? $refs : [] as $r) {
            if (!is_array($r)) {
                continue;
            }
            $table = (string) ($r['table'] ?? $r['kind'] ?? '');
            $table = match ($table) {
                'form', 'route', 'zone', 'shape' => 'shape',
                default => $table,
            };
            $id = (int) ($r['id'] ?? 0);
            if (!in_array($table, self::TABLES, true) || $id < 1 || isset($seen[$table . ':' . $id])) {
                continue;
            }
            $seen[$table . ':' . $id] = true;
            $out[] = ['table' => $table, 'id' => $id];
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /* ------------------------------------------------------------------ lecture */

    /**
     * Éléments affichés sur la carte, plus anciens d'abord (non supprimés, non masqués).
     *
     * @param list<string> $tables
     * @return list<Element>
     */
    public function elements(int $tenantId, int $mapId, array $tables = self::TABLES, int $limit = self::MAX_SCAN): array
    {
        $out = [];
        foreach (self::TABLES as $table) {
            if (!in_array($table, $tables, true)) {
                continue;
            }
            foreach ($this->rows($table, $tenantId, $mapId, null, max(1, $limit - count($out))) as $row) {
                $el = $this->element($table, $row);
                if ($el !== null) {
                    $out[] = $el;
                }
            }
        }

        return $out;
    }

    /**
     * @param list<Ref> $refs
     * @return array<string, Element> par ref
     */
    public function find(int $tenantId, int $mapId, array $refs): array
    {
        $out = [];
        foreach ($this->groupIds($refs) as $table => $ids) {
            foreach (array_chunk($ids, self::CHUNK) as $chunk) {
                foreach ($this->rows($table, $tenantId, $mapId, $chunk, self::CHUNK) as $row) {
                    $el = $this->element($table, $row);
                    if ($el !== null) {
                        $out[$el['ref']] = $el;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Éléments de la corbeille (suppression douce récente, annulable) de la carte.
     *
     * @return list<Element>
     */
    public function trashed(int $tenantId, int $mapId, int $now, int $limit = 500): array
    {
        if (!$this->softReady()) {
            return [];
        }
        $since = self::stamp($now - TocSoftDelete::TRASH_TTL);
        $out = [];
        foreach (['marker' => 'atak_markers', 'shape' => 'atak_map_shapes'] as $table => $name) {
            $st = $this->pdo->prepare(
                'SELECT * FROM ' . $name . ' WHERE tenant_id = ? AND map_id = ? AND deleted_at IS NOT NULL AND deleted_at >= ?'
                . ' ORDER BY deleted_at DESC, id DESC LIMIT ' . max(1, $limit)
            );
            $st->execute([$tenantId, $mapId, $since]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $el = $this->element($table, $row, true);
                if ($el !== null && preg_match(TocSoftDelete::BATCH_PATTERN, $el['batch']) === 1) {
                    $out[] = $el;
                }
            }
        }
        usort($out, static fn (array $a, array $b): int => [$b['deletedAt'], $b['id']] <=> [$a['deletedAt'], $a['id']]);

        return array_slice($out, 0, $limit);
    }

    /**
     * @param list<Ref> $refs
     * @return array<string, list<int>>
     */
    private function groupIds(array $refs): array
    {
        $by = [];
        foreach ($refs as $r) {
            $by[$r['table']][] = $r['id'];
        }

        return $by;
    }

    private function tableName(string $table): string
    {
        return match ($table) {
            'marker' => 'atak_markers',
            'shape' => 'atak_map_shapes',
            'ping' => 'atak_pings',
            default => 'atak_units',
        };
    }

    /**
     * @param list<int>|null $ids
     * @return list<array<string, mixed>>
     */
    private function rows(string $table, int $tenantId, int $mapId, ?array $ids, int $limit): array
    {
        $sql = 'SELECT * FROM ' . $this->tableName($table) . ' WHERE tenant_id = ? AND map_id = ?';
        $params = [$tenantId, $mapId];
        if (($table === 'marker' || $table === 'shape') && $this->softReady()) {
            $sql .= ' AND deleted_at IS NULL';
        }
        if ($ids !== null) {
            if ($ids === []) {
                return [];
            }
            $sql .= ' AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            array_push($params, ...$ids);
        }
        $sql .= ' ORDER BY id LIMIT ' . max(1, $limit);
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($params);
        } catch (\Throwable) {
            // Table absente (pings, pistes sur une base ancienne) : rien à proposer, jamais d'erreur.
            return [];
        }

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $row
     * @return Element|null
     */
    private function element(string $table, array $row, bool $withDeleted = false): ?array
    {
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1) {
            return null;
        }
        $base = [
            'ref' => $table . ':' . $id, 'table' => $table, 'kind' => $table, 'id' => $id, 'x' => null, 'y' => null,
            'label' => '', 'type' => '', 'author' => '', 'unit' => '', 'owner' => 0,
            'at' => max(self::ts($row['updated_at'] ?? null), self::ts($row['created_at'] ?? null)),
            'pinned' => false, 'game' => false,
            'deletedAt' => self::ts($row['deleted_at'] ?? null), 'deletedBy' => (int) ($row['deleted_by'] ?? 0),
            'batch' => (string) ($row['delete_batch'] ?? ''),
        ];
        if ($table === 'marker') {
            $d = json_decode((string) ($row['marker_data'] ?? ''), true);
            $d = is_array($d) ? $d : [];
            if (!$withDeleted && !empty($d['suppressed'])) {
                return null;
            }
            [$x, $y] = self::markerPos($d);

            return [
                'x' => $x, 'y' => $y, 'label' => mb_substr((string) ($d['text'] ?? $d['label'] ?? ''), 0, 120),
                'type' => (string) ($d['type'] ?? ''), 'author' => mb_substr((string) ($d['author'] ?? ''), 0, 60),
                'unit' => mb_substr((string) ($d['unit'] ?? $d['group'] ?? ''), 0, 60),
                'owner' => (int) ($d['owner_user_id'] ?? $d['mobile_user'] ?? 0),
                'pinned' => !empty($d['web_permanent']),
                'game' => trim((string) ($row['arma_name'] ?? '')) !== '',
                'kind' => 'marker',
            ] + $base;
        }
        if ($table === 'shape') {
            $geo = self::decode($row['geometry'] ?? null);
            $meta = self::decode($row['meta'] ?? null);
            [$x, $y] = self::centroid($geo);

            return [
                'x' => $x, 'y' => $y, 'label' => mb_substr((string) ($row['label'] ?? ''), 0, 120),
                'type' => (string) ($row['type'] ?? ''), 'author' => mb_substr((string) ($row['created_by'] ?? ''), 0, 60),
                'owner' => (int) ($meta['owner_user_id'] ?? 0), 'pinned' => !empty($meta['web_permanent']),
                'kind' => AtakMapClearService::shapeKind(['geometry' => $geo, 'type' => (string) ($row['type'] ?? '')]),
            ] + $base;
        }
        if ($table === 'ping') {
            return [
                'x' => self::num($row['pos_x'] ?? null), 'y' => self::num($row['pos_y'] ?? null),
                'label' => mb_substr((string) ($row['message'] ?? ''), 0, 120), 'author' => mb_substr((string) ($row['author'] ?? ''), 0, 60),
                'at' => max(self::ts($row['created_at'] ?? null), 0), 'kind' => 'ping',
            ] + $base;
        }

        return [
            'x' => self::num($row['pos_x'] ?? null), 'y' => self::num($row['pos_y'] ?? null),
            'label' => mb_substr((string) ($row['call_sign'] ?? ''), 0, 60), 'author' => mb_substr((string) ($row['call_sign'] ?? ''), 0, 60),
            'unit' => mb_substr((string) ($row['group_name'] ?? ''), 0, 60), 'type' => (string) ($row['role'] ?? ''),
            'at' => self::ts($row['updated_at'] ?? null), 'kind' => 'track',
        ] + $base;
    }

    private static function num(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    /** @return array<string, mixed> */
    private static function decode(mixed $v): array
    {
        if (is_array($v)) {
            return $v;
        }
        $d = is_string($v) && $v !== '' ? json_decode($v, true) : null;

        return is_array($d) ? $d : [];
    }

    /**
     * @param array<string, mixed> $d
     * @return array{0: ?float, 1: ?float}
     */
    public static function markerPos(array $d): array
    {
        $pos = $d['pos'] ?? null;
        if (is_array($pos) && isset($pos[0], $pos[1]) && is_numeric($pos[0]) && is_numeric($pos[1])) {
            return [(float) $pos[0], (float) $pos[1]];
        }
        $x = $d['pos_x'] ?? $d['x'] ?? null;
        $y = $d['pos_y'] ?? $d['y'] ?? null;

        return is_numeric($x) && is_numeric($y) ? [(float) $x, (float) $y] : [null, null];
    }

    /**
     * Centre d'un tracé : centre déclaré (cercle) ou moyenne des sommets.
     *
     * @param array<string, mixed> $geo
     * @return array{0: ?float, 1: ?float}
     */
    public static function centroid(array $geo): array
    {
        $c = $geo['center'] ?? null;
        if (is_array($c) && isset($c[0], $c[1]) && is_numeric($c[0]) && is_numeric($c[1])) {
            return [(float) $c[0], (float) $c[1]];
        }
        $pts = $geo['coordinates'] ?? $geo['points'] ?? null;
        $sx = 0.0;
        $sy = 0.0;
        $n = 0;
        foreach (is_array($pts) ? $pts : [] as $p) {
            if (is_array($p) && isset($p[0], $p[1]) && is_numeric($p[0]) && is_numeric($p[1])) {
                $sx += (float) $p[0];
                $sy += (float) $p[1];
                $n++;
            } elseif (is_array($p) && isset($p['x'], $p['y']) && is_numeric($p['x']) && is_numeric($p['y'])) {
                $sx += (float) $p['x'];
                $sy += (float) $p['y'];
                $n++;
            }
        }

        return $n > 0 ? [$sx / $n, $sy / $n] : [null, null];
    }

    /* ------------------------------------------------------------------ écriture */

    /**
     * Suppression douce (corbeille) de marqueurs et de tracés. Un marqueur reçoit aussi l'état « suppressed » déjà lu par
     * toutes les lectures du site et par le relais du jeu. Ne touche pas deux fois une ligne déjà dans la corbeille.
     *
     * @param list<Ref> $refs marqueurs et tracés seulement
     * @return int lignes mises dans la corbeille
     */
    public function softDelete(int $tenantId, int $mapId, array $refs, ?int $userId, string $batch, int $now): int
    {
        $n = 0;
        $stamp = self::stamp($now);
        $mk = $this->pdo->prepare(
            'UPDATE atak_markers SET marker_data = ?, updated_at = ?, deleted_at = ?, deleted_by = ?, delete_batch = ?'
            . ' WHERE tenant_id = ? AND map_id = ? AND id = ? AND deleted_at IS NULL'
        );
        $sh = $this->pdo->prepare(
            'UPDATE atak_map_shapes SET deleted_at = ?, deleted_by = ?, delete_batch = ? WHERE tenant_id = ? AND map_id = ? AND id = ? AND deleted_at IS NULL'
        );
        $get = $this->pdo->prepare('SELECT marker_data FROM atak_markers WHERE tenant_id = ? AND map_id = ? AND id = ? AND deleted_at IS NULL');
        foreach ($refs as $ref) {
            if ($ref['table'] === 'marker') {
                $get->execute([$tenantId, $mapId, $ref['id']]);
                $raw = $get->fetchColumn();
                if (!is_string($raw)) {
                    continue;
                }
                $d = json_decode($raw, true);
                $d = is_array($d) ? $d : [];
                $d['suppressed'] = true;
                $d['suppressed_at'] = gmdate('c', $now);
                $d['suppressed_by'] = 'toc';
                $d['deleted_at'] = $now;
                $json = json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                $mk->execute([is_string($json) ? $json : $raw, $stamp, $stamp, $userId, $batch, $tenantId, $mapId, $ref['id']]);
                $n += $mk->rowCount();
            } elseif ($ref['table'] === 'shape') {
                $sh->execute([$stamp, $userId, $batch, $tenantId, $mapId, $ref['id']]);
                $n += $sh->rowCount();
            }
        }
        $this->bump($tenantId);

        return $n;
    }

    /**
     * Annule un lot encore dans le délai : les éléments reviennent sur la carte tels qu'avant.
     *
     * @return int éléments remis
     */
    public function restoreBatch(int $tenantId, int $mapId, string $batch, int $now): int
    {
        if (preg_match(TocSoftDelete::BATCH_PATTERN, $batch) !== 1) {
            return 0;
        }
        $since = self::stamp($now - TocSoftDelete::TRASH_TTL);
        $n = 0;
        $st = $this->pdo->prepare(
            'SELECT id, marker_data FROM atak_markers WHERE tenant_id = ? AND map_id = ? AND delete_batch = ? AND deleted_at IS NOT NULL AND deleted_at >= ?'
        );
        $st->execute([$tenantId, $mapId, $batch, $since]);
        $upd = $this->pdo->prepare(
            'UPDATE atak_markers SET marker_data = ?, updated_at = ?, deleted_at = NULL, deleted_by = NULL, delete_batch = NULL'
            . ' WHERE tenant_id = ? AND map_id = ? AND id = ? AND delete_batch = ?'
        );
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $d = json_decode((string) $row['marker_data'], true);
            $d = is_array($d) ? $d : [];
            if (($d['suppressed_by'] ?? '') === 'toc') {
                unset($d['suppressed'], $d['suppressed_at'], $d['suppressed_by'], $d['deleted_at']);
            }
            $json = json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            $upd->execute([is_string($json) ? $json : (string) $row['marker_data'], self::stamp($now), $tenantId, $mapId, (int) $row['id'], $batch]);
            $n += $upd->rowCount();
        }
        $sh = $this->pdo->prepare(
            'UPDATE atak_map_shapes SET deleted_at = NULL, deleted_by = NULL, delete_batch = NULL, updated_at = ?'
            . ' WHERE tenant_id = ? AND map_id = ? AND delete_batch = ? AND deleted_at IS NOT NULL AND deleted_at >= ?'
        );
        $sh->execute([self::stamp($now), $tenantId, $mapId, $batch, $since]);
        $n += $sh->rowCount();
        $this->bump($tenantId);

        return $n;
    }

    /**
     * Suppression définitive (mode dégradé sans migration, pings et pistes). Un marqueur du jeu est seulement masqué
     * (état « suppressed » : le relais du jeu le retire de sa carte), comme la suppression à l'unité d'avant.
     *
     * @param list<Ref> $refs
     * @return int éléments retirés
     */
    public function hardDelete(int $tenantId, int $mapId, array $refs, int $now): int
    {
        $n = 0;
        foreach ($this->groupIds($refs) as $table => $ids) {
            foreach (array_chunk($ids, self::CHUNK) as $chunk) {
                $in = implode(',', array_fill(0, count($chunk), '?'));
                if ($table === 'marker') {
                    $st = $this->pdo->prepare('SELECT id, arma_name, marker_data FROM atak_markers WHERE tenant_id = ? AND map_id = ? AND id IN (' . $in . ')');
                    $st->execute([$tenantId, $mapId, ...$chunk]);
                    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        if (trim((string) ($row['arma_name'] ?? '')) !== '') {
                            $d = json_decode((string) $row['marker_data'], true);
                            $d = is_array($d) ? $d : [];
                            $d['suppressed'] = true;
                            $d['suppressed_at'] = gmdate('c', $now);
                            $d['suppressed_by'] = 'web';
                            $json = json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                            $this->pdo->prepare('UPDATE atak_markers SET marker_data = ?, updated_at = ? WHERE tenant_id = ? AND map_id = ? AND id = ?')
                                ->execute([is_string($json) ? $json : (string) $row['marker_data'], self::stamp($now), $tenantId, $mapId, (int) $row['id']]);
                        } else {
                            $this->pdo->prepare('DELETE FROM atak_markers WHERE tenant_id = ? AND map_id = ? AND id = ?')->execute([$tenantId, $mapId, (int) $row['id']]);
                        }
                        $n++;
                    }
                    continue;
                }
                $st = $this->pdo->prepare('DELETE FROM ' . $this->tableName($table) . ' WHERE tenant_id = ? AND map_id = ? AND id IN (' . $in . ')');
                try {
                    $st->execute([$tenantId, $mapId, ...$chunk]);
                    $n += $st->rowCount();
                } catch (\Throwable) {
                    // Table absente : rien à retirer.
                }
            }
        }
        $this->bump($tenantId);

        return $n;
    }

    /**
     * Fin de corbeille : supprime pour de bon ce qui a dépassé 15 minutes. Un marqueur du jeu garde sa ligne (pierre
     * tombale) jusqu'à 48 h : il n'est plus annulable mais le jeu peut encore apprendre sa suppression.
     *
     * @return int lignes supprimées
     */
    public function expire(?int $tenantId, int $now, int $limit = 2000): int
    {
        if (!$this->softReady()) {
            return 0;
        }
        $n = 0;
        $cut = self::stamp($now - TocSoftDelete::TRASH_TTL);
        $tomb = self::stamp($now - TocSoftDelete::TOMBSTONE_TTL);
        $scope = $tenantId !== null ? ' AND tenant_id = ?' : '';
        $sp = $tenantId !== null ? [$tenantId] : [];
        $jobs = [
            ['atak_map_shapes', 'deleted_at IS NOT NULL AND deleted_at < ?' . $scope, [$cut, ...$sp]],
            ['atak_markers', "deleted_at IS NOT NULL AND deleted_at < ? AND (arma_name IS NULL OR arma_name = '')" . $scope, [$cut, ...$sp]],
            ['atak_markers', 'deleted_at IS NOT NULL AND deleted_at < ?' . $scope, [$tomb, ...$sp]],
        ];
        foreach ($jobs as [$name, $where, $params]) {
            $st = $this->pdo->prepare('SELECT id FROM ' . $name . ' WHERE ' . $where . ' ORDER BY id LIMIT ' . max(1, $limit));
            $st->execute($params);
            $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
            foreach (array_chunk($ids, self::CHUNK) as $chunk) {
                $del = $this->pdo->prepare('DELETE FROM ' . $name . ' WHERE id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')');
                $del->execute($chunk);
                $n += $del->rowCount();
            }
        }

        return $n;
    }

    /**
     * Instantané pour l'archivage d'une mission (même forme que le cycle de 24 h : restaurable).
     *
     * @param list<Ref> $refs marqueurs et tracés seulement
     * @return list<array{kind: string, id: int, at: string, payload: array<string, mixed>}>
     */
    public function snapshot(int $tenantId, int $mapId, array $refs): array
    {
        $items = [];
        foreach ($this->groupIds($refs) as $table => $ids) {
            if ($table !== 'marker' && $table !== 'shape') {
                continue;
            }
            foreach (array_chunk($ids, self::CHUNK) as $chunk) {
                foreach ($this->rows($table, $tenantId, $mapId, $chunk, self::CHUNK) as $r) {
                    $r['id'] = (int) $r['id'];
                    if ($table === 'marker') {
                        $at = (string) (($r['updated_at'] ?? '') !== '' && $r['updated_at'] !== null ? $r['updated_at'] : ($r['created_at'] ?? ''));
                        $r['layer_id'] = (int) ($r['layer_id'] ?? 1);
                        foreach (['deleted_at', 'deleted_by', 'delete_batch', 'tenant_id', 'map_id'] as $k) {
                            unset($r[$k]);
                        }
                        $items[] = ['kind' => 'marker', 'id' => $r['id'], 'at' => $at, 'payload' => $r];
                        continue;
                    }
                    $at = (string) (($r['updated_at'] ?? '') !== '' && $r['updated_at'] !== null ? $r['updated_at'] : ($r['created_at'] ?? ''));
                    foreach (['visible_to', 'geometry', 'meta'] as $k) {
                        if (isset($r[$k]) && is_string($r[$k])) {
                            $dec = json_decode($r[$k], true);
                            $r[$k] = is_array($dec) ? $dec : $r[$k];
                        }
                    }
                    foreach (['deleted_at', 'deleted_by', 'delete_batch'] as $k) {
                        unset($r[$k]);
                    }
                    $items[] = ['kind' => 'shape', 'id' => $r['id'], 'at' => $at, 'payload' => $r];
                }
            }
        }

        return $items;
    }

    /** Épingle (ou désépingle) un marqueur ou un tracé. @return bool false si l'élément n'existe pas sur cette carte */
    public function setPinned(int $tenantId, int $mapId, string $table, int $id, bool $pinned, int $now): bool
    {
        if ($table === 'marker') {
            $st = $this->pdo->prepare('SELECT marker_data FROM atak_markers WHERE tenant_id = ? AND map_id = ? AND id = ?' . ($this->softReady() ? ' AND deleted_at IS NULL' : ''));
            $st->execute([$tenantId, $mapId, $id]);
            $raw = $st->fetchColumn();
            if (!is_string($raw)) {
                return false;
            }
            $d = json_decode($raw, true);
            $d = is_array($d) ? $d : [];
            if ($pinned) {
                $d['web_permanent'] = true;
            } else {
                unset($d['web_permanent']);
            }
            $this->pdo->prepare('UPDATE atak_markers SET marker_data = ?, updated_at = ? WHERE tenant_id = ? AND map_id = ? AND id = ?')
                ->execute([json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), self::stamp($now), $tenantId, $mapId, $id]);
            $this->bump($tenantId);

            return true;
        }
        if ($table !== 'shape') {
            return false;
        }
        $st = $this->pdo->prepare('SELECT meta FROM atak_map_shapes WHERE tenant_id = ? AND map_id = ? AND id = ?' . ($this->softReady() ? ' AND deleted_at IS NULL' : ''));
        $st->execute([$tenantId, $mapId, $id]);
        $row = $st->fetch(PDO::FETCH_NUM);
        if ($row === false) {
            return false;
        }
        $m = self::decode($row[0]);
        if ($pinned) {
            $m['web_permanent'] = true;
        } else {
            unset($m['web_permanent']);
        }
        $this->pdo->prepare('UPDATE atak_map_shapes SET meta = ?, updated_at = ? WHERE tenant_id = ? AND map_id = ? AND id = ?')
            ->execute([json_encode($m === [] ? new \stdClass() : $m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), self::stamp($now), $tenantId, $mapId, $id]);
        $this->bump($tenantId);

        return true;
    }

    /* ------------------------------------------------------------------ purges (état et journal) */

    public function jobsReady(): bool
    {
        return $this->softReady();
    }

    /**
     * @param array<string, mixed> $job colonnes de atak_toc_purges (sans id)
     * @return int identifiant
     */
    public function insertJob(array $job): int
    {
        $cols = array_keys($job);
        $st = $this->pdo->prepare('INSERT INTO atak_toc_purges (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')');
        $st->execute(array_values($job));

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function job(int $tenantId, int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM atak_toc_purges WHERE tenant_id = ? AND id = ?');
        $st->execute([$tenantId, $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($r) ? $r : null;
    }

    /** @param array<string, mixed> $fields */
    public function updateJob(int $tenantId, int $id, array $fields): void
    {
        if ($fields === []) {
            return;
        }
        $set = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($fields)));
        $this->pdo->prepare('UPDATE atak_toc_purges SET ' . $set . ' WHERE tenant_id = ? AND id = ?')
            ->execute([...array_values($fields), $tenantId, $id]);
    }

    /** Purge en cours (archivage ou suppression) d'une carte, mise à jour depuis moins de $staleAfter secondes. @return array<string, mixed>|null */
    public function activeJob(int $tenantId, int $mapId, int $now, int $staleAfter = 1800): ?array
    {
        if (!$this->softReady()) {
            return null;
        }
        $st = $this->pdo->prepare(
            "SELECT * FROM atak_toc_purges WHERE tenant_id = ? AND map_id = ? AND status IN ('archiving', 'deleting') AND updated_at >= ? ORDER BY id DESC LIMIT 1"
        );
        $st->execute([$tenantId, $mapId, self::stamp($now - $staleAfter)]);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($r) ? $r : null;
    }

    /** Prend le verrou d'un lot (une seule exécution à la fois par purge). */
    public function claimJob(int $tenantId, int $id, int $now, int $ttl): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE atak_toc_purges SET locked_until = ? WHERE tenant_id = ? AND id = ? AND status IN ('archiving', 'deleting') AND (locked_until IS NULL OR locked_until < ?)"
        );
        $st->execute([self::stamp($now + $ttl), $tenantId, $id, self::stamp($now)]);

        return $st->rowCount() === 1;
    }

    /**
     * Dernières purges de la carte (journal affiché dans l'écran « Purger »).
     *
     * @return list<array<string, mixed>>
     */
    public function recentJobs(int $tenantId, int $mapId, int $limit = 8): array
    {
        if (!$this->softReady()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT id, user_id, batch, status, scope_json, archive_first, keep_pinned, total, archived, deleted, kept_pinned, archive_id, error, created_at, finished_at'
            . ' FROM atak_toc_purges WHERE tenant_id = ? AND map_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit)
        );
        $st->execute([$tenantId, $mapId]);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ------------------------------------------------------------------ pierres tombales (jeu) */

    /**
     * Marqueurs du jeu supprimés depuis un curseur (instant, identifiant), plus anciens d'abord : le jeu les retire de sa
     * carte native. Additif : sans migration, repli sur « masqué depuis peu » (comportement d'avant).
     *
     * @return list<array{id: int, arma_name: string, pos: array{0: float, 1: float}, at: int}>
     */
    public function gameTombstones(int $tenantId, int $mapId, int $sinceTs, int $sinceId, int $now, int $limit): array
    {
        if (!$this->softReady()) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $floor = max($sinceTs, $now - TocSoftDelete::TOMBSTONE_TTL);
        $st = $this->pdo->prepare(
            "SELECT id, arma_name, marker_data, deleted_at FROM atak_markers WHERE tenant_id = ? AND map_id = ? AND arma_name IS NOT NULL AND arma_name <> ''"
            . ' AND deleted_at IS NOT NULL AND (deleted_at > ? OR (deleted_at = ? AND id > ?)) ORDER BY deleted_at, id LIMIT ' . $limit
        );
        $stamp = self::stamp($floor);
        $st->execute([$tenantId, $mapId, $stamp, $stamp, $floor === $sinceTs ? $sinceId : 0]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $d = json_decode((string) $row['marker_data'], true);
            [$x, $y] = self::markerPos(is_array($d) ? $d : []);
            $out[] = ['id' => (int) $row['id'], 'arma_name' => (string) $row['arma_name'], 'pos' => [$x ?? 1.0, $y ?? 1.0], 'at' => self::ts($row['deleted_at'])];
        }

        return $out;
    }

    /** Curseur `deleted_since` : « <instant> » ou « <instant>:<identifiant> » (secondes Unix). @return array{0: int, 1: int}|null */
    public static function parseCursor(string $cursor): ?array
    {
        if (preg_match('/^(\d{1,12})(?::(\d{1,12}))?$/', trim($cursor), $m) !== 1) {
            return null;
        }

        return [(int) $m[1], (int) ($m[2] ?? 0)];
    }

    /**
     * Lignes de la réponse de lecture des marqueurs pour les pierres tombales : même forme que les lignes « game_del » déjà
     * lues par le relais du jeu (id « del:N », colonne texte = nom Arma, source game_del), avec l'instant de suppression.
     *
     * @param list<array{id: int, arma_name: string, pos: array{0: float, 1: float}, at: int}> $tombs
     * @return list<array{id: string, layerId: int, markerData: string, updated_at: null}>
     */
    public static function tombstoneRows(array $tombs): array
    {
        $out = [];
        foreach ($tombs as $g) {
            $out[] = [
                'id' => 'del:' . $g['id'],
                'layerId' => 1,
                'markerData' => (string) json_encode([
                    'pos' => $g['pos'], 'type' => 'comspec_del', 'text' => $g['arma_name'], 'color' => '', 'source' => 'game_del', 'deleted_at' => $g['at'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => null,
            ];
        }

        return $out;
    }

    private function bump(int $tenantId): void
    {
        try {
            ShortCache::bump('markers:' . $tenantId);
            ShortCache::bump('pings:' . $tenantId);
        } catch (\Throwable) {
            // Cache indisponible : les lectures se rafraîchissent d'elles-mêmes en quelques secondes.
        }
    }
}
