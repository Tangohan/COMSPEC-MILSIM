<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Core\Database;
use App\Repositories\GradeRepository;
use App\Repositories\MemberGradeChangeRepository;
use App\Repositories\ModerationRepository;
use App\Repositories\OrbatBilletRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\QualificationDefinitionRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\QualificationTemporalStatusService;
use App\Services\Personnel\SenioritySummaryService;
use App\Support\GradeSelectOptions;
use PDO;

/**
 * Données de la fiche membre (Effectifs) qui ne sont pas des champs du dossier :
 * présence aux sessions, rang d’ancienneté, composition de l’unité, frise des grades,
 * éligibilité à une élévation et mesures disciplinaires.
 *
 * Toutes les lectures sont limitées à la communauté courante. Chaque bloc est calculé
 * indépendamment : une donnée indisponible n’empêche jamais l’affichage du reste de la fiche.
 */
class MemberFileInsightsService
{
    private ?PDO $pdo;

    public function __construct(
        private UserRepository $users,
        private UnitRepository $units,
        private PersonnelAssignmentRepository $assignments,
        private PersonnelJobRoleRepository $jobRoles,
        private GradeRepository $grades,
        private TenantRepository $tenants,
        private ?SenioritySummaryService $seniority = null,
        private ?MemberGradeChangeRepository $gradeChanges = null,
        private ?OrbatBilletRepository $billets = null,
        private ?ModerationRepository $moderation = null,
        ?PDO $pdo = null,
    ) {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /**
     * @param array<string, mixed> $member ligne enrichie de la fiche (id, grade_id, unit_id, enlistment_date_resolved, pre_platform_start, created_at)
     * @param list<array<string, mixed>> $qualifications attributions du membre
     * @param list<array<string, mixed>> $memberAssignments affectations actives
     * @return array<string, mixed>
     */
    public function build(int $tenantId, array $member, array $qualifications, array $memberAssignments, bool $withSanctions): array
    {
        $userId = (int) ($member['id'] ?? 0);
        $now = new \DateTimeImmutable('now');
        $out = [
            'attendance' => null,
            'rank' => null,
            'team' => null,
            'grades' => ['entries' => [], 'current_since' => null],
            'eligibility' => null,
            'eligibility_settings' => ElevationEligibility::sanitize([]),
            'sanctions' => null,
        ];
        if ($tenantId < 1 || $userId < 1) {
            return $out;
        }

        $engagement = MemberSeniorityRank::engagementDate(
            (string) ($member['enlistment_date_resolved'] ?? ''),
            (string) ($member['pre_platform_start'] ?? '')
        );

        $out['attendance'] = $this->safely(fn (): array => $this->attendance($tenantId, $userId, $member, $now), null);
        $out['rank'] = $this->safely(fn (): ?array => $this->rank($tenantId, $userId), null);
        $out['team'] = $this->safely(fn (): ?array => $this->team($tenantId, $userId, $member, $memberAssignments), null);
        $gradeId = (int) ($member['grade_id'] ?? 0);
        $out['grades'] = $this->safely(
            fn (): array => $this->gradeTimeline($tenantId, $userId, $gradeId > 0 ? $gradeId : null),
            $out['grades']
        );
        if ($withSanctions) {
            $out['sanctions'] = $this->safely(fn (): array => MemberSanctions::summarize(
                ($this->moderation ??= new ModerationRepository())->listTenantActionsForTarget($tenantId, $userId),
                $now
            ), null);
        }

        $settings = ElevationEligibility::settings($this->safely(fn (): array => $this->tenants->getSettings($tenantId), []));
        $out['eligibility_settings'] = $settings;
        if (ElevationEligibility::isConfigured($settings)) {
            $since = $out['grades']['current_since'] ?? null;
            if ($since === null && $gradeId < 1 && ($out['grades']['entries'] ?? []) === [] && $engagement !== '') {
                // Jamais gradé : le temps « sans grade » court depuis l’engagement.
                $since = $engagement;
            }
            $attendance = is_array($out['attendance']) ? $out['attendance'] : null;
            $out['eligibility'] = ElevationEligibility::evaluate($settings, [
                'months_in_grade' => $since !== null ? ElevationEligibility::monthsBetween((string) $since, $now) : null,
                'presences_90' => (int) ($attendance['present_90'] ?? 0),
                'rate_90' => $attendance['rate_90'] ?? null,
                'valid_qualification_ids' => $this->validQualificationIds($qualifications),
            ], $this->safely(fn (): array => $this->qualificationLabels($tenantId, $settings['required_qualification_ids']), []));
        }

        return $out;
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @param T $fallback
     * @return T
     */
    private function safely(callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            error_log('[fiche membre] donnée indisponible : ' . $e->getMessage());

            return $fallback;
        }
    }

    /** @param array<string, mixed> $member */
    private function attendance(int $tenantId, int $userId, array $member, \DateTimeImmutable $now): array
    {
        // Sessions retenues après l’arrivée : la plus tardive entre la création du compte et la date d’engagement.
        $join = '';
        foreach ([(string) ($member['created_at'] ?? ''), (string) ($member['enlistment_date_resolved'] ?? '')] as $candidate) {
            $candidate = substr(trim($candidate), 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate) && $candidate !== '0000-00-00' && strcmp($candidate, $join) > 0) {
                $join = $candidate;
            }
        }
        $params = [$tenantId, $now->format('Y-m-d H:i:s')];
        $joinSql = '';
        if ($join !== '') {
            $joinSql = ' AND starts_at >= ?';
            $params[] = $join . ' 00:00:00';
        }
        $st = $this->pdo()->prepare(
            'SELECT id, starts_at, title FROM community_events
             WHERE tenant_id = ? AND cancelled_at IS NULL AND starts_at < ?' . $joinSql . '
             ORDER BY starts_at DESC LIMIT 2000'
        );
        $st->execute($params);
        $events = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $st = $this->pdo()->prepare(
            'SELECT r.* FROM community_event_rsvps r
             INNER JOIN community_events ce ON ce.id = r.event_id
             WHERE r.user_id = ? AND ce.tenant_id = ? AND ce.cancelled_at IS NULL AND ce.starts_at < ?'
        );
        $st->execute([$userId, $tenantId, $now->format('Y-m-d H:i:s')]);
        $rsvps = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $rsvps[(int) ($r['event_id'] ?? 0)] = $r;
        }

        return MemberAttendanceSummary::summarize($events, $rsvps, $join !== '' ? $join : null, $now);
    }

    /** @return ?array{rank:int,total:int} */
    private function rank(int $tenantId, int $userId): ?array
    {
        $ids = $this->users->listActiveUserIdsForTenant($tenantId);
        if (!in_array($userId, $ids, true)) {
            return null;
        }
        $enlist = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach ($this->users->listEffectifsRosterByIds($tenantId, $chunk) as $row) {
                $start = trim((string) ($row['enlistment_date_resolved'] ?? ''));
                if ($start !== '') {
                    $enlist[(int) ($row['id'] ?? 0)] = $start;
                }
            }
        }
        $packs = [];
        if ($this->seniority !== null) {
            $packs = $this->seniority->dashboardLabelsByUsers($tenantId, $ids, $enlist);
        }
        $engagement = [];
        foreach ($ids as $id) {
            $pack = $packs[$id] ?? [];
            $date = MemberSeniorityRank::engagementDate(
                (string) ($pack['community_start'] ?? ($enlist[$id] ?? '')),
                (string) ($pack['pre_platform_start'] ?? '')
            );
            if ($date !== '') {
                $engagement[$id] = $date;
            }
        }

        return MemberSeniorityRank::rank($userId, $ids, $engagement);
    }

    /**
     * @param array<string, mixed> $member
     * @param list<array<string, mixed>> $memberAssignments
     * @return ?array<string, mixed>
     */
    private function team(int $tenantId, int $userId, array $member, array $memberAssignments): ?array
    {
        $unitId = 0;
        foreach ($memberAssignments as $a) {
            if (!empty($a['is_primary']) && (int) ($a['unit_id'] ?? 0) > 0) {
                $unitId = (int) $a['unit_id'];
                break;
            }
        }
        if ($unitId < 1) {
            $unitId = (int) ($member['unit_id'] ?? 0);
        }
        if ($unitId < 1 || $this->units->findById($unitId, $tenantId) === null) {
            return null;
        }
        $billetRepo = $this->billets ??= new OrbatBilletRepository();
        $billets = $billetRepo->schemaReady() ? $billetRepo->manningForUnit($tenantId, $unitId)['billets'] : [];

        $members = $this->assignments->listActiveMembersByUnitForTenant($tenantId)[$unitId] ?? [];
        $memberIds = array_values(array_unique(array_map(static fn (array $m): int => (int) ($m['user_id'] ?? 0), $members)));
        $jobs = $memberIds !== [] && $this->jobRoles->tablesExist()
            ? $this->jobRoles->listPivotAssignmentsForUsers($tenantId, $memberIds)
            : [];
        $unitMembers = [];
        $seen = [];
        foreach ($members as $m) {
            $uid = (int) ($m['user_id'] ?? 0);
            if ($uid < 1 || isset($seen[$uid])) {
                continue;
            }
            $seen[$uid] = true;
            $label = trim((string) ($m['display_name'] ?? '')) ?: trim((string) ($m['callsign'] ?? '')) ?: 'Membre';
            $rows = $jobs[$uid] ?? [];
            $unitMembers[] = [
                'user_id' => $uid,
                'label' => $label,
                'job_role_ids' => array_values(array_map(static fn (array $r): int => (int) ($r['personnel_job_role_id'] ?? 0), $rows)),
                'job_label' => trim((string) ($rows[0]['role_name'] ?? '')) ?: trim((string) ($m['role_name'] ?? '')),
            ];
        }
        $meta = $this->units->hierarchyMetaByUnitId($tenantId)[$unitId] ?? [];
        $path = array_values(array_filter(array_map('trim', explode(' / ', (string) ($meta['path'] ?? ''))), static fn (string $s): bool => $s !== ''));

        return TeamComposition::compose($billets, $unitMembers, $userId) + [
            'unit_id' => $unitId,
            'path' => $path,
            'has_posts' => $billets !== [],
        ];
    }

    /** @return array{entries: list<array<string, mixed>>, current_since: ?string} */
    private function gradeTimeline(int $tenantId, int $userId, ?int $currentGradeId): array
    {
        $labels = [];
        foreach ($this->grades->listForTenant($tenantId) as $g) {
            $labels[(int) ($g['id'] ?? 0)] = GradeSelectOptions::label($g);
        }
        $recorded = ($this->gradeChanges ??= new MemberGradeChangeRepository())->listForUser($tenantId, $userId);

        return MemberGradeTimeline::build($recorded, $this->gradeTraces($tenantId, $userId), $currentGradeId, $labels);
    }

    /**
     * Traces de changements de grade antérieures à l’historique : journal des modifications « grade »
     * faites depuis la fiche et demandes d’élévation acceptées avec un grade proposé.
     *
     * @return list<array{date:string,to_grade_id:?int,source:string,actor_label:string}>
     */
    private function gradeTraces(int $tenantId, int $userId): array
    {
        $out = [];
        try {
            $st = $this->pdo()->prepare(
                "SELECT a.created_at, a.new_value, u.display_name AS actor
                 FROM audit_logs a
                 LEFT JOIN users u ON u.id = a.user_id
                 WHERE a.tenant_id = ? AND a.action = 'user_updated' AND a.entity_type = 'user'
                   AND a.entity_id = ? AND a.old_value = 'grade'"
            );
            $st->execute([$tenantId, $userId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $value = trim((string) ($r['new_value'] ?? ''));
                if ($value !== 'aucun' && !ctype_digit($value)) {
                    continue;
                }
                $out[] = [
                    'date' => (string) ($r['created_at'] ?? ''),
                    'to_grade_id' => $value === 'aucun' ? null : (int) $value,
                    'source' => MemberGradeChangeRepository::SOURCE_MANUAL,
                    'actor_label' => trim((string) ($r['actor'] ?? '')),
                ];
            }
        } catch (\Throwable) {
        }
        try {
            $st = $this->pdo()->prepare(
                "SELECT er.resolved_at, er.proposed_grade_id, u.display_name AS actor
                 FROM elevation_requests er
                 LEFT JOIN users u ON u.id = er.resolved_by
                 WHERE er.tenant_id = ? AND er.target_user_id = ? AND er.status = 'approved'
                   AND er.proposed_grade_id IS NOT NULL AND er.resolved_at IS NOT NULL"
            );
            $st->execute([$tenantId, $userId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                if ((int) ($r['proposed_grade_id'] ?? 0) < 1) {
                    continue;
                }
                $out[] = [
                    'date' => (string) ($r['resolved_at'] ?? ''),
                    'to_grade_id' => (int) $r['proposed_grade_id'],
                    'source' => MemberGradeChangeRepository::SOURCE_ELEVATION,
                    'actor_label' => trim((string) ($r['actor'] ?? '')),
                ];
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    /**
     * Qualifications détenues et valides aujourd’hui (y compris celles qui expirent bientôt).
     *
     * @param list<array<string, mixed>> $qualifications
     * @return list<int>
     */
    private function validQualificationIds(array $qualifications): array
    {
        $temporal = new QualificationTemporalStatusService();
        $ids = [];
        foreach ($qualifications as $q) {
            $def = (int) ($q['definition_id'] ?? 0);
            if ($def < 1) {
                continue;
            }
            $code = (string) ($temporal->resolve($q)['code'] ?? '');
            if (in_array($code, [QualificationTemporalStatusService::VALID, QualificationTemporalStatusService::EXPIRING_SOON], true)) {
                $ids[$def] = $def;
            }
        }

        return array_values($ids);
    }

    /**
     * @param list<int> $ids
     * @return array<int, string>
     */
    private function qualificationLabels(int $tenantId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach ((new QualificationDefinitionRepository())->listForTenant($tenantId, true) as $d) {
            $id = (int) ($d['id'] ?? 0);
            if (in_array($id, $ids, true)) {
                $out[$id] = trim((string) ($d['name'] ?? '')) ?: 'Qualification';
            }
        }

        return $out;
    }
}
