<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityInvitationRepository;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\RoleRepository;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Auth\AuthService;
use App\Services\EmailService;
use App\Services\Platform\FeatureGateService;
use App\Support\OrganizationRoleLabels;

/**
 * Back-office › Invitations : une seule page (chiffres, jauge membres de la formule, assistant d'envoi, tableau),
 * envoi groupé, renvoi, nouveau lien à copier, révocation.
 */
final class InvitationAdminController
{
    /** Durées de validité proposées (jours). */
    public const EXPIRY_DAYS = [1, 3, 7, 14, 30];

    /** Adresses au plus par envoi. */
    public const MAX_BATCH = 50;

    public const MESSAGE_MAX = 1000;

    private const STATUSES = ['pending', 'accepted', 'revoked', 'expired'];

    public function __construct(
        private AuthService $authService,
        private TenantRepository $tenantRepository,
        private CommunityInvitationRepository $invitations,
        private RoleRepository $roleRepository,
        private UnitRepository $unitRepository,
        private PersonnelJobRoleRepository $personnelJobRoleRepository,
        private FeatureGateService $featureGate,
        private AuditService $auditService,
        private EmailService $emailService,
        private ?UserRepository $users = null,
        private ?SubscriptionPlanRepository $plans = null,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        return $this->page($request, $request->queryString('status'));
    }

    /** Ancienne adresse du tableur : même page, filtre d'état conservé. */
    public function sent(Request $request, array $params = []): Response
    {
        return $this->page($request, $request->queryString('status'));
    }

    private function page(Request $request, string $statusFilter): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $this->invitations->expireStale();
        if (!in_array($statusFilter, self::STATUSES, true)) {
            $statusFilter = '';
        }
        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $tenantRow = $this->tenantRepository->findById($tenantId) ?: [];
        $organizationRoleLabelMode = OrganizationRoleLabels::mode($community, $tenantRow);
        $jobRoleOptions = $this->personnelJobRoleRepository->tablesExist()
            ? $this->personnelJobRoleRepository->listRoleOptionsForSelect($tenantId, false, true, $organizationRoleLabelMode)
            : [];
        $planName = '';
        if ($tenantRow !== []) {
            $slug = $this->featureGate->effectivePlanSlug($tenantRow);
            $plan = $this->plans?->findBySlug($slug);
            $planName = trim((string) ($plan['name'] ?? ''));
        }

        return Response::view('layout.main', [
            'title' => 'Invitations',
            'content' => 'admin.organization.invitations',
            'backOfficePageCss' => ['effectifs-kit.css', 'invitations-ath.css', 'invitations-admin.css'],
            'rolesOrganization' => $this->roleRepository->forTenantOrganization($tenantId),
            'inviteUnits' => $this->unitRepository->allForTenant($tenantId),
            'inviteJobRoleOptions' => $jobRoleOptions,
            'inviteGrades' => $this->grades($tenantId),
            'organizationRoleLabelMode' => $organizationRoleLabelMode,
            'invitations' => $this->invitations->listForTenant($tenantId),
            'inviteStatusCounts' => $this->invitations->countByStatus($tenantId),
            'inviteFilterStatus' => $statusFilter,
            'memberCapacity' => $this->featureGate->memberCapacity($tenantId),
            'planName' => $planName,
            'canAdmin' => self::canAdmin(),
            'currentUserId' => (int) ($this->authService->user()['id'] ?? 0),
            'showPortalFooter' => false,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $back = url('back-office/invitations');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($back);
        }
        $tenantId = (int) Session::get('tenant_id');
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $cap = $this->featureGate->memberCapacity($tenantId);
        if (!$cap['can_add']) {
            Session::flash('error', 'Plafond de membres de votre formule atteint (' . $cap['used'] . ' / ' . $cap['limit'] . ').');

            return Response::redirect($back);
        }

        $parsed = self::parseEmails((string) $request->input('emails', (string) $request->input('email', '')));
        if ($parsed['valid'] === []) {
            Session::flash('error', $parsed['invalid'] !== []
                ? 'Adresse e-mail invalide : ' . implode(', ', array_slice($parsed['invalid'], 0, 5)) . '. Vérifiez la saisie et réessayez.'
                : 'Indiquez au moins une adresse e-mail.');

            return Response::redirect($back);
        }
        if (count($parsed['valid']) > self::MAX_BATCH) {
            Session::flash('error', 'Au plus ' . self::MAX_BATCH . ' adresses par envoi.');

            return Response::redirect($back);
        }

        $roleId = (int) $request->input('role_id');
        if ($roleId < 1) {
            Session::flash('error', 'Sélectionnez un rôle organisation.');

            return Response::redirect($back);
        }
        $roleRow = $this->roleRepository->findById($roleId, $tenantId);
        if (!$roleRow || !$this->roleRepository->canAssignInTenantAdminContext($roleId, $tenantId)) {
            Session::flash('error', 'Rôle non autorisé pour une invitation : choisissez un rôle de gouvernance ou opérationnel de votre communauté, pas un rôle réservé à la plateforme.');

            return Response::redirect($back);
        }

        // Adresses déjà membres ou déjà invitées : ignorées, signalées.
        $already = [];
        $pending = $this->invitations->pendingEmails($tenantId, $parsed['valid']);
        $toSend = [];
        foreach ($parsed['valid'] as $email) {
            if (in_array($email, $pending, true) || ($this->users !== null && $this->users->emailExistsInTenant($tenantId, $email))) {
                $already[] = $email;
            } else {
                $toSend[] = $email;
            }
        }
        if ($toSend === []) {
            Session::flash('error', 'Ces personnes sont déjà membres ou ont déjà une invitation en attente : ' . implode(', ', array_slice($already, 0, 5)) . '.');

            return Response::redirect($back);
        }
        if (!$cap['unlimited'] && count($toSend) > (int) $cap['remaining']) {
            Session::flash('error', 'Votre formule permet encore ' . (int) $cap['remaining'] . ' membre(s) (' . $cap['used'] . ' / ' . $cap['limit'] . ') : retirez des adresses ou passez à une offre supérieure.');

            return Response::redirect($back);
        }

        $payload = $this->arrivalPayload($request, $tenantId);
        $days = (int) $request->input('expiry_days', 7);
        if (!in_array($days, self::EXPIRY_DAYS, true)) {
            $days = 7;
        }
        $payload['expiry_days'] = $days;
        $message = trim(str_replace("\r\n", "\n", (string) $request->input('message', '')));
        $message = mb_substr($message, 0, self::MESSAGE_MAX);
        if ($message !== '') {
            $payload['message'] = $message;
        }
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;

        $links = [];
        foreach ($toSend as $email) {
            $token = bin2hex(random_bytes(32));
            $id = $this->invitations->create($tenantId, $email, hash('sha256', $token), (int) $user['id'], $roleId, new \DateTimeImmutable('+' . $days . ' days'), $payloadJson);
            $url = url('invitations/accept') . '?token=' . rawurlencode($token);
            $this->sendMail($tenantId, $user, $email, $url, $roleId, $days, $message);
            $this->auditService->log(AuditAction::INVITATION_SENT, $tenantId, (int) $user['id'], 'invitation', $id > 0 ? $id : null, null, $email);
            $links[] = ['email' => $email, 'url' => $url];
        }
        Session::flash('invitation_links', json_encode($links, JSON_UNESCAPED_SLASHES) ?: '[]');
        $msg = count($toSend) === 1
            ? 'L’invitation a été envoyée. La personne recevra un message avec un lien pour rejoindre l’unité.'
            : count($toSend) . ' invitations envoyées. Chaque personne recevra un message avec son lien.';
        if ($already !== []) {
            $msg .= ' Ignorées (déjà membres ou déjà invitées) : ' . implode(', ', array_slice($already, 0, 5)) . (count($already) > 5 ? '…' : '') . '.';
        }
        Session::flash('success', $msg);

        return Response::redirect($back . '?status=pending');
    }

    /** Renvoyer : nouveau lien par e-mail, validité repartie de zéro (invitation en attente ou expirée). */
    public function resend(Request $request, array $params = []): Response
    {
        return $this->rotate($request, true);
    }

    /** Nouveau lien à copier (sans e-mail) : réponse JSON pour le bouton « Copier le lien ». */
    public function link(Request $request, array $params = []): Response
    {
        return $this->rotate($request, false);
    }

    private function rotate(Request $request, bool $sendMail): Response
    {
        $json = str_contains((string) $request->header('Accept'), 'application/json');
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) $request->input('id');
        $returnStatus = $request->inputString('return_status');
        $fail = function (string $msg, int $code = 422) use ($json, $returnStatus): Response {
            if ($json) {
                return Response::json(['ok' => false, 'message' => $msg], $code);
            }
            Session::flash('error', $msg);

            return Response::redirect($this->listUrl($returnStatus));
        };
        if (!Csrf::validate($request->input('_csrf_token'))) {
            return $fail('Session expirée. Rechargez la page avant de réessayer.', 419);
        }
        $actor = $this->authService->user();
        $inv = $this->invitations->findByIdForTenant($id, $tenantId);
        if (!$actor || !$inv) {
            return $fail('Invitation introuvable.', 404);
        }
        if (!self::canAdmin() && (int) ($inv['invited_by_user_id'] ?? 0) !== (int) ($actor['id'] ?? 0)) {
            return $fail('Vous ne pouvez gérer que les invitations que vous avez envoyées.', 403);
        }
        $status = (string) ($inv['status'] ?? '');
        if (!in_array($status, ['pending', 'expired'], true)) {
            return $fail('Cette invitation a déjà été utilisée ou annulée : envoyez-en une nouvelle.');
        }
        if ($status === 'expired' && !$this->featureGate->canAddMember($tenantId)) {
            $cap = $this->featureGate->memberCapacity($tenantId);

            return $fail('Plafond de membres de votre formule atteint (' . $cap['used'] . ' / ' . $cap['limit'] . ').');
        }
        $payload = json_decode((string) ($inv['invitation_payload'] ?? ''), true);
        $payload = is_array($payload) ? $payload : [];
        $days = (int) ($payload['expiry_days'] ?? 7);
        $days = in_array($days, self::EXPIRY_DAYS, true) ? $days : 7;
        $token = bin2hex(random_bytes(32));
        $expires = new \DateTimeImmutable('+' . $days . ' days');
        if (!$this->invitations->rotateToken($id, $tenantId, hash('sha256', $token), $expires)) {
            return $fail('Impossible de renouveler cette invitation pour le moment.');
        }
        $url = url('invitations/accept') . '?token=' . rawurlencode($token);
        $email = (string) ($inv['email'] ?? '');
        if ($sendMail) {
            $this->sendMail($tenantId, $actor, $email, $url, (int) ($inv['role_id'] ?? 0), $days, (string) ($payload['message'] ?? ''));
            $this->auditService->log(AuditAction::INVITATION_SENT, $tenantId, (int) $actor['id'], 'invitation', $id, null, $email);
        }
        $msg = $sendMail
            ? 'Invitation renvoyée à ' . $email . ' : nouveau lien valable ' . $days . ' jour(s), l’ancien ne fonctionne plus.'
            : 'Nouveau lien copié : l’ancien lien envoyé par e-mail ne fonctionne plus.';
        if ($json) {
            return Response::json(['ok' => true, 'message' => $msg, 'url' => $url, 'expires_at' => $expires->format('Y-m-d H:i:s')]);
        }
        Session::flash('success', $msg);

        return Response::redirect($this->listUrl($returnStatus));
    }

    public function revoke(Request $request, array $params = []): Response
    {
        $returnStatus = $request->inputString('return_status');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($this->listUrl($returnStatus));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) $request->input('id');
        $inv = $this->invitations->findByIdForTenant($id, $tenantId);
        if (!$inv) {
            Session::flash('error', 'Invitation introuvable.');

            return Response::redirect($this->listUrl($returnStatus));
        }
        $actor = $this->authService->user();
        if (!self::canAdmin() && $actor && (int) ($inv['invited_by_user_id'] ?? 0) !== (int) ($actor['id'] ?? 0)) {
            Session::flash('error', 'Vous ne pouvez révoquer que les invitations que vous avez envoyées.');

            return Response::redirect($this->listUrl($returnStatus));
        }
        if ($this->invitations->markRevoked($id, $tenantId)) {
            $this->auditService->log(AuditAction::INVITATION_REVOKED, $tenantId, (int) ($actor['id'] ?? 0), 'invitation', $id, null, (string) ($inv['email'] ?? ''));
            Session::flash('success', 'L’invitation a été annulée. Le lien envoyé par e-mail ne fonctionnera plus.');
        } else {
            Session::flash('error', 'Impossible d’annuler cette invitation. Elle a peut-être déjà été utilisée ou retirée.');
        }

        return Response::redirect($this->listUrl($returnStatus));
    }

    /**
     * Adresses saisies (virgule, point-virgule, espace ou retour à la ligne), en minuscules, sans doublon.
     *
     * @return array{valid: list<string>, invalid: list<string>}
     */
    public static function parseEmails(string $raw): array
    {
        $valid = [];
        $invalid = [];
        foreach (preg_split('/[\s,;]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $email = strtolower(trim($part, " \t<>\"'"));
            if ($email === '') {
                continue;
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $invalid[] = $email;
            } elseif (!in_array($email, $valid, true)) {
                $valid[] = $email;
            }
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    private static function canAdmin(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization') || $gate->allows('admin.access');
    }

    private function listUrl(string $status = ''): string
    {
        $url = url('back-office/invitations');

        return in_array($status, self::STATUSES, true) ? $url . '?status=' . rawurlencode($status) : $url;
    }

    /**
     * Arrivée prévue (appliquée à l'acceptation) : unité, libellé d'affectation, fonction, grade d'entrée.
     *
     * @return array<string, mixed>
     */
    private function arrivalPayload(Request $request, int $tenantId): array
    {
        $payload = [];
        $unitId = (int) $request->input('unit_id', 0);
        if ($unitId > 0 && $this->unitRepository->findById($unitId, $tenantId)) {
            $label = trim((string) $request->input('assignment_label', ''));
            $payload['unit_id'] = $unitId;
            $payload['assignment_label'] = $label !== '' ? mb_substr($label, 0, 120) : 'Membre';
        }
        $jobRoleId = (int) $request->input('personnel_job_role_id', 0);
        if ($jobRoleId > 0 && $this->personnelJobRoleRepository->tablesExist() && $this->personnelJobRoleRepository->findRoleById($jobRoleId, $tenantId)) {
            $payload['personnel_job_role_id'] = $jobRoleId;
        }
        $gradeId = (int) $request->input('grade_id', 0);
        if ($gradeId > 0 && in_array($gradeId, array_map(static fn (array $g): int => (int) ($g['id'] ?? 0), $this->grades($tenantId)), true)) {
            $payload['grade_id'] = $gradeId;
        }

        return $payload;
    }

    /** @return list<array<string, mixed>> */
    private function grades(int $tenantId): array
    {
        try {
            return array_values(array_filter(
                (new GradeRepository())->listForTenant($tenantId),
                static fn (array $g): bool => (int) ($g['is_active'] ?? 1) === 1
            ));
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> $user */
    private function sendMail(int $tenantId, array $user, string $email, string $url, int $roleId, int $days, string $message): void
    {
        $tenant = $this->tenantRepository->findById($tenantId);
        $roleLabel = '';
        if ($roleId > 0) {
            $rr = $this->roleRepository->findById($roleId, $tenantId);
            $roleLabel = $rr ? trim((string) ($rr['name'] ?? '')) : '';
        }
        $inviterLabel = trim((string) ($user['display_name'] ?? '')) !== '' ? (string) $user['display_name'] : (string) ($user['email'] ?? '');
        $replyTo = (string) ($user['email'] ?? '');
        $this->emailService->sendCommunityInvitation(
            $email,
            (string) ($tenant['name'] ?? 'Communauté'),
            $url,
            $roleLabel,
            $inviterLabel,
            $tenantId,
            $replyTo !== '' ? $replyTo : null,
            $message,
            $days
        );
    }
}
