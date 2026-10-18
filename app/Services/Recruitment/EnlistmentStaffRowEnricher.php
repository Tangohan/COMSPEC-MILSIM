<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use App\Core\Container;
use App\Repositories\EnlistmentRecruitmentEngagementRepository;
use App\Repositories\EnlistmentTimelineRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\RecruitmentOpeningRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Support\EnlistmentStatusCatalog;

/**
 * Enrichit les lignes de candidature pour les tableaux staff (bureau recrutement et tableau de bord) :
 * statut, étape, délai, bilan 30 jours, instructeur et affectation — calculés une seule fois, au même endroit.
 *
 * Clés ajoutées à chaque ligne :
 *   status_meta, stage, delay, retro (tableaux de EnlistmentStatusCatalog),
 *   submitted_age_hours, submitted_sla_breached, enlistment_sla_hours, last_action_at, enlistment_age_days,
 *   staff_retro_done_at, staff_retro_status (done | due | waiting | pending_decision | not_applicable | unavailable | unknown),
 *   instructor_user_id, instructor_label,
 *   assignment_unit_label, assignment_role_label, assignment_opening_title, assignment_source (opening | member | ''),
 *   dossier_url, assignment_define_url, bilan_url.
 */
final class EnlistmentStaffRowEnricher
{
    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function enrich(int $tenantId, array $rows, int $slaHours, ?int $now = null): array
    {
        if ($rows === [] || $tenantId < 1) {
            return $rows;
        }
        $now ??= time();
        $slaHours = max(1, $slaHours);

        $reviewerIds = [];
        $openingIds = [];
        $enlistmentIds = [];
        $memberIds = [];
        foreach ($rows as $r) {
            $rid = (int) ($r['reviewed_by'] ?? 0);
            if ($rid > 0) {
                $reviewerIds[$rid] = $rid;
            }
            $oid = (int) ($r['recruitment_opening_id'] ?? 0);
            if ($oid > 0) {
                $openingIds[$oid] = $oid;
            }
            $eid = (int) ($r['id'] ?? 0);
            if ($eid > 0) {
                $enlistmentIds[$eid] = $eid;
            }
            $mid = (int) ($r['submitter_user_id'] ?? 0);
            if ($mid > 0 && (string) ($r['status'] ?? '') === 'reviewed') {
                $memberIds[$mid] = $mid;
            }
        }

        $reviewers = $this->reviewers($tenantId, array_values($reviewerIds));
        [$openings, $jobRoleLabels] = $this->openings($tenantId, array_values($openingIds));
        [$retroDone, $retroReady] = $this->retroDoneMap($tenantId, array_values($enlistmentIds));
        $memberUnits = $memberIds !== [] ? $this->memberUnitLabels($tenantId) : [];

        foreach ($rows as &$row) {
            $eid = (int) ($row['id'] ?? 0);
            $status = (string) ($row['status'] ?? '');

            $lastAction = trim((string) ($row['updated_at'] ?? ''));
            if ($lastAction === '') {
                $lastAction = trim((string) ($row['created_at'] ?? ''));
            }
            $row['last_action_at'] = $lastAction !== '' ? $lastAction : null;
            $lastTs = $lastAction !== '' ? strtotime($lastAction) : false;
            $ageHours = $lastTs !== false && $lastTs > 0 ? max(0, (int) floor(($now - $lastTs) / 3600)) : null;
            $row['submitted_age_hours'] = $ageHours;
            $row['submitted_sla_breached'] = $status === 'submitted' && $ageHours !== null && $ageHours > $slaHours;
            $row['enlistment_sla_hours'] = $slaHours;
            $row['enlistment_age_days'] = EnlistmentStatusCatalog::daysSince((string) ($row['created_at'] ?? ''), $now);

            $row['status_meta'] = EnlistmentStatusCatalog::status($status, !empty($row['auto_rejected']));
            $row['stage'] = EnlistmentStatusCatalog::stage($row);
            $row['delay'] = EnlistmentStatusCatalog::delay($row, $slaHours, $now);

            $doneAt = $eid > 0 && array_key_exists($eid, $retroDone) ? (string) $retroDone[$eid] : null;
            $row['staff_retro_done_at'] = $doneAt !== null && trim($doneAt) !== '' ? trim($doneAt) : null;
            $retro = EnlistmentStatusCatalog::retro($row, $doneAt, $retroReady, $now);
            $row['retro'] = $retro;
            $row['staff_retro_status'] = $retro['key'];

            $reviewedById = (int) ($row['reviewed_by'] ?? 0);
            $row['instructor_user_id'] = $reviewedById > 0 ? $reviewedById : null;
            $row['instructor_label'] = $reviewedById > 0 ? self::userLabel($reviewers[$reviewedById] ?? null, $reviewedById) : '';

            $oid = (int) ($row['recruitment_opening_id'] ?? 0);
            $opening = $oid > 0 ? ($openings[$oid] ?? null) : null;
            $unitLabel = $opening ? trim((string) ($opening['unit_name'] ?? '')) : '';
            $jrid = $opening ? (int) ($opening['personnel_job_role_id'] ?? 0) : 0;
            $roleLabel = $jrid > 0 ? trim((string) ($jobRoleLabels[$jrid] ?? '')) : '';
            if ($roleLabel === '') {
                $roleLabel = trim((string) ($row['specialty'] ?? ''));
            }
            $source = $unitLabel !== '' ? 'opening' : '';
            // Membre intégré sans poste lié : on montre son unité réelle plutôt que « Non définie ».
            $mid = (int) ($row['submitter_user_id'] ?? 0);
            if ($unitLabel === '' && $status === 'reviewed' && $mid > 0 && isset($memberUnits[$mid])) {
                $unitLabel = $memberUnits[$mid];
                $source = 'member';
            }
            $row['assignment_unit_label'] = $unitLabel;
            $row['assignment_role_label'] = $roleLabel;
            $row['assignment_opening_title'] = $opening ? trim((string) ($opening['title'] ?? '')) : '';
            $row['assignment_source'] = $source;

            $row['dossier_url'] = url('back-office/recruitments/' . $eid . '?dossier=1');
            $row['assignment_define_url'] = url('back-office/recruitments/' . $eid . '?dossier=1#coordination-dossier');
            $row['bilan_url'] = url('back-office/recruitments/' . $eid . '?dossier=1#bilan-recrutement');
        }
        unset($row);

        return $rows;
    }

    /**
     * Libellé lisible d'un membre (nom affiché, indicatif, e-mail).
     *
     * @param array<string, mixed>|null $urow
     */
    public static function userLabel(?array $urow, int $userId): string
    {
        if ($userId < 1) {
            return '';
        }
        if (!is_array($urow)) {
            return 'Membre n°' . $userId;
        }
        foreach (['display_name', 'callsign', 'email'] as $k) {
            $lab = trim((string) ($urow[$k] ?? ''));
            if ($lab !== '') {
                return $lab;
            }
        }

        return 'Membre n°' . $userId;
    }

    /**
     * @param list<int> $ids
     * @return array<int, array<string, mixed>>
     */
    private function reviewers(int $tenantId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        try {
            return Container::get(UserRepository::class)->findByIdsForTenant($tenantId, $ids);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param list<int> $ids
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function openings(int $tenantId, array $ids): array
    {
        $openings = [];
        $labels = [];
        if ($ids === []) {
            return [$openings, $labels];
        }
        try {
            $repo = Container::get(RecruitmentOpeningRepository::class);
            if (!$repo->tablesExist()) {
                return [$openings, $labels];
            }
            $jobRoles = new PersonnelJobRoleRepository();
            foreach ($ids as $oid) {
                $orow = $repo->findByIdForTenant($oid, $tenantId);
                if (!$orow) {
                    continue;
                }
                $openings[$oid] = $orow;
                $jrid = (int) ($orow['personnel_job_role_id'] ?? 0);
                if ($jrid > 0 && !isset($labels[$jrid])) {
                    $jr = $jobRoles->findRoleById($jrid, $tenantId) ?? [];
                    $lab = trim((string) ($jr['name'] ?? ''));
                    $labels[$jrid] = $lab !== '' ? $lab : trim((string) ($jr['title'] ?? ''));
                }
            }
        } catch (\Throwable) {
            return [[], []];
        }

        return [$openings, $labels];
    }

    /**
     * Bilans d'équipe déjà saisis (table dédiée, puis chronologie du dossier en secours).
     *
     * @param list<int> $ids
     * @return array{0: array<int, string>, 1: bool}
     */
    private function retroDoneMap(int $tenantId, array $ids): array
    {
        $map = [];
        $ready = false;
        try {
            $engagement = Container::get(EnlistmentRecruitmentEngagementRepository::class);
            $ready = $engagement->retroTableExists();
            if ($ready && $ids !== []) {
                $map = $engagement->mapStaffRetroDoneIds($tenantId, $ids);
            }
        } catch (\Throwable) {
            $map = [];
            $ready = false;
        }
        if ($ids !== []) {
            try {
                $timeline = new EnlistmentTimelineRepository();
                if ($timeline->tableExists()) {
                    foreach ($timeline->mapStaffRetroDoneFromTimeline($tenantId, $ids) as $eid => $doneAt) {
                        $eid = (int) $eid;
                        if ($eid > 0 && !array_key_exists($eid, $map)) {
                            $map[$eid] = (string) $doneAt;
                        }
                    }
                }
            } catch (\Throwable) {
            }
        }

        return [$map, $ready];
    }

    /**
     * Unité principale actuelle des membres du tenant (user_id => nom d'unité).
     *
     * @return array<int, string>
     */
    private function memberUnitLabels(int $tenantId): array
    {
        try {
            $byUser = Container::get(PersonnelAssignmentRepository::class)->primaryUnitIdByUserForTenant($tenantId);
            if ($byUser === []) {
                return [];
            }
            $names = [];
            foreach ((new UnitRepository())->allForTenant($tenantId) as $u) {
                $uid = (int) ($u['id'] ?? 0);
                $name = trim((string) ($u['name'] ?? ''));
                if ($uid > 0 && $name !== '') {
                    $names[$uid] = $name;
                }
            }
            $out = [];
            foreach ($byUser as $userId => $unitId) {
                if (isset($names[$unitId])) {
                    $out[(int) $userId] = $names[$unitId];
                }
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
