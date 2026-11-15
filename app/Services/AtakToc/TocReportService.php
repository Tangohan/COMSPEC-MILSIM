<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

use App\Core\Database;
use PDO;

/**
 * Lecture des rapports tactiques par le poste TOC web : comptes rendus du jeu (atak_tactical_reports : BDA, contact,
 * SALUTE, SITREP, patrouille, blessé, logistique, engin explosif, NRBC, ordre de conduite, libres) et demandes 9-line
 * (atak_nine_line : CAS, 5-line, MEDEVAC / CASEVAC), dans une seule liste normalisée.
 *
 * Règles de lecture :
 *  - tout est borné à la communauté de la session (le tenant n'est jamais lu dans la requête) ;
 *  - un rapport au-dessus de l'habilitation de l'opérateur (classification ou visibilité, TocReportCatalog::requiredLevel)
 *    n'est ni listé ni ouvert : sa fiche répond « introuvable » (même réponse qu'un identifiant inconnu) ;
 *  - les passages caviardés du bureau Remontées sont masqués au niveau de l'opérateur, et la fiche structurée, qui
 *    répète le texte, est retirée d'un compte rendu caviardé ;
 *  - lu / non lu par opérateur (atak_toc_report_reads) ; sans cette table (étape 4750 pas encore lancée), tout est
 *    « non lu » et rien n'est conservé : readState vaut « degraded ».
 *
 * Clé d'un rapport : « tr-12 » (compte rendu), « nl-7 » (9-line) ; sans deux-points, elle passe telle quelle dans une adresse.
 */
final class TocReportService
{
    public const SOURCE_TACTICAL = 'tr';
    public const SOURCE_NINE = 'nl';
    public const MAX_LIMIT = 300;
    public const DEFAULT_SINCE_DAYS = 14;

    private ?bool $readsReady = null;

    /** @var callable(int, list<array<string, mixed>>, string, int): list<array<string, mixed>> */
    private $redact;

    /**
     * @param callable(int, list<array<string, mixed>>, string, int): list<array<string, mixed>>|null $redact caviardage du bureau Remontées (tenant, lignes, niveau, membre)
     */
    public function __construct(private ?PDO $pdo = null, ?callable $redact = null)
    {
        $this->redact = $redact ?? static function (int $tenantId, array $rows, string $level, int $userId): array {
            return (new \App\Services\Intel\IntelIntakeService())->presentReports($tenantId, $rows, $level, $userId);
        };
    }

    private function db(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /** Clé « tr-12 » (ou « tr:12 ») ou « nl-7 » => [source, id] ; null si mal formée. @return array{0: string, 1: int}|null */
    public static function parseKey(string $key): ?array
    {
        if (preg_match('/^(tr|nl)[-:]([1-9][0-9]{0,9})$/', trim($key), $m) !== 1) {
            return null;
        }

        return [$m[1], (int) $m[2]];
    }

    public static function key(string $source, int $id): string
    {
        return $source . '-' . $id;
    }

    // ------------------------------------------------------------------ lecture

    /**
     * Liste normalisée, filtrée et triée.
     *
     * Filtres : map (carte, 0 = toutes), family, type, unread (1), priority, status (open|closed|code), unit, who, q, since (époque, secondes),
     * until, sort (recent | oldest | priority | unread), limit.
     *
     * @param array<string, mixed> $q
     * @return array{reports: list<array<string, mixed>>, counts: array<string, mixed>, hidden: int, readState: string, truncated: bool}
     */
    public function list(int $tenantId, int $userId, string $level, array $q = []): array
    {
        $empty = ['reports' => [], 'counts' => self::emptyCounts(), 'hidden' => 0, 'readState' => $this->readState(), 'truncated' => false];
        if ($tenantId < 1) {
            return $empty;
        }
        $mapId = max(0, (int) ($q['map'] ?? 0));
        $since = isset($q['since']) && is_numeric($q['since']) ? (int) $q['since'] : time() - self::DEFAULT_SINCE_DAYS * 86400;
        $limit = max(1, min(self::MAX_LIMIT, (int) ($q['limit'] ?? 200)));

        $items = [];
        $hidden = 0;
        $truncated = false;
        $tactical = $this->fetchTactical($tenantId, $mapId, $since, $limit + 1);
        if (count($tactical) > $limit) {
            $truncated = true;
            $tactical = array_slice($tactical, 0, $limit);
        }
        foreach ($this->visibleTactical($tenantId, $userId, $level, $tactical, $hidden) as $row) {
            $items[] = $this->presentTactical($row, false);
        }
        $nine = $this->fetchNine($tenantId, $mapId, $since, $limit + 1);
        if (count($nine) > $limit) {
            $truncated = true;
            $nine = array_slice($nine, 0, $limit);
        }
        foreach ($nine as $row) {
            $items[] = $this->presentNine($row, false);
        }

        $reads = $this->readSet($tenantId, $userId, $items);
        foreach ($items as &$it) {
            $it['read'] = isset($reads[$it['key']]);
        }
        unset($it);

        $counts = self::countsOf($items);
        $items = self::filter($items, $q);
        $items = self::sort($items, (string) ($q['sort'] ?? 'recent'));

        return ['reports' => array_slice($items, 0, $limit), 'counts' => $counts, 'hidden' => $hidden, 'readState' => $this->readState(), 'truncated' => $truncated];
    }

    /**
     * Fiche complète d'un rapport, ou null (inconnu, autre communauté, au-dessus de l'habilitation : même réponse).
     *
     * @return array<string, mixed>|null
     */
    public function find(int $tenantId, int $userId, string $level, string $key): ?array
    {
        $parsed = self::parseKey($key);
        if ($parsed === null || $tenantId < 1) {
            return null;
        }
        [$source, $id] = $parsed;
        if ($source === self::SOURCE_TACTICAL) {
            $row = $this->tacticalRow($tenantId, $id);
            if ($row === null) {
                return null;
            }
            $hidden = 0;
            $rows = $this->visibleTactical($tenantId, $userId, $level, [$row], $hidden);
            if ($rows === []) {
                return null;
            }
            $item = $this->presentTactical($rows[0], true);
            $item['photos'] = $this->photosOf($tenantId, $item, $rows[0]);
        } else {
            $row = $this->nineRow($tenantId, $id);
            if ($row === null) {
                return null;
            }
            $item = $this->presentNine($row, true);
            $item['photos'] = [];
        }
        $reads = $this->readSet($tenantId, $userId, [$item]);
        $item['read'] = isset($reads[$item['key']]);
        $item['plans'] = $this->attachedPlans($tenantId, $item['key']);
        $item['readState'] = $this->readState();

        return $item;
    }

    /**
     * Empreinte des rapports d'une carte (nombre, dernier identifiant, dernière modification des deux sources) pour le canal
     * de synchro du poste : deux agrégats, pas de lecture des fiches. Elle change quand un rapport arrive ou évolue ; le
     * panneau relit alors sa liste. Identique pour tous les opérateurs d'une communauté (les droits jouent à la lecture).
     */
    public function stamp(int $tenantId, int $mapId = 0): string
    {
        if ($tenantId < 1) {
            return '';
        }
        $since = gmdate('Y-m-d H:i:s', time() - self::DEFAULT_SINCE_DAYS * 86400);
        $parts = [];
        foreach ([
            ['SELECT COUNT(*), MAX(id), MAX(updated_at) FROM atak_tactical_reports WHERE tenant_id = ? AND deleted_at IS NULL AND report_timestamp >= ?', 'context_id'],
            ['SELECT COUNT(*), MAX(id), MAX(COALESCE(updated_at, created_at)) FROM atak_nine_line WHERE tenant_id = ? AND COALESCE(updated_at, created_at) >= ?', 'map_id'],
        ] as [$sql, $mapColumn]) {
            $params = [$tenantId, $since];
            if ($mapId > 0) {
                $sql .= ' AND ' . $mapColumn . ' = ?';
                $params[] = $mapId;
            }
            try {
                $st = $this->db()->prepare($sql);
                $st->execute($params);
                $parts[] = implode('|', array_map('strval', $st->fetch(PDO::FETCH_NUM) ?: []));
            } catch (\Throwable) {
                $parts[] = '-';
            }
        }

        return substr(md5(implode(';', $parts)), 0, 12);
    }

    // ------------------------------------------------------------------ actions

    /** Marque lu ou non lu. Faux si le rapport n'est pas lisible ou si le suivi n'est pas installé. */
    public function markRead(int $tenantId, int $userId, string $level, string $key, bool $read): bool
    {
        if ($userId < 1 || $this->find($tenantId, $userId, $level, $key) === null || !$this->readsReady()) {
            return false;
        }
        [$source, $id] = self::parseKey($key) ?? ['', 0];
        try {
            if ($read) {
                $st = $this->db()->prepare('SELECT 1 FROM atak_toc_report_reads WHERE tenant_id = ? AND user_id = ? AND report_kind = ? AND report_id = ?');
                $st->execute([$tenantId, $userId, $source, $id]);
                if ($st->fetchColumn() === false) {
                    $this->db()->prepare('INSERT INTO atak_toc_report_reads (tenant_id, user_id, report_kind, report_id, read_at) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$tenantId, $userId, $source, $id, gmdate('Y-m-d H:i:s')]);
                }
            } else {
                $this->db()->prepare('DELETE FROM atak_toc_report_reads WHERE tenant_id = ? AND user_id = ? AND report_kind = ? AND report_id = ?')
                    ->execute([$tenantId, $userId, $source, $id]);
            }
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /** Marque tous les rapports visibles (carte et période demandées) comme lus. Retourne le nombre de rapports marqués. */
    public function markAllRead(int $tenantId, int $userId, string $level, array $q = []): int
    {
        if ($userId < 1 || !$this->readsReady()) {
            return 0;
        }
        $res = $this->list($tenantId, $userId, $level, $q + ['limit' => self::MAX_LIMIT]);
        $n = 0;
        foreach ($res['reports'] as $r) {
            if (!$r['read'] && $this->markRead($tenantId, $userId, $level, $r['key'], true)) {
                ++$n;
            }
        }

        return $n;
    }

    /** Accuse réception : le compte rendu passe « accusé » (une fois), avec l'opérateur et l'heure ; un 9-line passe à « ACKNOWLEDGED ». */
    public function acknowledge(int $tenantId, int $userId, string $level, string $key): ?array
    {
        $item = $this->find($tenantId, $userId, $level, $key);
        if ($item === null) {
            return null;
        }
        [$source, $id] = self::parseKey($key) ?? ['', 0];
        $now = gmdate('Y-m-d H:i:s');
        if ($source === self::SOURCE_TACTICAL) {
            $this->db()->prepare(
                "UPDATE atak_tactical_reports SET acknowledged_by_user_id = ?, acknowledged_at = ?, status = CASE WHEN status = 'SUBMITTED' THEN 'ACKNOWLEDGED' ELSE status END
                 WHERE id = ? AND tenant_id = ? AND acknowledged_at IS NULL"
            )->execute([$userId > 0 ? $userId : null, $now, $id, $tenantId]);
        } else {
            $this->db()->prepare("UPDATE atak_nine_line SET status = 'ACKNOWLEDGED', updated_at = ? WHERE tenant_id = ? AND id = ? AND status IN ('SUBMITTED', 'REQUESTED', 'DRAFT')")
                ->execute([$now, $tenantId, $id]);
        }

        return $this->find($tenantId, $userId, $level, $key);
    }

    /**
     * Joint le rapport à un plan d'opération (et à une phase) : un repère à la position du rapport, de uid stable
     * (un seul repère par rapport et par plan : joindre deux fois met à jour). Le droit sur le plan est vérifié par l'appelant.
     *
     * @param array<string, mixed> $plan plan lisible et modifiable (AtakPlanRepository::find)
     * @return array{ok: bool, reason?: string, uid?: string}
     */
    public function attachToPlan(int $tenantId, int $userId, string $level, string $key, array $plan, ?string $phaseUid, PlanService $plans, bool $canUnlock): array
    {
        $item = $this->find($tenantId, $userId, $level, $key);
        if ($item === null) {
            return ['ok' => false, 'reason' => 'not_found'];
        }
        if ((int) ($plan['tenant_id'] ?? 0) !== $tenantId) {
            return ['ok' => false, 'reason' => 'plan_not_found'];
        }
        $pos = $item['pos'];
        if ($pos === null && !empty($item['grid_hint'])) {
            $pos = self::gridToWorld((string) $item['grid_hint']);
        }
        if ($pos === null) {
            return ['ok' => false, 'reason' => 'no_position'];
        }
        $uid = self::planUid($key);
        $icon = ['bda' => 'target', 'cr' => 'triangle', 'sitrep' => 'flag', 'nineline' => 'cross', 'other' => 'dot'][$item['family']] ?? 'dot';
        $color = match (true) {
            $item['rank'] >= 3 => '#f24d47', $item['rank'] === 2 => '#f5b841', default => '#62b0f5',
        };
        $label = mb_substr($item['type_label'] . ' ' . $item['t'], 0, 60);
        $note = mb_substr(trim($item['title'] . '. ' . $item['summary']), 0, 400);
        $el = ['uid' => $uid, 'kind' => 'icon', 'geom' => 'point', 'pts' => [[$pos[0], $pos[1]]], 'props' => ['icon' => $icon, 'color' => $color, 'label' => $label, 'note' => $note, 'size' => 24], 'phases' => []];
        if ($phaseUid !== null && $phaseUid !== '') {
            $known = array_column($plans->repo()->phases((int) $plan['id']), 'uid');
            if (!in_array($phaseUid, $known, true)) {
                return ['ok' => false, 'reason' => 'phase_not_found'];
            }
            $el['phases'] = [$phaseUid];
        }
        $res = $plans->saveElements($plan, [$el], [], $userId > 0 ? $userId : null, [], $canUnlock);
        if (($res['rejected'] ?? []) !== []) {
            return ['ok' => false, 'reason' => (string) ($res['rejected'][0]['reason'] ?? 'rejected')];
        }
        if (($res['refused'] ?? []) !== []) {
            return ['ok' => false, 'reason' => 'locked'];
        }

        return ['ok' => true, 'uid' => $uid];
    }

    /** Identifiant du repère d'un rapport dans un plan (32 caractères au plus : [A-Za-z0-9_-]). */
    public static function planUid(string $key): string
    {
        $parsed = self::parseKey($key);

        return 'rep-' . ($parsed === null ? 'x' : self::key($parsed[0], $parsed[1]));
    }

    /**
     * Plans (de la communauté) où le rapport est déjà joint, avec la phase.
     *
     * @return list<array{plan_id: int, title: string, phases: list<string>}>
     */
    public function attachedPlans(int $tenantId, string $key): array
    {
        try {
            $st = $this->db()->prepare(
                'SELECT p.id, p.title, e.phases_json FROM atak_plan_elements e JOIN atak_plans p ON p.id = e.plan_id
                 WHERE e.uid = ? AND e.deleted = 0 AND p.tenant_id = ? AND p.deleted_at IS NULL ORDER BY p.id DESC LIMIT 12'
            );
            $st->execute([self::planUid($key), $tenantId]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $ph = json_decode((string) $r['phases_json'], true);
                $out[] = ['plan_id' => (int) $r['id'], 'title' => (string) $r['title'], 'phases' => is_array($ph) ? array_values(array_map('strval', $ph)) : []];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    // ------------------------------------------------------------------ filtres, tri, compteurs

    /** @return array<string, mixed> */
    public static function emptyCounts(): array
    {
        $fam = [];
        foreach (array_keys(TocReportCatalog::FAMILIES) as $f) {
            $fam[$f] = ['total' => 0, 'unread' => 0];
        }

        return ['total' => 0, 'unread' => 0, 'families' => $fam];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    public static function countsOf(array $items): array
    {
        $c = self::emptyCounts();
        foreach ($items as $it) {
            $f = isset($c['families'][$it['family']]) ? $it['family'] : 'other';
            ++$c['total'];
            ++$c['families'][$f]['total'];
            if (!$it['read']) {
                ++$c['unread'];
                ++$c['families'][$f]['unread'];
            }
        }

        return $c;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $q
     * @return list<array<string, mixed>>
     */
    public static function filter(array $items, array $q): array
    {
        $family = (string) ($q['family'] ?? '');
        $type = strtoupper((string) ($q['type'] ?? ''));
        $priority = strtoupper((string) ($q['priority'] ?? ''));
        $status = strtolower((string) ($q['status'] ?? ''));
        $unit = TocReportCatalog::fold((string) ($q['unit'] ?? $q['who'] ?? ''));
        $text = TocReportCatalog::fold((string) ($q['q'] ?? ''));
        $until = isset($q['until']) && is_numeric($q['until']) ? (int) $q['until'] : 0;
        $unread = !empty($q['unread']) && (string) $q['unread'] !== '0';

        return array_values(array_filter($items, static function (array $it) use ($family, $type, $priority, $status, $unit, $text, $until, $unread): bool {
            if ($family !== '' && $it['family'] !== $family) {
                return false;
            }
            if ($type !== '' && strtoupper((string) $it['type']) !== $type) {
                return false;
            }
            if ($priority !== '' && strtoupper((string) $it['priority']) !== $priority) {
                return false;
            }
            if ($status === 'open' && $it['closed']) {
                return false;
            }
            if ($status === 'closed' && !$it['closed']) {
                return false;
            }
            if ($status !== '' && $status !== 'open' && $status !== 'closed' && strtolower((string) $it['status']) !== $status) {
                return false;
            }
            if ($unread && $it['read']) {
                return false;
            }
            if ($until > 0 && $it['ts'] > $until) {
                return false;
            }
            if ($unit !== '' && !str_contains(TocReportCatalog::fold($it['who'] . ' ' . $it['unit']), $unit)) {
                return false;
            }
            if ($text !== '' && !str_contains(TocReportCatalog::fold($it['title'] . ' ' . $it['summary'] . ' ' . $it['who'] . ' ' . $it['unit'] . ' ' . $it['type_label'] . ' ' . $it['grid'] . ' ' . $it['ref']), $text)) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public static function sort(array $items, string $mode): array
    {
        $recent = static fn (array $a, array $b): int => [$b['ts'], $b['id']] <=> [$a['ts'], $a['id']];
        usort($items, match ($mode) {
            'oldest' => static fn (array $a, array $b): int => [$a['ts'], $a['id']] <=> [$b['ts'], $b['id']],
            'priority' => static fn (array $a, array $b): int => $b['rank'] <=> $a['rank'] ?: $recent($a, $b),
            'unread' => static fn (array $a, array $b): int => (int) $a['read'] <=> (int) $b['read'] ?: $recent($a, $b),
            default => $recent,
        });

        return $items;
    }

    // ------------------------------------------------------------------ sources

    /** @return list<array<string, mixed>> */
    private function fetchTactical(int $tenantId, int $mapId, int $since, int $limit): array
    {
        $sql = 'SELECT * FROM atak_tactical_reports WHERE tenant_id = ? AND deleted_at IS NULL AND report_timestamp >= ?';
        $params = [$tenantId, gmdate('Y-m-d H:i:s', max(0, $since))];
        if ($mapId > 0) {
            $sql .= ' AND context_id = ?';
            $params[] = $mapId;
        }
        $sql .= ' ORDER BY report_timestamp DESC, id DESC LIMIT ' . (int) $limit;
        try {
            $st = $this->db()->prepare($sql);
            $st->execute($params);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[toc-reports] comptes rendus illisibles : ' . $e->getMessage());

            return [];
        }
    }

    /** @return array<string, mixed>|null */
    private function tacticalRow(int $tenantId, int $id): ?array
    {
        try {
            $st = $this->db()->prepare('SELECT * FROM atak_tactical_reports WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL');
            $st->execute([$id, $tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<array<string, mixed>> */
    private function fetchNine(int $tenantId, int $mapId, int $since, int $limit): array
    {
        $sql = 'SELECT * FROM atak_nine_line WHERE tenant_id = ? AND COALESCE(updated_at, created_at) >= ?';
        $params = [$tenantId, gmdate('Y-m-d H:i:s', max(0, $since))];
        if ($mapId > 0) {
            $sql .= ' AND map_id = ?';
            $params[] = $mapId;
        }
        $sql .= ' ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT ' . (int) $limit;
        try {
            $st = $this->db()->prepare($sql);
            $st->execute($params);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[toc-reports] 9-line illisibles : ' . $e->getMessage());

            return [];
        }
    }

    /** @return array<string, mixed>|null */
    private function nineRow(int $tenantId, int $id): ?array
    {
        try {
            $st = $this->db()->prepare('SELECT * FROM atak_nine_line WHERE id = ? AND tenant_id = ?');
            $st->execute([$id, $tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Comptes rendus que cet opérateur peut lire, passages caviardés masqués.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function visibleTactical(int $tenantId, int $userId, string $level, array $rows, int &$hidden): array
    {
        $ok = [];
        foreach ($rows as $row) {
            $need = TocReportCatalog::requiredLevel((string) ($row['classification'] ?? ''), (string) ($row['visibility'] ?? 'ALL'));
            if (TocReportCatalog::rank($need) > TocReportCatalog::rank($level)) {
                ++$hidden;
                continue;
            }
            $ok[] = $row;
        }
        if ($ok === []) {
            return [];
        }
        try {
            $red = ($this->redact)($tenantId, $ok, $level, $userId);
        } catch (\Throwable) {
            // Le caviardage ne se lit pas : par prudence, aucun texte libre n'est montré.
            $red = array_map(static function (array $r): array {
                foreach (['summary', 'details', 'remarks', 'location_description'] as $f) {
                    if (isset($r[$f]) && $r[$f] !== '') {
                        $r[$f] = "\u{2588}\u{2588}\u{2588}\u{2588}";
                    }
                }

                return $r;
            }, $ok);
        }
        foreach ($ok as $i => $row) {
            $after = $red[$i] ?? $row;
            $changed = false;
            foreach (['summary', 'details', 'remarks', 'location_description'] as $f) {
                if (($after[$f] ?? null) !== ($row[$f] ?? null)) {
                    $changed = true;
                }
            }
            $ok[$i] = $after;
            $ok[$i]['_redacted'] = $changed;
        }

        return $ok;
    }

    // ------------------------------------------------------------------ présentation

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function presentTactical(array $row, bool $full): array
    {
        $type = TocReportCatalog::normalizeType((string) ($row['report_type'] ?? 'OTHER'));
        $sd = $row['structured_data'] ?? null;
        $sd = is_string($sd) ? json_decode($sd, true) : $sd;
        $sd = is_array($sd) ? $sd : [];
        $redacted = !empty($row['_redacted']);
        if ($redacted) {
            $sd = [];
        }
        $posX = self::num($row['pos_x'] ?? null);
        $posY = self::num($row['pos_y'] ?? null);
        $pos = $posX !== null && $posY !== null && !($posX == 0.0 && $posY == 0.0) ? [round($posX), round($posY)] : null;
        $stamp = (string) ($row['report_timestamp'] ?? $row['created_at'] ?? '');
        $ts = self::epoch($stamp);
        $summary = trim((string) ($row['summary'] ?? ''));
        $details = trim((string) ($row['details'] ?? ''));
        $status = strtoupper((string) ($row['status'] ?? 'SUBMITTED'));
        [$statusLabel, $closed] = TocReportCatalog::statusInfo($status);
        $priority = strtoupper((string) ($row['priority'] ?? 'ROUTINE'));
        $classification = (string) ($row['classification'] ?? 'UNCLASSIFIED');
        $visibility = (string) ($row['visibility'] ?? 'ALL');
        $bda = $type === 'BDA' ? TocReportCatalog::bdaResult($sd) : null;
        $text = TocReportCatalog::stripLead($summary !== '' ? $summary : $details, $type);
        $title = self::tacticalTitle($type, $sd, $text, $bda);
        // Ligne de description de la liste : ce que le titre ne dit pas (BDA : bilan chiffré ; autres : suite du texte).
        $lead = $type === 'BDA' ? self::join([
            TocReportCatalog::scalar($sd['ekia'] ?? null) !== '' ? 'EKIA ' . TocReportCatalog::scalar($sd['ekia']) : '',
            TocReportCatalog::scalar($sd['equip'] ?? null), TocReportCatalog::scalar($sd['ordnance'] ?? null),
        ]) : '';
        if ($lead !== '') {
            $rest = $lead;
        } elseif (!str_starts_with($text, rtrim($title, '…'))) {
            $rest = $text;
        } else {
            $rest = mb_strlen($text, 'UTF-8') > 90 && str_ends_with($title, '…') ? '…' . ltrim(mb_substr($text, mb_strlen($title, 'UTF-8') - 1, null, 'UTF-8')) : '';
        }
        $grid = trim((string) ($row['grid_reference'] ?? ''));
        $who = trim((string) ($row['submitter_callsign'] ?? ''));
        $item = [
            'key' => self::key(self::SOURCE_TACTICAL, (int) $row['id']), 'source' => self::SOURCE_TACTICAL, 'id' => (int) $row['id'],
            'family' => TocReportCatalog::familyOfType($type), 'type' => $type, 'type_label' => TocReportCatalog::typeLabel($type),
            'icon' => TocReportCatalog::icon($type), 'ref' => (string) ($row['report_number'] ?? ''),
            'title' => $title, 'summary' => self::clip($rest, 240),
            'priority' => $priority, 'priority_label' => TocReportCatalog::priorityLabel($priority), 'rank' => TocReportCatalog::priorityRank($priority),
            'status' => $status, 'status_label' => $statusLabel, 'closed' => $closed, 'acked' => !empty($row['acknowledged_at']),
            'classification' => TocReportCatalog::banner($classification, $visibility), 'required_level' => TocReportCatalog::requiredLevel($classification, $visibility),
            'who' => $who !== '' ? $who : 'Opérateur', 'unit' => trim((string) ($row['submitter_unit'] ?? '')),
            'at' => $stamp, 'ts' => $ts, 't' => self::hhmm($stamp), 'dtg' => self::dtg($ts, (string) ($row['dtg'] ?? '')), 'updated' => (string) ($row['updated_at'] ?? $stamp),
            'grid' => $grid, 'grid_hint' => $grid, 'pos' => $pos ?? self::gridToWorld($grid), 'map_id' => (int) ($row['context_id'] ?? 0),
            'read' => false, 'redacted' => $redacted, 'has_photo' => trim((string) ($sd['photo'] ?? '')) !== '' || !empty($row['has_attachments']),
            'result' => $bda === null ? null : ['code' => $bda['code'], 'label' => $bda['label'], 'tone' => $bda['tone']],
        ];
        if ($full) {
            $doc = TocReportCatalog::render($type, $sd, $details);
            $item += [
                'text' => $text, 'details' => $details, 'remarks' => trim((string) ($row['remarks'] ?? '')), 'location' => trim((string) ($row['location_description'] ?? '')),
                'facts' => $doc['facts'], 'blocks' => $doc['blocks'], 'extra' => $doc['extra'],
                'acked_at' => (string) ($row['acknowledged_at'] ?? ''), 'event_at' => (string) ($row['event_timestamp'] ?? ''),
                'mission_key' => (string) ($row['mission_key'] ?? ''), 'photo_ref' => trim((string) ($sd['photo'] ?? '')),
            ];
        }

        return $item;
    }

    /** @param array<string, mixed> $sd @param array{code: string, label: string, tone: string, raw: string}|null $bda */
    private static function tacticalTitle(string $type, array $sd, string $summary, ?array $bda): string
    {
        if ($type === 'BDA') {
            $target = '';
            foreach (['type', 'target'] as $k) {
                $v = TocReportCatalog::scalar($sd[$k] ?? null);
                if ($v !== '') {
                    $target = mb_convert_case($v, MB_CASE_TITLE, 'UTF-8');
                    break;
                }
            }
            if ($target !== '' && $bda !== null && $bda['code'] !== 'unknown') {
                return $target . ' : ' . mb_strtolower($bda['label'], 'UTF-8');
            }
            if ($bda !== null && $bda['code'] !== 'unknown') {
                return $bda['label'];
            }
        }
        // Titre tiré de la fiche elle-même (le texte du résumé écrit par le téléphone n'en dit pas toujours assez : « retour 1355 »).
        $s = static fn (string $k): string => str_replace("\n", ' ', TocReportCatalog::scalar($sd[$k] ?? null));
        $own = match ($type) {
            'OTHER' => [$s('title')],
            'TIC', 'CONTACT' => [$s('nature'), $s('size')],
            'SALUTE' => [$s('activity'), $s('size')],
            'SPOTREP' => [$s('what'), $s('count') !== '' && $s('count') !== '1' ? '× ' . $s('count') : ''],
            'SITREP' => [$s('activity'), $s('capability') !== '' ? 'capacité ' . $s('capability') : ''],
            'PATROLREP' => [$s('result'), $s('route')],
            'EAGLE_DOWN' => [$s('casualty'), $s('status')],
            'UXO' => [$s('type'), $s('priority')],
            'NBC1' => [$s('agent'), $s('delivery')],
            'FRAGO' => [$s('reference'), $s('mission')],
            'DEBRIEF' => [$s('label')],
            default => [],
        };
        $own = self::join($own);
        if ($own !== '') {
            return self::clip($own, 90);
        }
        if ($summary !== '') {
            return self::clip(trim((string) preg_replace('/\s+/u', ' ', $summary)), 90);
        }

        return TocReportCatalog::typeLabel($type);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function presentNine(array $row, bool $full): array
    {
        $kind = \App\Repositories\CasNineLineRepository::normalizeKind($row['mission_kind'] ?? 'cas');
        $lines = [];
        $count = $kind === 'fiveline' ? 5 : 9;
        for ($n = 1; $n <= $count; ++$n) {
            $lines[$n] = trim((string) ($row['line' . $n] ?? ''));
        }
        $geo = is_string($row['geo_json'] ?? null) ? json_decode($row['geo_json'], true) : null;
        $target = is_array($geo) && is_array($geo['target'] ?? null) && count($geo['target']) >= 2 ? [round((float) $geo['target'][0]), round((float) $geo['target'][1])] : null;
        $stamp = (string) ($row['created_at'] ?? '');
        $updated = (string) ($row['updated_at'] ?? $stamp);
        $ts = self::epoch($updated !== '' ? $updated : $stamp);
        $status = strtoupper((string) ($row['status'] ?? 'SUBMITTED'));
        [$statusLabel, $closed] = TocReportCatalog::statusInfo($status);
        $gridLine = $lines[TocReportCatalog::NINE_GRID_LINE[$kind]] ?? '';
        $priority = $kind === 'medevac' ? 'IMMEDIATE' : 'PRIORITY';
        $label = TocReportCatalog::nineKindLabel($kind);
        $head = match ($kind) {
            'medevac' => ($lines[3] ?? '') !== '' ? 'MEDEVAC · ' . $lines[3] : 'MEDEVAC',
            'fiveline' => ($lines[4] ?? '') !== '' ? '5-LINE · ' . $lines[4] : '5-LINE',
            default => ($lines[5] ?? '') !== '' ? '9-LINE · ' . $lines[5] : '9-LINE CAS',
        };
        $author = trim((string) ($row['author'] ?? ''));
        $item = [
            'key' => self::key(self::SOURCE_NINE, (int) $row['id']), 'source' => self::SOURCE_NINE, 'id' => (int) $row['id'],
            'family' => 'nineline', 'type' => strtoupper($kind === 'fiveline' ? 'FIVELINE' : $kind), 'type_label' => $label, 'icon' => TocReportCatalog::icon($kind === 'fiveline' ? 'FIVELINE' : $kind),
            'ref' => '', 'title' => self::clip($head, 90), 'summary' => self::clip(match ($kind) {
                'medevac' => self::join([self::tag('LZ', $lines[1] ?? ''), $lines[2] ?? '', self::tag('Sécurité', $lines[6] ?? ''), self::tag('Marquage', $lines[7] ?? '')]),
                'fiveline' => self::join([self::tag('Cible', $lines[3] ?? ''), self::tag('Amis', $lines[2] ?? '')]),
                default => self::join([self::tag('Cible', $lines[6] ?? ''), self::tag('Marquage', $lines[7] ?? ''), self::tag('Amis', $lines[8] ?? '')]),
            }, 240),
            'priority' => $priority, 'priority_label' => TocReportCatalog::priorityLabel($priority), 'rank' => TocReportCatalog::priorityRank($priority),
            'status' => $status, 'status_label' => $statusLabel, 'closed' => $closed, 'acked' => !in_array($status, ['SUBMITTED', 'REQUESTED', 'DRAFT'], true),
            'classification' => 'UNCLASSIFIED', 'required_level' => 'interne',
            'who' => $author !== '' ? $author : ($kind === 'medevac' ? 'MEDEVAC' : 'JTAC'), 'unit' => trim((string) ($row['assigned_aircraft'] ?? '')),
            'at' => $stamp, 'ts' => $ts, 't' => self::hhmm($stamp), 'dtg' => self::dtg(self::epoch($stamp), ''), 'updated' => $updated,
            'grid' => $gridLine, 'grid_hint' => $gridLine, 'pos' => $target ?? self::gridToWorld($gridLine), 'map_id' => (int) ($row['map_id'] ?? 0),
            'read' => false, 'redacted' => false, 'has_photo' => false, 'result' => null,
        ];
        if ($full) {
            $names = TocReportCatalog::NINE_LINES[$kind] ?? TocReportCatalog::NINE_LINES['cas'];
            $rows = [];
            foreach ($lines as $n => $v) {
                $rows[] = ['n' => $n, 'label' => $names[$n - 1] ?? ('Ligne ' . $n), 'value' => $v];
            }
            $item += [
                'text' => '', 'details' => '', 'remarks' => trim((string) ($row['remarks'] ?? '')), 'location' => '', 'lines' => $rows, 'mission_id' => (string) ($row['mission_id'] ?? ''),
                'facts' => [], 'blocks' => [], 'extra' => [], 'acked_at' => '', 'event_at' => '', 'mission_key' => '', 'photo_ref' => '',
                'geo' => is_array($geo) ? [
                    'fah' => (string) ($geo['fah'] ?? ''), 'danger_close' => !empty($geo['dangerClose']), 'laser' => !empty($geo['laser']), 'laser_code' => (int) ($geo['laserCode'] ?? 0),
                    'ip_name' => (string) ($geo['ipName'] ?? ''), 'aircraft' => (string) ($geo['aircraft'] ?? ''), 'ordnance' => (string) ($geo['ordnance'] ?? ''),
                ] : null,
            ];
        }

        return $item;
    }

    /**
     * Photos du rapport : clichés de la photothèque légendés avec ce compte rendu par le téléphone (« TAG · indicatif · grille »),
     * hors confidentiels. Liens servis par la route existante (communauté de la session).
     *
     * @param array<string, mixed> $item
     * @param array<string, mixed> $row
     * @return list<array{id: int, url: string}>
     */
    private function photosOf(int $tenantId, array $item, array $row): array
    {
        if (!$item['has_photo'] || $item['grid'] === '' || $item['who'] === '') {
            return [];
        }
        try {
            $st = $this->db()->prepare(
                "SELECT id, is_confidential FROM recon_images WHERE tenant_id = ? AND caption LIKE ? AND (captured_at IS NULL OR captured_at >= ?) ORDER BY id DESC LIMIT 4"
            );
            $needle = '%' . str_replace(['%', '_'], ['', ''], $item['who'] . ' · ' . $item['grid']) . '%';
            $st->execute([$tenantId, $needle, gmdate('Y-m-d H:i:s', max(0, $item['ts'] - 3600))]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                if (!empty($r['is_confidential'])) {
                    continue;
                }
                $out[] = ['id' => (int) $r['id'], 'url' => '/api/recon/images/' . (int) $r['id'] . '/file?raw=1'];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    // ------------------------------------------------------------------ lu / non lu

    public function readsReady(): bool
    {
        if ($this->readsReady === null) {
            try {
                $this->db()->query('SELECT 1 FROM atak_toc_report_reads WHERE 1 = 0');
                $this->readsReady = true;
            } catch (\Throwable) {
                $this->readsReady = false;
            }
        }

        return $this->readsReady;
    }

    public function readState(): string
    {
        return $this->readsReady() ? 'ok' : 'degraded';
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, true> clés lues
     */
    private function readSet(int $tenantId, int $userId, array $items): array
    {
        if ($items === [] || $userId < 1 || !$this->readsReady()) {
            return [];
        }
        $by = [self::SOURCE_TACTICAL => [], self::SOURCE_NINE => []];
        foreach ($items as $it) {
            $by[$it['source']][] = (int) $it['id'];
        }
        $set = [];
        foreach ($by as $source => $ids) {
            foreach (array_chunk($ids, 400) as $chunk) {
                $marks = implode(',', array_fill(0, count($chunk), '?'));
                try {
                    $st = $this->db()->prepare("SELECT report_id FROM atak_toc_report_reads WHERE tenant_id = ? AND user_id = ? AND report_kind = ? AND report_id IN ({$marks})");
                    $st->execute(array_merge([$tenantId, $userId, $source], $chunk));
                    foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
                        $set[self::key($source, (int) $id)] = true;
                    }
                } catch (\Throwable) {
                    return $set;
                }
            }
        }

        return $set;
    }

    // ------------------------------------------------------------------ utilitaires

    private static function num(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    private static function epoch(string $stamp): int
    {
        if ($stamp === '') {
            return 0;
        }
        $t = strtotime($stamp . (preg_match('/[zZ]|[+-]\d\d:?\d\d$/', $stamp) === 1 ? '' : ' UTC'));

        return $t === false ? 0 : $t;
    }

    private static function hhmm(string $stamp): string
    {
        return preg_match('/(\d{2}):(\d{2})/', $stamp, $m) === 1 ? $m[1] . ':' . $m[2] : '';
    }

    /** Groupe date-heure militaire (« 141604Z NOV 26 ») ; la valeur saisie en jeu prime si elle y ressemble. */
    private static function dtg(int $ts, string $given): string
    {
        $given = trim($given);
        if ($given !== '' && mb_strlen($given) <= 24) {
            return $given;
        }

        return $ts > 0 ? strtoupper(gmdate('dHi', $ts) . 'Z ' . gmdate('M y', $ts)) : '';
    }

    private static function clip(string $s, int $max): string
    {
        $s = trim($s);

        return mb_strlen($s, 'UTF-8') > $max ? rtrim(mb_substr($s, 0, $max - 1, 'UTF-8')) . '…' : $s;
    }

    private static function tag(string $label, string $value): string
    {
        return trim($value) === '' ? '' : $label . ' ' . trim($value);
    }

    /** @param list<string> $parts */
    private static function join(array $parts): string
    {
        return implode(' · ', array_values(array_filter(array_map('trim', $parts), static fn (string $p): bool => $p !== '')));
    }

    /**
     * Grille Arma « 0123 4567 » (ou 6 / 8 / 10 chiffres) en mètres du monde : chaque moitié des chiffres vaut un
     * nombre de kilomètres et sa fraction. Null si la grille n'est pas lisible.
     *
     * @return array{0: float, 1: float}|null
     */
    public static function gridToWorld(string $grid): ?array
    {
        // « 1538 1745 », « 153-174 » ou « 15381745 » : deux moitiés de même longueur, rien d'autre (une grille MGRS avec zone
        // ou des lettres n'est pas convertie : mieux vaut pas de repère qu'un repère faux).
        if (preg_match('/^\s*(\d{2,5})[\s.,\-\/]+(\d{2,5})\s*$/', $grid, $m) === 1 && strlen($m[1]) === strlen($m[2])) {
            [$a, $b] = [$m[1], $m[2]];
        } elseif (preg_match('/^\s*(\d{4}|\d{6}|\d{8}|\d{10})\s*$/', $grid, $m) === 1) {
            $half = intdiv(strlen($m[1]), 2);
            [$a, $b] = [substr($m[1], 0, $half), substr($m[1], $half)];
        } else {
            return null;
        }
        $scale = 10 ** (5 - strlen($a));

        return [(float) ((int) $a * $scale), (float) ((int) $b * $scale)];
    }
}
