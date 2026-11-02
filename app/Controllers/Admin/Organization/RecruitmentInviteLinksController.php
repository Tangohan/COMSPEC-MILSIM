<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EnlistmentRepository;
use App\Repositories\GradeRepository;
use App\Repositories\InviteLinkRepository;
use App\Repositories\RecruitmentInviteCodeRepository;
use App\Repositories\RecruitmentOpeningRepository;
use App\Repositories\UnitRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Community\TenantCommunityProfileService;
use App\Services\Qr\QrPngGenerator;
use App\Services\Recruitment\InviteLinkService;

/**
 * Back-office › Recrutement › Liens d'invitation : liens partageables /i/<code> (façon Discord) pour candidater ou
 * rejoindre la communauté. Chaque lien a son nom interne, son échéance, son plafond d'utilisations, son action,
 * et se révoque ou se réactive. Toutes les opérations sont bornées à la communauté de la session.
 */
final class RecruitmentInviteLinksController
{
    private const BASE_PATH = 'back-office/recruitments/liens-invitation';

    public function __construct(
        private InviteLinkRepository $links,
        private InviteLinkService $service,
        private RecruitmentOpeningRepository $openings,
        private RecruitmentInviteCodeRepository $priorityCodes,
        private UnitRepository $units,
        private EnlistmentRepository $enlistments,
        private AuditService $audit,
        private QrPngGenerator $qr,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!$this->links->tablesExist()) {
            return $this->notInstalled();
        }

        return $this->page('admin.organization.recruitment_invite_links.index', 'Liens d’invitation', [
            'inviteLinks' => $this->links->listForTenant($tenantId),
            'inviteLinkStats' => $this->links->statsByLinkForTenant($tenantId),
            'inviteLinkQuota' => $this->quota($tenantId),
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!$this->links->tablesExist()) {
            return $this->notInstalled();
        }
        $quota = $this->quota($tenantId);
        if (!$quota['can_create']) {
            Session::flash('error', $this->quotaMessage($quota['max']));

            return Response::redirect(url(self::BASE_PATH));
        }
        $old = Session::getFlash('invite_link_old');

        return $this->page('admin.organization.recruitment_invite_links.create', 'Nouveau lien d’invitation', $this->formData($tenantId) + [
            'inviteLinkQuota' => $quota,
            'inviteLinkOld' => is_array($old) ? $old : [],
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1 || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::BASE_PATH));
        }
        if (!$this->links->tablesExist()) {
            return $this->notInstalled();
        }
        if (!$this->service->canActivateAnother($tenantId, $this->links)) {
            Session::flash('error', $this->quotaMessage($this->service->maxActiveLinks($tenantId)));

            return Response::redirect(url(self::BASE_PATH));
        }
        $back = url(self::BASE_PATH . '/creer');
        $data = $this->readForm($request, $tenantId);
        if (is_string($data)) {
            Session::flash('error', $data);
            Session::flash('invite_link_old', $this->oldInput($request));

            return Response::redirect($back);
        }

        $slugRaw = trim((string) $request->input('custom_slug', ''));
        if ($slugRaw !== '') {
            if (!$this->service->customSlugAllowed($tenantId)) {
                Session::flash('error', 'L’adresse personnalisée est réservée aux formules payantes. Laissez le champ vide pour une adresse générée.');
                Session::flash('invite_link_old', $this->oldInput($request));

                return Response::redirect($back);
            }
            $code = InviteLinkService::normalizeCustomSlug($slugRaw);
            if ($code === null) {
                Session::flash('error', 'Adresse personnalisée invalide : 3 à 40 caractères, lettres minuscules, chiffres et tirets (pas au début ni à la fin).');
                Session::flash('invite_link_old', $this->oldInput($request));

                return Response::redirect($back);
            }
            if ($this->links->publicCodeTaken($code)) {
                Session::flash('error', 'Cette adresse est déjà prise par un autre lien. Choisissez-en une autre.');
                Session::flash('invite_link_old', $this->oldInput($request));

                return Response::redirect($back);
            }
        } else {
            $code = InviteLinkService::generateCode(fn (string $c): bool => $this->links->publicCodeTaken($c));
        }

        try {
            $id = $this->links->create($tenantId, $data + [
                'public_code' => $code,
                'created_by' => (int) Session::get('user_id'),
            ]);
        } catch (\Throwable $e) {
            error_log('[invite_link.create] ' . $e->getMessage());
            $id = 0;
        }
        if ($id < 1) {
            Session::flash('error', 'Le lien n’a pas pu être créé. Réessayez.');
            Session::flash('invite_link_old', $this->oldInput($request));

            return Response::redirect($back);
        }
        $this->audit->log(AuditAction::INVITE_LINK_CREATED, $tenantId, (int) Session::get('user_id'), 'invite_link', $id, null, $code);
        Session::flash('success', 'Lien d’invitation créé. Copiez-le ou partagez son QR code.');

        return Response::redirect(url(self::BASE_PATH . '/' . $id));
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $link = $this->findOrNull($tenantId, $params);
        if ($link === null) {
            return $this->missing();
        }
        $stats = $this->links->statsByLinkForTenant($tenantId)[$link['id']] ?? InviteLinkRepository::emptyStats();

        return $this->page('admin.organization.recruitment_invite_links.show', (string) ($link['label'] ?? 'Lien d’invitation'), [
            'inviteLink' => $link,
            'inviteLinkStats' => $stats,
            'inviteLinkUses' => $this->links->recentUsesForLink($tenantId, (int) $link['id']),
            'inviteLinkSummary' => $this->summary($tenantId, $link),
            'inviteLinkQuota' => $this->quota($tenantId),
        ]);
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $link = $this->findOrNull($tenantId, $params);
        if ($link === null) {
            return $this->missing();
        }

        return $this->page('admin.organization.recruitment_invite_links.edit', 'Modifier : ' . (string) ($link['label'] ?? ''), $this->formData($tenantId) + [
            'inviteLink' => $link,
            'inviteLinkQuota' => $this->quota($tenantId),
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $link = $this->findOrNull($tenantId, $params);
        if ($link === null) {
            return $this->missing();
        }
        $id = (int) $link['id'];
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::BASE_PATH . '/' . $id . '/modifier'));
        }
        $keepExpiry = (string) $request->input('expiry', '') === 'keep';
        $data = $this->readForm($request, $tenantId, $keepExpiry ? (string) ($link['expires_at'] ?? '') : null);
        if (is_string($data)) {
            Session::flash('error', $data);

            return Response::redirect(url(self::BASE_PATH . '/' . $id . '/modifier'));
        }
        // Un lien inactif rendu utilisable par la modification (échéance repoussée, plafond relevé) compte dans la limite.
        $wasActive = InviteLinkService::status($link) === InviteLinkService::STATUS_ACTIVE;
        $willBeActive = InviteLinkService::status(array_merge($link, ['expires_at' => $data['expires_at'], 'max_uses' => $data['max_uses']])) === InviteLinkService::STATUS_ACTIVE;
        if (!$wasActive && $willBeActive && !$this->service->canActivateAnother($tenantId, $this->links)) {
            Session::flash('error', $this->quotaMessage($this->service->maxActiveLinks($tenantId)));

            return Response::redirect(url(self::BASE_PATH . '/' . $id . '/modifier'));
        }
        if (!$this->links->update($tenantId, $id, $data)) {
            Session::flash('error', 'Le lien n’a pas pu être modifié. Réessayez.');

            return Response::redirect(url(self::BASE_PATH . '/' . $id . '/modifier'));
        }
        $this->audit->log(AuditAction::INVITE_LINK_UPDATED, $tenantId, (int) Session::get('user_id'), 'invite_link', $id, null, (string) $link['public_code']);
        Session::flash('success', 'Lien d’invitation mis à jour. L’adresse ne change pas.');

        return Response::redirect(url(self::BASE_PATH . '/' . $id));
    }

    public function revoke(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $link = $this->findOrNull($tenantId, $params);
        if ($link === null) {
            return $this->missing();
        }
        $id = (int) $link['id'];
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::BASE_PATH . '/' . $id));
        }
        if ($this->links->revoke($tenantId, $id)) {
            $this->audit->log(AuditAction::INVITE_LINK_REVOKED, $tenantId, (int) Session::get('user_id'), 'invite_link', $id, null, (string) $link['public_code']);
            Session::flash('success', 'Lien révoqué : il ne fonctionne plus. Vous pourrez le réactiver.');
        } else {
            Session::flash('error', 'Le lien n’a pas pu être révoqué. Réessayez.');
        }

        return Response::redirect(url(self::BASE_PATH . '/' . $id));
    }

    public function reactivate(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $link = $this->findOrNull($tenantId, $params);
        if ($link === null) {
            return $this->missing();
        }
        $id = (int) $link['id'];
        $back = url(self::BASE_PATH . '/' . $id);
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $expiresAt = InviteLinkService::expiresAtFromInput((string) $request->input('expiry', '7d'), (string) $request->input('expires_at', ''));
        if ($expiresAt === false) {
            Session::flash('error', 'Choisissez une durée de validité, ou une date à venir.');

            return Response::redirect($back);
        }
        $after = array_merge($link, ['revoked_at' => null, 'expires_at' => $expiresAt]);
        if (InviteLinkService::status($after) === InviteLinkService::STATUS_EXHAUSTED) {
            Session::flash('error', 'Ce lien a atteint son nombre maximum d’utilisations : relevez-le dans « Modifier » avant de le réactiver.');

            return Response::redirect($back);
        }
        if (!$this->service->canActivateAnother($tenantId, $this->links)) {
            Session::flash('error', $this->quotaMessage($this->service->maxActiveLinks($tenantId)));

            return Response::redirect($back);
        }
        if ($this->links->reactivate($tenantId, $id, $expiresAt)) {
            $this->audit->log(AuditAction::INVITE_LINK_REACTIVATED, $tenantId, (int) Session::get('user_id'), 'invite_link', $id, null, (string) $link['public_code']);
            Session::flash('success', 'Lien réactivé : la même adresse fonctionne de nouveau.');
        } else {
            Session::flash('error', 'Le lien n’a pas pu être réactivé. Réessayez.');
        }

        return Response::redirect($back);
    }

    public function qr(Request $request, array $params = []): Response
    {
        $link = $this->findOrNull($this->tenantId(), $params);
        if ($link === null) {
            return (new Response())->setStatusCode(404)->setBody('');
        }
        $png = $this->qr->png(InviteLinkService::publicUrl((string) $link['public_code']), 480, 16);
        if ($png === null) {
            return (new Response())->setStatusCode(503)->setBody('');
        }
        $download = (string) $request->query('telecharger', '') === '1';
        $response = (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', $png['mime'])
            ->header('Cache-Control', 'private, max-age=600')
            ->setBody($png['body']);
        if ($download) {
            $response->header('Content-Disposition', 'attachment; filename="invitation-' . preg_replace('/[^a-z0-9-]/', '', (string) $link['public_code']) . '.png"');
        }

        return $response;
    }

    /**
     * Réglages lus du formulaire, ou message d'erreur.
     *
     * @param string|null $keptExpiry échéance actuelle conservée (modification sans changer la durée)
     * @return array{label: string, link_action: string, requires_approval: bool, max_uses: ?int, expires_at: ?string, opening_id: ?int, metadata: array<string, mixed>}|string
     */
    private function readForm(Request $request, int $tenantId, ?string $keptExpiry = null): array|string
    {
        $label = trim((string) $request->input('label', ''));
        if ($label === '') {
            return 'Donnez un nom à ce lien pour le retrouver dans la liste (il n’est pas montré aux invités).';
        }
        $action = (string) $request->input('link_action', InviteLinkRepository::ACTION_APPLY) === InviteLinkRepository::ACTION_JOIN
            ? InviteLinkRepository::ACTION_JOIN
            : InviteLinkRepository::ACTION_APPLY;
        if ($keptExpiry !== null) {
            $expiresAt = $keptExpiry !== '' ? $keptExpiry : null;
        } else {
            $expiresAt = InviteLinkService::expiresAtFromInput((string) $request->input('expiry', 'never'), (string) $request->input('expires_at', ''));
            if ($expiresAt === false) {
                return 'Choisissez une durée de validité, ou une date à venir.';
            }
        }
        $maxUses = (int) $request->input('max_uses', 0);
        if ($maxUses < 0 || $maxUses > 100000) {
            return 'Le nombre maximum d’utilisations doit être compris entre 1 et 100 000 (vide = illimité).';
        }

        $openingId = (int) $request->input('opening_id', 0);
        if ($openingId > 0 && ($this->openings->tablesExist() ? $this->openings->findByIdForTenant($openingId, $tenantId) : null) === null) {
            $openingId = 0;
        }
        $unitId = (int) $request->input('unit_id', 0);
        if ($unitId > 0 && $this->units->findById($unitId, $tenantId) === null) {
            $unitId = 0;
        }
        $metadata = ['unit_id' => $unitId];

        if ($action === InviteLinkRepository::ACTION_APPLY) {
            $mode = (string) $request->input('registration_mode', '');
            $metadata['registration_mode'] = in_array($mode, TenantCommunityProfileService::allowedRegistrationModes(), true) ? $mode : '';
            $priorityId = (int) $request->input('priority_code_id', 0);
            if ($priorityId > 0 && ($this->priorityCodes->tablesExist() ? $this->priorityCodes->findById($tenantId, $priorityId, RecruitmentInviteCodeRepository::KIND_PRIORITY) : null) === null) {
                $priorityId = 0;
            }
            $metadata['priority_code_id'] = $priorityId;
            $requiresApproval = false;
        } else {
            // Sans le droit d'ajouter des membres, l'adhésion passe toujours par la validation de l'équipe.
            $requiresApproval = !self::canGrantDirectJoin() || (string) $request->input('requires_approval', '0') === '1';
            $gradeId = self::canGrantDirectJoin() ? (int) $request->input('grade_id', 0) : 0;
            if ($gradeId > 0 && !in_array($gradeId, array_map(static fn (array $g): int => (int) ($g['id'] ?? 0), $this->grades($tenantId)), true)) {
                $gradeId = 0;
            }
            $metadata['grade_id'] = $gradeId;
        }

        return [
            'label' => $label,
            'link_action' => $action,
            'requires_approval' => $requiresApproval,
            'max_uses' => $maxUses > 0 ? $maxUses : null,
            'expires_at' => $expiresAt,
            'opening_id' => $openingId > 0 ? $openingId : null,
            'metadata' => $metadata,
        ];
    }

    /** Ajout direct de membres : mêmes droits que l'envoi d'invitations par e-mail. */
    public static function canGrantDirectJoin(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('invitations.send');
    }

    /** @return array<string, mixed> */
    private function formData(int $tenantId): array
    {
        $priority = [];
        if ($this->priorityCodes->tablesExist()) {
            foreach ($this->priorityCodes->listForTenant($tenantId, true) as $row) {
                $priority[] = ['id' => (int) $row['id'], 'label' => trim((string) ($row['label'] ?? '')) ?: (string) $row['code']];
            }
        }

        return [
            'recruitmentOpenings' => $this->openings->tablesExist() ? $this->openings->listPublishedForTenant($tenantId) : [],
            'inviteLinkUnits' => $this->units->allForTenant($tenantId),
            'inviteLinkGrades' => $this->grades($tenantId),
            'inviteLinkPriorityCodes' => $priority,
            'inviteLinkCanDirectJoin' => self::canGrantDirectJoin(),
            'inviteLinkCustomSlug' => $this->service->customSlugAllowed($tenantId),
        ];
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

    /**
     * Lignes « réglage / valeur » de la fiche.
     *
     * @param array<string, mixed> $link
     * @return list<array{0: string, 1: string}>
     */
    private function summary(int $tenantId, array $link): array
    {
        $meta = $link['metadata'];
        $isApply = (string) $link['link_action'] === InviteLinkRepository::ACTION_APPLY;
        $rows = [['Action', $isApply ? 'Candidature au recrutement' : ($link['requires_approval'] ? 'Adhésion après validation de l’équipe' : 'Adhésion directe')]];
        if ($isApply) {
            $mode = (string) ($meta['registration_mode'] ?? '');
            $rows[] = ['Type de candidature', $mode !== '' ? TenantCommunityProfileService::registrationModeLabel($mode) : 'Celui de la communauté'];
            $priorityId = (int) ($meta['priority_code_id'] ?? 0);
            if ($priorityId > 0 && $this->priorityCodes->tablesExist()) {
                $p = $this->priorityCodes->findById($tenantId, $priorityId, RecruitmentInviteCodeRepository::KIND_PRIORITY);
                if ($p !== null) {
                    $rows[] = ['Code prioritaire appliqué', trim((string) ($p['label'] ?? '')) ?: (string) $p['code']];
                }
            }
        } else {
            $gradeId = (int) ($meta['grade_id'] ?? 0);
            $gradeName = 'Grade d’entrée des recrues';
            foreach ($gradeId > 0 ? $this->grades($tenantId) : [] as $g) {
                if ((int) ($g['id'] ?? 0) === $gradeId) {
                    $gradeName = (string) ($g['label_long'] ?? $g['label_short'] ?? $gradeName);
                }
            }
            $rows[] = ['Grade à l’arrivée', $gradeName];
        }
        $openingId = (int) ($link['assign_to_opening_id'] ?? 0);
        if ($openingId > 0 && $this->openings->tablesExist()) {
            $o = $this->openings->findByIdForTenant($openingId, $tenantId);
            if ($o) {
                $rows[] = ['Offre de recrutement', (string) ($o['title'] ?? '')];
            }
        }
        $unitId = (int) ($meta['unit_id'] ?? 0);
        if ($unitId > 0) {
            $u = $this->units->findById($unitId, $tenantId);
            if ($u) {
                $rows[] = [$isApply ? 'Unité visée' : 'Unité d’affectation', (string) ($u['name'] ?? '')];
            }
        }

        return $rows;
    }

    /** @return array{max: int, active: int, can_create: bool, custom_slug: bool} */
    private function quota(int $tenantId): array
    {
        $max = $this->service->maxActiveLinks($tenantId);
        $active = $this->links->countActiveForTenant($tenantId);

        return [
            'max' => $max,
            'active' => $active,
            'can_create' => $max === 0 || $active < $max,
            'custom_slug' => $this->service->customSlugAllowed($tenantId),
        ];
    }

    private function quotaMessage(int $max): string
    {
        return 'Votre formule permet ' . $max . ' liens actifs à la fois. Révoquez un lien inutile ou passez à une formule supérieure pour en ouvrir davantage.';
    }

    private function tenantId(): int
    {
        return (int) (Session::get('tenant_id') ?? 0);
    }

    /** @return array<string, mixed>|null */
    private function findOrNull(int $tenantId, array $params): ?array
    {
        $id = (int) ($params['id'] ?? 0);

        return $tenantId > 0 && $id > 0 ? $this->links->findById($tenantId, $id) : null;
    }

    private function missing(): Response
    {
        Session::flash('error', 'Lien d’invitation introuvable.');

        return Response::redirect(url(self::BASE_PATH));
    }

    private function notInstalled(): Response
    {
        Session::flash('error', 'Les liens d’invitation nécessitent une mise à jour de la base de données. Contactez l’équipe technique.');

        return Response::redirect(url('back-office/recruitments'));
    }

    /** @return array<string, string> */
    private function oldInput(Request $request): array
    {
        $out = [];
        foreach (['label', 'custom_slug', 'link_action', 'registration_mode', 'opening_id', 'unit_id', 'priority_code_id', 'grade_id', 'requires_approval', 'expiry', 'expires_at', 'max_uses'] as $k) {
            $out[$k] = mb_substr(trim((string) $request->input($k, '')), 0, 200);
        }

        return $out;
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, string $title, array $data): Response
    {
        return Response::view('layout.recruitment_lms', $data + [
            'content' => $view,
            'title' => $title,
            'recruitmentLmsTitle' => $title,
            'recruitmentSidebarCounts' => $this->enlistments->countsByStatusForTenant($this->tenantId()),
            'recruitmentAdminNav' => 'invite_links',
            'showPortalFooter' => false,
        ]);
    }
}
