<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PersonnelRoleplayTimelineRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;

final class RoleplayFollowupAdminController
{
    public function __construct(
        private UserRepository $userRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelRoleplayTimelineRepository $timelineRepository,
        private TenantRepository $tenantRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }

        $cfg = $this->roleplayFollowupConfig($tenantId);

        $rows = [];
        $enabled = !empty($cfg['enabled']);
        $today = date('Y-m-d');
        $countEligible = 0;
        $countTracked = 0;
        foreach ($this->userRepository->allForTenant($tenantId) as $u) {
            if ((string) ($u['status'] ?? '') !== 'active') {
                continue;
            }
            $uid = (int) ($u['id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $p = $this->personnelProfileRepository->getByUserId($uid) ?? [];
            $hasTracking = trim((string) ($p['rp_followup_stage'] ?? '')) !== ''
                || (int) ($p['rp_tutor_user_id'] ?? 0) > 0
                || trim((string) ($p['rp_recruitment_stream'] ?? '')) !== ''
                || trim((string) ($p['rp_operational_function'] ?? '')) !== ''
                || trim((string) ($p['rp_recruitment_origin'] ?? '')) !== '';
            if ($hasTracking) {
                $countTracked++;
            }
            $snapshot = [];
            if (!empty($p['rp_eligibility_snapshot_json']) && is_string($p['rp_eligibility_snapshot_json'])) {
                $decoded = json_decode((string) $p['rp_eligibility_snapshot_json'], true);
                $snapshot = is_array($decoded) ? $decoded : [];
            }
            if (!empty($snapshot['eligible'])) {
                $countEligible++;
            }
            $tutorLabel = null;
            $tid = (int) ($p['rp_tutor_user_id'] ?? 0);
            if ($tid > 0) {
                $tu = $this->userRepository->findById($tid, $tenantId);
                if ($tu) {
                    $tutorLabel = trim((string) ($tu['display_name'] ?? '')) ?: trim((string) ($tu['callsign'] ?? ''));
                }
            }
            $timeline = $this->timelineRepository->listForUser($tenantId, $uid, 1);
            $nextDue = null;
            foreach (['rp_next_interview_date', 'rp_medical_due_date', 'rp_service_rotation_date'] as $k) {
                $raw = trim((string) ($p[$k] ?? ''));
                if ($raw === '') {
                    continue;
                }
                if ($nextDue === null || $raw < $nextDue) {
                    $nextDue = $raw;
                }
            }
            $rpProgress = $p['rp_followup_progress'] ?? null;
            $rows[] = [
                'user_id' => $uid,
                'display_name' => trim((string) ($u['display_name'] ?? '')),
                'callsign' => trim((string) ($u['callsign'] ?? '')),
                'stage' => trim((string) ($p['rp_followup_stage'] ?? '')),
                'status' => trim((string) ($p['rp_followup_status'] ?? '')),
                'progress' => $rpProgress !== null ? (int) $rpProgress : null,
                'track' => trim((string) ($p['rp_recruitment_stream'] ?? '')),
                'function' => trim((string) ($p['rp_operational_function'] ?? '')),
                'origin' => trim((string) ($p['rp_recruitment_origin'] ?? '')),
                'notes' => trim((string) ($p['rp_followup_notes'] ?? '')),
                'tutor_id' => $tid,
                'tutor_label' => $tutorLabel,
                'next_interview_date' => trim((string) ($p['rp_next_interview_date'] ?? '')) ?: null,
                'medical_due_date' => trim((string) ($p['rp_medical_due_date'] ?? '')) ?: null,
                'service_rotation_date' => trim((string) ($p['rp_service_rotation_date'] ?? '')) ?: null,
                'next_due' => $nextDue,
                'next_due_is_overdue' => $nextDue !== null && $nextDue < $today,
                'eligible' => !empty($snapshot['eligible']),
                'latest_timeline' => $timeline[0] ?? null,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $ad = (string) ($a['next_due'] ?? '9999-12-31');
            $bd = (string) ($b['next_due'] ?? '9999-12-31');
            if ($ad !== $bd) {
                return strcmp($ad, $bd);
            }

            return strcmp((string) ($a['display_name'] ?? ''), (string) ($b['display_name'] ?? ''));
        });

        $tutorChoices = [];
        foreach ($this->userRepository->allForTenant($tenantId) as $u) {
            if ((string) ($u['status'] ?? 'active') !== 'active') {
                continue;
            }
            $id = (int) ($u['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $label = trim((string) ($u['display_name'] ?? '')) ?: (trim((string) ($u['callsign'] ?? '')) ?: trim((string) ($u['email'] ?? '')));
            if ($label === '') {
                $label = 'Compte #' . $id;
            }
            $tutorChoices[] = ['id' => $id, 'label' => $label];
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.roleplay_followup',
            'title' => 'Back-office roleplay — suivi',
            'rpFeatureEnabled' => $enabled,
            'rpConfig' => $cfg,
            'rpRows' => $rows,
            'rpTrackedCount' => $countTracked,
            'rpEligibleCount' => $countEligible,
            'rpTotalActiveMembers' => count($rows),
            'rpTimelineTableReady' => $this->timelineRepository->tableExists(),
            'rpStagesOptions' => $cfg['stages'],
            'rpTracksOptions' => $cfg['recruitment_tracks'],
            'rpTutorChoices' => $tutorChoices,
            'rpCsrfToken' => Csrf::token(),
        ]);
    }

    public function updateStage(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $uid = (int) ($params['id'] ?? 0);
        if ($tenantId < 1 || $uid < 1) {
            return Response::redirect(url('back-office/roleplay-followup'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/roleplay-followup'));
        }
        $target = $this->userRepository->findById($uid, $tenantId);
        if (!$target) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(url('back-office/roleplay-followup'));
        }
        $cfg = $this->roleplayFollowupConfig($tenantId);
        $stage = trim((string) $request->input('rp_followup_stage', ''));
        if ($stage !== '' && !in_array($stage, $cfg['stages'], true)) {
            Session::flash('error', 'Étape inconnue.');

            return Response::redirect(url('back-office/roleplay-followup'));
        }
        $existing = $this->personnelProfileRepository->getByUserId($uid) ?? [];
        $oldStage = trim((string) ($existing['rp_followup_stage'] ?? ''));
        $this->personnelProfileRepository->update($uid, ['rp_followup_stage' => $stage !== '' ? $stage : null]);
        if ($stage !== '' && $stage !== $oldStage) {
            $actorId = (int) Session::get('user_id');
            $this->timelineRepository->addEvent(
                $tenantId,
                $uid,
                'stage',
                'Changement d’étape RP',
                'Nouvelle étape : ' . $stage,
                date('Y-m-d'),
                null,
                'completed',
                null,
                $actorId > 0 ? $actorId : null
            );
        }
        Session::flash('success', 'Étape mise à jour.');

        return Response::redirect(url('back-office/roleplay-followup'));
    }

    public function updateTutor(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $uid = (int) ($params['id'] ?? 0);
        if ($tenantId < 1 || $uid < 1) {
            return Response::redirect(url('back-office/roleplay-followup'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/roleplay-followup'));
        }
        $target = $this->userRepository->findById($uid, $tenantId);
        if (!$target) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(url('back-office/roleplay-followup'));
        }
        $tutorRaw = $request->input('rp_tutor_user_id', '');
        $tutorId = ($tutorRaw === null || $tutorRaw === '') ? null : (int) $tutorRaw;
        if ($tutorId !== null && $tutorId > 0) {
            $tu = $this->userRepository->findById($tutorId, $tenantId);
            if (!$tu) {
                Session::flash('error', 'Tuteur introuvable.');

                return Response::redirect(url('back-office/roleplay-followup'));
            }
        } else {
            $tutorId = null;
        }
        $existing = $this->personnelProfileRepository->getByUserId($uid) ?? [];
        $oldTutorId = (int) ($existing['rp_tutor_user_id'] ?? 0);
        $this->personnelProfileRepository->update($uid, ['rp_tutor_user_id' => $tutorId]);
        if ($tutorId !== null && $tutorId !== $oldTutorId) {
            $tu = $this->userRepository->findById($tutorId, $tenantId);
            $tuLabel = $tu ? (trim((string) ($tu['display_name'] ?? '')) ?: trim((string) ($tu['callsign'] ?? ''))) : ('#' . $tutorId);
            $actorId = (int) Session::get('user_id');
            $this->timelineRepository->addEvent(
                $tenantId,
                $uid,
                'tutorat',
                'Affectation tuteur',
                'Tuteur assigné : ' . $tuLabel,
                date('Y-m-d'),
                null,
                'completed',
                null,
                $actorId > 0 ? $actorId : null
            );
        }
        Session::flash('success', 'Tuteur mis à jour.');

        return Response::redirect(url('back-office/roleplay-followup'));
    }

    public function validateStage(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $uid = (int) ($params['id'] ?? 0);
        if ($tenantId < 1 || $uid < 1) {
            return Response::redirect(url('back-office/roleplay-followup'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/roleplay-followup'));
        }
        $target = $this->userRepository->findById($uid, $tenantId);
        if (!$target) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(url('back-office/roleplay-followup'));
        }
        $existing = $this->personnelProfileRepository->getByUserId($uid) ?? [];
        $stage = trim((string) ($existing['rp_followup_stage'] ?? ''));
        $this->personnelProfileRepository->update($uid, ['rp_followup_progress' => 100]);
        $actorId = (int) Session::get('user_id');
        $this->timelineRepository->addEvent(
            $tenantId,
            $uid,
            'stage',
            'Étape validée',
            $stage !== '' ? 'Étape validée par l’encadrement : ' . $stage : 'Étape validée par l’encadrement.',
            date('Y-m-d'),
            null,
            'completed',
            null,
            $actorId > 0 ? $actorId : null
        );
        Session::flash('success', 'Étape validée.');

        return Response::redirect(url('back-office/roleplay-followup'));
    }

    /** @return array{enabled: bool, optional: bool, stages: list<string>, recruitment_tracks: list<string>, eligibility: array<string,mixed>} */
    private function roleplayFollowupConfig(int $tenantId): array
    {
        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $cfg = is_array($community['roleplay_followup'] ?? null) ? $community['roleplay_followup'] : [];
        $stages = [];
        foreach (($cfg['stages'] ?? []) as $s) {
            $v = trim((string) $s);
            if ($v !== '') {
                $stages[] = $v;
            }
        }
        if ($stages === []) {
            $stages = ['Pré-qualification', 'Tutorat', 'Validation', 'Intégration active'];
        }
        $tracks = [];
        foreach (($cfg['recruitment_tracks'] ?? []) as $s) {
            $v = trim((string) $s);
            if ($v !== '') {
                $tracks[] = $v;
            }
        }

        return [
            'enabled' => !empty($cfg['enabled']),
            'optional' => !empty($cfg['optional']),
            'stages' => array_values(array_unique($stages)),
            'recruitment_tracks' => array_values(array_unique($tracks)),
            'eligibility' => is_array($cfg['eligibility'] ?? null) ? $cfg['eligibility'] : [],
        ];
    }
}
