<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EnlistmentRepository;
use App\Repositories\RecruitmentInviteCodeRepository;
use App\Repositories\RecruitmentOpeningRepository;

/**
 * Gestion des codes d’invitation prioritaires (accélération des candidatures).
 * Distinct des invitations par e-mail et du code communauté.
 */
class RecruitmentInviteCodesController
{
    private const BASE_PATH = 'back-office/recruitments/codes-invitation';

    public function __construct(
        private RecruitmentInviteCodeRepository $inviteCodeRepository,
        private RecruitmentOpeningRepository $recruitmentOpeningRepository,
        private EnlistmentRepository $enlistmentRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        if (!$this->inviteCodeRepository->tablesExist()) {
            Session::flash('error', 'Les codes d’invitation prioritaires nécessitent une mise à jour de la base de données. Contactez l’équipe technique.');

            return Response::redirect(url('back-office/organization'));
        }

        $showAll = (string) $request->query('all', '0') === '1';
        $codes = $this->inviteCodeRepository->listForTenant(
            (int) $tenantId,
            !$showAll,
            RecruitmentInviteCodeRepository::KIND_PRIORITY
        );

        $enlistmentCounts = $this->enlistmentRepository->countsByStatusForTenant((int) $tenantId);

        return Response::view('layout.recruitment_lms', [
            'content' => 'admin.organization.recruitment_invite_codes.index',
            'title' => 'Codes d’invitation prioritaires',
            'recruitmentLmsTitle' => 'Codes d’invitation prioritaires',
            'inviteCodes' => $codes,
            'showAll' => $showAll,
            'recruitmentSidebarCounts' => $enlistmentCounts,
            'recruitmentAdminNav' => 'invite_codes',
            'showPortalFooter' => false,
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        if (!$this->inviteCodeRepository->tablesExist()) {
            Session::flash('error', 'Les codes d’invitation prioritaires nécessitent une mise à jour de la base de données.');

            return Response::redirect(url('back-office/organization'));
        }

        $openings = [];
        if ($this->recruitmentOpeningRepository->tablesExist()) {
            $openings = $this->recruitmentOpeningRepository->listPublishedForTenant((int) $tenantId);
        }

        $enlistmentCounts = $this->enlistmentRepository->countsByStatusForTenant((int) $tenantId);

        return Response::view('layout.recruitment_lms', [
            'content' => 'admin.organization.recruitment_invite_codes.create',
            'title' => 'Créer un code d’invitation prioritaire',
            'recruitmentLmsTitle' => 'Nouveau code prioritaire',
            'recruitmentOpenings' => $openings,
            'recruitmentSidebarCounts' => $enlistmentCounts,
            'recruitmentAdminNav' => 'invite_codes',
            'showPortalFooter' => false,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId || !$request->isPost()) {
            return Response::redirect(url(self::BASE_PATH));
        }

        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::BASE_PATH));
        }

        if (!$this->inviteCodeRepository->tablesExist()) {
            Session::flash('error', 'Cette fonctionnalité n’est pas encore disponible.');

            return Response::redirect(url(self::BASE_PATH));
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        $label = trim((string) $request->input('label', ''));
        $maxUses = (int) $request->input('max_uses', 0);
        $expiresAt = trim((string) $request->input('expires_at', ''));
        $autoAccept = (string) $request->input('auto_accept', '0') === '1';
        $openingId = (int) $request->input('assign_to_opening_id', 0);
        $specialty = trim((string) $request->input('default_specialty', ''));

        if ($code !== '' && !preg_match('/^[A-Z0-9\-_]{3,64}$/', $code)) {
            Session::flash('error', 'Le code doit contenir uniquement des lettres majuscules, des chiffres, des tirets et des underscores (3 à 64 caractères).');

            return Response::redirect(url(self::BASE_PATH . '/creer'));
        }

        if ($code !== '' && $this->inviteCodeRepository->findByCode((int) $tenantId, $code) !== null) {
            Session::flash('error', 'Ce code existe déjà pour votre communauté. Choisissez-en un autre.');

            return Response::redirect(url(self::BASE_PATH . '/creer'));
        }

        if ($label === '') {
            Session::flash('error', 'Donnez un nom à ce code pour le retrouver facilement dans la liste.');

            return Response::redirect(url(self::BASE_PATH . '/creer'));
        }

        $data = [
            'code' => $code !== '' ? $code : null,
            'label' => $label,
            'code_kind' => RecruitmentInviteCodeRepository::KIND_PRIORITY,
            'max_uses' => $maxUses > 0 ? $maxUses : null,
            'expires_at' => $expiresAt !== '' ? $expiresAt : null,
            'auto_accept' => $autoAccept,
            'assign_to_opening_id' => $openingId > 0 ? $openingId : null,
            'default_specialty' => $specialty !== '' ? $specialty : null,
            'created_by' => (int) Session::get('user_id'),
        ];

        $id = $this->inviteCodeRepository->create((int) $tenantId, $data);

        if ($id < 1) {
            Session::flash('error', 'Impossible de créer le code d’invitation prioritaire.');

            return Response::redirect(url(self::BASE_PATH . '/creer'));
        }

        Session::flash('success', 'Code d’invitation prioritaire créé.');

        return Response::redirect(url(self::BASE_PATH . '/' . $id));
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::redirect(url(self::BASE_PATH));
        }

        if (!$this->inviteCodeRepository->tablesExist()) {
            Session::flash('error', 'Cette fonctionnalité n’est pas encore disponible.');

            return Response::redirect(url(self::BASE_PATH));
        }

        $code = $this->inviteCodeRepository->findById(
            (int) $tenantId,
            $id,
            RecruitmentInviteCodeRepository::KIND_PRIORITY
        );
        if ($code === null) {
            Session::flash('error', 'Code d’invitation prioritaire introuvable.');

            return Response::redirect(url(self::BASE_PATH));
        }

        $stats = $this->inviteCodeRepository->getCodeStatistics((int) $tenantId, $id);
        $isValid = $this->inviteCodeRepository->isPriorityCodeValid((int) $tenantId, (string) $code['code']);

        $linkedOpening = null;
        $openingId = (int) ($code['assign_to_opening_id'] ?? 0);
        if ($openingId > 0 && $this->recruitmentOpeningRepository->tablesExist()) {
            $linkedOpening = $this->recruitmentOpeningRepository->findByIdForTenant($openingId, (int) $tenantId);
        }

        $enlistmentCounts = $this->enlistmentRepository->countsByStatusForTenant((int) $tenantId);

        return Response::view('layout.recruitment_lms', [
            'content' => 'admin.organization.recruitment_invite_codes.show',
            'title' => 'Code d’invitation prioritaire',
            'recruitmentLmsTitle' => $code['label'] ?? 'Code prioritaire',
            'inviteCode' => $code,
            'inviteCodeStats' => $stats,
            'inviteCodeValid' => $isValid,
            'linkedRecruitmentOpening' => $linkedOpening,
            'recruitmentSidebarCounts' => $enlistmentCounts,
            'recruitmentAdminNav' => 'invite_codes',
            'showPortalFooter' => false,
        ]);
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::redirect(url(self::BASE_PATH));
        }

        if (!$this->inviteCodeRepository->tablesExist()) {
            Session::flash('error', 'Cette fonctionnalité n’est pas encore disponible.');

            return Response::redirect(url(self::BASE_PATH));
        }

        $code = $this->inviteCodeRepository->findById(
            (int) $tenantId,
            $id,
            RecruitmentInviteCodeRepository::KIND_PRIORITY
        );
        if ($code === null) {
            Session::flash('error', 'Code d’invitation prioritaire introuvable.');

            return Response::redirect(url(self::BASE_PATH));
        }

        $openings = [];
        if ($this->recruitmentOpeningRepository->tablesExist()) {
            $openings = $this->recruitmentOpeningRepository->listPublishedForTenant((int) $tenantId);
        }

        $enlistmentCounts = $this->enlistmentRepository->countsByStatusForTenant((int) $tenantId);

        return Response::view('layout.recruitment_lms', [
            'content' => 'admin.organization.recruitment_invite_codes.edit',
            'title' => 'Modifier le code prioritaire',
            'recruitmentLmsTitle' => 'Modifier : ' . ($code['label'] ?? 'Code'),
            'inviteCode' => $code,
            'recruitmentOpenings' => $openings,
            'recruitmentSidebarCounts' => $enlistmentCounts,
            'recruitmentAdminNav' => 'invite_codes',
            'showPortalFooter' => false,
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId || !$request->isPost()) {
            return Response::redirect(url(self::BASE_PATH));
        }

        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::redirect(url(self::BASE_PATH));
        }

        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::BASE_PATH . '/' . $id . '/modifier'));
        }

        if (!$this->inviteCodeRepository->tablesExist()) {
            Session::flash('error', 'Cette fonctionnalité n’est pas encore disponible.');

            return Response::redirect(url(self::BASE_PATH));
        }

        $code = $this->inviteCodeRepository->findById(
            (int) $tenantId,
            $id,
            RecruitmentInviteCodeRepository::KIND_PRIORITY
        );
        if ($code === null) {
            Session::flash('error', 'Code d’invitation prioritaire introuvable.');

            return Response::redirect(url(self::BASE_PATH));
        }

        $data = [
            'label' => trim((string) $request->input('label', '')),
            'max_uses' => (int) $request->input('max_uses', 0) > 0 ? (int) $request->input('max_uses', 0) : null,
            'expires_at' => trim((string) $request->input('expires_at', '')) ?: null,
            'auto_accept' => (string) $request->input('auto_accept', '0') === '1',
            'assign_to_opening_id' => (int) $request->input('assign_to_opening_id', 0) > 0 ? (int) $request->input('assign_to_opening_id', 0) : null,
            'default_specialty' => trim((string) $request->input('default_specialty', '')) ?: null,
        ];

        if ($data['label'] === '') {
            Session::flash('error', 'Donnez un nom à ce code pour le retrouver facilement dans la liste.');

            return Response::redirect(url(self::BASE_PATH . '/' . $id . '/modifier'));
        }

        $ok = $this->inviteCodeRepository->update((int) $tenantId, $id, $data);

        if (!$ok) {
            Session::flash('error', 'Impossible de mettre à jour le code d’invitation prioritaire.');

            return Response::redirect(url(self::BASE_PATH . '/' . $id . '/modifier'));
        }

        Session::flash('success', 'Code d’invitation prioritaire mis à jour.');

        return Response::redirect(url(self::BASE_PATH . '/' . $id));
    }

    public function delete(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId || !$request->isPost()) {
            return Response::redirect(url(self::BASE_PATH));
        }

        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::redirect(url(self::BASE_PATH));
        }

        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::BASE_PATH . '/' . $id));
        }

        if (!$this->inviteCodeRepository->tablesExist()) {
            Session::flash('error', 'Cette fonctionnalité n’est pas encore disponible.');

            return Response::redirect(url(self::BASE_PATH));
        }

        $code = $this->inviteCodeRepository->findById(
            (int) $tenantId,
            $id,
            RecruitmentInviteCodeRepository::KIND_PRIORITY
        );
        if ($code === null) {
            Session::flash('error', 'Code d’invitation prioritaire introuvable.');

            return Response::redirect(url(self::BASE_PATH));
        }

        $ok = $this->inviteCodeRepository->delete((int) $tenantId, $id);

        if (!$ok) {
            Session::flash('error', 'Impossible de désactiver le code d’invitation prioritaire.');

            return Response::redirect(url(self::BASE_PATH . '/' . $id));
        }

        Session::flash('success', 'Code d’invitation prioritaire désactivé.');

        return Response::redirect(url(self::BASE_PATH));
    }
}
