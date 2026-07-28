<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Repositories\EnlistmentRepository;
use App\Repositories\EnlistmentTimelineRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\RecruitmentDiscordQuestionRepository;
use App\Repositories\RecruitmentInviteCodeRepository;
use App\Repositories\RecruitmentOpeningRepository;
use App\Repositories\RecruitmentPresetRepository;
use App\Repositories\TenantBrandingRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Community\EnlistmentMilsimPackService;
use App\Services\Community\TenantCommunityProfileService;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Services\Moderation\IndicatorBlocklistService;
use App\Services\Profile\RecruitmentPresetPayloadService;
use App\Services\Analytics\AnalyticsEventCategory;
use App\Services\Analytics\AnalyticsEventName;
use App\Services\Analytics\AnalyticsEventService;
use App\Services\Analytics\AnalyticsSubjectType;
use App\Services\Recruitment\RecruitmentOpeningPresentation;

class EnlistmentController
{
    public function __construct(
        private EnlistmentRepository $enlistmentRepository,
        private EnlistmentTimelineRepository $enlistmentTimelineRepository,
        private TenantRepository $tenantRepository,
        private AuthService $authService,
        private UserRepository $userRepository,
        private UserProfileRepository $userProfileRepository,
        private RecruitmentPresetRepository $recruitmentPresetRepository,
        private RecruitmentPresetPayloadService $recruitmentPresetPayloadService,
        private EmailService $emailService,
        private IndicatorBlocklistService $indicatorBlocklist,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository,
        private RecruitmentOpeningRepository $recruitmentOpeningRepository,
        private AnalyticsEventService $analyticsEventService,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelAssignmentRepository $personnelAssignmentRepository,
        private PersonnelJobRoleRepository $personnelJobRoleRepository,
        private UnitRepository $unitRepository,
        private TenantBrandingRepository $tenantBrandingRepository,
        private ?RecruitmentDiscordQuestionRepository $recruitmentDiscordQuestionRepository = null,
        private ?RecruitmentInviteCodeRepository $inviteCodeRepository = null,
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        $routeSlug = trim((string) ($params['slug'] ?? ''));
        $tenant = $this->resolveTenantForDisplay($request, $params);
        if (!$tenant) {
            if ($routeSlug !== '') {
                return Response::view('enlistment.error', [
                    'message' => 'Organisation introuvable.',
                    'enlistmentRetryUrl' => url('enlistment'),
                ]);
            }

            return Response::view('enlistment.no_community', [
                'loginUrl' => url('login'),
                'joinUrl' => url('join'),
            ]);
        }

        $communityConfig = $this->communityConfig($tenant);
        if (!empty($communityConfig['community_locked'])) {
            return Response::view('enlistment.error', ['message' => 'Le recrutement est verrouillé pour cette communauté.', 'enlistmentRetryUrl' => $this->enlistmentFormUrl($tenant)]);
        }

        $mode = TenantCommunityProfileService::normalizeRegistrationMode($communityConfig['registration_mode'] ?? TenantCommunityProfileService::REGISTRATION_MODE_MILSIM);
        $tenantName = trim((string) ($tenant['name'] ?? 'Communauté'));
        $formAction = $this->enlistmentActionUrl($tenant);
        $targetTenantId = (int) $tenant['id'];
        $enlistmentContext = $this->buildEnlistmentContext($request, $tenant, $targetTenantId);

        $selectedRecruitmentOpening = null;
        $ouvertureId = (int) $request->query('ouverture', 0);
        if ($ouvertureId > 0 && $this->recruitmentOpeningRepository->tablesExist()) {
            $ro = $this->recruitmentOpeningRepository->findByIdForTenant($ouvertureId, $targetTenantId);
            if ($ro && (string) ($ro['status'] ?? '') === 'published') {
                $selectedRecruitmentOpening = [
                    'id' => (int) $ro['id'],
                    'title' => trim((string) ($ro['title'] ?? '')),
                ];
            }
        }

        $openingProps = null;
        if ($selectedRecruitmentOpening !== null && !empty($selectedRecruitmentOpening['id'])) {
            $openingProps = ['opening_id' => (int) $selectedRecruitmentOpening['id']];
        }
        $this->analyticsEventService->record(
            $targetTenantId,
            $this->authService->check() && Session::get('user_id') ? (int) Session::get('user_id') : null,
            AnalyticsEventCategory::RECRUITMENT,
            AnalyticsEventName::ENLISTMENT_FORM_OPEN,
            AnalyticsSubjectType::TENANT,
            $targetTenantId,
            null,
            $openingProps
        );

        $beacon = [
            'tenantId' => $targetTenantId,
            'category' => AnalyticsEventCategory::TENANT_PUBLIC,
            'durationEvent' => AnalyticsEventName::TENANT_PUBLIC_PAGE_DURATION,
            'subjectType' => AnalyticsSubjectType::TENANT,
            'subjectId' => $targetTenantId,
        ];

        $enlistmentMemberOpeningInsight = $this->resolveEnlistmentMemberOpeningInsight(
            $targetTenantId,
            $enlistmentContext,
            $selectedRecruitmentOpening
        );

        $tenantBranding = $this->tenantBrandingRepository->mergeWithTenantLogo(
            $tenant,
            $this->tenantBrandingRepository->findByTenantId($targetTenantId)
        );
        $publishedOpenings = $this->recruitmentOpeningRepository->listPublishedForTenant($targetTenantId);

        $viewData = [
            'tenant' => $tenant,
            'communityConfig' => $communityConfig,
            'formAction' => $formAction,
            'enlistmentContext' => $enlistmentContext,
            'selectedRecruitmentOpening' => $selectedRecruitmentOpening,
            'enlistmentMemberOpeningInsight' => $enlistmentMemberOpeningInsight,
            'tenantBranding' => $tenantBranding,
            'publishedOpenings' => $publishedOpenings,
            'analyticsBeacon' => $beacon,
        ];

        if ($mode === 'simple') {
            return Response::view('layout.main', array_merge($viewData, [
                'content' => 'enlistment.simple',
                'title' => 'Inscription — ' . $tenantName,
                'simpleEnlistmentUrl' => $this->enlistmentFormUrl($tenant),
                'showMilsimUnavailableNotice' => true,
            ]));
        }

        if ($mode === 'discord') {
            return Response::view('layout.main', array_merge($viewData, [
                'content' => 'enlistment.discord',
                'title' => 'Rejoindre sur Discord — ' . $tenantName,
                'discordInviteUrl' => trim((string) ($communityConfig['contact_discord_url'] ?? '')),
                'discordQuestions' => $this->recruitmentDiscordQuestionRepository()->listForTenant($targetTenantId, true),
            ]));
        }

        return Response::view('enlistment', array_merge($viewData, [
            'milsimPack' => EnlistmentMilsimPackService::forCommunity($communityConfig),
        ]));
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!$request->isPost()) {
            return Response::redirect(url('enlistment'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('enlistment_error', 'Session expirée. Veuillez recharger la page et soumettre à nouveau le formulaire.');
            $this->flashEnlistmentRetryUrl($this->resolveTenantForRequest($request, $params));

            return Response::redirect(url('enlistment/error'));
        }

        $tenant = $this->resolveTenantForRequest($request, $params);
        if (!$tenant) {
            Session::flash('enlistment_error', 'Organisation non configurée. Merci de réessayer plus tard.');
            Session::flash('enlistment_retry_url', url('enlistment'));

            return Response::redirect(url('enlistment/error'));
        }
        $this->flashEnlistmentRetryUrl($tenant);

        $communityConfig = $this->communityConfig($tenant);
        if (!empty($communityConfig['community_locked'])) {
            Session::flash('enlistment_error', 'Le recrutement est verrouillé pour cette communauté.');

            return Response::redirect(url('enlistment/error'));
        }

        if ((string) ($communityConfig['registration_mode'] ?? 'milsim') === 'discord') {
            return $this->storeDiscordEnlistment($request, $tenant);
        }

        $requireAiAck = array_key_exists('require_ai_ack', $communityConfig) ? (bool) $communityConfig['require_ai_ack'] : true;
        if ($requireAiAck && !$request->input('no_ai_confirmed')) {
            Session::flash('enlistment_error', 'Vous devez confirmer l\'absence d\'IA dans ce rapport (case à cocher obligatoire).');

            return Response::redirect(url('enlistment/error'));
        }

        $targetTenantId = (int) $tenant['id'];
        $flow = trim((string) $request->input('enlistment_flow', 'guest'));

        $formModeRaw = trim((string) $request->input('enlistment_form_mode', 'full'));
        $formMode = $formModeRaw === 'compact' ? 'compact' : 'full';
        $openingIdPost = (int) $request->input('enlistment_opening_id', 0);
        $openingValidForCompact = false;
        if ($openingIdPost > 0 && $this->recruitmentOpeningRepository->tablesExist()) {
            $roCompact = $this->recruitmentOpeningRepository->findByIdForTenant($openingIdPost, $targetTenantId);
            if ($roCompact && (string) ($roCompact['status'] ?? '') === 'published') {
                $openingValidForCompact = true;
            }
        }
        $isCompactAccount = $flow === 'account' && $formMode === 'compact' && $openingValidForCompact;

        if ($flow !== 'account') {
            $guestEmail = '';
            if ($request->input('use_platform_email') && $this->authService->check()) {
                $u = $this->authService->user();
                $guestEmail = trim((string) ($u['email'] ?? ''));
            }
            if ($guestEmail === '') {
                $guestEmail = trim((string) $request->input('email'));
            }
            if ($guestEmail === '' || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
                Session::flash('enlistment_error', 'Merci d’indiquer une adresse email valide (ou cochez l’utilisation de l’e-mail du compte si vous êtes connecté).');

                return Response::redirect(url('enlistment/error'));
            }
        }

        $payload = [
            'country' => trim((string) $request->input('country')) ?: null,
            'experience' => trim((string) $request->input('experience')) ?: null,
            'specialty' => trim((string) $request->input('specialty')) ?: null,
            'platform' => trim((string) $request->input('platform')) ?: null,
            'availability' => trim((string) $request->input('availability')) ?: null,
            'notes' => trim((string) $request->input('notes')) ?: null,
            'age' => $request->input('age'),
            'timezone' => trim((string) $request->input('timezone')) ?: null,
            'weekly_availability' => trim((string) $request->input('weekly_availability')) ?: null,
            'system_config' => trim((string) $request->input('system_config')) ?: null,
            'microphone_quality' => trim((string) $request->input('microphone_quality')) ?: null,
            'past_milsim_experience' => trim((string) $request->input('past_milsim_experience')) ?: null,
            'ace_acre_level' => trim((string) $request->input('ace_acre_level')) ?: null,
            'motivation_why_join' => trim((string) $request->input('motivation_why_join')) ?: null,
            'motivation_accountability' => trim((string) $request->input('motivation_accountability')) ?: null,
            'commitment_effort' => trim((string) $request->input('commitment_effort')) ?: null,
            'availability_wed_sat' => trim((string) $request->input('availability_wed_sat')) ?: null,
            'no_ai_confirmed' => $requireAiAck ? $request->input('no_ai_confirmed') : 1,
            'submitted_via' => 'guest',
            'submitter_user_id' => null,
            'recruitment_preset_id' => null,
            'consent_sharing_at' => null,
            'shared_fields' => null,
        ];

        if ($flow === 'account') {
            if (!$this->authService->check()) {
                Session::flash('enlistment_error', 'Session expirée. Reconnectez-vous pour envoyer avec votre compte.');

                return Response::redirect(url('enlistment/error'));
            }
            $sessionTenant = Session::get('tenant_id');
            if ((int) $sessionTenant !== $targetTenantId) {
                Session::flash('enlistment_error', 'Contexte communautaire invalide. Basculez vers cette communauté ou utilisez le formulaire invité.');

                return Response::redirect(url('enlistment/error'));
            }
            if (!$request->input('consent_data_sharing')) {
                Session::flash('enlistment_error', 'Vous devez accepter le partage des données avec le staff de la communauté.');

                return Response::redirect(url('enlistment/error'));
            }
            if (!$request->input('share_email')) {
                Session::flash('enlistment_error', 'Une adresse email de contact est requise (partage email).');

                return Response::redirect(url('enlistment/error'));
            }

            $user = $this->authService->user();
            if (!$user || (int) ($user['tenant_id'] ?? 0) !== $targetTenantId) {
                Session::flash('enlistment_error', 'Compte non valide pour cette communauté.');

                return Response::redirect(url('enlistment/error'));
            }

            $uid = (int) $user['id'];
            $shareName = (bool) $request->input('share_name');
            $shareEmail = (bool) $request->input('share_email');
            $shareCallsign = (bool) $request->input('share_callsign');
            $profile = $this->userProfileRepository->getByUserId($uid);

            $email = $shareEmail ? trim((string) ($user['email'] ?? '')) : '';
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Session::flash('enlistment_error', 'Adresse email du compte invalide. Mettez-la à jour dans les paramètres.');

                return Response::redirect(url('enlistment/error'));
            }

            if ($shareName) {
                [$first, $last] = $this->resolveNamePartsFromAccount($user, $profile);
            } else {
                $first = '—';
                $last = '—';
            }

            $callsign = null;
            if ($shareCallsign) {
                $callsign = trim((string) ($user['callsign'] ?? '')) ?: null;
            } else {
                $callsign = trim((string) $request->input('callsign')) ?: null;
            }

            $presetId = (int) $request->input('recruitment_preset_id');
            $presetRow = $presetId > 0 ? $this->recruitmentPresetRepository->findForUser($presetId, $uid) : null;
            if ($presetId > 0 && !$presetRow) {
                Session::flash('enlistment_error', 'Profil de candidature invalide.');

                return Response::redirect(url('enlistment/error'));
            }
            $pData = [];
            if ($presetRow) {
                $rawPayload = $presetRow['payload'] ?? [];
                $pData = is_array($rawPayload) ? $rawPayload : [];
            }

            $rpSharesRaw = [];
            foreach (RecruitmentPresetPayloadService::rpShareSelectionKeys() as $rk) {
                $rpSharesRaw[$rk] = $request->input('share_rp_' . $rk);
            }
            $rpShares = $this->recruitmentPresetPayloadService->normalizeRpShareSelections($rpSharesRaw);
            $includeMilsimFromPreset = (bool) $request->input('include_milsim_from_preset');

            if ($presetRow !== null) {
                $shareOptions = [
                    'include_milsim_from_preset' => $includeMilsimFromPreset,
                    'rp_shares' => $rpShares,
                ];
                $this->recruitmentPresetPayloadService->mergePresetIntoEnlistmentPayload($pData, $payload, $shareOptions);

                $fullSnapForValidation = $this->recruitmentPresetPayloadService->buildRpSnapshotForEnlistment($pData, null);
                if (!$includeMilsimFromPreset
                    && !$this->recruitmentPresetPayloadService->snapshotHasAnyRpContent($fullSnapForValidation, $rpShares)) {
                    Session::flash(
                        'enlistment_error',
                        'Pour utiliser ce profil enregistré, indiquez au moins un élément à transmettre au recrutement, ou cochez l’inclusion des réponses techniques du modèle.'
                    );

                    return Response::redirect(url('enlistment/error'));
                }

                $snap = $this->recruitmentPresetPayloadService->buildRpSnapshotForEnlistment($pData, $rpShares);
                if ($this->recruitmentPresetPayloadService->snapshotHasVisibleRpContent($snap)) {
                    $payload['recruitment_rp_snapshot'] = $snap;
                }
            }

            if ($isCompactAccount && trim((string) ($payload['motivation_why_join'] ?? '')) === '') {
                Session::flash('enlistment_error', 'Indiquez en quelques lignes votre motivation pour ce poste.');

                return Response::redirect(url('enlistment/error'));
            }

            if ($callsign === null && $presetRow !== null) {
                $pn = $this->recruitmentPresetPayloadService->normalizeDecodedPayload($pData);
                if (trim((string) ($pn['callsign'] ?? '')) !== '') {
                    $callsign = trim((string) $pn['callsign']) ?: null;
                }
            }

            $payload['first_name'] = $first ?: '—';
            $payload['last_name'] = $last ?: '—';
            $payload['email'] = $email;
            $payload['callsign'] = $callsign;
            $payload['submitter_user_id'] = $uid;
            $payload['recruitment_preset_id'] = $presetRow ? $presetId : null;
            $payload['submitted_via'] = $presetRow ? 'preset' : 'account';
            $payload['consent_sharing_at'] = date('Y-m-d H:i:s');
            $payload['shared_fields'] = [
                'share_name' => $shareName,
                'share_email' => $shareEmail,
                'share_callsign' => $shareCallsign,
                'rp_shares' => $presetRow ? $rpShares : null,
                'include_milsim_from_preset' => $presetRow ? $includeMilsimFromPreset : null,
                'form_mode' => $isCompactAccount ? 'compact' : 'full',
            ];
        } else {
            // Identité réelle désactivée : candidature invitée = personnage uniquement.
            $fullName = trim((string) $request->input('full_name'));
            if ($fullName === '') {
                Session::flash('enlistment_error', 'Merci d’indiquer un nom pour la candidature.');

                return Response::redirect(url('enlistment/error'));
            }
            $guestFn = trim((string) $request->input('guest_rp_first_name'));
            $guestLn = trim((string) $request->input('guest_rp_last_name'));
            $guestBd = RecruitmentPresetPayloadService::normalizeRpBirthDate((string) $request->input('guest_rp_birth_date'));
            $guestNat = trim((string) $request->input('guest_rp_nationality'));
            $guestScene = trim((string) $request->input('guest_rp_scene_name'));
            if ($guestFn === '' && $guestLn === '' && $fullName !== '') {
                $guestFn = $fullName;
                $guestLn = '';
                if (str_contains($fullName, ' ')) {
                    $pos = strpos($fullName, ' ');
                    $guestFn = trim(substr($fullName, 0, $pos));
                    $guestLn = trim(substr($fullName, $pos));
                }
            }
            $pseudoPreset = [
                'payload_version' => RecruitmentPresetPayloadService::PAYLOAD_VERSION,
                'rp' => [
                    'first_name' => $guestFn,
                    'last_name' => $guestLn,
                    'birth_date' => $guestBd,
                    'nationality' => function_exists('mb_substr') ? mb_substr($guestNat, 0, 100) : substr($guestNat, 0, 100),
                    'character_name' => function_exists('mb_substr') ? mb_substr($guestScene, 0, 150) : substr($guestScene, 0, 150),
                ],
                'admin_notes' => '',
                'availability' => ['schedule' => [], 'timezone_label' => '', 'free_text' => ''],
            ];
            $snap = $this->recruitmentPresetPayloadService->buildRpSnapshotForEnlistment($pseudoPreset, null);
            $snap['identity_kind'] = 'rp';
            $snap['legal_contact_name'] = null;
            $payload['recruitment_rp_snapshot'] = $snap;
            $nameForSplit = $fullName;
            $first = $nameForSplit;
            $last = '';
            if ($nameForSplit !== '' && str_contains($nameForSplit, ' ')) {
                $pos = strpos($nameForSplit, ' ');
                $first = substr($nameForSplit, 0, $pos);
                $last = trim(substr($nameForSplit, $pos));
            }
            if ($first === '' && trim((string) $request->input('first_name')) !== '') {
                $first = trim((string) $request->input('first_name'));
                $last = trim((string) $request->input('last_name'));
            }
            $payload['first_name'] = $first ?: '—';
            $payload['last_name'] = $last ?: '—';
            $payload['email'] = trim((string) $request->input('email'));
            $payload['callsign'] = null;
        }

        $joinEmail = strtolower(trim((string) ($payload['email'] ?? '')));
        if ($joinEmail !== '' && filter_var($joinEmail, FILTER_VALIDATE_EMAIL)
            && $this->indicatorBlocklist->isEmailBlockedForTenant($targetTenantId, $joinEmail)) {
            Session::flash('enlistment_error', 'Cette adresse ne peut pas être utilisée pour une candidature dans cette communauté pour le moment.');
            Session::flash('enlistment_retry_url', $this->enlistmentFormUrl($tenant));

            return Response::redirect(url('enlistment/error'));
        }

        $openingId = (int) $request->input('enlistment_opening_id', 0);
        if ($openingId > 0 && $this->recruitmentOpeningRepository->tablesExist()) {
            $ro = $this->recruitmentOpeningRepository->findByIdForTenant($openingId, $targetTenantId);
            if ($ro && (string) ($ro['status'] ?? '') === 'published') {
                $payload['recruitment_opening_id'] = $openingId;
            }
        }

        // Gestion du code d'invitation
        $inviteCode = trim((string) $request->input('invite_code', ''));
        $inviteCodeData = null;
        if ($inviteCode !== '' && $this->inviteCodeRepository()->tablesExist()) {
            $inviteCodeData = $this->inviteCodeRepository()->findByCode($targetTenantId, $inviteCode);
            if ($inviteCodeData === null) {
                Session::flash('enlistment_error', 'Le code d\'invitation fourni n\'existe pas.');
                return Response::redirect(url('enlistment/error'));
            }

            if (!$this->inviteCodeRepository()->isCodeValid($targetTenantId, $inviteCode)) {
                Session::flash('enlistment_error', 'Le code d\'invitation fourni n\'est plus valide (expiré ou limite d\'utilisations atteinte).');
                return Response::redirect(url('enlistment/error'));
            }

            // Appliquer les paramètres du code d'invitation
            if (!empty($inviteCodeData['assign_to_opening_id'])) {
                $payload['recruitment_opening_id'] = (int) $inviteCodeData['assign_to_opening_id'];
            }
            if (!empty($inviteCodeData['default_specialty']) && empty($payload['specialty'])) {
                $payload['specialty'] = (string) $inviteCodeData['default_specialty'];
            }

            // Si le code accepte automatiquement, on change le statut
            if (!empty($inviteCodeData['auto_accept'])) {
                $payload['status'] = 'reviewed';
            }
        }

        try {
            $enlistmentId = $this->enlistmentRepository->create((int) $tenant['id'], $payload);
        } catch (\Throwable $e) {
            Session::flash('enlistment_error', 'Une erreur technique a empêché l\'enregistrement de votre candidature. Veuillez réessayer ou contacter le support.');

            return Response::redirect(url('enlistment/error'));
        }

        // Enregistrer l'utilisation du code d'invitation
        if ($inviteCodeData !== null && $enlistmentId > 0) {
            $this->inviteCodeRepository()->recordUse(
                $targetTenantId,
                (int) $inviteCodeData['id'],
                $enlistmentId,
                $inviteCode
            );

            // Logger dans la timeline
            if ($this->enlistmentTimelineRepository->tableExists()) {
                $codeLabel = trim((string) ($inviteCodeData['label'] ?? $inviteCode));
                $autoAcceptNote = !empty($inviteCodeData['auto_accept']) ? ' (validation automatique)' : '';
                $this->enlistmentTimelineRepository->append(
                    $targetTenantId,
                    $enlistmentId,
                    'system',
                    'reception',
                    'Code d\'invitation utilisé',
                    'Code : ' . $codeLabel . $autoAcceptNote,
                    null,
                    ['invite_code_id' => (int) $inviteCodeData['id']]
                );
            }
        }

        $subUid = isset($payload['submitter_user_id']) ? (int) $payload['submitter_user_id'] : 0;
        $this->enlistmentTimelineRepository->logIntakeFromSubmission(
            (int) $tenant['id'],
            $enlistmentId,
            $subUid > 0 ? $subUid : null,
            (string) ($payload['submitted_via'] ?? 'guest')
        );

        $submittedProps = null;
        if (!empty($payload['recruitment_opening_id'])) {
            $submittedProps = ['opening_id' => (int) $payload['recruitment_opening_id']];
        }
        $this->analyticsEventService->record(
            (int) $tenant['id'],
            null,
            AnalyticsEventCategory::RECRUITMENT,
            AnalyticsEventName::ENLISTMENT_SUBMITTED,
            AnalyticsSubjectType::TENANT,
            (int) $tenant['id'],
            null,
            $submittedProps
        );

        $this->notifyStaffNewEnlistment((int) $tenant['id'], $tenant, $enlistmentId, $payload);

        return Response::redirect($this->enlistmentSuccessUrl($tenant));
    }

    /**
     * Dépôt de candidature via le formulaire de recrutement Discord (mode registration_mode
     * = discord) : pseudo Discord + réponses aux questions custom du tenant, sans le dossier
     * MilSim complet.
     *
     * @param array<string, mixed> $tenant
     */
    private function storeDiscordEnlistment(Request $request, array $tenant): Response
    {
        $targetTenantId = (int) $tenant['id'];

        $discordPseudo = trim((string) $request->input('discord_pseudo'));
        $displayName = trim((string) $request->input('display_name'));
        $email = trim((string) $request->input('email'));

        if ($discordPseudo === '' || $displayName === '') {
            Session::flash('enlistment_error', 'Merci d’indiquer votre pseudo Discord et votre nom.');

            return Response::redirect(url('enlistment/error'));
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('enlistment_error', 'Merci d’indiquer une adresse email valide (pour le suivi de votre candidature).');

            return Response::redirect(url('enlistment/error'));
        }
        if ($this->indicatorBlocklist->isEmailBlockedForTenant($targetTenantId, strtolower($email))) {
            Session::flash('enlistment_error', 'Cette adresse ne peut pas être utilisée pour une candidature dans cette communauté pour le moment.');
            Session::flash('enlistment_retry_url', $this->enlistmentFormUrl($tenant));

            return Response::redirect(url('enlistment/error'));
        }

        $questions = $this->recruitmentDiscordQuestionRepository()->listForTenant($targetTenantId, true);
        $answers = [];
        foreach ($questions as $q) {
            $field = 'discord_q_' . $q['id'];
            $raw = trim((string) $request->input($field, ''));
            if ($q['required'] && $raw === '') {
                Session::flash('enlistment_error', 'Merci de répondre à toutes les questions obligatoires (« ' . $q['label'] . ' »).');

                return Response::redirect(url('enlistment/error'));
            }
            if ($raw === '') {
                continue;
            }
            $answers[] = [
                'question_id' => $q['id'],
                'label' => $q['label'],
                'type' => $q['type'],
                'answer' => mb_substr($raw, 0, 4000),
            ];
        }

        $payload = [
            'first_name' => $displayName,
            'last_name' => '—',
            'email' => $email,
            'callsign' => $discordPseudo,
            'submitted_via' => 'guest',
            'form_channel' => 'discord',
            'discord_pseudo' => $discordPseudo,
            'discord_answers' => $answers,
        ];

        // Gestion du code d'invitation
        $inviteCode = trim((string) $request->input('invite_code', ''));
        $inviteCodeData = null;
        if ($inviteCode !== '' && $this->inviteCodeRepository()->tablesExist()) {
            $inviteCodeData = $this->inviteCodeRepository()->findByCode($targetTenantId, $inviteCode);
            if ($inviteCodeData === null) {
                Session::flash('enlistment_error', 'Le code d\'invitation fourni n\'existe pas.');
                return Response::redirect(url('enlistment/error'));
            }

            if (!$this->inviteCodeRepository()->isCodeValid($targetTenantId, $inviteCode)) {
                Session::flash('enlistment_error', 'Le code d\'invitation fourni n\'est plus valide (expiré ou limite d\'utilisations atteinte).');
                return Response::redirect(url('enlistment/error'));
            }

            // Appliquer les paramètres du code d'invitation
            if (!empty($inviteCodeData['default_specialty']) && empty($payload['specialty'])) {
                $payload['specialty'] = (string) $inviteCodeData['default_specialty'];
            }

            // Si le code accepte automatiquement, on change le statut
            if (!empty($inviteCodeData['auto_accept'])) {
                $payload['status'] = 'reviewed';
            }
        }

        try {
            $enlistmentId = $this->enlistmentRepository->create($targetTenantId, $payload);
        } catch (\Throwable) {
            Session::flash('enlistment_error', 'Une erreur technique a empêché l\'enregistrement de votre candidature. Veuillez réessayer ou contacter le support.');

            return Response::redirect(url('enlistment/error'));
        }

        // Enregistrer l'utilisation du code d'invitation
        if ($inviteCodeData !== null && $enlistmentId > 0) {
            $this->inviteCodeRepository()->recordUse(
                $targetTenantId,
                (int) $inviteCodeData['id'],
                $enlistmentId,
                $inviteCode
            );

            // Logger dans la timeline
            if ($this->enlistmentTimelineRepository->tableExists()) {
                $codeLabel = trim((string) ($inviteCodeData['label'] ?? $inviteCode));
                $autoAcceptNote = !empty($inviteCodeData['auto_accept']) ? ' (validation automatique)' : '';
                $this->enlistmentTimelineRepository->append(
                    $targetTenantId,
                    $enlistmentId,
                    'system',
                    'reception',
                    'Code d\'invitation utilisé',
                    'Code : ' . $codeLabel . $autoAcceptNote,
                    null,
                    ['invite_code_id' => (int) $inviteCodeData['id']]
                );
            }
        }

        $this->enlistmentTimelineRepository->logIntakeFromSubmission($targetTenantId, $enlistmentId, null, 'guest');
        $this->analyticsEventService->record(
            $targetTenantId,
            null,
            AnalyticsEventCategory::RECRUITMENT,
            AnalyticsEventName::ENLISTMENT_SUBMITTED,
            AnalyticsSubjectType::TENANT,
            $targetTenantId,
            null,
            ['channel' => 'discord']
        );
        $this->notifyStaffNewEnlistment($targetTenantId, $tenant, $enlistmentId, $payload);

        return Response::redirect($this->enlistmentSuccessUrl($tenant));
    }

    public function success(Request $request, array $params = []): Response
    {
        $slug = trim((string) $request->query('community', ''));

        return Response::view('enlistment.success', [
            'communitySlug' => $slug !== '' ? $slug : null,
        ]);
    }

    public function error(Request $request, array $params = []): Response
    {
        $message = Session::getFlash('enlistment_error', 'Une erreur est survenue lors de la soumission.');
        $retry = Session::getFlash('enlistment_retry_url', url('enlistment'));

        return Response::view('enlistment.error', ['message' => $message, 'enlistmentRetryUrl' => $retry]);
    }

    /**
     * E-mails : rôles recruteur, fondateur (community_owner), RH ; sinon gouvernance (tenant_admin + community_owner) ;
     * sinon administrateur communauté ; en dernier recours, e-mail de contact de la fiche présentation (si renseigné).
     *
     * @param array<string, mixed> $tenant
     * @param array<string, mixed> $payload
     */
    private function notifyStaffNewEnlistment(int $tenantId, array $tenant, int $enlistmentId, array $payload): void
    {
        // Aligné sur l'accès réel au back-office recrutement (OrganizationAdminMiddleware) : les
        // anciens filtres sur des slugs de rôle fixes ('recruiter', 'hr'...) ne correspondaient à
        // aucun rôle réellement utilisé par la plupart des communautés, ce qui laissait cette
        // alerte silencieusement sans destinataire.
        $ids = $this->userRepository->listActiveUserIdsWithAnyPermissionSlug($tenantId, [
            'organization.recruitment.manage',
            'admin.organization',
            'admin.access',
        ]);
        $recipients = [];
        if ($ids !== []) {
            $users = $this->userRepository->findByIdsForTenant($tenantId, $ids);
            foreach ($ids as $uid) {
                $email = strtolower(trim((string) ($users[$uid]['email'] ?? '')));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipients[] = $email;
                }
            }
            $recipients = array_values(array_unique($recipients));
        }
        if ($recipients === []) {
            $contact = trim((string) ($this->communityConfig($tenant)['contact_email'] ?? ''));
            if ($contact !== '' && filter_var($contact, FILTER_VALIDATE_EMAIL)) {
                $recipients = [strtolower($contact)];
            }
        }
        if ($recipients === []) {
            return;
        }

        $tenantName = trim((string) ($tenant['name'] ?? 'Communauté'));
        $first = trim((string) ($payload['first_name'] ?? ''));
        $last = trim((string) ($payload['last_name'] ?? ''));
        $full = trim($first . ' ' . $last);
        if ($full === '') {
            $full = '—';
        }
        $candidateEmail = trim((string) ($payload['email'] ?? ''));
        $availability = trim((string) ($payload['availability'] ?? ''));
        $motivation = trim((string) ($payload['motivation_why_join'] ?? ''));
        $reviewUrl = url('back-office/recruitments/' . $enlistmentId . '?dossier=1');

        foreach ($recipients as $to) {
            try {
                $em = strtolower(trim($to));
                $u = $em !== '' ? $this->userRepository->findByEmail($tenantId, $em) : null;
                if ($u && !$this->notificationPreferencesRepository->isEmailEventEnabled((int) ($u['id'] ?? 0), EmailEvents::ENLISTMENT_SUBMITTED_STAFF)) {
                    continue;
                }
                $this->emailService->sendEnlistmentSubmittedStaffNotify(
                    $to,
                    $tenantName,
                    $full,
                    $candidateEmail,
                    $availability !== '' ? $availability : null,
                    $motivation !== '' ? $motivation : null,
                    $enlistmentId,
                    $reviewUrl,
                    $tenantId
                );
            } catch (\Throwable) {
                // La candidature est déjà enregistrée ; l’échec mail ne doit pas bloquer l’utilisateur.
            }
        }
    }

    private function recruitmentDiscordQuestionRepository(): RecruitmentDiscordQuestionRepository
    {
        return $this->recruitmentDiscordQuestionRepository
            ??= Container::get(RecruitmentDiscordQuestionRepository::class);
    }

    private function inviteCodeRepository(): RecruitmentInviteCodeRepository
    {
        return $this->inviteCodeRepository
            ??= Container::get(RecruitmentInviteCodeRepository::class);
    }

    /** @param array<string,mixed> $tenant */
    private function enlistmentFormUrl(array $tenant): string
    {
        $slug = trim((string) ($tenant['slug'] ?? ''));
        if ($slug !== '') {
            return url('c/' . $slug . '/enlistment');
        }

        return url('enlistment');
    }

    /** @param array<string,mixed> $tenant */
    private function enlistmentSuccessUrl(array $tenant): string
    {
        $slug = trim((string) ($tenant['slug'] ?? ''));
        if ($slug !== '') {
            return url('enlistment/success') . '?community=' . rawurlencode($slug);
        }

        return url('enlistment/success');
    }

    /** @param array<string,mixed>|null $tenant */
    private function flashEnlistmentRetryUrl(?array $tenant): void
    {
        if ($tenant) {
            Session::flash('enlistment_retry_url', $this->enlistmentFormUrl($tenant));
        } else {
            Session::flash('enlistment_retry_url', url('enlistment'));
        }
    }

    /**
     * @param array<string,mixed> $tenant
     * @return array<string,mixed>
     */
    private function buildEnlistmentContext(Request $request, array $tenant, int $targetTenantId): array
    {
        $slug = trim((string) ($tenant['slug'] ?? ''));
        $canUseAccount = false;
        $prefill = [
            'full_name' => '',
            'email' => '',
            'age' => '',
            'timezone' => '',
            'weekly_availability' => '',
        ];
        foreach ($this->sanitizePrefillFromQuery($request) as $k => $v) {
            if ($v !== '' && array_key_exists($k, $prefill)) {
                $prefill[$k] = $v;
            }
        }
        $recruitmentPresets = [];
        $switchToTargetUrl = $slug !== '' ? url('c/' . $slug . '/enlistment/enter') : null;
        $hasMembershipOnTarget = false;

        if ($this->authService->check()) {
            $sessionTenant = (int) (Session::get('tenant_id') ?? 0);
            if ($sessionTenant === $targetTenantId) {
                $canUseAccount = true;
                $user = $this->authService->user();
                if ($user) {
                    $uid = (int) $user['id'];
                    $prefill['email'] = (string) ($user['email'] ?? '');
                    $profile = $this->userProfileRepository->getByUserId($uid);
                    [$fn, $ln] = $this->resolveNamePartsFromAccount($user, $profile);
                    if ($fn !== '—' || $ln !== '—') {
                        $prefill['full_name'] = trim($fn . ' ' . $ln);
                    } else {
                        $prefill['full_name'] = trim((string) ($user['display_name'] ?? ''));
                    }
                    try {
                        $recruitmentPresets = $this->recruitmentPresetRepository->listForUser($uid);
                    } catch (\Throwable) {
                        $recruitmentPresets = [];
                    }
                }
            } else {
                $email = (string) (Session::get('email') ?? '');
                if ($email !== '') {
                    $rows = $this->userRepository->listTenantsForEmail($email);
                    foreach ($rows as $r) {
                        if ((int) ($r['tenant_id'] ?? 0) === $targetTenantId) {
                            $hasMembershipOnTarget = true;
                            break;
                        }
                    }
                }
            }
        }

        $platformEmail = '';
        if ($this->authService->check()) {
            $u = $this->authService->user();
            $platformEmail = trim((string) ($u['email'] ?? ''));
        }

        return [
            'canUseAccount' => $canUseAccount,
            'prefill' => $prefill,
            'platform_email' => $platformEmail,
            'recruitmentPresets' => $recruitmentPresets,
            'hasMembershipOnTarget' => $hasMembershipOnTarget,
            'switchToTargetUrl' => $switchToTargetUrl,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function sanitizePrefillFromQuery(Request $request): array
    {
        $limits = [
            'full_name' => 200,
            'email' => 254,
            'callsign' => 120,
            'timezone' => 80,
            'weekly_availability' => 300,
        ];
        $out = [];
        foreach ($limits as $k => $maxLen) {
            $v = trim((string) $request->query($k, ''));
            if ($v === '') {
                continue;
            }
            if ($k === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $out[$k] = mb_substr($v, 0, $maxLen);
        }
        $age = trim((string) $request->query('age', ''));
        if ($age !== '' && ctype_digit($age)) {
            $a = (int) $age;
            if ($a >= 16 && $a <= 99) {
                $out['age'] = (string) $a;
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed>|null $profile
     * @return array{0:string,1:string}
     */
    private function resolveNamePartsFromAccount(array $user, ?array $profile): array
    {
        $fn = trim((string) ($profile['first_name'] ?? ''));
        $ln = trim((string) ($profile['last_name'] ?? ''));
        if ($fn !== '' || $ln !== '') {
            return [$fn !== '' ? $fn : '—', $ln !== '' ? $ln : '—'];
        }
        $dn = trim((string) ($user['display_name'] ?? ''));
        if ($dn !== '') {
            if (str_contains($dn, ' ')) {
                $pos = strpos($dn, ' ');

                return [substr($dn, 0, $pos), trim(substr($dn, $pos))];
            }

            return [$dn, '—'];
        }

        return ['—', '—'];
    }

    /**
     * Résolution tenant pour l’affichage : /enlistment sans slug valide ne retombe plus sur un tenant « placeholder ».
     */
    private function resolveTenantForDisplay(Request $request, array $params): ?array
    {
        $routeSlug = trim((string) ($params['slug'] ?? ''));
        if ($routeSlug !== '') {
            $t = $this->tenantRepository->findBySlug($routeSlug);
            if (!$t || $this->isPlaceholderTenant($t)) {
                return null;
            }

            return $t;
        }
        $qSlug = trim((string) $request->query('community', ''));
        if ($qSlug !== '') {
            $t = $this->tenantRepository->findBySlug($qSlug);
            if ($t && !$this->isPlaceholderTenant($t)) {
                return $t;
            }

            return null;
        }
        $default = $this->tenantRepository->getDefaultTenant();
        if (!$default || $this->isPlaceholderTenant($default)) {
            return null;
        }

        return $default;
    }

    /** @param array<string, mixed> $tenant */
    private function isPlaceholderTenant(array $tenant): bool
    {
        $slug = strtolower(trim((string) ($tenant['slug'] ?? '')));
        if ($slug === '' || $slug === 'default') {
            return true;
        }
        $name = mb_strtolower(trim((string) ($tenant['name'] ?? '')));
        if ($name === 'aucune organisation' || str_contains($name, 'aucune organisation')) {
            return true;
        }

        return false;
    }

    private function resolveTenantForRequest(Request $request, array $params): ?array
    {
        $routeSlug = trim((string) ($params['slug'] ?? ''));
        if ($routeSlug !== '') {
            $t = $this->tenantRepository->findBySlug($routeSlug);
            if (!$t || $this->isPlaceholderTenant($t)) {
                return null;
            }

            return $t;
        }
        $hidden = trim((string) $request->input('enlistment_tenant_slug', ''));
        if ($hidden !== '') {
            $t = $this->tenantRepository->findBySlug($hidden);
            if ($t && !$this->isPlaceholderTenant($t)) {
                return $t;
            }

            return null;
        }
        $default = $this->tenantRepository->getDefaultTenant();
        if (!$default || $this->isPlaceholderTenant($default)) {
            return null;
        }

        return $default;
    }

    /** @param array<string,mixed> $tenant */
    private function communityConfig(array $tenant): array
    {
        $raw = $tenant['settings'] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        return is_array($decoded['community'] ?? null) ? $decoded['community'] : [];
    }

    /**
     * Encadré « évolution de carrière » : membre déjà dans le tenant + avis ciblé.
     *
     * @param array<string, mixed> $enlistmentContext
     * @param array<string, mixed>|null $selectedRecruitmentOpening
     * @return array<string, mixed>|null
     */
    private function resolveEnlistmentMemberOpeningInsight(
        int $targetTenantId,
        array $enlistmentContext,
        ?array $selectedRecruitmentOpening
    ): ?array {
        if ($selectedRecruitmentOpening === null || empty($selectedRecruitmentOpening['id']) || empty($enlistmentContext['canUseAccount'])) {
            return null;
        }
        if (!$this->authService->check() || (int) Session::get('tenant_id') !== $targetTenantId) {
            return null;
        }
        $user = $this->authService->user();
        if (!$user) {
            return null;
        }
        $oid = (int) $selectedRecruitmentOpening['id'];
        $ro = $this->recruitmentOpeningRepository->findByIdForTenant($oid, $targetTenantId);
        if (!$ro || (string) ($ro['status'] ?? '') !== 'published') {
            return null;
        }

        return $this->buildMemberOpeningCareerInsight($targetTenantId, (int) ($user['id'] ?? 0), $ro);
    }

    /**
     * @param array<string, mixed> $opening
     * @return array<string, mixed>
     */
    private function buildMemberOpeningCareerInsight(int $tenantId, int $userId, array $opening): array
    {
        $unknownDossier = 'Non renseigné sur votre dossier';

        $assignments = $this->personnelAssignmentRepository->listActiveForUserResolved($userId);
        $primary = $assignments[0] ?? null;
        $currentUnit = '';
        if ($primary !== null) {
            $currentUnit = trim((string) ($primary['unit_name'] ?? ''));
        }
        $pp = $this->personnelProfileRepository->getByUserId($userId);
        if ($currentUnit === '' && is_array($pp) && !empty($pp['primary_unit_id'])) {
            $urow = $this->unitRepository->findById((int) $pp['primary_unit_id'], $tenantId);
            if ($urow) {
                $currentUnit = trim((string) ($urow['name'] ?? ''));
            }
        }

        $currentAffectRole = '';
        if ($primary !== null) {
            $currentAffectRole = trim((string) ($primary['role_name'] ?? ''));
        }

        $currentJobLabel = $this->resolveCurrentPersonnelJobRoleLabel($tenantId, $userId, $pp);

        $currentClearanceKey = is_array($pp) ? trim((string) ($pp['clearance_level'] ?? '')) : '';
        $currentClearance = $currentClearanceKey !== ''
            ? RecruitmentOpeningPresentation::clearanceLabel($currentClearanceKey)
            : $unknownDossier;

        $targetUnit = trim((string) ($opening['unit_name'] ?? ''));
        $openingJobId = !empty($opening['personnel_job_role_id']) ? (int) $opening['personnel_job_role_id'] : 0;
        $targetJob = $this->personnelJobRoleDisplayName($tenantId, $openingJobId);
        if ($targetJob === '') {
            $targetJob = trim((string) ($opening['title'] ?? ''));
        }
        $openingTitle = trim((string) ($opening['title'] ?? ''));
        $targetEngagement = trim((string) ($opening['employment_contract_label'] ?? ''));
        if ($targetEngagement === '') {
            $targetEngagement = trim((string) ($opening['employment_context_label'] ?? ''));
        }
        if ($targetEngagement === '') {
            $targetEngagement = '—';
        }
        $openingClearanceKey = trim((string) ($opening['clearance_level'] ?? 'none'));
        $targetClearance = RecruitmentOpeningPresentation::clearanceLabel($openingClearanceKey !== '' ? $openingClearanceKey : 'none');

        $targetCadre = RecruitmentOpeningPresentation::personnelCategoryLabel((string) ($opening['personnel_category'] ?? 'other'))
            . ' · '
            . RecruitmentOpeningPresentation::armDomainLabel(isset($opening['arm_domain']) ? (string) $opening['arm_domain'] : null);

        $row = static function (string $theme, string $current, string $target, bool $emphasize): array {
            return [
                'theme' => $theme,
                'current' => $current,
                'target' => $target,
                'emphasize' => $emphasize,
            ];
        };
        $norm = static function (string $a, string $b): bool {
            return mb_strtolower(trim($a)) !== mb_strtolower(trim($b));
        };

        $dispUnitCur = $currentUnit !== '' ? $currentUnit : $unknownDossier;
        $dispUnitTgt = $targetUnit !== '' ? $targetUnit : '—';
        $dispJobCur = $currentJobLabel !== '' ? $currentJobLabel : $unknownDossier;
        $dispJobTgt = $targetJob !== '' ? $targetJob : '—';
        $dispAffCur = $currentAffectRole !== '' ? $currentAffectRole : $unknownDossier;
        $dispAffTgt = $openingTitle !== '' ? $openingTitle : '—';

        $rows = [
            $row('Unité / formation', $dispUnitCur, $dispUnitTgt, $norm($dispUnitCur, $dispUnitTgt)),
            $row('Poste métier (référence dossier)', $dispJobCur, $dispJobTgt, $norm($dispJobCur, $dispJobTgt)),
            $row('Fonction sur l’affectation', $dispAffCur, $dispAffTgt, $norm($dispAffCur, $dispAffTgt)),
            $row(
                'Engagement envisagé pour ce poste',
                $unknownDossier,
                $targetEngagement,
                $targetEngagement !== '—' && $norm($unknownDossier, $targetEngagement)
            ),
            $row('Habilitation requise à l’issue', $currentClearance, $targetClearance, $norm($currentClearance, $targetClearance)),
            $row('Cadre du poste (profil / domaine)', '—', $targetCadre, false),
        ];

        $lead = 'Vous êtes déjà membre de cette communauté : cette candidature ne correspond pas à une première inscription, '
            . 'mais à une demande d’évolution de carrière (changement d’unité, de fonction sur l’affectation, de poste métier référencé sur le dossier, '
            . 'd’engagement ou d’habilitation). Le tableau ci-dessous confronte ce que le portail affiche aujourd’hui dans votre dossier '
            . 'et ce que décrit l’avis pour le poste visé. La décision finale et les ajustements exacts du dossier restent du ressort du commandement et des RH.';

        $footnote = 'Si une ligne affiche « Non renseigné sur votre dossier », complétez votre fiche personnelle ou demandez au staff de mettre à jour votre affectation pour éviter tout malentendu.';

        return [
            'lead' => $lead,
            'rows' => $rows,
            'footnote' => $footnote,
        ];
    }

    /** @param array<string, mixed>|null $personnelProfile */
    private function resolveCurrentPersonnelJobRoleLabel(int $tenantId, int $userId, ?array $personnelProfile): string
    {
        if ($this->personnelJobRoleRepository->personnelProfilesHaveJobRoleColumns()
            && is_array($personnelProfile)
            && !empty($personnelProfile['personnel_job_role_id'])) {
            $lbl = $this->personnelJobRoleDisplayName($tenantId, (int) $personnelProfile['personnel_job_role_id']);
            if ($lbl !== '') {
                return $lbl;
            }
        }
        if ($this->personnelJobRoleRepository->pivotTableExists()) {
            $map = $this->personnelJobRoleRepository->listPivotAssignmentsForUsers($tenantId, [$userId]);
            foreach ($map[$userId] ?? [] as $p) {
                $rn = trim((string) ($p['role_name'] ?? ''));
                if ($rn === '') {
                    continue;
                }
                $rd = trim((string) ($p['role_detail'] ?? ''));

                return $rd !== '' ? $rn . ' — ' . $rd : $rn;
            }
        }

        return '';
    }

    private function personnelJobRoleDisplayName(int $tenantId, int $roleId): string
    {
        if ($roleId <= 0 || !$this->personnelJobRoleRepository->tablesExist()) {
            return '';
        }
        $r = $this->personnelJobRoleRepository->findRoleById($roleId, $tenantId);

        return trim((string) ($r['name'] ?? ''));
    }

    /** @param array<string,mixed> $tenant */
    private function enlistmentActionUrl(array $tenant): string
    {
        $slug = trim((string) ($tenant['slug'] ?? ''));
        if ($slug === '') {
            return url('enlistment');
        }
        return url('c/' . $slug . '/enlistment');
    }
}
