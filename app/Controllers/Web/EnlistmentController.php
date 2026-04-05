<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Repositories\EnlistmentRepository;
use App\Repositories\RecruitmentPresetRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Community\EnlistmentMilsimPackService;
use App\Services\Profile\RecruitmentPresetPayloadService;

class EnlistmentController
{
    public function __construct(
        private EnlistmentRepository $enlistmentRepository,
        private TenantRepository $tenantRepository,
        private AuthService $authService,
        private UserRepository $userRepository,
        private UserProfileRepository $userProfileRepository,
        private RecruitmentPresetRepository $recruitmentPresetRepository,
        private RecruitmentPresetPayloadService $recruitmentPresetPayloadService
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

        $mode = ($communityConfig['registration_mode'] ?? 'milsim') === 'simple' ? 'simple' : 'milsim';
        $tenantName = trim((string) ($tenant['name'] ?? 'Communauté'));
        $formAction = $this->enlistmentActionUrl($tenant);
        $targetTenantId = (int) $tenant['id'];
        $enlistmentContext = $this->buildEnlistmentContext($request, $tenant, $targetTenantId);

        $viewData = [
            'tenant' => $tenant,
            'communityConfig' => $communityConfig,
            'formAction' => $formAction,
            'enlistmentContext' => $enlistmentContext,
        ];

        if ($mode === 'simple') {
            return Response::view('layout.main', array_merge($viewData, [
                'content' => 'enlistment.simple',
                'title' => 'Inscription — ' . $tenantName,
                'simpleEnlistmentUrl' => $this->enlistmentFormUrl($tenant),
                'showMilsimUnavailableNotice' => true,
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

        $requireAiAck = array_key_exists('require_ai_ack', $communityConfig) ? (bool) $communityConfig['require_ai_ack'] : true;
        if ($requireAiAck && !$request->input('no_ai_confirmed')) {
            Session::flash('enlistment_error', 'Vous devez confirmer l\'absence d\'IA dans ce rapport (case à cocher obligatoire).');

            return Response::redirect(url('enlistment/error'));
        }

        $targetTenantId = (int) $tenant['id'];
        $flow = trim((string) $request->input('enlistment_flow', 'guest'));

        if ($flow !== 'account') {
            $guestEmail = trim((string) $request->input('email'));
            if ($guestEmail === '' || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
                Session::flash('enlistment_error', 'Merci d’indiquer une adresse email valide.');

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
            $pData = $presetRow['payload'] ?? [];
            if (is_array($pData)) {
                $this->recruitmentPresetPayloadService->mergePresetIntoEnlistmentPayload($pData, $payload);
                if ($presetRow) {
                    $payload['recruitment_rp_snapshot'] = $this->recruitmentPresetPayloadService->buildRpSnapshotForEnlistment($pData);
                }
            }
            if ($callsign === null && is_array($pData)) {
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
            ];
        } else {
            $identityKind = trim((string) $request->input('identity_kind', 'admin'));
            if ($identityKind !== 'rp' && $identityKind !== 'admin') {
                $identityKind = 'admin';
            }
            $fullName = trim((string) $request->input('full_name'));
            if ($fullName === '') {
                Session::flash('enlistment_error', 'Merci d’indiquer un nom pour la candidature.');

                return Response::redirect(url('enlistment/error'));
            }
            $legalFull = trim((string) $request->input('legal_full_name'));
            if ($identityKind === 'rp') {
                $payload['recruitment_rp_snapshot'] = [
                    'identity_kind' => 'rp',
                    'character_name' => $fullName,
                    'legal_contact_name' => $legalFull !== '' ? $legalFull : null,
                ];
                $nameForSplit = $legalFull !== '' ? $legalFull : $fullName;
            } else {
                $nameForSplit = $fullName;
            }
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
            $payload['callsign'] = trim((string) $request->input('callsign')) ?: null;
        }

        try {
            $this->enlistmentRepository->create((int) $tenant['id'], $payload);
        } catch (\Throwable $e) {
            Session::flash('enlistment_error', 'Une erreur technique a empêché l\'enregistrement de votre candidature. Veuillez réessayer ou contacter le support.');

            return Response::redirect(url('enlistment/error'));
        }

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
            'callsign' => '',
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
                    $prefill['callsign'] = trim((string) ($user['callsign'] ?? ''));
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

        return [
            'canUseAccount' => $canUseAccount,
            'prefill' => $prefill,
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
