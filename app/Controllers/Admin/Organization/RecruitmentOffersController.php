<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\RecruitmentOpeningRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Services\Recruitment\RecruitmentOpeningForumPublisher;
use App\Services\Recruitment\RecruitmentOpeningPresentation;
use App\Services\Recruitment\RecruitmentOpeningReferenceService;
use App\Services\Recruitment\TenantRecruitmentSettings;

class RecruitmentOffersController
{
    public function __construct(
        private RecruitmentOpeningRepository $openings,
        private UnitRepository $unitRepository,
        private TenantRepository $tenantRepository,
        private PersonnelJobRoleRepository $jobRoleRepository,
        private RecruitmentOpeningForumPublisher $recruitmentForumPublisher,
        private UserRepository $userRepository,
        private EmailService $emailService,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository,
        private RecruitmentOpeningReferenceService $referenceService = new RecruitmentOpeningReferenceService()
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!$this->openings->tablesExist()) {
            Session::flash('error', 'Fonctionnalité non disponible sur cette base (migration à exécuter).');

            return Response::redirect(url('dashboard'));
        }
        $st = trim((string) $request->query('status', 'all'));

        return Response::view('layout.main', [
            'title' => 'Offres publiées',
            'content' => 'admin.organization.recruitment_offers.index',
            'openings' => $this->openings->listForTenantAdmin($tenantId, $st === 'all' ? null : $st),
            'statusFilter' => $st,
            'statusLabels' => RecruitmentOpeningPresentation::statusLabels(),
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        return $this->formCreateEdit(null);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/recruitment/offers/create'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        $data = $this->collectFormData($request);
        if ($data['title'] === '' || $data['unit_id'] < 1) {
            Session::flash('error', 'Le titre et l’unité sont obligatoires.');

            return Response::redirect(url('back-office/recruitment/offers/create'));
        }
        $unit = $this->unitRepository->findById($data['unit_id'], $tenantId);
        if (!$unit) {
            Session::flash('error', 'Unité invalide.');

            return Response::redirect(url('back-office/recruitment/offers/create'));
        }
        $id = $this->openings->create($tenantId, $userId, $data);
        Session::flash('success', 'Brouillon enregistré. Vous pouvez le publier quand il est prêt.');

        return Response::redirect(url('back-office/recruitment/offers/' . $id . '/edit'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        $row = $this->openings->findByIdForTenant($id, $tenantId);
        if (!$row) {
            Session::flash('error', 'Offre introuvable.');

            return Response::redirect(url('back-office/recruitment/offers'));
        }

        return $this->formCreateEdit($row);
    }

    public function update(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/recruitment/offers'));
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        $row = $this->openings->findByIdForTenant($id, $tenantId);
        if (!$row || ($row['status'] ?? '') !== 'draft') {
            Session::flash('error', 'Seuls les brouillons sont modifiables.');

            return Response::redirect(url('back-office/recruitment/offers'));
        }
        $data = $this->collectFormData($request);
        if ($data['title'] === '' || $data['unit_id'] < 1) {
            Session::flash('error', 'Le titre et l’unité sont obligatoires.');

            return Response::redirect(url('back-office/recruitment/offers/' . $id . '/edit'));
        }
        $unit = $this->unitRepository->findById($data['unit_id'], $tenantId);
        if (!$unit) {
            Session::flash('error', 'Unité invalide.');

            return Response::redirect(url('back-office/recruitment/offers/' . $id . '/edit'));
        }
        $this->openings->update($id, $tenantId, $data);
        Session::flash('success', 'Modifications enregistrées.');

        return Response::redirect(url('back-office/recruitment/offers/' . $id . '/edit'));
    }

    public function publish(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/recruitment/offers'));
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return Response::redirect(url('dashboard'));
        }
        $row = $this->openings->findByIdForTenant($id, $tenantId);
        if (!$row || ($row['status'] ?? '') !== 'draft') {
            Session::flash('error', 'Publication impossible.');

            return Response::redirect(url('back-office/recruitment/offers'));
        }
        $unit = $this->unitRepository->findById((int) $row['unit_id'], $tenantId);
        if (!$unit) {
            Session::flash('error', 'Unité introuvable.');

            return Response::redirect(url('back-office/recruitment/offers'));
        }
        $settings = $this->tenantRepository->getSettings($tenantId);
        $ok = $this->openings->publish($id, $tenantId, $tenant, $settings, $unit);
        $msg = $ok ? 'Offre publiée. Elle apparaît sur la vitrine.' : 'Échec de la publication.';
        if ($ok) {
            $published = $this->openings->findByIdForTenant($id, $tenantId);
            if ($published) {
                $this->notifyStaffRecruitmentOpeningPublished($tenantId, $tenant, $published);
            }
            $wantExterne = $request->input('forum_annonce_generale') === '1';
            $wantInterne = $request->input('forum_annonce_organisation') === '1';
            $uid = (int) Session::get('user_id');
            $forumNotes = $this->recruitmentForumPublisher->publishForumThreads(
                $tenantId,
                $uid,
                $id,
                $tenant,
                $wantExterne,
                $wantInterne
            );
            if ($forumNotes !== []) {
                $msg .= ' ' . implode(' ', $forumNotes);
            }
        }
        Session::flash($ok ? 'success' : 'error', $msg);

        return Response::redirect(url('back-office/recruitment/offers'));
    }

    public function close(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/recruitment/offers'));
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        $ok = $this->openings->close($id, $tenantId);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Offre clôturée.' : 'Clôture impossible.');

        return Response::redirect(url('back-office/recruitment/offers'));
    }

    public function referenceFormat(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $settings = $this->tenantRepository->getSettings($tenantId);
        $rec = TenantRecruitmentSettings::getRecruitmentBlock($settings);
        $fmt = TenantRecruitmentSettings::mergeReferenceFormat($rec, []);
        $docRef = trim((string) ($rec['prospection_document_ref'] ?? ''));

        $tenantRow = $this->tenantRepository->findById($tenantId) ?? [];
        $units = $this->unitRepository->allForTenant($tenantId);
        $previewUnit = $units[0] ?? ['name' => '', 'slug' => '', 'code' => ''];
        $previewUnitLabel = trim((string) ($previewUnit['name'] ?? ''));
        if ($previewUnitLabel === '') {
            $previewUnitLabel = 'Aucune unité enregistrée';
        }
        $year = (int) date('Y');
        $lastSeq = $this->openings->tablesExist() ? $this->openings->currentLastSeq($tenantId, $year) : 0;
        $previewSeq = $lastSeq < 1 ? 1 : $lastSeq + 1;
        $previewRef = $this->referenceService->buildReference($fmt, $tenantRow, $previewUnit, $year, $previewSeq);

        return Response::view('layout.main', [
            'title' => 'Format des références des offres',
            'content' => 'admin.organization.recruitment_offers.reference_format',
            'format' => $fmt,
            'prospectionDocumentRef' => $docRef,
            'previewReference' => $previewRef,
            'previewYear' => $year,
            'previewSeq' => $previewSeq,
            'previewLastSeq' => $lastSeq,
            'previewUnitLabel' => $previewUnitLabel,
            'previewTenantName' => trim((string) ($tenantRow['name'] ?? '')),
            'previewHasUnits' => $units !== [],
        ]);
    }

    public function referenceFormatSave(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/recruitment/reference-format'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $sep = trim((string) $request->input('separator', '/'));
        if ($sep === '') {
            $sep = '/';
        }
        $orgTag = trim((string) $request->input('organization_tag', ''));
        $recSeg = trim((string) $request->input('rec_segment', 'REC'));
        if ($recSeg === '') {
            $recSeg = 'REC';
        }
        $patch = [
            'recruitment' => [
                'prospection_document_ref' => trim((string) $request->input('prospection_document_ref', '')),
                'reference_format' => [
                    'separator' => substr($sep, 0, 4),
                    'include_organization_tag' => $request->input('include_organization_tag') === '1',
                    'organization_tag' => substr($orgTag, 0, 32),
                    'include_unit_code' => $request->input('include_unit_code') === '1',
                    'include_rec_segment' => $request->input('include_rec_segment') === '1',
                    'rec_segment' => substr($recSeg, 0, 16),
                ],
            ],
        ];
        $this->tenantRepository->updateSettings($tenantId, $patch);
        Session::flash('success', 'Paramètres enregistrés.');

        return Response::redirect(url('back-office/recruitment/reference-format'));
    }

    /**
     * Notifie recruteur / RH / fondateur (et à défaut la gouvernance) qu’une offre vient d’être publiée.
     *
     * @param array<string, mixed> $tenant
     * @param array<string, mixed> $opening Ligne offre au statut publié
     */
    private function notifyStaffRecruitmentOpeningPublished(int $tenantId, array $tenant, array $opening): void
    {
        $recipients = $this->userRepository->listRecruitmentNotificationEmailsForTenant($tenantId);
        if ($recipients === []) {
            $recipients = $this->userRepository->listGovernanceEmailsForTenant($tenantId);
        }
        if ($recipients === []) {
            return;
        }

        $tenantName = trim((string) ($tenant['name'] ?? 'Communauté'));
        $slug = trim((string) ($tenant['slug'] ?? ''));
        $publicPage = trim((string) ($opening['public_page_slug'] ?? ''));
        $oid = (int) ($opening['id'] ?? 0);
        $hrefFiche = ($slug !== '' && $publicPage !== '')
            ? url('c/' . rawurlencode($slug) . '/avis/' . rawurlencode($publicPage))
            : '';
        $hrefCand = $slug !== '' ? url('c/' . rawurlencode($slug) . '/enlistment?ouverture=' . $oid) : '';
        $title = trim((string) ($opening['title'] ?? 'Offre'));
        $ref = trim((string) ($opening['reference_public'] ?? ''));

        foreach ($recipients as $to) {
            try {
                $em = strtolower(trim($to));
                $u = $em !== '' ? $this->userRepository->findByEmail($tenantId, $em) : null;
                if ($u && !$this->notificationPreferencesRepository->isEmailEventEnabled((int) ($u['id'] ?? 0), EmailEvents::RECRUITMENT_OPENING_PUBLISHED_STAFF)) {
                    continue;
                }
                $this->emailService->sendRecruitmentOpeningPublishedStaffNotify(
                    $to,
                    $tenantName,
                    $title !== '' ? $title : 'Offre',
                    $ref !== '' ? $ref : '—',
                    $hrefFiche,
                    $hrefCand,
                    $oid,
                    $tenantId
                );
            } catch (\Throwable) {
            }
        }
    }

    /** @param array<string, mixed>|null $opening */
    private function formCreateEdit(?array $opening): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!$this->openings->tablesExist()) {
            Session::flash('error', 'Fonctionnalité non disponible sur cette base.');

            return Response::redirect(url('dashboard'));
        }
        $units = $this->unitRepository->allForTenant($tenantId);
        $roles = $this->jobRoleRepository->listRolesWithCategory($tenantId);
        $decoded = $opening ? $this->decodeOpeningJson($opening) : null;

        return Response::view('layout.main', [
            'title' => $opening ? 'Modifier une offre' : 'Nouvelle offre',
            'content' => 'admin.organization.recruitment_offers.form',
            'opening' => $opening,
            'openingDecoded' => $decoded,
            'units' => $units,
            'jobRoles' => $roles,
            'personnelCategories' => RecruitmentOpeningPresentation::personnelCategories(),
            'armDomains' => RecruitmentOpeningPresentation::armDomains(),
            'clearanceLevels' => RecruitmentOpeningPresentation::clearanceLevels(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function collectFormData(Request $request): array
    {
        $reqLines = trim((string) $request->input('requirements_lines', ''));
        $reqArr = [];
        if ($reqLines !== '') {
            foreach (preg_split('/\R/', $reqLines) ?: [] as $ln) {
                $ln = trim((string) $ln);
                if ($ln !== '') {
                    $reqArr[] = $ln;
                }
            }
        }

        return [
            'unit_id' => (int) $request->input('unit_id', 0),
            'personnel_job_role_id' => (int) $request->input('personnel_job_role_id', 0) ?: null,
            'title' => trim((string) $request->input('title', '')),
            'summary' => trim((string) $request->input('summary', '')),
            'description' => trim((string) $request->input('description', '')),
            'requirements_json' => $reqArr,
            'employment_contract_label' => trim((string) $request->input('employment_contract_label', '')),
            'employment_context_label' => trim((string) $request->input('employment_context_label', '')),
            'personnel_category' => trim((string) $request->input('personnel_category', 'other')),
            'arm_domain' => trim((string) $request->input('arm_domain', '')),
            'clearance_level' => trim((string) $request->input('clearance_level', 'none')),
            'candidate_profile_items' => $this->parseProfileItems($request),
            'technical_notice' => trim((string) $request->input('technical_notice', '')),
            'mission_lead' => trim((string) $request->input('mission_lead', '')),
            'responsibility_blocks' => $this->parseResponsibilityBlocks($request),
        ];
    }

    private function parseProfileItems(Request $request): array
    {
        $rub = $request->input('profile_rubrique', []);
        $det = $request->input('profile_detail', []);
        if (!is_array($rub)) {
            $rub = [];
        }
        if (!is_array($det)) {
            $det = [];
        }
        $n = min(count($rub), count($det), 8);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $r = trim((string) ($rub[$i] ?? ''));
            $d = trim((string) ($det[$i] ?? ''));
            if ($r === '' && $d === '') {
                continue;
            }
            $out[] = [
                'rubrique' => $r !== '' ? $r : 'Exigence',
                'detail' => $d,
            ];
        }

        return $out;
    }

    private function parseResponsibilityBlocks(Request $request): array
    {
        $themes = $request->input('block_theme', []);
        $titres = $request->input('block_titre', []);
        $corps = $request->input('block_corps', []);
        if (!is_array($themes)) {
            $themes = [];
        }
        if (!is_array($titres)) {
            $titres = [];
        }
        if (!is_array($corps)) {
            $corps = [];
        }
        $n = min(count($themes), count($titres), count($corps), 6);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $t = trim((string) ($themes[$i] ?? ''));
            $ti = trim((string) ($titres[$i] ?? ''));
            $c = trim((string) ($corps[$i] ?? ''));
            if ($t === '' && $ti === '' && $c === '') {
                continue;
            }
            $out[] = [
                'ordre' => count($out) + 1,
                'theme' => $t !== '' ? $t : 'Mission',
                'titre' => $ti !== '' ? $ti : 'Responsabilité',
                'corps' => $c,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $opening
     * @return array<string, mixed>
     */
    private function decodeOpeningJson(array $opening): array
    {
        $out = $opening;
        foreach (['requirements_json', 'candidate_profile_items', 'responsibility_blocks'] as $k) {
            $raw = $opening[$k] ?? null;
            if (is_string($raw) && $raw !== '') {
                $d = json_decode($raw, true);
                $out[$k] = is_array($d) ? $d : [];
            } elseif (!is_array($raw)) {
                $out[$k] = [];
            }
        }
        $req = $out['requirements_json'];
        $out['requirements_lines'] = is_array($req) ? implode("\n", array_map('strval', $req)) : '';

        return $out;
    }
}
