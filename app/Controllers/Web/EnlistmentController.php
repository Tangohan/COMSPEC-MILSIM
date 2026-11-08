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
use App\Repositories\InviteLinkRepository;
use App\Repositories\RecruitmentInviteCodeRepository;
use App\Services\Recruitment\InviteLinkService;
use App\Repositories\RecruitmentOpeningRepository;
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
use App\Services\Profile\CandidateRpSnapshotService;
use App\Support\Profile\EnlistmentTimezoneCatalog;
use App\Services\Analytics\AnalyticsEventCategory;
use App\Services\Analytics\AnalyticsEventName;
use App\Services\Analytics\AnalyticsEventService;
use App\Services\Analytics\AnalyticsSubjectType;
use App\Services\Recruitment\RecruitmentOpeningPresentation;
use App\Services\Recruitment\CandidateAccountIdentity;
use App\Support\PendingEnlistmentReturn;

class EnlistmentController
{
    /** Lien d'invitation /i/<code> (action candidature) valide pour la communauté de la requête en cours. */
    private ?array $activeInviteLink = null;

    public function __construct(
        private EnlistmentRepository $enlistmentRepository,
        private EnlistmentTimelineRepository $enlistmentTimelineRepository,
        private TenantRepository $tenantRepository,
        private AuthService $authService,
        private UserRepository $userRepository,
        private UserProfileRepository $userProfileRepository,
        private CandidateRpSnapshotService $candidateRpSnapshotService,
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
        private ?\App\Services\Moderation\AiTextLikelihoodDetector $aiTextLikelihoodDetector = null,
    ) {
        $this->aiTextLikelihoodDetector ??= new \App\Services\Moderation\AiTextLikelihoodDetector();
    }

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
        $this->activeInviteLink = $this->resolveInviteLink((string) $request->query('lien', ''), $tenant);
        $communityConfig = $this->applyInviteLinkMode($communityConfig);
        $discordOnly = $this->discordOnlyResponse($tenant, $communityConfig);
        if ($discordOnly !== null) {
            return $discordOnly;
        }
        if (!empty($communityConfig['community_locked'])) {
            return Response::view('enlistment.error', ['message' => 'Le recrutement est verrouillé pour cette communauté.', 'enlistmentRetryUrl' => $this->enlistmentFormUrl($tenant)]);
        }

        $otherOrgBlock = $this->otherCommunityMembershipBlockReason($communityConfig, (int) $tenant['id']);
        if ($otherOrgBlock !== null) {
            return Response::view('enlistment.error', [
                'message' => $otherOrgBlock,
                'enlistmentRetryUrl' => $this->enlistmentFormUrl($tenant),
            ]);
        }

        $mode = TenantCommunityProfileService::normalizeRegistrationMode($communityConfig['registration_mode'] ?? TenantCommunityProfileService::REGISTRATION_MODE_MILSIM);
        $tenantName = trim((string) ($tenant['name'] ?? 'Communauté'));
        $formAction = $this->enlistmentActionUrl($tenant);
        $targetTenantId = (int) $tenant['id'];
        $enlistmentContext = $this->buildEnlistmentContext($request, $tenant, $targetTenantId, $mode === TenantCommunityProfileService::REGISTRATION_MODE_DISCORD);

        $selectedRecruitmentOpening = null;
        $ouvertureId = (int) $request->query('ouverture', 0);
        if ($ouvertureId < 1 && $this->activeInviteLink !== null) {
            $ouvertureId = (int) ($this->activeInviteLink['assign_to_opening_id'] ?? 0);
        }
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

        $existingCandidature = $this->resolveExistingCandidatureCard($targetTenantId, $tenant);

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
            'existingCandidature' => $existingCandidature,
            'inviteLinkBanner' => $this->inviteLinkBanner($targetTenantId),
        ];

        if ($mode === 'simple') {
            return Response::view('layout.main', array_merge($viewData, [
                'content' => 'enlistment.simple',
                'title' => 'Inscription — ' . $tenantName,
                'simpleEnlistmentUrl' => $this->enlistmentFormUrl($tenant),
                'showMilsimUnavailableNotice' => true,
                'milsimPack' => EnlistmentMilsimPackService::forCommunity($communityConfig),
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

        $tenant = $this->resolveTenantForRequest($request, $params);

        // Un compte est nécessaire pour candidater : refus avant toute autre lecture de la requête, quel que soit
        // le contenu envoyé. L'identité ne vient jamais du navigateur mais du compte connecté (ci-dessous).
        $user = $this->authService->check() ? $this->authService->user() : null;
        $uid = is_array($user) ? (int) ($user['id'] ?? 0) : 0;
        if ($uid < 1) {
            return $this->refuseWithoutAccount($request, $tenant);
        }

        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('enlistment_error', 'Session expirée. Veuillez recharger la page et soumettre à nouveau le formulaire.');
            $this->flashEnlistmentRetryUrl($tenant);

            return Response::redirect(url('enlistment/error'));
        }

        if (!$tenant) {
            Session::flash('enlistment_error', 'Organisation non configurée. Merci de réessayer plus tard.');
            Session::flash('enlistment_retry_url', url('enlistment'));

            return Response::redirect(url('enlistment/error'));
        }
        $this->flashEnlistmentRetryUrl($tenant);

        $communityConfig = $this->communityConfig($tenant);
        // Lien d'invitation expiré ou épuisé entre l'ouverture et l'envoi du formulaire : la candidature part quand même,
        // sans être rattachée au lien.
        $this->activeInviteLink = $this->resolveInviteLink((string) $request->input('invite_link', ''), $tenant);
        $communityConfig = $this->applyInviteLinkMode($communityConfig);
        if (\App\Services\Community\TenantTypeConfig::recruitsOnDiscordOnly((string) ($tenant['tenant_type'] ?? 'full'))) {
            // Profils sans recrutement : aucune candidature déposée sur le site, même par un formulaire déjà ouvert.
            return Response::redirect($this->enlistmentFormUrl($tenant));
        }
        if (!empty($communityConfig['community_locked'])) {
            Session::flash('enlistment_error', 'Le recrutement est verrouillé pour cette communauté.');

            return Response::redirect(url('enlistment/error'));
        }

        $otherOrgBlock = $this->otherCommunityMembershipBlockReason($communityConfig, (int) $tenant['id']);
        if ($otherOrgBlock !== null) {
            Session::flash('enlistment_error', $otherOrgBlock);

            return Response::redirect(url('enlistment/error'));
        }

        $uemail = trim((string) ($user['email'] ?? ''));
        $ongoing = $this->enlistmentRepository->findOngoingSubmittedForAccount((int) $tenant['id'], $uid, $uemail);
        if ($ongoing !== null) {
            Session::flash(
                'enlistment_error',
                'Vous avez déjà une candidature en cours pour cette communauté. Consultez le suivi de votre dossier plutôt que d’en déposer une nouvelle.'
            );
            Session::flash('enlistment_retry_url', $this->enlistmentFormUrl($tenant) . '#candidature');

            return Response::redirect(url('enlistment/error'));
        }

        // Identité du compte (jamais lue dans la requête) ; la requête ne fournit que les manques du profil.
        $isDiscord = (string) ($communityConfig['registration_mode'] ?? 'milsim') === 'discord';
        $profile = $this->userProfileRepository->getByUserId($uid);
        $identity = CandidateAccountIdentity::fromAccount($user, $profile, $isDiscord);
        $completed = CandidateAccountIdentity::complete($identity, $this->identityFormInput($request));
        if ($completed['error'] !== null) {
            Session::flash('enlistment_error', $completed['error']);

            return Response::redirect(url('enlistment/error'));
        }
        $identity = $completed['identity'];
        $profileUpdates = $completed['profile_updates'];

        $email = (string) $identity['email'];
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('enlistment_error', 'Adresse email du compte invalide. Mettez-la à jour dans les paramètres.');

            return Response::redirect(url('enlistment/error'));
        }
        if (!$request->input('consent_data_sharing')) {
            Session::flash('enlistment_error', 'Vous devez accepter le partage des données avec l’équipe de la communauté.');

            return Response::redirect(url('enlistment/error'));
        }

        if ($isDiscord) {
            return $this->storeDiscordEnlistment($request, $tenant, $identity, $uid, $profileUpdates);
        }

        $requireAiAck = array_key_exists('require_ai_ack', $communityConfig) ? (bool) $communityConfig['require_ai_ack'] : true;
        if ($requireAiAck && !$request->input('no_ai_confirmed')) {
            Session::flash('enlistment_error', 'Vous devez confirmer l\'absence d\'IA dans ce rapport (case à cocher obligatoire).');

            return Response::redirect(url('enlistment/error'));
        }

        $targetTenantId = (int) $tenant['id'];

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
        $isCompactAccount = $formMode === 'compact' && $openingValidForCompact;

        $payload = [
            'country' => (string) $identity['nationality'] !== '' ? (string) $identity['nationality'] : null,
            'experience' => trim((string) $request->input('experience')) ?: null,
            'specialty' => trim((string) $request->input('specialty')) ?: null,
            'platform' => trim((string) $request->input('platform')) ?: null,
            'availability' => trim((string) $request->input('availability')) ?: null,
            'notes' => trim((string) $request->input('notes')) ?: null,
            'age' => $identity['age'],
            'timezone' => EnlistmentTimezoneCatalog::normalize((string) $request->input('timezone')),
            'weekly_availability' => trim((string) $request->input('weekly_availability')) ?: null,
            'system_config' => EnlistmentTimezoneCatalog::normalizePcPerformance((string) $request->input('system_config')),
            'microphone_quality' => trim((string) $request->input('microphone_quality')) ?: null,
            'past_milsim_experience' => trim((string) $request->input('past_milsim_experience')) ?: null,
            'ace_acre_level' => trim((string) $request->input('ace_acre_level')) ?: null,
            'motivation_why_join' => trim((string) $request->input('motivation_why_join')) ?: null,
            'motivation_accountability' => trim((string) $request->input('motivation_accountability')) ?: null,
            'commitment_effort' => trim((string) $request->input('commitment_effort')) ?: null,
            'availability_wed_sat' => trim((string) $request->input('availability_wed_sat')) ?: null,
            'no_ai_confirmed' => $requireAiAck ? $request->input('no_ai_confirmed') : 1,
            'recruitment_preset_id' => null,
        ];

        $milsimPack = EnlistmentMilsimPackService::forCommunity($communityConfig);
        $configuredSlots = EnlistmentMilsimPackService::slotsOrDefault($milsimPack['availability_slots'] ?? []);
        if ($configuredSlots !== []) {
            $selectedLabels = EnlistmentMilsimPackService::filterCandidateSlotSelection(
                $configuredSlots,
                $request->input('availability_slot')
            );
            $notes = trim((string) $request->input('weekly_availability_notes', ''));
            if (mb_strlen($notes) > 300) {
                $notes = mb_substr($notes, 0, 300);
            }
            $parts = $selectedLabels;
            if ($notes !== '') {
                $parts[] = $notes;
            }
            $payload['weekly_availability'] = $parts !== [] ? implode(', ', $parts) : null;
            if ($payload['availability'] === null && $payload['weekly_availability'] !== null) {
                $payload['availability'] = $payload['weekly_availability'];
            }
        }

        if ($isCompactAccount && trim((string) ($payload['motivation_why_join'] ?? '')) === '') {
            Session::flash('enlistment_error', 'Indiquez en quelques lignes votre motivation pour ce poste.');

            return Response::redirect(url('enlistment/error'));
        }

        // Identité, contact et rattachement : toujours ceux du compte connecté. L'e-mail du compte est transmis
        // au staff sans option de masquage (le masque n'avait aucun effet réel sur les listes et les messages).
        $payload['first_name'] = (string) $identity['first_name'];
        $payload['last_name'] = (string) $identity['last_name'];
        $payload['email'] = $email;
        $payload['callsign'] = null;
        $payload['submitter_user_id'] = $uid;
        $payload['submitted_via'] = 'account';
        $payload['consent_sharing_at'] = date('Y-m-d H:i:s');
        $payload['shared_fields'] = [
            'share_name' => true,
            'share_email' => true,
            'share_callsign' => false,
            'form_mode' => $isCompactAccount ? 'compact' : 'full',
        ];

        if (!$isCompactAccount) {
            $motivationPack = EnlistmentMilsimPackService::forCommunity($communityConfig);
            $motivationCfg = EnlistmentMilsimPackService::normalizeMotivationSection(
                is_array($motivationPack['motivation'] ?? null) ? $motivationPack['motivation'] : null,
                is_array($motivationPack['fields'] ?? null) ? $motivationPack['fields'] : null
            );
            if (!empty($motivationCfg['why_join']['enabled'])
                && !empty($motivationCfg['why_join']['required'])
                && trim((string) ($payload['motivation_why_join'] ?? '')) === '') {
                Session::flash('enlistment_error', 'Merci de répondre à la question de motivation.');

                return Response::redirect(url('enlistment/error'));
            }
            if (!empty($motivationCfg['accountability']['enabled'])
                && !empty($motivationCfg['accountability']['required'])
                && trim((string) ($payload['motivation_accountability'] ?? '')) === '') {
                Session::flash('enlistment_error', 'Merci de répondre à la question complémentaire sur la motivation.');

                return Response::redirect(url('enlistment/error'));
            }
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

        $this->applyInviteLinkOpening($payload, $targetTenantId);

        // Gestion du code d'invitation prioritaire
        $inviteCode = $this->priorityCodeInput($request, $targetTenantId);
        $inviteCodeData = null;
        if ($inviteCode !== '' && $this->inviteCodeRepository()->tablesExist()) {
            $inviteCodeData = $this->inviteCodeRepository()->findPriorityByCode($targetTenantId, $inviteCode);
            if ($inviteCodeData === null) {
                Session::flash('enlistment_error', 'Le code d\'invitation prioritaire fourni n\'existe pas.');
                return Response::redirect(url('enlistment/error'));
            }

            if (!$this->inviteCodeRepository()->isPriorityCodeValid($targetTenantId, $inviteCode)) {
                Session::flash('enlistment_error', 'Le code d\'invitation prioritaire fourni n\'est plus valide (expiré ou limite d\'utilisations atteinte).');
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

        $customQuestions = EnlistmentMilsimPackService::normalizeCustomQuestions($milsimPack['custom_questions'] ?? []);
        $customAnswersPayload = [];
        $answerMapForRefuse = [
            'microphone_quality' => trim((string) ($payload['microphone_quality'] ?? '')),
            'system_config' => trim((string) ($payload['system_config'] ?? '')),
            'ace_acre_level' => trim((string) ($payload['ace_acre_level'] ?? '')),
            'commitment_effort' => trim((string) ($payload['commitment_effort'] ?? '')),
            'availability_wed_sat' => trim((string) ($payload['availability_wed_sat'] ?? '')),
        ];
        foreach ($customQuestions as $cq) {
            $cid = $cq['id'];
            $rawAns = trim((string) $request->input('custom_q_' . $cid, ''));
            if ($cq['required'] && $rawAns === '') {
                Session::flash('enlistment_error', 'Merci de répondre à la question : « ' . $cq['label'] . ' ».');

                return Response::redirect(url('enlistment/error'));
            }
            if (in_array($cq['widget'], ['select', 'yesno'], true) && $rawAns !== '' && $cq['options'] !== []) {
                $allowed = array_map(static fn (string $o): string => mb_strtolower($o), $cq['options']);
                if (!in_array(mb_strtolower($rawAns), $allowed, true)) {
                    Session::flash('enlistment_error', 'Réponse invalide pour la question : « ' . $cq['label'] . ' ».');

                    return Response::redirect(url('enlistment/error'));
                }
            }
            if ($rawAns === '') {
                continue;
            }
            $customAnswersPayload[] = [
                'question_id' => $cid,
                'label' => $cq['label'],
                'widget' => $cq['widget'],
                'answer' => mb_substr($rawAns, 0, 4000),
            ];
            $answerMapForRefuse[$cid] = $rawAns;
        }
        if ($customAnswersPayload !== []) {
            $payload['custom_answers'] = $customAnswersPayload;
        }

        $refuseHit = EnlistmentMilsimPackService::evaluateAutoRefuse($milsimPack, $answerMapForRefuse);
        if ($refuseHit !== null) {
            $payload['status'] = 'rejected';
            $payload['auto_rejected'] = true;
            $payload['reviewer_comment'] = $refuseHit['staff_note'];
        }

        $this->completeProfile($uid, $profileUpdates);

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
                    'Code d\'invitation prioritaire utilisé',
                    'Code : ' . $codeLabel . $autoAcceptNote,
                    null,
                    ['invite_code_id' => (int) $inviteCodeData['id']]
                );
            }
        }

        $subUid = isset($payload['submitter_user_id']) ? (int) $payload['submitter_user_id'] : 0;
        $this->recordInviteLinkApplication((int) $tenant['id'], $enlistmentId, $subUid, $request->ip());
        $this->enlistmentTimelineRepository->logIntakeFromSubmission(
            (int) $tenant['id'],
            $enlistmentId,
            $subUid > 0 ? $subUid : null,
            (string) ($payload['submitted_via'] ?? 'guest')
        );

        $this->flagAiLikelihoodIfNeeded((int) $tenant['id'], $enlistmentId, $payload);

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

        if ($refuseHit !== null) {
            if ($this->enlistmentTimelineRepository->tableExists()) {
                $this->enlistmentTimelineRepository->append(
                    (int) $tenant['id'],
                    $enlistmentId,
                    'system',
                    'decision',
                    'Refus automatique',
                    $refuseHit['staff_note'],
                    null,
                    ['auto_rejected' => true, 'field_key' => $refuseHit['field_key']]
                );
            }
            Session::flash('enlistment_error', $refuseHit['candidate_message']);
            Session::flash('enlistment_retry_url', $this->enlistmentFormUrl($tenant));

            return Response::redirect(url('enlistment/error'));
        }

        return Response::redirect($this->enlistmentSuccessUrl($tenant));
    }

    /**
     * Dépôt de candidature via le formulaire de recrutement Discord (mode registration_mode
     * = discord) : pseudo Discord + réponses aux questions custom du tenant, sans le dossier
     * MilSim complet.
     *
     * @param array<string, mixed> $tenant
     */
    private function storeDiscordEnlistment(Request $request, array $tenant, array $identity, int $uid, array $profileUpdates): Response
    {
        $targetTenantId = (int) $tenant['id'];

        // Pseudo Discord, nom et e-mail viennent du compte (le pseudo Discord du profil, ou celui qui le complète).
        $discordPseudo = (string) $identity['discord_handle'];
        $displayName = (string) $identity['display_name'];
        $email = (string) $identity['email'];

        if ($discordPseudo === '' || $displayName === '') {
            Session::flash('enlistment_error', 'Merci d’indiquer votre pseudo Discord et votre nom.');

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
            'first_name' => (string) $identity['first_name'] !== '' ? (string) $identity['first_name'] : $displayName,
            'last_name' => (string) $identity['last_name'] !== '' ? (string) $identity['last_name'] : '—',
            'email' => $email,
            'callsign' => $discordPseudo,
            'submitted_via' => 'account',
            'submitter_user_id' => $uid,
            'consent_sharing_at' => date('Y-m-d H:i:s'),
            'shared_fields' => ['share_name' => true, 'share_email' => true, 'share_callsign' => true, 'form_mode' => 'discord'],
            'age' => $identity['age'],
            'country' => (string) $identity['nationality'] !== '' ? (string) $identity['nationality'] : null,
            'form_channel' => 'discord',
            'discord_pseudo' => $discordPseudo,
            'discord_answers' => $answers,
        ];

        $this->applyInviteLinkOpening($payload, $targetTenantId);

        // Gestion du code d'invitation prioritaire
        $inviteCode = $this->priorityCodeInput($request, $targetTenantId);
        $inviteCodeData = null;
        if ($inviteCode !== '' && $this->inviteCodeRepository()->tablesExist()) {
            $inviteCodeData = $this->inviteCodeRepository()->findPriorityByCode($targetTenantId, $inviteCode);
            if ($inviteCodeData === null) {
                Session::flash('enlistment_error', 'Le code d\'invitation prioritaire fourni n\'existe pas.');
                return Response::redirect(url('enlistment/error'));
            }

            if (!$this->inviteCodeRepository()->isPriorityCodeValid($targetTenantId, $inviteCode)) {
                Session::flash('enlistment_error', 'Le code d\'invitation prioritaire fourni n\'est plus valide (expiré ou limite d\'utilisations atteinte).');
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

        $this->completeProfile($uid, $profileUpdates);

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
                    'Code d\'invitation prioritaire utilisé',
                    'Code : ' . $codeLabel . $autoAcceptNote,
                    null,
                    ['invite_code_id' => (int) $inviteCodeData['id']]
                );
            }
        }

        $this->recordInviteLinkApplication($targetTenantId, $enlistmentId, $uid, $request->ip());
        $this->enlistmentTimelineRepository->logIntakeFromSubmission($targetTenantId, $enlistmentId, $uid, 'account');
        $this->flagAiLikelihoodIfNeeded($targetTenantId, $enlistmentId, $payload);
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

    /**
     * Envoi sans compte : refusé. On garde la communauté visée pour y revenir après la connexion ou la création du compte.
     *
     * @param array<string, mixed>|null $tenant
     */
    private function refuseWithoutAccount(Request $request, ?array $tenant): Response
    {
        $slug = trim((string) ($tenant['slug'] ?? ''));
        if ($slug !== '') {
            PendingEnlistmentReturn::remember($slug, (int) $request->input('enlistment_opening_id', 0), (string) $request->input('invite_link', ''));
        }
        Session::flash('info', 'Un compte est nécessaire pour candidater. Connectez-vous ou créez-en un : vous reviendrez sur le formulaire de la communauté.');

        return Response::redirect(url('login'));
    }

    /**
     * Étape « compte » : depuis le bouton de candidature d'un visiteur, mémorise la communauté (et l'offre ou le
     * lien d'invitation) puis envoie vers la connexion, ou la création de compte si « creer=1 ».
     */
    public function accountStep(Request $request, array $params = []): Response
    {
        $tenant = $this->resolveTenantForDisplay($request, $params);
        if (!$tenant) {
            return Response::redirect(url('communities'));
        }
        $slug = trim((string) ($tenant['slug'] ?? ''));
        $opening = max(0, (int) $request->query('ouverture', 0));
        $invite = strtolower(trim((string) $request->query('lien', '')));
        if (!InviteLinkService::isWellFormedCode($invite)) {
            $invite = '';
        }
        $formQuery = ($opening > 0 ? '?ouverture=' . $opening : '') . ($invite !== '' ? (($opening > 0 ? '&' : '?') . 'lien=' . rawurlencode($invite)) : '');
        if ($this->authService->check()) {
            return Response::redirect($this->enlistmentFormUrl($tenant) . $formQuery);
        }
        PendingEnlistmentReturn::remember($slug, $opening, $invite);
        $wantsRegister = (string) $request->query('creer', '') === '1';
        Session::flash('info', 'Connectez-vous' . ($wantsRegister ? '' : ' ou créez un compte') . ' pour candidater : vous reviendrez ensuite sur le formulaire de ' . trim((string) ($tenant['name'] ?? 'la communauté')) . '.');

        return Response::redirect($wantsRegister
            ? url('register') . ($invite !== '' ? '?invite=' . rawurlencode($invite) : '')
            : url('login'));
    }

    /**
     * Réponses du formulaire aux seuls champs que le profil ne connaît pas.
     *
     * @return array<string, mixed>
     */
    private function identityFormInput(Request $request): array
    {
        $out = [];
        foreach (CandidateAccountIdentity::FORM_FIELDS as $field) {
            $out[$field] = $request->input($field, '');
        }

        return $out;
    }

    /**
     * Ce que la candidature a appris sur la personne complète son profil.
     *
     * @param array<string, string> $updates
     */
    private function completeProfile(int $uid, array $updates): void
    {
        if ($updates === []) {
            return;
        }
        try {
            $this->userProfileRepository->upsert($uid, $updates);
        } catch (\Throwable) {
            // La candidature part quand même ; le profil pourra être complété depuis « Mon compte ».
        }
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

    /**
     * Lien d'invitation « candidature » actif et propre à cette communauté ; null sinon (code absent, inconnu, expiré,
     * épuisé, révoqué, lien d'adhésion ou d'une autre communauté).
     *
     * @param array<string, mixed> $tenant
     * @return array<string, mixed>|null
     */
    private function resolveInviteLink(string $code, array $tenant): ?array
    {
        $code = strtolower(trim($code));
        if ($code === '' || !InviteLinkService::isWellFormedCode($code)) {
            return null;
        }
        $link = Container::get(InviteLinkRepository::class)->findByPublicCode($code);
        if ($link === null
            || (int) $link['tenant_id'] !== (int) ($tenant['id'] ?? 0)
            || (string) ($link['link_action'] ?? '') !== InviteLinkRepository::ACTION_APPLY
            || InviteLinkService::status($link) !== InviteLinkService::STATUS_ACTIVE) {
            return null;
        }

        return $link;
    }

    /**
     * Type de candidature imposé par le lien (MilSim complet, simple, Discord), sinon celui de la communauté.
     *
     * @param array<string, mixed> $communityConfig
     * @return array<string, mixed>
     */
    private function applyInviteLinkMode(array $communityConfig): array
    {
        $mode = (string) ($this->activeInviteLink['metadata']['registration_mode'] ?? '');
        if (in_array($mode, [
            TenantCommunityProfileService::REGISTRATION_MODE_MILSIM,
            TenantCommunityProfileService::REGISTRATION_MODE_SIMPLE,
            TenantCommunityProfileService::REGISTRATION_MODE_DISCORD,
        ], true)) {
            $communityConfig['registration_mode'] = $mode;
        }

        return $communityConfig;
    }

    /** @param array<string, mixed> $payload */
    private function applyInviteLinkOpening(array &$payload, int $tenantId): void
    {
        $openingId = (int) ($this->activeInviteLink['assign_to_opening_id'] ?? 0);
        if ($openingId < 1 || !empty($payload['recruitment_opening_id']) || !$this->recruitmentOpeningRepository->tablesExist()) {
            return;
        }
        $ro = $this->recruitmentOpeningRepository->findByIdForTenant($openingId, $tenantId);
        if ($ro && (string) ($ro['status'] ?? '') === 'published') {
            $payload['recruitment_opening_id'] = $openingId;
        }
    }

    /** Code prioritaire saisi, sinon celui que le lien d'invitation applique d'office. */
    private function priorityCodeInput(Request $request, int $tenantId): string
    {
        $typed = trim((string) $request->input('invite_code', ''));
        if ($typed !== '' || $this->activeInviteLink === null) {
            return $typed;
        }
        $priorityId = (int) ($this->activeInviteLink['metadata']['priority_code_id'] ?? 0);
        if ($priorityId < 1 || !$this->inviteCodeRepository()->tablesExist()) {
            return '';
        }
        $row = $this->inviteCodeRepository()->findById($tenantId, $priorityId, RecruitmentInviteCodeRepository::KIND_PRIORITY);
        if ($row === null || !$this->inviteCodeRepository()->isPriorityCodeValid($tenantId, (string) $row['code'])) {
            return '';
        }

        return (string) $row['code'];
    }

    /** Candidature déposée depuis un lien d'invitation : utilisation décomptée, statistiques et fil du dossier. */
    private function recordInviteLinkApplication(int $tenantId, int $enlistmentId, int $userId, string $ip): void
    {
        $link = $this->activeInviteLink;
        if ($link === null || $enlistmentId < 1) {
            return;
        }
        $links = Container::get(InviteLinkRepository::class);
        if (!$links->consumeUse($tenantId, (int) $link['id'])) {
            return;
        }
        $links->recordEvent($tenantId, (int) $link['id'], InviteLinkRepository::EVENT_APPLICATION, $userId > 0 ? $userId : null, $enlistmentId, $ip);
        $label = trim((string) ($link['label'] ?? ''));
        $detail = 'Lien : ' . ($label !== '' ? $label : (string) $link['public_code']);
        $unitId = (int) ($link['metadata']['unit_id'] ?? 0);
        if ($unitId > 0) {
            $unit = $this->unitRepository->findById($unitId, $tenantId);
            if ($unit !== null) {
                $detail .= ' · Unité visée : ' . (string) ($unit['name'] ?? '');
            }
        }
        if ($this->enlistmentTimelineRepository->tableExists()) {
            $this->enlistmentTimelineRepository->append(
                $tenantId,
                $enlistmentId,
                'system',
                'reception',
                'Candidature par lien d’invitation',
                $detail,
                null,
                ['invite_code_id' => (int) $link['id']]
            );
        }
        try {
            Container::get(\App\Services\Audit\AuditService::class)->log(
                \App\Services\Audit\AuditAction::INVITE_LINK_APPLIED,
                $tenantId,
                $userId > 0 ? $userId : null,
                'invite_link',
                (int) $link['id'],
                null,
                (string) $link['public_code']
            );
        } catch (\Throwable) {
        }
    }

    /**
     * Bandeau et champ caché du formulaire quand la candidature vient d'un lien d'invitation.
     *
     * @return array{code: string, label: string, unit: string}|null
     */
    private function inviteLinkBanner(int $tenantId): ?array
    {
        $link = $this->activeInviteLink;
        if ($link === null) {
            return null;
        }
        $unitName = '';
        $unitId = (int) ($link['metadata']['unit_id'] ?? 0);
        if ($unitId > 0) {
            $unit = $this->unitRepository->findById($unitId, $tenantId);
            $unitName = $unit !== null ? trim((string) ($unit['name'] ?? '')) : '';
        }

        return ['code' => (string) $link['public_code'], 'label' => (string) ($link['label'] ?? ''), 'unit' => $unitName];
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
    private function buildEnlistmentContext(Request $request, array $tenant, int $targetTenantId, bool $needsDiscordHandle = false): array
    {
        $slug = trim((string) ($tenant['slug'] ?? ''));
        $prefill = [
            'timezone' => '',
            'weekly_availability' => '',
        ];
        foreach ($this->sanitizePrefillFromQuery($request) as $k => $v) {
            if ($v !== '' && array_key_exists($k, $prefill)) {
                $prefill[$k] = $v;
            }
        }

        $opening = max(0, (int) $request->query('ouverture', 0));
        $invite = strtolower(trim((string) $request->query('lien', '')));
        if (!InviteLinkService::isWellFormedCode($invite)) {
            $invite = '';
        }
        $stepQuery = [];
        if ($opening > 0) {
            $stepQuery[] = 'ouverture=' . $opening;
        }
        if ($invite !== '') {
            $stepQuery[] = 'lien=' . rawurlencode($invite);
        }
        $stepBase = $slug !== '' ? url('c/' . rawurlencode($slug) . '/enlistment/connexion') : url('login');
        $loginStepUrl = $stepBase . ($stepQuery !== [] ? '?' . implode('&', $stepQuery) : '');
        $registerStepUrl = $stepBase . '?' . implode('&', array_merge($stepQuery, ['creer=1']));

        // Compte connecté : son identité (profil) est reprise telle quelle, seuls les manques sont demandés.
        $account = null;
        $user = $this->authService->check() ? $this->authService->user() : null;
        $uid = is_array($user) ? (int) ($user['id'] ?? 0) : 0;
        if ($uid > 0) {
            $profile = $this->userProfileRepository->getByUserId($uid);
            $identity = CandidateAccountIdentity::fromAccount($user, $profile, $needsDiscordHandle);
            $tz = EnlistmentTimezoneCatalog::normalize((string) ($profile['timezone'] ?? ''));
            if ($prefill['timezone'] === '' && $tz !== null) {
                $prefill['timezone'] = $tz;
            }
            $personnel = null;
            try {
                $personnel = $this->personnelProfileRepository->getByUserId($uid);
            } catch (\Throwable) {
                $personnel = null;
            }
            $account = [
                'display_name' => $identity['display_name'] !== '' ? $identity['display_name'] : 'Mon compte',
                'portrait_url' => function_exists('user_site_avatar_url') ? (string) user_site_avatar_url($user, $personnel) : '',
                'profile_url' => url('account'),
                'missing' => $identity['missing'],
                'suggest_first_name' => $identity['suggest_first_name'],
                'suggest_last_name' => $identity['suggest_last_name'],
            ];
        }

        return [
            'isAuthenticated' => $account !== null,
            'account' => $account,
            'prefill' => $prefill,
            'loginStepUrl' => $loginStepUrl,
            'registerStepUrl' => $registerStepUrl,
        ];
    }

    /**
     * Carte « candidature déjà en cours » pour le compte connecté (statut submitted).
     *
     * @param array<string, mixed> $tenant
     * @return array<string, mixed>|null
     */
    private function resolveExistingCandidatureCard(int $targetTenantId, array $tenant): ?array
    {
        if (!$this->authService->check()) {
            return null;
        }
        $user = $this->authService->user();
        $uid = (int) ($user['id'] ?? 0);
        if ($uid < 1) {
            return null;
        }
        $email = trim((string) ($user['email'] ?? ''));
        $row = $this->enlistmentRepository->findOngoingSubmittedForAccount($targetTenantId, $uid, $email);
        if ($row === null) {
            // Dernière candidature terminale (refusée / acceptée / annulée) — info seule, formulaire autorisé sauf acceptée récente liée
            $latest = $this->enlistmentRepository->findLatestBySubmitter($targetTenantId, $uid);
            if ($latest === null) {
                return null;
            }
            $st = (string) ($latest['status'] ?? '');
            if ($st === 'submitted') {
                $row = $latest;
            } elseif (in_array($st, ['rejected', 'blocked', 'cancelled'], true)) {
                return [
                    'kind' => 'closed',
                    'status' => $st,
                    'status_label' => match ($st) {
                        'rejected' => 'Refusée',
                        'blocked' => 'Non admise',
                        'cancelled' => 'Annulée',
                        default => 'Clôturée',
                    },
                    'blocks_form' => false,
                    'title' => 'Dernière candidature clôturée',
                    'lead' => 'Votre précédente candidature pour cette communauté est clôturée. Vous pouvez en déposer une nouvelle si le recrutement est ouvert.',
                    'portal_url' => null,
                    'submitted_at' => trim((string) ($latest['created_at'] ?? '')),
                ];
            } elseif ($st === 'reviewed') {
                return [
                    'kind' => 'accepted',
                    'status' => $st,
                    'status_label' => 'Acceptée',
                    'blocks_form' => false,
                    'title' => 'Candidature déjà acceptée',
                    'lead' => 'Une candidature liée à votre compte a déjà été acceptée pour cette communauté. Utilisez le formulaire uniquement pour une évolution de poste si un avis est ouvert.',
                    'portal_url' => null,
                    'submitted_at' => trim((string) ($latest['created_at'] ?? '')),
                ];
            } else {
                return null;
            }
        }

        $eid = (int) ($row['id'] ?? 0);
        $portalUrl = null;
        if ($eid > 0) {
            $token = $this->enlistmentRepository->findValidCandidatePortalTokenForEnlistment($targetTenantId, $eid);
            if ($token === null) {
                $token = $this->enlistmentRepository->ensureCandidatePortalToken($targetTenantId, $eid, 24 * 7);
            }
            if ($token !== null) {
                $portalUrl = url('enlistment/suivi/' . rawurlencode($token));
            }
        }

        return [
            'kind' => 'ongoing',
            'status' => 'submitted',
            'status_label' => 'En cours',
            'blocks_form' => true,
            'title' => 'Candidature déjà en cours',
            'lead' => 'Vous avez déjà déposé un dossier pour cette communauté. Il est en cours d’examen par l’équipe de recrutement. Vous ne pouvez pas en envoyer un second tant que celui-ci n’est pas clôturé.',
            'next_steps' => 'Consultez le suivi de votre dossier pour connaître l’avancement et échanger avec l’équipe si besoin.',
            'portal_url' => $portalUrl,
            'submitted_at' => trim((string) ($row['created_at'] ?? '')),
            'enlistment_id' => $eid,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function sanitizePrefillFromQuery(Request $request): array
    {
        // Identité et e-mail ne se préremplissent plus par l'adresse : ils viennent du compte connecté.
        $out = [];
        $tz = EnlistmentTimezoneCatalog::normalize(trim((string) $request->query('timezone', '')));
        if ($tz !== null) {
            $out['timezone'] = $tz;
        }
        $week = trim((string) $request->query('weekly_availability', ''));
        if ($week !== '') {
            $out['weekly_availability'] = mb_substr($week, 0, 300);
        }

        return $out;
    }

    /**
     * Signal d’instruction : textes libres du dossier scorés localement (heuristique IA).
     * N’empêche pas le dépôt — journalise une entrée chronologie si le score dépasse le seuil.
     *
     * @param array<string, mixed> $payload
     */
    private function flagAiLikelihoodIfNeeded(int $tenantId, int $enlistmentId, array $payload): void
    {
        if ($tenantId < 1 || $enlistmentId < 1 || !$this->enlistmentTimelineRepository->tableExists()) {
            return;
        }
        $fields = [
            'motivation_why_join' => (string) ($payload['motivation_why_join'] ?? ''),
            'motivation_accountability' => (string) ($payload['motivation_accountability'] ?? ''),
            'commitment_effort' => (string) ($payload['commitment_effort'] ?? ''),
            'past_milsim_experience' => (string) ($payload['past_milsim_experience'] ?? ''),
            'notes' => (string) ($payload['notes'] ?? ''),
            'experience' => (string) ($payload['experience'] ?? ''),
        ];
        $custom = $payload['custom_answers'] ?? null;
        if (is_array($custom)) {
            foreach ($custom as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $widget = (string) ($row['widget'] ?? 'text');
                if (!in_array($widget, ['text', 'textarea', ''], true)) {
                    continue;
                }
                $id = trim((string) ($row['question_id'] ?? $row['label'] ?? 'custom'));
                $fields['custom_' . $id] = (string) ($row['answer'] ?? '');
            }
        }
        $discordAnswers = $payload['discord_answers'] ?? null;
        if (is_array($discordAnswers)) {
            foreach ($discordAnswers as $i => $ans) {
                if (is_string($ans)) {
                    $fields['discord_' . $i] = $ans;
                } elseif (is_array($ans)) {
                    $fields['discord_' . $i] = (string) ($ans['answer'] ?? $ans['value'] ?? '');
                }
            }
        }

        $analysis = $this->aiTextLikelihoodDetector->analyzeFields($fields);
        if (!$this->aiTextLikelihoodDetector->shouldFlag($analysis)) {
            return;
        }

        $signals = is_array($analysis['signals'] ?? null) ? $analysis['signals'] : [];
        $body = (string) ($analysis['label'] ?? 'Suspicion de texte généré par IA')
            . ' — score ' . (int) ($analysis['score'] ?? 0) . '/100.';
        if ($signals !== []) {
            $body .= "\n• " . implode("\n• ", array_slice($signals, 0, 6));
        }
        $body .= "\n\nIndicateur automatique (heuristique locale). À croiser avec le reste du dossier — pas une preuve.";

        $this->enlistmentTimelineRepository->append(
            $tenantId,
            $enlistmentId,
            'system',
            'reception',
            'Détection IA — ' . (string) ($analysis['label'] ?? 'signal'),
            $body,
            null,
            [
                'timeline_family' => 'ai_likelihood',
                'ai_score' => (int) ($analysis['score'] ?? 0),
                'ai_level' => (string) ($analysis['level'] ?? ''),
                'ai_signals' => $signals,
                'ai_word_count' => (int) ($analysis['word_count'] ?? 0),
                'ai_fields_scanned' => (int) ($analysis['fields_scanned'] ?? 0),
            ]
        );
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed>|null $profile
     * @return array{0:string,1:string}
     */
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

    /**
     * Si le tenant refuse les comptes déjà ailleurs : message FR, sinon null.
     * S’applique uniquement aux comptes connectés ayant une communauté réelle hors cible / hors « Pas d’organisation ».
     *
     * @param array<string, mixed> $communityConfig
     */
    private function otherCommunityMembershipBlockReason(array $communityConfig, int $targetTenantId): ?string
    {
        if (empty($communityConfig['refuse_other_community_members'])) {
            return null;
        }
        if (!$this->authService->check()) {
            return null;
        }
        $user = $this->authService->user();
        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '') {
            $email = trim((string) (Session::get('email') ?? ''));
        }
        if (!$this->userRepository->hasOtherNonDefaultCommunityMembership($email, $targetTenantId)) {
            return null;
        }

        return 'Cette communauté n’accepte pas les candidatures des comptes Athena déjà rattachés à une autre communauté. '
            . 'Les comptes sans communauté restent acceptés. Contactez le staff de la communauté si besoin.';
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
    /**
     * Profils « Bureau des effectifs » et « Carte ATAK » : pas de candidature sur le site,
     * la page d’enrôlement explique qu’on rejoint la communauté par son Discord.
     *
     * @param array<string, mixed> $tenant
     * @param array<string, mixed> $communityConfig
     */
    private function discordOnlyResponse(array $tenant, array $communityConfig): ?Response
    {
        if (!\App\Services\Community\TenantTypeConfig::recruitsOnDiscordOnly((string) ($tenant['tenant_type'] ?? 'full'))) {
            return null;
        }
        $slug = trim((string) ($tenant['slug'] ?? ''));

        return Response::view('enlistment.discord_only', [
            'tenantName' => trim((string) ($tenant['name'] ?? 'Communauté')),
            'discordUrl' => trim((string) ($communityConfig['contact_discord_url'] ?? '')),
            'contactEmail' => trim((string) ($communityConfig['contact_email'] ?? '')),
            'publicPageUrl' => $slug !== '' ? url('c/' . rawurlencode($slug)) : url('communities'),
        ]);
    }

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
            $row('Cadre du poste (profil / domaine)', '—', $targetCadre, false),
        ];

        $lead = 'Vous êtes déjà membre de cette communauté : cette candidature ne correspond pas à une première inscription, '
            . 'mais à une demande d’évolution de carrière (changement d’unité, de fonction sur l’affectation, de poste métier référencé sur le dossier '
            . 'ou d’engagement). Le tableau ci-dessous confronte ce que le portail affiche aujourd’hui dans votre dossier '
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
