<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\TrainingFormationCustomPageRepository;
use App\Support\TrainingFormationCustomPageRenderer;
use App\Support\TrainingLmsStaffAccess;

/**
 * Documentations HTML autonomes (équivalent rédactionnel d’un parcours, style documentation, sans quiz).
 */
final class AdminTrainingCustomPageController
{
    public function __construct(
        private TrainingFormationCustomPageRepository $pageRepository,
        private TrainingCourseRepository $trainingCourseRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->canAccess()) {
            return Response::redirect(url('formation'));
        }
        $tenantId = $this->tenantId();
        $rows = $this->pageRepository->listByTenant($tenantId, 200);
        $totalModules = count($this->trainingCourseRepository->listForTenant($tenantId, null));

        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.custom_pages',
            'title' => 'Documentations HTML',
            'trainingAdminNav' => 'custom_pages',
            'activeNav' => 'docs_html',
            'customPagesRows' => $rows,
            'totalModules' => $totalModules,
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        if (!$this->canAccess()) {
            return Response::redirect(url('formation'));
        }
        $tenantId = $this->tenantId();
        $totalModules = count($this->trainingCourseRepository->listForTenant($tenantId, null));

        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.custom_pages_form',
            'title' => 'Nouvelle documentation',
            'trainingAdminNav' => 'custom_pages',
            'activeNav' => 'docs_html',
            'customPage' => null,
            'totalModules' => $totalModules,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!$this->canAccess()) {
            return Response::redirect(url('formation'));
        }
        $redirect = Response::redirect(training_lms_admin_url('pages-html'));
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée ou requête invalide.');

            return $redirect;
        }
        $tenantId = $this->tenantId();
        $userId = (int) (Session::get('user_id') ?? 0);
        $title = trim((string) $request->input('title', ''));
        $slug = $this->normalizeSlug((string) $request->input('slug', ''));
        $html = (string) $request->input('html_body', '');
        $sectionsRes = $this->resolveSectionsPayload($request);
        if (!$sectionsRes['ok']) {
            Session::flash('error', $sectionsRes['error']);

            return Response::redirect(training_lms_admin_url('pages-html/nouvelle'));
        }
        $sectionsJson = $sectionsRes['sections_json'];
        if ($title === '' || $slug === '') {
            Session::flash('error', 'Titre et identifiant d’URL sont requis.');

            return Response::redirect(training_lms_admin_url('pages-html/nouvelle'));
        }
        if ($sectionsJson === null && trim($html) === '') {
            Session::flash('error', 'Renseignez le corps HTML, ou passez en mode manuel avec au moins un chapitre.');

            return Response::redirect(training_lms_admin_url('pages-html/nouvelle'));
        }
        if ($this->pageRepository->slugExistsForTenant($tenantId, $slug, null)) {
            Session::flash('error', 'Cet identifiant d’URL est déjà utilisé. Choisissez-en un autre.');

            return Response::redirect(training_lms_admin_url('pages-html/nouvelle'));
        }
        $id = $this->pageRepository->create($tenantId, [
            'slug' => $slug,
            'title' => $title,
            'html_body' => $html,
            'sections_json' => $sectionsJson,
            'is_published' => (int) (bool) $request->input('is_published'),
            'created_by' => $userId > 0 ? $userId : null,
            'updated_by' => $userId > 0 ? $userId : null,
        ]);
        Session::flash('success', 'Documentation enregistrée.');

        return Response::redirect(training_lms_admin_url('pages-html/' . $id . '/modifier'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        if (!$this->canAccess()) {
            return Response::redirect(url('formation'));
        }
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $row = $this->pageRepository->findById($id, $tenantId);
        if (!$row) {
            Session::flash('error', 'Page introuvable.');

            return Response::redirect(training_lms_admin_url('pages-html'));
        }
        $totalModules = count($this->trainingCourseRepository->listForTenant($tenantId, null));

        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.custom_pages_form',
            'title' => 'Modifier — ' . (string) $row['title'],
            'trainingAdminNav' => 'custom_pages',
            'activeNav' => 'docs_html',
            'customPage' => $row,
            'totalModules' => $totalModules,
        ]);
    }

    public function preview(Request $request, array $params = []): Response
    {
        if (!$this->canAccess()) {
            return Response::redirect(url('formation'));
        }
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $row = $this->pageRepository->findById($id, $tenantId);
        if (!$row) {
            return (new Response())->setStatusCode(404)->setBody('Documentation introuvable.');
        }
        $base = rtrim(url(''), '/');
        $html = TrainingFormationCustomPageRenderer::render($row, $base);

        return (new Response())
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->setBody($html);
    }

    public function update(Request $request, array $params = []): Response
    {
        if (!$this->canAccess()) {
            return Response::redirect(url('formation'));
        }
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $redirect = Response::redirect(training_lms_admin_url('pages-html/' . $id . '/modifier'));
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return $redirect;
        }
        $row = $this->pageRepository->findById($id, $tenantId);
        if (!$row) {
            Session::flash('error', 'Page introuvable.');

            return Response::redirect(training_lms_admin_url('pages-html'));
        }
        $userId = (int) (Session::get('user_id') ?? 0);
        $title = trim((string) $request->input('title', ''));
        $slug = $this->normalizeSlug((string) $request->input('slug', ''));
        $html = (string) $request->input('html_body', '');
        $sectionsRes = $this->resolveSectionsPayload($request);
        if (!$sectionsRes['ok']) {
            Session::flash('error', $sectionsRes['error']);

            return $redirect;
        }
        $sectionsJson = $sectionsRes['sections_json'];
        if ($title === '' || $slug === '') {
            Session::flash('error', 'Titre et identifiant d’URL sont requis.');

            return $redirect;
        }
        if ($sectionsJson === null && trim($html) === '') {
            Session::flash('error', 'Renseignez le corps HTML, ou passez en mode manuel avec au moins un chapitre.');

            return $redirect;
        }
        if ($this->pageRepository->slugExistsForTenant($tenantId, $slug, $id)) {
            Session::flash('error', 'Cet identifiant d’URL est déjà utilisé.');

            return $redirect;
        }
        $this->pageRepository->update($id, $tenantId, [
            'slug' => $slug,
            'title' => $title,
            'html_body' => $html,
            'sections_json' => $sectionsJson,
            'is_published' => (int) (bool) $request->input('is_published'),
            'updated_by' => $userId > 0 ? $userId : null,
        ]);
        Session::flash('success', 'Modifications enregistrées.');

        return $redirect;
    }

    public function destroy(Request $request, array $params = []): Response
    {
        if (!$this->canAccess()) {
            return Response::redirect(url('formation'));
        }
        $redirect = Response::redirect(training_lms_admin_url('pages-html'));
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return $redirect;
        }
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return $redirect;
        }
        $this->pageRepository->delete($id, $tenantId);
        Session::flash('success', 'Documentation supprimée.');

        return $redirect;
    }

    private function canAccess(): bool
    {
        return TrainingLmsStaffAccess::allows(Gate::getInstance());
    }

    private function tenantId(): int
    {
        return (int) (Session::get('tenant_id') ?? 0);
    }

    private function normalizeSlug(string $raw): string
    {
        $s = strtolower(preg_replace('/[^a-z0-9-]+/', '-', trim($raw)));
        $s = trim($s, '-');

        return $s !== '' ? substr($s, 0, 120) : '';
    }

    /**
     * @return array{ok: bool, sections_json: ?string, error: ?string}
     */
    private function resolveSectionsPayload(Request $request): array
    {
        $mode = trim((string) $request->input('doc_structure', 'single'));
        if ($mode !== 'handbook') {
            return ['ok' => true, 'sections_json' => null, 'error' => null];
        }
        $raw = trim((string) $request->input('sections_json', ''));
        $decoded = $raw === '' ? [] : json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'sections_json' => null, 'error' => 'Structure des chapitres invalide (JSON).'];
        }
        $normalized = [];
        foreach ($decoded as $it) {
            if (!is_array($it)) {
                continue;
            }
            $t = trim((string) ($it['title'] ?? ''));
            $h = (string) ($it['html'] ?? '');
            $slug = trim((string) ($it['slug'] ?? ''));
            if ($t === '' && trim(strip_tags($h)) === '') {
                continue;
            }
            if ($t === '') {
                $t = 'Chapitre ' . (count($normalized) + 1);
            }
            $normalized[] = ['title' => $t, 'slug' => $slug, 'html' => $h];
        }
        if ($normalized === []) {
            return ['ok' => false, 'sections_json' => null, 'error' => 'En mode manuel, ajoutez au moins un chapitre avec du contenu.'];
        }
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return ['ok' => true, 'sections_json' => $json !== false ? $json : null, 'error' => null];
    }
}
