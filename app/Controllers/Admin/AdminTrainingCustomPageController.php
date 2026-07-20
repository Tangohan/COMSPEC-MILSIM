<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ContentTagRepository;
use App\Repositories\TrainingFormationCustomPageFeedbackRepository;
use App\Repositories\TrainingFormationCustomPageRepository;
use App\Services\Audit\AuditService;
use App\Services\Platform\FeatureGateService;
use App\Services\Training\TrainingFormationCustomPageExportPdfService;
use App\Services\Training\TrainingHtmlPageService;
use App\Support\Training\TrainingHtmlPagePolicy;
use App\Support\HtmlContentSanitizer;
use App\Support\TrainingFormationCustomPageRenderer;
use App\Support\TrainingLmsStaffAccess;

final class AdminTrainingCustomPageController
{
    public function __construct(
        private TrainingFormationCustomPageRepository $pageRepository,
        private TrainingHtmlPageService $htmlPageService,
        private FeatureGateService $featureGate,
        private TrainingFormationCustomPageExportPdfService $pdfExport,
        private AuditService $auditService,
        private TrainingFormationCustomPageFeedbackRepository $feedbackRepository,
        private ContentTagRepository $tagRepository,
    ) {}

    private const TAG_CONTENT_TYPE = 'formation_doc';

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->canView()) {
            return Response::redirect(url('formation'));
        }
        $tenantId = $this->tenantId();
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'training')) {
            return Response::redirect(url('platform/upgrade'));
        }
        $this->htmlPageService->applyScheduledPublicationIfDue($tenantId);
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $docStructure = trim((string) $request->query('doc_structure', ''));
        $tagSlug = trim((string) $request->query('tag', ''));
        $filters = ['q' => $search, 'status' => $status, 'doc_structure' => $docStructure];
        if ($tagSlug !== '') {
            $filters['id_in'] = $this->tagRepository->listContentIdsForTagSlug($tenantId, self::TAG_CONTENT_TYPE, $tagSlug);
        }
        $perPage = 50;
        $page = max(1, (int) $request->query('page', 1));
        $total = $this->pageRepository->countByTenant($tenantId, $filters);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $rows = $this->pageRepository->listByTenant($tenantId, $perPage, $filters, ($page - 1) * $perPage);
        $metrics = $this->pageRepository->dashboardMetrics($tenantId);
        $tagsByPageId = $this->tagRepository->listForContentIds(self::TAG_CONTENT_TYPE, array_map(static fn (array $r): int => (int) $r['id'], $rows));

        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.custom_pages',
            'title' => 'Documentations HTML',
            'trainingAdminNav' => 'custom_pages',
            'activeNav' => 'docs_html',
            'customPagesRows' => $rows,
            'customPagesMetrics' => $metrics,
            'customPagesSearch' => $search,
            'customPagesStatus' => $status,
            'customPagesDocStructure' => $docStructure,
            'customPagesTagSlug' => $tagSlug,
            'customPagesTagsByPageId' => $tagsByPageId,
            'customPagesAllTags' => $this->tagRepository->listForTenant($tenantId),
            'customPagesTotal' => $total,
            'customPagesPage' => $page,
            'customPagesTotalPages' => $totalPages,
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        if (!$this->canCreate()) {
            return Response::redirect(training_lms_admin_url('pages-html'));
        }

        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.custom_pages_form',
            'title' => 'Nouveau DOC HTML',
            'trainingAdminNav' => 'custom_pages',
            'activeNav' => 'docs_html',
            'customPage' => null,
            'customPageThemes' => $this->pageRepository->listThemesByTenant($this->tenantId()),
            'customPageTemplates' => $this->pageRepository->listTemplatesByTenant($this->tenantId()),
            'customPageRevisions' => [],
            'customPageActivity' => [],
            'customPagePolicy' => $this->policyMatrix(),
            'customPageAllTags' => $this->tagRepository->listForTenant($this->tenantId()),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!$this->canCreate()) {
            return Response::redirect(training_lms_admin_url('pages-html'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée ou requête invalide.');
            return Response::redirect(training_lms_admin_url('pages-html/nouvelle'));
        }

        $tenantId = $this->tenantId();
        $userId = (int) (Session::get('user_id') ?? 0);
        $payload = $this->extractPayload($request, null);

        if ($payload['error'] !== null) {
            Session::flash('error', $payload['error']);
            return Response::redirect(training_lms_admin_url('pages-html/nouvelle'));
        }
        if ($this->pageRepository->slugExistsForTenant($tenantId, (string) $payload['data']['slug'])) {
            Session::flash('error', 'Slug déjà utilisé dans ce tenant.');
            return Response::redirect(training_lms_admin_url('pages-html/nouvelle'));
        }
        $newStatus = (string) ($payload['data']['status'] ?? 'draft');
        if (!$this->canSetStatus($newStatus)) {
            Session::flash('error', $this->statusPermissionErrorMessage($newStatus));
            return Response::redirect(training_lms_admin_url('pages-html/nouvelle'));
        }

        $id = $this->pageRepository->create($tenantId, $payload['data'] + ['created_by' => $userId ?: null, 'updated_by' => $userId ?: null]);
        $row = $this->pageRepository->findById($id, $tenantId);
        if ($row) {
            $this->htmlPageService->createRevision($id, $tenantId, $userId, 'create', $row, null);
        }
        $this->pageRepository->addActivity($id, $tenantId, $userId ?: null, 'created', ['status' => $payload['data']['status']]);
        $this->auditService->log('formation_doc_created', $tenantId, $userId ?: null, 'formation_doc', $id, null, (string) $payload['data']['title']);
        $this->tagRepository->setTagsFromCommaText($tenantId, self::TAG_CONTENT_TYPE, $id, (string) $request->input('tags', ''));
        Session::flash('success', 'Documentation créée.');

        return Response::redirect(training_lms_admin_url('pages-html/' . $id . '/modifier'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        if (!$this->canEdit()) {
            return Response::redirect(training_lms_admin_url('pages-html'));
        }
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $row = $this->pageRepository->findById($id, $tenantId);
        if (!$row) {
            Session::flash('error', 'Document introuvable pour le tenant actif.');
            return Response::redirect(training_lms_admin_url('pages-html'));
        }

        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.custom_pages_form',
            'title' => 'Studio DOC HTML — ' . (string) $row['title'],
            'trainingAdminNav' => 'custom_pages',
            'activeNav' => 'docs_html',
            'customPage' => $row,
            'customPageThemes' => $this->pageRepository->listThemesByTenant($tenantId),
            'customPageTemplates' => $this->pageRepository->listTemplatesByTenant($tenantId),
            'customPageRevisions' => $this->pageRepository->listRevisions($id, $tenantId),
            'customPageActivity' => $this->pageRepository->listActivity($id, $tenantId),
            'customPagePolicy' => $this->policyMatrix(),
            'customPageFeedbackAgg' => $this->feedbackRepository->aggregateForPage($id, $tenantId),
            'customPageFeedbackRows' => $this->feedbackRepository->listRecentForPage($id, $tenantId, 20),
            'customPageTags' => implode(', ', array_map(static fn (array $t): string => $t['name'], $this->tagRepository->listForContent($tenantId, self::TAG_CONTENT_TYPE, $id))),
            'customPageAllTags' => $this->tagRepository->listForTenant($tenantId),
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        if (!$this->canEdit()) {
            return Response::redirect(training_lms_admin_url('pages-html'));
        }
        $id = (int) ($params['id'] ?? 0);
        $redirect = Response::redirect(training_lms_admin_url('pages-html/' . $id . '/modifier'));
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');
            return $redirect;
        }

        $tenantId = $this->tenantId();
        $before = $this->pageRepository->findById($id, $tenantId);
        if (!$before) {
            Session::flash('error', 'Document introuvable.');
            return Response::redirect(training_lms_admin_url('pages-html'));
        }

        $payload = $this->extractPayload($request, $before);
        if ($payload['error'] !== null) {
            Session::flash('error', $payload['error']);
            return $redirect;
        }
        $slug = (string) ($payload['data']['slug'] ?? '');
        if ($this->pageRepository->slugExistsForTenant($tenantId, $slug, $id)) {
            Session::flash('error', 'Slug déjà utilisé dans ce tenant.');
            return $redirect;
        }
        $newStatus = (string) ($payload['data']['status'] ?? 'draft');
        $previousStatus = (string) ($before['status'] ?? 'draft');
        if ($newStatus !== $previousStatus && !$this->canSetStatus($newStatus)) {
            Session::flash('error', $this->statusPermissionErrorMessage($newStatus));
            return $redirect;
        }

        $userId = (int) (Session::get('user_id') ?? 0);
        $payload['data']['updated_by'] = $userId > 0 ? $userId : null;
        $this->pageRepository->update($id, $tenantId, $payload['data']);
        $after = $this->pageRepository->findById($id, $tenantId);
        if ($after) {
            $this->htmlPageService->createRevision($id, $tenantId, $userId, 'update', $after, $before);
        }
        $this->pageRepository->addActivity($id, $tenantId, $userId ?: null, 'updated', ['status' => $payload['data']['status'] ?? null]);
        if ($newStatus === 'published' && $previousStatus !== 'published') {
            $this->auditService->log('formation_doc_published', $tenantId, $userId ?: null, 'formation_doc', $id, $previousStatus, $newStatus);
        } elseif ($newStatus === 'archived' && $previousStatus !== 'archived') {
            $this->auditService->log('formation_doc_archived', $tenantId, $userId ?: null, 'formation_doc', $id, $previousStatus, $newStatus);
        } else {
            $this->auditService->log('formation_doc_updated', $tenantId, $userId ?: null, 'formation_doc', $id, $previousStatus, $newStatus);
        }
        $this->tagRepository->setTagsFromCommaText($tenantId, self::TAG_CONTENT_TYPE, $id, (string) $request->input('tags', ''));
        Session::flash('success', 'Modifications enregistrées.');

        return $redirect;
    }

    public function preview(Request $request, array $params = []): Response
    {
        if (!$this->canView()) {
            return Response::redirect(training_lms_admin_url('pages-html'));
        }
        $row = $this->pageRepository->findById((int) ($params['id'] ?? 0), $this->tenantId());
        if (!$row) {
            return (new Response())->setStatusCode(404)->setBody('Documentation introuvable.');
        }

        return (new Response())->header('Content-Type', 'text/html; charset=utf-8')->setBody(
            TrainingFormationCustomPageRenderer::render($row, rtrim(url(''), '/'), $this->previewChrome($row))
        );
    }

    public function compareRevisions(Request $request, array $params = []): Response
    {
        if (!$this->canView()) {
            return Response::redirect(training_lms_admin_url('pages-html'));
        }
        $pageId = (int) ($params['id'] ?? 0);
        $tenantId = $this->tenantId();
        $page = $this->pageRepository->findById($pageId, $tenantId);
        if (!$page) {
            return (new Response())->setStatusCode(404)->setBody('Documentation introuvable.');
        }
        $revisions = $this->pageRepository->listRevisions($pageId, $tenantId, 100);
        $revA = (int) $request->query('a', 0);
        $revB = (int) $request->query('b', 0);
        $diff = null;
        $snapA = null;
        $snapB = null;
        if ($revA > 0 && $revB > 0) {
            $rowA = $this->pageRepository->findRevision($pageId, $revA, $tenantId);
            $rowB = $this->pageRepository->findRevision($pageId, $revB, $tenantId);
            $snapA = $rowA ? json_decode((string) ($rowA['content_snapshot_json'] ?? ''), true) : null;
            $snapB = $rowB ? json_decode((string) ($rowB['content_snapshot_json'] ?? ''), true) : null;
            if (is_array($snapA) && is_array($snapB)) {
                $diff = (new \App\Support\Training\TrainingRevisionDiffService())->diffSnapshots($snapA, $snapB);
            }
        }

        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.custom_page_diff',
            'title' => 'Comparer des versions — ' . (string) ($page['title'] ?? ''),
            'trainingAdminNav' => 'custom_pages',
            'activeNav' => 'docs_html',
            'customPage' => $page,
            'customPageRevisions' => $revisions,
            'diffRevA' => $revA,
            'diffRevB' => $revB,
            'diffRows' => $diff,
            'diffSnapAFound' => $snapA !== null,
            'diffSnapBFound' => $snapB !== null,
        ]);
    }

    public function exportPdf(Request $request, array $params = []): Response
    {
        if (!$this->canView()) {
            return Response::redirect(training_lms_admin_url('pages-html'));
        }
        $row = $this->pageRepository->findById((int) ($params['id'] ?? 0), $this->tenantId());
        if (!$row) {
            return (new Response())->setStatusCode(404)->setBody('Documentation introuvable.');
        }
        $binary = $this->pdfExport->generateBinary($row);
        if ($binary === null) {
            Session::flash('error', $this->pdfExport->getLastFailureReason() ?? 'Impossible de générer le PDF.');

            return Response::redirect(training_lms_admin_url('pages-html/' . $row['id'] . '/modifier'));
        }
        $filename = 'documentation-' . ($row['slug'] ?: $row['id']) . '.pdf';

        return (new Response())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($binary);
    }

    /**
     * Bandeau studio affiché uniquement sur l'aperçu interne (jamais sur la route publique
     * /formations/page/{slug}, qui appelle le renderer sans ce paramètre).
     *
     * @param array<string,mixed> $row
     */
    private function previewChrome(array $row): string
    {
        $id = (int) ($row['id'] ?? 0);
        $backUrl = training_lms_admin_url('pages-html');
        $editUrl = training_lms_admin_url('pages-html/' . $id . '/modifier');
        [$label, $tone] = $this->statusMeta((string) ($row['status'] ?? 'draft'));
        $isPublished = !empty($row['is_published']);
        $slug = (string) ($row['slug'] ?? '');
        $publicUrl = ($isPublished && $slug !== '') ? rtrim(url(''), '/') . '/formations/page/' . rawurlencode($slug) : null;

        $actions = '<a class="formation-doc-adminbar__btn formation-doc-adminbar__btn--ghost" href="' . htmlspecialchars($backUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M12.5 4.5 6.5 10l6 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            . 'Retour au pilotage</a>';
        if ($this->canEdit()) {
            $actions .= '<a class="formation-doc-adminbar__btn formation-doc-adminbar__btn--solid" href="' . htmlspecialchars($editUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Modifier le document</a>';
        }
        if ($publicUrl !== null) {
            $actions .= '<a class="formation-doc-adminbar__btn formation-doc-adminbar__btn--ghost" href="' . htmlspecialchars($publicUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">Voir la fiche publique</a>';
        }
        $pdfUrl = training_lms_admin_url('pages-html/' . $id . '/pdf');
        $actions .= '<a class="formation-doc-adminbar__btn formation-doc-adminbar__btn--ghost" href="' . htmlspecialchars($pdfUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Exporter en PDF</a>';

        $note = $isPublished
            ? ''
            : '<p class="formation-doc-adminbar__note">Aperçu interne : ce document n\'est pas encore visible des apprenants.</p>';

        return '<div class="formation-doc-adminbar">'
            . '<div class="formation-doc-adminbar__row">'
            . '<div class="formation-doc-adminbar__identity">'
            . '<span class="formation-doc-adminbar__kicker">Aperçu studio</span>'
            . '<span class="formation-doc-adminbar__status formation-doc-adminbar__status--' . $tone . '">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>'
            . '</div>'
            . '<div class="formation-doc-adminbar__actions">' . $actions . '</div>'
            . '</div>'
            . $note
            . '</div>';
    }

    /** @return array{0:string,1:string} */
    private function statusMeta(string $status): array
    {
        return match ($status) {
            'review' => ['En révision', 'review'],
            'scheduled' => ['Programmé', 'scheduled'],
            'published' => ['Publié', 'published'],
            'archived' => ['Archivé', 'archived'],
            default => ['Brouillon', 'draft'],
        };
    }

    public function duplicate(Request $request, array $params = []): Response
    {
        if (!$this->canDuplicate() || !$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            return Response::redirect(training_lms_admin_url('pages-html'));
        }
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $row = $this->pageRepository->findById($id, $tenantId);
        if (!$row) {
            Session::flash('error', 'Document introuvable.');
            return Response::redirect(training_lms_admin_url('pages-html'));
        }

        $baseSlug = (string) $row['slug'] . '-copie';
        $slug = $baseSlug;
        $i = 2;
        while ($this->pageRepository->slugExistsForTenant($tenantId, $slug)) {
            $slug = $baseSlug . '-' . $i;
            ++$i;
        }
        $userId = (int) (Session::get('user_id') ?? 0);
        // Re-sanitisé au cas où la source proviendrait d'une version antérieure au filtrage HTML.
        $sanitizedSource = $this->sanitizeSnapshotHtml($row);
        $newId = $this->pageRepository->create($tenantId, [
            'slug' => $slug,
            'title' => (string) $row['title'] . ' (copie)',
            'subtitle' => $row['subtitle'] ?? null,
            'summary' => $row['summary'] ?? null,
            'doc_structure' => $row['doc_structure'] ?? 'single',
            'intro_html' => $sanitizedSource['intro_html'] ?? null,
            'html_body' => $sanitizedSource['html_body'] ?? '',
            'sections_json' => $sanitizedSource['sections_json'] ?? null,
            'theme_id' => $row['theme_id'] ?? null,
            'icon' => $row['icon'] ?? null,
            'accent_color' => $row['accent_color'] ?? null,
            'layout_mode' => $row['layout_mode'] ?? 'standard',
            'show_toc' => $row['show_toc'] ?? 1,
            'show_reading_progress' => $row['show_reading_progress'] ?? 1,
            'visibility_level' => $row['visibility_level'] ?? 'tenant',
            'allowed_roles_json' => $row['allowed_roles_json'] ?? null,
            'status' => 'draft',
            'is_published' => 0,
            'published_at' => null,
            'scheduled_publish_at' => null,
            'estimated_read_time' => $row['estimated_read_time'] ?? 1,
            'created_by' => $userId ?: null,
            'updated_by' => $userId ?: null,
        ]);
        $this->pageRepository->addActivity($newId, $tenantId, $userId ?: null, 'duplicated', ['source_id' => $id]);
        $this->auditService->log('formation_doc_duplicated', $tenantId, $userId ?: null, 'formation_doc', $newId, (string) $id, (string) $row['title']);
        Session::flash('success', 'Copie créée.');

        return Response::redirect(training_lms_admin_url('pages-html/' . $newId . '/modifier'));
    }

    public function restoreRevision(Request $request, array $params = []): Response
    {
        $pageId = (int) ($params['id'] ?? 0);
        if (!$this->canEdit() || !$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            return Response::redirect(training_lms_admin_url('pages-html/' . $pageId . '/modifier'));
        }
        $tenantId = $this->tenantId();
        $revId = (int) ($params['revisionId'] ?? 0);
        $rev = $this->pageRepository->findRevision($pageId, $revId, $tenantId);
        $current = $this->pageRepository->findById($pageId, $tenantId);
        if (!$rev || !$current) {
            Session::flash('error', 'Version introuvable.');
            return Response::redirect(training_lms_admin_url('pages-html/' . $pageId . '/modifier'));
        }
        $snapshot = json_decode((string) ($rev['content_snapshot_json'] ?? ''), true);
        if (!is_array($snapshot)) {
            Session::flash('error', 'Snapshot de version invalide.');
            return Response::redirect(training_lms_admin_url('pages-html/' . $pageId . '/modifier'));
        }
        // Re-sanitisé au cas où le snapshot provient d'une version antérieure au filtrage HTML.
        $snapshot = $this->sanitizeSnapshotHtml($snapshot);
        $restoredStatus = (string) ($snapshot['status'] ?? 'draft');
        $currentStatus = (string) ($current['status'] ?? 'draft');
        if ($restoredStatus !== $currentStatus && !$this->canSetStatus($restoredStatus)) {
            Session::flash('error', $this->statusPermissionErrorMessage($restoredStatus));
            return Response::redirect(training_lms_admin_url('pages-html/' . $pageId . '/modifier'));
        }
        $userId = (int) (Session::get('user_id') ?? 0);
        $snapshot['updated_by'] = $userId ?: null;
        $this->pageRepository->update($pageId, $tenantId, $snapshot);
        $this->pageRepository->addActivity($pageId, $tenantId, $userId ?: null, 'restored_revision', ['revision_id' => $revId]);
        $this->auditService->log('formation_doc_restored', $tenantId, $userId ?: null, 'formation_doc', $pageId, null, 'v' . $revId);
        $updated = $this->pageRepository->findById($pageId, $tenantId);
        if ($updated) {
            $this->htmlPageService->createRevision($pageId, $tenantId, $userId, 'restore', $updated, $current);
        }
        Session::flash('success', 'Version restaurée.');

        return Response::redirect(training_lms_admin_url('pages-html/' . $pageId . '/modifier'));
    }

    public function destroy(Request $request, array $params = []): Response
    {
        if (!$this->canDelete() || !$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            return Response::redirect(training_lms_admin_url('pages-html'));
        }
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        if ($id > 0) {
            $row = $this->pageRepository->findById($id, $tenantId);
            $this->pageRepository->delete($id, $tenantId);
            $userId = (int) (Session::get('user_id') ?? 0);
            $this->auditService->log('formation_doc_deleted', $tenantId, $userId ?: null, 'formation_doc', $id, $row ? (string) $row['title'] : null, null);
        }
        Session::flash('success', 'Documentation supprimée.');

        return Response::redirect(training_lms_admin_url('pages-html'));
    }

    private function tenantId(): int { return (int) (Session::get('tenant_id') ?? 0); }

    private function canView(): bool
    {
        return TrainingLmsStaffAccess::allows(Gate::getInstance()) && TrainingHtmlPagePolicy::canView(Gate::getInstance());
    }
    private function canCreate(): bool
    {
        return TrainingLmsStaffAccess::allows(Gate::getInstance()) && TrainingHtmlPagePolicy::canCreate(Gate::getInstance());
    }
    private function canEdit(): bool
    {
        return TrainingLmsStaffAccess::allows(Gate::getInstance()) && TrainingHtmlPagePolicy::canEdit(Gate::getInstance());
    }
    private function canDelete(): bool
    {
        return TrainingLmsStaffAccess::allows(Gate::getInstance()) && TrainingHtmlPagePolicy::canDelete(Gate::getInstance());
    }
    private function canDuplicate(): bool
    {
        return TrainingLmsStaffAccess::allows(Gate::getInstance()) && TrainingHtmlPagePolicy::canDuplicate(Gate::getInstance());
    }

    /**
     * Un simple droit d'édition (training.create/training.update) ne suffit pas à faire
     * franchir au document une étape du circuit éditorial — mettre en revue, publier
     * (y compris programmer) ou archiver sont des droits distincts (canReview/canPublish/
     * canArchive), déjà calculés dans policyMatrix() mais jusqu'ici jamais vérifiés côté
     * serveur : le formulaire les exposait sans que rien n'empêche un simple rédacteur de
     * les déclencher directement.
     */
    private function canSetStatus(string $status): bool
    {
        $g = Gate::getInstance();

        return match ($status) {
            'review' => TrainingHtmlPagePolicy::canReview($g),
            'scheduled', 'published' => TrainingHtmlPagePolicy::canPublish($g),
            'archived' => TrainingHtmlPagePolicy::canArchive($g),
            default => true,
        };
    }

    private function statusPermissionErrorMessage(string $status): string
    {
        return match ($status) {
            'review' => 'Vous n’avez pas les droits pour passer ce document en revue.',
            'scheduled' => 'Vous n’avez pas les droits pour programmer la publication de ce document.',
            'published' => 'Vous n’avez pas les droits pour publier ce document.',
            'archived' => 'Vous n’avez pas les droits pour archiver ce document.',
            default => 'Vous n’avez pas les droits pour ce changement de statut.',
        };
    }

    /** @return array<string,bool> */
    private function policyMatrix(): array
    {
        $g = Gate::getInstance();
        return [
            'create' => TrainingHtmlPagePolicy::canCreate($g),
            'view' => TrainingHtmlPagePolicy::canView($g),
            'edit' => TrainingHtmlPagePolicy::canEdit($g),
            'review' => TrainingHtmlPagePolicy::canReview($g),
            'publish' => TrainingHtmlPagePolicy::canPublish($g),
            'archive' => TrainingHtmlPagePolicy::canArchive($g),
            'delete' => TrainingHtmlPagePolicy::canDelete($g),
            'duplicate' => TrainingHtmlPagePolicy::canDuplicate($g),
            'manage_templates' => TrainingHtmlPagePolicy::canManageTemplates($g),
            'manage_themes' => TrainingHtmlPagePolicy::canManageThemes($g),
        ];
    }

    private function normalizeSlug(string $raw): string
    {
        $s = strtolower(trim($raw));
        $s = preg_replace('/[^a-z0-9-]+/', '-', $s) ?? '';
        return trim(substr($s, 0, 120), '-');
    }

    /** @return array{error:?string,data:array<string,mixed>} */
    private function extractPayload(Request $request, ?array $existing): array
    {
        $title = trim((string) $request->input('title', ''));
        $slug = $this->normalizeSlug((string) $request->input('slug', ''));
        $structure = (string) $request->input('doc_structure', 'single');
        $statusRequested = (string) $request->input('status', 'draft');
        $schedule = trim((string) $request->input('scheduled_publish_at', ''));
        $workflow = $this->htmlPageService->resolveWorkflowState($statusRequested, (bool) $request->input('publish_now'), $schedule !== '' ? $schedule : null);

        $sectionsRes = $this->resolveSectionsPayload($request, $structure);
        if ($sectionsRes['error'] !== null) {
            return ['error' => $sectionsRes['error'], 'data' => []];
        }
        $body = HtmlContentSanitizer::sanitize((string) $request->input('html_body', ''));
        if ($title === '' || $slug === '') {
            return ['error' => 'Titre et slug sont requis.', 'data' => []];
        }
        if ($structure === 'single' && trim($body) === '') {
            return ['error' => 'Le corps HTML est requis en mode page unique.', 'data' => []];
        }

        $readTime = $this->htmlPageService->estimateReadTimeMinutes($body, $sectionsRes['sections_json']);
        $payload = [
            'slug' => $slug,
            'title' => $title,
            'subtitle' => trim((string) $request->input('subtitle', '')) ?: null,
            'summary' => trim((string) $request->input('summary', '')) ?: null,
            'doc_structure' => in_array($structure, ['single', 'handbook'], true) ? $structure : 'single',
            'intro_html' => HtmlContentSanitizer::sanitize((string) $request->input('intro_html', '')),
            'html_body' => $body,
            'sections_json' => $sectionsRes['sections_json'],
            'theme_id' => ($tid = (int) $request->input('theme_id', 0)) > 0 ? $tid : null,
            'icon' => trim((string) $request->input('icon', '')) ?: null,
            'accent_color' => trim((string) $request->input('accent_color', '')) ?: null,
            'layout_mode' => trim((string) $request->input('layout_mode', 'standard')) ?: 'standard',
            'show_toc' => (int) (bool) $request->input('show_toc', 1),
            'show_reading_progress' => (int) (bool) $request->input('show_reading_progress', 1),
            'visibility_level' => trim((string) $request->input('visibility_level', 'tenant')) ?: 'tenant',
            'allowed_roles_json' => ($ar = trim((string) $request->input('allowed_roles_json', ''))) !== '' ? $ar : null,
            'canonical_url' => trim((string) $request->input('canonical_url', '')) ?: null,
            'meta_title' => trim((string) $request->input('meta_title', '')) ?: null,
            'meta_description' => trim((string) $request->input('meta_description', '')) ?: null,
            'og_image' => trim((string) $request->input('og_image', '')) ?: null,
            'estimated_read_time' => $readTime,
            'status' => $workflow['status'],
            'is_published' => $workflow['is_published'],
            'published_at' => $workflow['published_at'] ?? ($existing['published_at'] ?? null),
            'scheduled_publish_at' => $workflow['scheduled_publish_at'] ?? null,
            'archived_at' => $workflow['archived_at'] ?? ($existing['archived_at'] ?? null),
        ];

        if ($workflow['status'] === 'published') {
            $payload['last_published_by'] = (int) (Session::get('user_id') ?? 0) ?: null;
        }

        return ['error' => null, 'data' => $payload];
    }

    /** @param array<string,mixed> $snapshot @return array<string,mixed> */
    private function sanitizeSnapshotHtml(array $snapshot): array
    {
        if (isset($snapshot['html_body'])) {
            $snapshot['html_body'] = HtmlContentSanitizer::sanitize((string) $snapshot['html_body']);
        }
        if (isset($snapshot['intro_html'])) {
            $snapshot['intro_html'] = HtmlContentSanitizer::sanitize((string) $snapshot['intro_html']);
        }
        if (!empty($snapshot['sections_json']) && is_string($snapshot['sections_json'])) {
            $decoded = json_decode($snapshot['sections_json'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $i => $section) {
                    if (is_array($section) && isset($section['html'])) {
                        $decoded[$i]['html'] = HtmlContentSanitizer::sanitize((string) $section['html']);
                    }
                }
                $snapshot['sections_json'] = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return $snapshot;
    }

    /** @return array{sections_json:?string,error:?string} */
    private function resolveSectionsPayload(Request $request, string $mode): array
    {
        if ($mode !== 'handbook') {
            return ['sections_json' => null, 'error' => null];
        }
        $raw = trim((string) $request->input('sections_json', ''));
        $decoded = $raw === '' ? [] : json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['sections_json' => null, 'error' => 'JSON chapitres invalide.'];
        }
        $normalized = [];
        foreach ($decoded as $it) {
            if (!is_array($it)) {
                continue;
            }
            $title = trim((string) ($it['title'] ?? ''));
            $html = HtmlContentSanitizer::sanitize((string) ($it['html'] ?? ''));
            $slug = $this->normalizeSlug((string) ($it['slug'] ?? ''));
            if ($title === '' && trim(strip_tags($html)) === '') {
                continue;
            }
            if ($title === '') {
                $title = 'Chapitre ' . (count($normalized) + 1);
            }
            if ($slug === '') {
                $slug = $this->normalizeSlug($title);
            }
            $normalized[] = ['title' => $title, 'slug' => $slug, 'html' => $html];
            if (count($normalized) >= 120) {
                break;
            }
        }
        if ($normalized === []) {
            return ['sections_json' => null, 'error' => 'Ajoutez au moins un chapitre en mode manuel.'];
        }

        return ['sections_json' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'error' => null];
    }
}
