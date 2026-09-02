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
use App\Services\Personnel\RoleplayFollowupNotificationService;
use App\Support\RoleplayDeadlinePolicy;

final class RoleplayFollowupAdminController
{
    /** @var array<string, array{field: string, type: string, label: string, plan_title: string, done_title: string}> */
    private const DEADLINE_KINDS = [
        'entretien' => [
            'field' => 'rp_next_interview_date',
            'type' => 'entretien',
            'label' => 'Entretien',
            'plan_title' => 'Planification entretien individuel',
            'done_title' => 'Entretien individuel réalisé',
        ],
        'medical' => [
            'field' => 'rp_medical_due_date',
            'type' => 'medical',
            'label' => 'Médical',
            'plan_title' => 'Planification visite médicale',
            'done_title' => 'Visite médicale réalisée',
        ],
        'rotation' => [
            'field' => 'rp_service_rotation_date',
            'type' => 'rotation',
            'label' => 'Rotation',
            'plan_title' => 'Planification rotation',
            'done_title' => 'Rotation réalisée',
        ],
    ];

    public function __construct(
        private UserRepository $userRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelRoleplayTimelineRepository $timelineRepository,
        private TenantRepository $tenantRepository,
        private RoleplayFollowupNotificationService $roleplayFollowupNotificationService,
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
            $rpLastReviewAt = trim((string) ($p['rp_last_review_at'] ?? '')) ?: null;
            $rpJoinedAt = trim((string) ($u['created_at'] ?? '')) ?: null;
            $rpBilanNextDueAt = \App\Support\RoleplayBilanPolicy::nextReviewDueAt($rpJoinedAt, $rpLastReviewAt);
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
                'rp_last_review_at' => $rpLastReviewAt,
                'rp_bilan_next_due_at' => $rpBilanNextDueAt?->format('Y-m-d'),
                'rp_bilan_overdue' => \App\Support\RoleplayBilanPolicy::isOverdue($rpJoinedAt, $rpLastReviewAt),
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

    public function deadlines(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }

        $cfg = $this->roleplayFollowupConfig($tenantId);
        $today = date('Y-m-d');
        $rows = [];
        $overdue = ['entretien' => 0, 'medical' => 0, 'rotation' => 0];
        $planned = ['entretien' => 0, 'medical' => 0, 'rotation' => 0];

        foreach ($this->userRepository->allForTenant($tenantId) as $u) {
            if ((string) ($u['status'] ?? '') !== 'active') {
                continue;
            }
            $uid = (int) ($u['id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $p = $this->personnelProfileRepository->getByUserId($uid) ?? [];
            $interview = trim((string) ($p['rp_next_interview_date'] ?? '')) ?: null;
            $medical = trim((string) ($p['rp_medical_due_date'] ?? '')) ?: null;
            $rotation = trim((string) ($p['rp_service_rotation_date'] ?? '')) ?: null;

            foreach ([
                'entretien' => $interview,
                'medical' => $medical,
                'rotation' => $rotation,
            ] as $kind => $date) {
                if ($date === null) {
                    continue;
                }
                $planned[$kind]++;
                if ($date < $today) {
                    $overdue[$kind]++;
                }
            }

            $nextDue = null;
            foreach ([$interview, $medical, $rotation] as $d) {
                if ($d === null) {
                    continue;
                }
                if ($nextDue === null || $d < $nextDue) {
                    $nextDue = $d;
                }
            }

            $tutorLabel = null;
            $tid = (int) ($p['rp_tutor_user_id'] ?? 0);
            if ($tid > 0) {
                $tu = $this->userRepository->findById($tid, $tenantId);
                if ($tu) {
                    $tutorLabel = trim((string) ($tu['display_name'] ?? '')) ?: trim((string) ($tu['callsign'] ?? ''));
                }
            }

            $bloodDossier = trim((string) ($p['blood_type'] ?? ''));
            $bloodConfirmed = trim((string) ($p['rp_blood_type_confirmed'] ?? ''));
            $bloodArma = trim((string) ($p['rp_arma_blood_type'] ?? ''));
            $interviewDoneAt = trim((string) ($p['rp_last_interview_completed_at'] ?? '')) ?: null;
            $medicalDoneAt = trim((string) ($p['rp_last_medical_completed_at'] ?? $p['rp_blood_type_confirmed_at'] ?? '')) ?: null;
            $rotationDoneAt = trim((string) ($p['rp_last_rotation_completed_at'] ?? '')) ?: null;
            $rotationKind = RoleplayDeadlinePolicy::normalizeRotationKind((string) ($p['rp_rotation_kind'] ?? 'service'));

            $rows[] = [
                'user_id' => $uid,
                'display_name' => trim((string) ($u['display_name'] ?? '')),
                'callsign' => trim((string) ($u['callsign'] ?? '')),
                'stage' => trim((string) ($p['rp_followup_stage'] ?? '')),
                'tutor_label' => $tutorLabel,
                'next_interview_date' => $interview,
                'medical_due_date' => $medical,
                'service_rotation_date' => $rotation,
                'next_due' => $nextDue,
                'next_due_is_overdue' => $nextDue !== null && $nextDue < $today,
                'notes' => trim((string) ($p['rp_followup_notes'] ?? '')),
                'blood_type' => $bloodDossier,
                'blood_type_confirmed' => $bloodConfirmed,
                'blood_type_confirmed_at' => trim((string) ($p['rp_blood_type_confirmed_at'] ?? '')) ?: null,
                'arma_blood_type' => $bloodArma,
                'blood_type_mismatch' => RoleplayDeadlinePolicy::bloodTypeMismatch($bloodDossier, $bloodArma),
                'blood_needs_confirmation' => RoleplayDeadlinePolicy::bloodTypeNeedsConfirmation($bloodDossier, $bloodConfirmed, $bloodArma),
                'suggested_blood_type' => RoleplayDeadlinePolicy::suggestedBloodType($bloodDossier, $bloodConfirmed, $bloodArma),
                'last_interview_completed_at' => $interviewDoneAt,
                'last_medical_completed_at' => $medicalDoneAt,
                'last_rotation_completed_at' => $rotationDoneAt,
                'rotation_kind' => $rotationKind,
                'rotation_kind_label' => RoleplayDeadlinePolicy::rotationKindLabel($rotationKind),
                'rotation_interview_ready' => RoleplayDeadlinePolicy::canProceedWithRotation($interviewDoneAt, $rotationDoneAt),
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

        return Response::view('layout.main', [
            'content' => 'admin.organization.roleplay_deadlines',
            'title' => 'Échéances roleplay',
            'rpFeatureEnabled' => !empty($cfg['enabled']),
            'rpRows' => $rows,
            'rpTotalActiveMembers' => count($rows),
            'rpOverdueCounts' => $overdue,
            'rpPlannedCounts' => $planned,
            'rpTimelineTableReady' => $this->timelineRepository->tableExists(),
            'rpCsrfToken' => Csrf::token(),
            'rpDeadlineKinds' => self::DEADLINE_KINDS,
            'rpBloodTypes' => RoleplayDeadlinePolicy::BLOOD_TYPES,
            'rpRotationKinds' => RoleplayDeadlinePolicy::ROTATION_KINDS,
        ]);
    }

    public function updateDeadline(Request $request, array $params = []): Response
    {
        $redirectTo = url('back-office/roleplay-followup/echeances');
        $tenantId = (int) Session::get('tenant_id');
        $uid = (int) ($params['id'] ?? 0);
        if ($tenantId < 1 || $uid < 1) {
            return Response::redirect($redirectTo);
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($redirectTo);
        }
        $target = $this->userRepository->findById($uid, $tenantId);
        if (!$target) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect($redirectTo);
        }

        $kind = strtolower(trim((string) $request->input('deadline_kind', '')));
        if (!isset(self::DEADLINE_KINDS[$kind])) {
            Session::flash('error', 'Type d’échéance inconnu.');

            return Response::redirect($redirectTo);
        }
        $meta = self::DEADLINE_KINDS[$kind];
        $field = $meta['field'];
        $action = strtolower(trim((string) $request->input('deadline_action', 'save')));
        if (!in_array($action, ['save', 'complete', 'clear'], true)) {
            Session::flash('error', 'Action non reconnue.');

            return Response::redirect($redirectTo);
        }

        $existing = $this->personnelProfileRepository->getByUserId($uid) ?? [];
        $oldDate = trim((string) ($existing[$field] ?? ''));
        $note = trim((string) $request->input('deadline_note', ''));
        $rotationKind = RoleplayDeadlinePolicy::normalizeRotationKind((string) $request->input('rotation_kind', (string) ($existing['rp_rotation_kind'] ?? 'service')));
        $bloodType = RoleplayDeadlinePolicy::normalizeBloodType((string) $request->input('blood_type', ''));
        if ($kind === 'rotation' && in_array($action, ['save', 'complete'], true)) {
            $interviewDoneAt = trim((string) ($existing['rp_last_interview_completed_at'] ?? '')) ?: null;
            $rotationDoneAt = trim((string) ($existing['rp_last_rotation_completed_at'] ?? '')) ?: null;
            if (!RoleplayDeadlinePolicy::canProceedWithRotation($interviewDoneAt, $rotationDoneAt)) {
                Session::flash('error', 'Un entretien individuel doit d’abord être réalisé avant de planifier ou valider une rotation.');

                return Response::redirect($redirectTo);
            }
        }
        if ($kind === 'medical' && $action === 'complete' && $bloodType === '') {
            Session::flash('error', 'Indiquez le groupe sanguin constaté au bilan, pour que les médecins l’aient au dossier.');

            return Response::redirect($redirectTo);
        }
        if (function_exists('mb_strlen') && mb_strlen($note) > 500) {
            $note = mb_substr($note, 0, 500);
        } elseif (strlen($note) > 500) {
            $note = substr($note, 0, 500);
        }

        $newDate = null;
        if ($action === 'save') {
            $rawDate = trim((string) $request->input('deadline_date', ''));
            $newDate = $this->normalizeDeadlineDate($rawDate);
            if ($rawDate === '') {
                Session::flash('error', 'Indiquez une date, ou utilisez « Effacer » pour retirer l’échéance.');

                return Response::redirect($redirectTo);
            }
            if ($newDate === null) {
                Session::flash('error', 'La date indiquée n’est pas valide.');

                return Response::redirect($redirectTo);
            }
        } elseif ($action === 'complete') {
            $newDate = null;
        } else {
            $newDate = null;
        }

        $extra = [$field => $newDate];
        $description = $note !== '' ? $note : null;
        if ($kind === 'rotation' && in_array($action, ['save', 'complete'], true)) {
            $extra['rp_rotation_kind'] = $rotationKind;
            $kindLabel = RoleplayDeadlinePolicy::rotationKindLabel($rotationKind);
            $kindLine = 'Objet : ' . $kindLabel . '.';
            $description = $description !== null ? $kindLine . ' ' . $description : $kindLine;
        }
        if ($kind === 'entretien' && $action === 'complete') {
            $extra['rp_last_interview_completed_at'] = date('Y-m-d H:i:s');
        }
        if ($kind === 'rotation' && $action === 'complete') {
            $extra['rp_last_rotation_completed_at'] = date('Y-m-d H:i:s');
        }
        if ($kind === 'medical' && $action === 'complete') {
            $previousBlood = RoleplayDeadlinePolicy::normalizeBloodType((string) ($existing['rp_blood_type_confirmed'] ?? $existing['blood_type'] ?? ''));
            $armaBlood = RoleplayDeadlinePolicy::normalizeBloodType((string) ($existing['rp_arma_blood_type'] ?? ''));
            $extra['blood_type'] = $bloodType;
            $extra['rp_blood_type_confirmed'] = $bloodType;
            $extra['rp_blood_type_confirmed_at'] = date('Y-m-d H:i:s');
            $extra['rp_last_medical_completed_at'] = date('Y-m-d H:i:s');
            $bloodLine = 'Groupe sanguin constaté : ' . $bloodType . '.';
            if (RoleplayDeadlinePolicy::bloodTypeChanged($previousBlood, $bloodType)) {
                $bloodLine = 'Groupe sanguin mis à jour : '
                    . ($previousBlood !== '' ? $previousBlood : 'non renseigné')
                    . ' → ' . $bloodType . '.';
            } elseif (RoleplayDeadlinePolicy::bloodTypeChanged($armaBlood, $bloodType) && $armaBlood !== '') {
                $bloodLine = 'Groupe sanguin confirmé : ' . $bloodType
                    . ' (Arma indiquait ' . $armaBlood . ').';
            }
            $description = $description !== null ? $bloodLine . ' ' . $description : $bloodLine;
        }
        $this->personnelProfileRepository->update($uid, $extra);

        $actorId = (int) Session::get('user_id');
        $actorOrNull = $actorId > 0 ? $actorId : null;

        if ($action === 'complete') {
            $this->timelineRepository->addEvent(
                $tenantId,
                $uid,
                $meta['type'],
                $meta['done_title'],
                $description ?? ($oldDate !== '' ? 'Échéance prévue le ' . $this->formatFrDate($oldDate) . '.' : null),
                date('Y-m-d'),
                $oldDate !== '' ? $oldDate : date('Y-m-d'),
                'completed',
                null,
                $actorOrNull
            );
            Session::flash('success', $meta['label'] . ' marqué comme réalisé.');
        } elseif ($action === 'clear') {
            if ($oldDate !== '') {
                $this->timelineRepository->addEvent(
                    $tenantId,
                    $uid,
                    $meta['type'],
                    'Échéance ' . strtolower($meta['label']) . ' annulée',
                    $description,
                    date('Y-m-d'),
                    $oldDate,
                    'cancelled',
                    null,
                    $actorOrNull
                );
            }
            Session::flash('success', 'Échéance ' . strtolower($meta['label']) . ' effacée.');
        } else {
            $newStr = $newDate ?? '';
            if ($newStr !== '' && $newStr !== $oldDate) {
                $this->timelineRepository->addEvent(
                    $tenantId,
                    $uid,
                    $meta['type'],
                    $meta['plan_title'],
                    $description,
                    date('Y-m-d'),
                    $newStr,
                    'planned',
                    null,
                    $actorOrNull
                );
            }
            Session::flash('success', 'Échéance ' . strtolower($meta['label']) . ' enregistrée.');
        }

        $after = array_merge($existing, $extra);
        $this->roleplayFollowupNotificationService->notifyAfterSave(
            $tenantId,
            $uid,
            $actorId > 0 ? $actorId : 0,
            $existing,
            $after,
            $target,
            url('back-office/roleplay-followup/echeances'),
            false
        );

        return Response::redirect($redirectTo);
    }

    private function normalizeDeadlineDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);

        return ($dt instanceof \DateTimeImmutable && $dt->format('Y-m-d') === $raw) ? $raw : null;
    }

    private function formatFrDate(string $ymd): string
    {
        $ts = strtotime($ymd);

        return $ts ? date('d/m/Y', $ts) : $ymd;
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

    public function markBilanReviewed(Request $request, array $params = []): Response
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
        $this->personnelProfileRepository->update($uid, ['rp_last_review_at' => date('Y-m-d H:i:s')]);
        $actorId = (int) Session::get('user_id');
        $this->timelineRepository->addEvent(
            $tenantId,
            $uid,
            'bilan',
            'Bilan roleplay effectué',
            'Bilan marqué comme fait par l’encadrement.',
            date('Y-m-d'),
            null,
            'completed',
            null,
            $actorId > 0 ? $actorId : null
        );
        Session::flash('success', 'Bilan roleplay marqué comme fait. Prochaine échéance recalculée.');

        return Response::redirect(url('back-office/roleplay-followup'));
    }

    public function immersionSettings(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }

        $cfg = $this->roleplayFollowupConfig($tenantId);
        $raw = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($raw['community'] ?? null) ? $raw['community'] : [];
        $stored = is_array($community['roleplay_followup'] ?? null) ? $community['roleplay_followup'] : [];

        return Response::view('layout.main', [
            'title' => 'Réglages d’immersion',
            'content' => 'admin.organization.roleplay_immersion_settings',
            'rpConfig' => $cfg,
            'rpEligibility' => is_array($stored['eligibility'] ?? null) ? $stored['eligibility'] : ($cfg['eligibility'] ?? []),
            'immersionFormAction' => url('back-office/roleplay/immersion'),
        ]);
    }

    public function immersionSettingsUpdate(Request $request, array $params = []): Response
    {
        $redirectTo = url('back-office/roleplay/immersion');
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($redirectTo);
        }

        $parseLines = static function (string $raw): array {
            $out = [];
            foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
                $v = trim((string) $line);
                if ($v !== '') {
                    $out[] = $v;
                }
            }

            return array_values(array_unique($out));
        };

        $this->tenantRepository->updateSettings($tenantId, [
            'community' => [
                'roleplay_followup' => [
                    'enabled' => $request->input('rp_followup_enabled') ? 1 : 0,
                    'optional' => $request->input('rp_followup_optional') ? 1 : 0,
                    'stages' => $parseLines((string) $request->input('rp_followup_stages')),
                    'recruitment_tracks' => $parseLines((string) $request->input('rp_followup_tracks')),
                    'eligibility' => [
                        'min_completeness' => max(0, min(100, (int) $request->input('rp_eligibility_min_completeness', 50))),
                        'min_readiness' => max(0, min(100, (int) $request->input('rp_eligibility_min_readiness', 30))),
                        'require_unit' => $request->input('rp_eligibility_require_unit') ? 1 : 0,
                        'require_callsign' => $request->input('rp_eligibility_require_callsign') ? 1 : 0,
                        'require_tutor' => $request->input('rp_eligibility_require_tutor') ? 1 : 0,
                    ],
                ],
            ],
        ]);
        Session::flash('success', 'Réglages du suivi d’immersion enregistrés.');

        return Response::redirect($redirectTo);
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
