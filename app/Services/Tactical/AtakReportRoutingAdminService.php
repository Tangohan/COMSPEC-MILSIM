<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Core\Database;
use App\Repositories\RoleRepository;
use App\Repositories\UnitRepository;
use App\Support\AtakIcemanReportCatalog;
use PDO;

/**
 * Lecture et outils de l'écran « Diffusion des rapports » (back-office).
 *
 * - Annuaire des destinataires possibles (fonctions, unités, membres) et résolution des identifiants
 *   enregistrés dans les règles (le moteur accepte id, slug ou nom : les anciennes règles saisies à la
 *   main restent lisibles, celles qui ne correspondent à rien sont signalées).
 * - Statistiques par règle et journal des diffusions.
 * - Simulateur : quelles règles s'appliqueraient à un rapport donné, et pourquoi les autres non.
 *   Reprend exactement les conditions du moteur (AtakReportRoutingRepository::matchesConditions).
 */
final class AtakReportRoutingAdminService
{
    private ?PDO $pdo = null;

    /** @var array<string, array<string, string>>|null */
    private ?array $directoryIndex = null;

    public function __construct(private int $tenantId)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /**
     * @param list<mixed> $p
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $p = []): array
    {
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($p);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    // ================================================================= types

    /**
     * Types de rapport : formats standard puis formats Iceman (ATAK natif).
     *
     * @param array<string, string> $standard
     * @return array<string, array<string, string>> groupe => [code => libellé]
     */
    public static function reportTypeGroups(array $standard): array
    {
        $iceman = [];
        try {
            foreach (AtakIcemanReportCatalog::routingOptions() as $code => $label) {
                if (!isset($standard[$code])) {
                    $iceman[$code] = $label;
                }
            }
        } catch (\Throwable) {
        }

        return array_filter(['Type de rapport' => $standard, 'Autres formats ATAK natif' => $iceman]);
    }

    // ================================================================= annuaire

    /**
     * @return array{roles: list<array{id: string, label: string, sub: string}>, units: list<array{id: string, label: string, sub: string}>, users: list<array{id: string, label: string, sub: string}>}
     */
    public function directory(): array
    {
        $roles = [];
        try {
            foreach ((new RoleRepository())->allForTenant($this->tenantId) as $r) {
                $roles[] = ['id' => (string) (($r['slug'] ?? '') !== '' ? $r['slug'] : $r['id']), 'label' => (string) ($r['name'] ?? $r['slug'] ?? ''), 'sub' => (string) ($r['slug'] ?? '')];
            }
        } catch (\Throwable) {
        }
        $units = [];
        try {
            foreach ((new UnitRepository())->allForTenant($this->tenantId) as $u) {
                $units[] = ['id' => (string) $u['id'], 'label' => (string) ($u['name'] ?? ''), 'sub' => trim((string) ($u['code'] ?? $u['short_label'] ?? ''))];
            }
        } catch (\Throwable) {
        }
        $users = [];
        foreach ($this->rows(
            "SELECT id, COALESCE(NULLIF(display_name, ''), email) AS label, callsign FROM users
             WHERE tenant_id = ? AND (deleted_at IS NULL) AND COALESCE(status, '') = 'active'
             ORDER BY label LIMIT 1000",
            [$this->tenantId]
        ) as $u) {
            $users[] = ['id' => (string) $u['id'], 'label' => (string) $u['label'], 'sub' => (string) ($u['callsign'] ?? '')];
        }
        if ($users === []) {
            // Installations sans colonne deleted_at.
            foreach ($this->rows("SELECT id, COALESCE(NULLIF(display_name, ''), email) AS label, callsign FROM users WHERE tenant_id = ? AND COALESCE(status, '') = 'active' ORDER BY label LIMIT 1000", [$this->tenantId]) as $u) {
                $users[] = ['id' => (string) $u['id'], 'label' => (string) $u['label'], 'sub' => (string) ($u['callsign'] ?? '')];
            }
        }

        return ['roles' => $roles, 'units' => $units, 'users' => $users];
    }

    /**
     * Libellé lisible d'un destinataire enregistré + s'il correspond encore à quelque chose.
     *
     * @return array{label: string, known: bool, id: string}
     */
    public function resolve(string $type, string $identifier): array
    {
        if ($this->directoryIndex === null) {
            $this->directoryIndex = ['ROLE' => [], 'UNIT' => [], 'USER' => []];
            try {
                foreach ((new RoleRepository())->allForTenant($this->tenantId) as $r) {
                    foreach (['id', 'slug', 'name'] as $f) {
                        $v = mb_strtolower(trim((string) ($r[$f] ?? '')), 'UTF-8');
                        if ($v !== '') {
                            $this->directoryIndex['ROLE'][$v] = (string) ($r['name'] ?? $r['slug']);
                        }
                    }
                }
            } catch (\Throwable) {
            }
            try {
                foreach ((new UnitRepository())->allForTenant($this->tenantId) as $u) {
                    foreach (['id', 'slug', 'code', 'name'] as $f) {
                        $v = mb_strtolower(trim((string) ($u[$f] ?? '')), 'UTF-8');
                        if ($v !== '') {
                            $this->directoryIndex['UNIT'][$v] = (string) ($u['name'] ?? $u['code']);
                        }
                    }
                }
            } catch (\Throwable) {
            }
        }
        $key = mb_strtolower(trim($identifier), 'UTF-8');
        if ($type === 'USER') {
            $u = $this->rows("SELECT COALESCE(NULLIF(display_name, ''), email) l FROM users WHERE id = ? AND tenant_id = ?", [(int) $identifier, $this->tenantId])[0] ?? null;

            return ['label' => $u !== null ? (string) $u['l'] : 'Membre #' . $identifier, 'known' => $u !== null, 'id' => $identifier];
        }
        $label = $this->directoryIndex[$type][$key] ?? null;

        return ['label' => $label ?? $identifier, 'known' => $label !== null, 'id' => $identifier];
    }

    // ================================================================= statistiques & journal

    /**
     * @return array{rules: array<int, array<string, mixed>>, totals: array<string, mixed>}
     */
    public function stats(int $days = 30): array
    {
        $byRule = [];
        foreach ($this->rows(
            "SELECT rh.routing_rule_id rid, COUNT(DISTINCT rh.report_id) reports, COUNT(*) routes,
                    SUM(rh.notification_sent = 1) notified, SUM(rh.acknowledged = 1) acked,
                    ROUND(AVG(CASE WHEN rh.acknowledged = 1 THEN TIMESTAMPDIFF(MINUTE, rh.routed_at, rh.acknowledged_at) END)) ack_min,
                    MAX(rh.routed_at) last_at
             FROM atak_report_routing_history rh JOIN atak_tactical_reports r ON r.id = rh.report_id
             WHERE r.tenant_id = ? AND rh.routed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY rh.routing_rule_id",
            [$this->tenantId, $days]
        ) as $r) {
            $byRule[(int) $r['rid']] = $r;
        }
        $t = $this->rows(
            "SELECT COUNT(DISTINCT rh.report_id) reports, COUNT(*) routes, SUM(rh.notification_sent = 1) notified,
                    SUM(rh.acknowledged = 1) acked,
                    ROUND(AVG(CASE WHEN rh.acknowledged = 1 THEN TIMESTAMPDIFF(MINUTE, rh.routed_at, rh.acknowledged_at) END)) ack_min,
                    SUM(rh.acknowledged = 0 AND r.status = 'SUBMITTED') waiting
             FROM atak_report_routing_history rh JOIN atak_tactical_reports r ON r.id = rh.report_id
             WHERE r.tenant_id = ? AND rh.routed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$this->tenantId, $days]
        )[0] ?? [];
        $reports = (int) ($this->rows(
            'SELECT COUNT(*) n FROM atak_tactical_reports WHERE tenant_id = ? AND deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)',
            [$this->tenantId, $days]
        )[0]['n'] ?? 0);

        return ['rules' => $byRule, 'totals' => $t + ['all_reports' => $reports]];
    }

    /**
     * Dernières diffusions (une ligne par destinataire).
     *
     * @return list<array<string, mixed>>
     */
    public function journal(int $limit = 150, ?int $ruleId = null, bool $waitingOnly = false): array
    {
        $where = 'r.tenant_id = ?';
        $p = [$this->tenantId];
        if ($ruleId !== null && $ruleId > 0) {
            $where .= ' AND rh.routing_rule_id = ?';
            $p[] = $ruleId;
        }
        if ($waitingOnly) {
            $where .= ' AND rh.acknowledged = 0';
        }
        $rows = $this->rows(
            "SELECT rh.id, rh.report_id, rh.routing_rule_id, rr.rule_name, rh.routed_to_type, rh.routed_to_identifier,
                    rh.notification_sent, rh.notification_sent_at, rh.acknowledged, rh.acknowledged_at, rh.routed_at,
                    COALESCE(NULLIF(ack.display_name, ''), ack.email) ack_by,
                    r.report_type, r.report_number, r.priority, r.summary, r.status, r.submitter_callsign,
                    TIMESTAMPDIFF(MINUTE, rh.routed_at, COALESCE(rh.acknowledged_at, NOW())) delay_min,
                    rr.escalate_after_minutes
             FROM atak_report_routing_history rh
             JOIN atak_tactical_reports r ON r.id = rh.report_id
             LEFT JOIN atak_report_routing_rules rr ON rr.id = rh.routing_rule_id
             LEFT JOIN users ack ON ack.id = rh.acknowledged_by_user_id
             WHERE {$where}
             ORDER BY rh.id DESC LIMIT " . max(1, min(500, $limit)),
            $p
        );
        foreach ($rows as &$r) {
            $r['recipient'] = $this->resolve((string) $r['routed_to_type'], (string) $r['routed_to_identifier']);
            $r['overdue'] = (int) $r['acknowledged'] === 0 && (int) ($r['escalate_after_minutes'] ?? 0) > 0
                && (int) $r['delay_min'] >= (int) $r['escalate_after_minutes'];
        }
        unset($r);

        return $rows;
    }

    /**
     * Rapports récents proposés dans le simulateur.
     *
     * @return list<array<string, mixed>>
     */
    public function recentReports(int $limit = 40): array
    {
        return $this->rows(
            'SELECT id, report_type, report_number, priority, summary, submitter_callsign, created_at
             FROM atak_tactical_reports WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)),
            [$this->tenantId]
        );
    }

    // ================================================================= simulateur

    /**
     * Évalue toutes les règles (actives et inactives) pour un rapport réel ou fictif.
     *
     * @param array{report_id?: int, report_type?: string, priority?: string, text?: string} $input
     * @param list<array<string, mixed>> $rules
     * @return array{report: array<string, mixed>, results: list<array<string, mixed>>, recipients: list<array<string, mixed>>}
     */
    public function simulate(array $input, array $rules): array
    {
        $report = null;
        if ((int) ($input['report_id'] ?? 0) > 0) {
            $report = $this->rows(
                'SELECT id, report_type, report_number, priority, summary, details FROM atak_tactical_reports WHERE id = ? AND tenant_id = ?',
                [(int) $input['report_id'], $this->tenantId]
            )[0] ?? null;
        }
        $report ??= [
            'id' => null,
            'report_type' => strtoupper(trim((string) ($input['report_type'] ?? ''))),
            'priority' => strtoupper(trim((string) ($input['priority'] ?? 'ROUTINE'))),
            'summary' => (string) ($input['text'] ?? ''),
            'details' => '',
        ];

        $results = [];
        $recipients = [];
        usort($rules, static fn ($a, $b) => (int) $a['priority_order'] <=> (int) $b['priority_order']);
        foreach ($rules as $rule) {
            [$ok, $reasons] = self::evaluate($report, $rule);
            $active = !empty($rule['is_active']);
            $dest = [];
            foreach (['ROLE' => 'auto_assign_to_roles', 'UNIT' => 'auto_assign_to_units', 'USER' => 'auto_assign_to_users'] as $type => $col) {
                foreach (self::jsonList($rule[$col] ?? null) as $idf) {
                    $r = $this->resolve($type, (string) $idf) + ['type' => $type];
                    $dest[] = $r;
                    if ($ok && $active) {
                        $recipients[$type . ':' . mb_strtolower((string) $idf)] = $r + ['rule' => (string) $rule['rule_name']];
                    }
                }
            }
            $results[] = [
                'id' => (int) $rule['id'],
                'name' => (string) $rule['rule_name'],
                'order' => (int) $rule['priority_order'],
                'active' => $active,
                'matches' => $ok,
                'applies' => $ok && $active,
                'reasons' => $reasons,
                'recipients' => $dest,
            ];
        }

        return ['report' => $report, 'results' => $results, 'recipients' => array_values($recipients)];
    }

    /**
     * Mêmes conditions que le moteur, avec l'explication de chaque verdict.
     *
     * @param array<string, mixed> $report
     * @param array<string, mixed> $rule
     * @return array{0: bool, 1: list<array{ok: bool, text: string}>}
     */
    public static function evaluate(array $report, array $rule): array
    {
        $c = json_decode((string) ($rule['trigger_conditions'] ?? ''), true);
        $c = is_array($c) ? $c : [];
        $reasons = [];
        $ok = true;
        if (!empty($c['report_types'])) {
            $hit = in_array((string) ($report['report_type'] ?? ''), $c['report_types'], true);
            $reasons[] = ['ok' => $hit, 'text' => $hit ? 'Type ' . $report['report_type'] . ' visé' : 'Type ' . (($report['report_type'] ?? '') ?: '—') . ' non visé'];
            $ok = $ok && $hit;
        } else {
            $reasons[] = ['ok' => true, 'text' => 'Tous les types'];
        }
        if (!empty($c['priorities'])) {
            $hit = in_array((string) ($report['priority'] ?? ''), $c['priorities'], true);
            $reasons[] = ['ok' => $hit, 'text' => $hit ? 'Priorité ' . $report['priority'] . ' visée' : 'Priorité ' . (($report['priority'] ?? '') ?: '—') . ' non visée'];
            $ok = $ok && $hit;
        }
        if (!empty($c['keywords'])) {
            $content = mb_strtolower((string) ($report['summary'] ?? '') . ' ' . (string) ($report['details'] ?? ''), 'UTF-8');
            $found = null;
            foreach ($c['keywords'] as $k) {
                if ($k !== '' && str_contains($content, mb_strtolower((string) $k, 'UTF-8'))) {
                    $found = (string) $k;
                    break;
                }
            }
            $reasons[] = ['ok' => $found !== null, 'text' => $found !== null ? 'Mot-clé « ' . $found . ' » trouvé' : 'Aucun mot-clé dans le texte'];
            $ok = $ok && $found !== null;
        }
        if (!empty($c['within_zone_ids'])) {
            $reasons[] = ['ok' => true, 'text' => 'Condition de zone : non simulée (vérifiée à la réception)'];
        }
        if (empty($rule['is_active'])) {
            $reasons[] = ['ok' => false, 'text' => 'Règle désactivée'];
        }

        return [$ok, $reasons];
    }

    /** @return list<mixed> */
    public static function jsonList(mixed $v): array
    {
        $d = is_string($v) ? json_decode($v, true) : (is_array($v) ? $v : null);

        return is_array($d) && array_is_list($d) ? $d : [];
    }
}
