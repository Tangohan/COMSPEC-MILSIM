<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DoctrineRepository;
use App\Services\Auth\AuthService;
use App\Services\Doctrine\DoctrineHubService;
use App\Services\Doctrine\DoctrineNotificationService;
use App\Services\Doctrine\DoctrinePublicationService;

/**
 * Back-office > Doctrine : page de pilotage des documents doctrinaux (écrire, classer, publier, diffuser, suivre),
 * plus les fiches SOP opérationnelles historiques (doctrine_documents) servies au poste ATAK.
 */
final class DoctrineAdminController
{
    public function __construct(
        private AuthService $authService,
        private DoctrineRepository $doctrineRepository,
        private DoctrineHubService $hubService,
        private DoctrineNotificationService $notificationService,
        private DoctrinePublicationService $publicationService,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        if (!Gate::getInstance()->allows('admin.organization')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }

        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }

        $this->doctrineRepository->ensureSchema();
        $gate = Gate::getInstance();

        return Response::view('layout.main', [
            'title' => 'Doctrine et SOP',
            'content' => 'admin.organization.doctrine',
            'backOfficePageCss' => ['doctrine-hub.css'],
            'boSkipPageHead' => true,
            'doctrine_documents' => $this->doctrineRepository->listDocumentsWithVersions($tenantId),
            'hub' => $this->hubService->overview($tenantId),
            'can' => [
                'create' => !$this->denyCreate(),
                'manage' => !$this->denyManage(),
                'publish' => $gate->allows('doctrine.publish') || $gate->allows('documents.publish') || $gate->allows('admin.access'),
                'remind' => $gate->allows('doctrine.send_reminders') || !$this->denyManage(),
                'archive' => $gate->allows('doctrine.archive') || $gate->allows('documents.archive') || $gate->allows('admin.access'),
                'compliance' => $gate->allows('doctrine.view_compliance') || !$this->denyManage(),
            ],
            'csrf_token' => Csrf::token(),
        ]);
    }

    /** Export CSV de conformité : une ligne par destinataire et par document publié (?document_id= pour un seul). */
    public function exportCompliance(Request $request, array $params = []): Response
    {
        if (!$this->authService->user()) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if ($gate->deny('doctrine.view_compliance') && $this->denyManage()) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $documentId = (int) $request->input('document_id', 0);
        $rows = [['Référence', 'Titre', 'Version', 'Membre', 'Unité', 'Statut', 'Première ouverture', 'Lecture confirmée le', 'Échéance']];
        foreach ($this->hubService->complianceRows($tenantId, $documentId) as $r) {
            $rows[] = [
                $r['reference'], $r['title'], $r['version'], $r['member'], $r['unit'], $r['status'],
                self::csvDate($r['first_viewed_at']), self::csvDate($r['signed_at']), self::csvDate($r['deadline']),
            ];
        }
        $out = "\xEF\xBB\xBF";
        foreach ($rows as $row) {
            $out .= implode(';', array_map([self::class, 'csvCell'], $row)) . "\r\n";
        }

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="doctrine-lectures-' . date('Ymd-Hi') . '.csv"')
            ->header('Cache-Control', 'private, no-store')
            ->setBody($out);
    }

    /** Relance groupée : tous les documents publiés qui ont encore des destinataires sans lecture ou sans accusé. */
    public function remindAll(Request $request, array $params = []): Response
    {
        $back = url('back-office/doctrine');
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($back);
        }
        $gate = Gate::getInstance();
        if (!$this->authService->user() || ($gate->deny('doctrine.send_reminders') && $this->denyManage())) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        $note = mb_substr(trim((string) $request->input('note', '')), 0, 500);
        $docs = 0;
        $people = 0;
        foreach ($this->hubService->documentIdsWithMissingReaders($tenantId) as $documentId) {
            $result = $this->notificationService->sendReminders($tenantId, $userId, $documentId, $note);
            if (!empty($result['ok'])) {
                ++$docs;
                $people += (int) ($result['count'] ?? 0);
            }
        }
        Session::flash($docs > 0 ? 'success' : 'error', $docs > 0
            ? $people . ' rappel(s) envoyé(s) pour ' . $docs . ' document(s).'
            : 'Aucun document n’attend de lecture : personne à relancer.');

        return Response::redirect($back);
    }

    /** Publication directe d’un brouillon ou d’un document validé, depuis la page de pilotage. */
    public function publishDocument(Request $request, array $params = []): Response
    {
        $back = url('back-office/doctrine');
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($back);
        }
        $gate = Gate::getInstance();
        if (!$this->authService->user() || $this->denyCreate()
            || ($gate->deny('doctrine.publish') && $gate->deny('documents.publish') && $gate->deny('admin.access'))) {
            Session::flash('error', 'Vous n’avez pas le droit de publier.');

            return Response::redirect($back);
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        $documentId = (int) ($params['id'] ?? 0);
        $pub = $this->publicationService->publish($tenantId, $userId, $documentId, true);
        if (empty($pub['ok'])) {
            Session::flash('error', (string) ($pub['error'] ?? 'Publication impossible.'));
        } else {
            Session::flash('success', 'Document publié et destinataires informés.');
        }

        return Response::redirect($back . '#doc-' . $documentId);
    }

    public function archiveDocument(Request $request, array $params = []): Response
    {
        $back = url('back-office/doctrine');
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($back);
        }
        $gate = Gate::getInstance();
        if (!$this->authService->user()
            || ($gate->deny('doctrine.archive') && $gate->deny('documents.archive') && $gate->deny('admin.access'))) {
            Session::flash('error', 'Vous n’avez pas le droit d’archiver.');

            return Response::redirect($back);
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        $result = $this->hubService->archive($tenantId, $userId, (int) ($params['id'] ?? 0));
        Session::flash(empty($result['ok']) ? 'error' : 'success', empty($result['ok'])
            ? (string) ($result['error'] ?? 'Archivage impossible.')
            : 'Document archivé : il ne figure plus dans les lectures à faire.');

        return Response::redirect($back);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/doctrine'));
        }
        $user = $this->authService->user();
        if (!$user || !Gate::getInstance()->allows('admin.organization')) {
            return Response::redirect(url('back-office/doctrine'));
        }

        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $this->doctrineRepository->ensureSchema();

        $title = trim((string) $request->input('title', ''));
        $type = trim((string) $request->input('document_type', 'sop'));
        $version = trim((string) $request->input('version_label', '1.0.0'));
        $effectiveAt = trim((string) $request->input('effective_at', ''));
        $content = trim((string) $request->input('content_markdown', ''));

        if ($title === '' || $content === '') {
            Session::flash('error', 'Titre et contenu requis.');

            return Response::redirect(url('back-office/doctrine') . '#sop');
        }

        if (!in_array($type, ['sop', 'checklist', 'report_format'], true)) {
            $type = 'sop';
        }

        $this->doctrineRepository->createDocumentWithVersion(
            $tenantId,
            (int) ($user['id'] ?? 0),
            mb_substr($title, 0, 180),
            $type,
            mb_substr($version !== '' ? $version : '1.0.0', 0, 20),
            $effectiveAt !== '' ? $effectiveAt . ' 00:00:00' : null,
            $content
        );

        Session::flash('success', 'Fiche SOP créée en brouillon : activez-la pour la mettre en vigueur.');

        return Response::redirect(url('back-office/doctrine') . '#sop');
    }

    public function activate(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/doctrine'));
        }
        $user = $this->authService->user();
        if (!$user || !Gate::getInstance()->allows('admin.organization')) {
            return Response::redirect(url('back-office/doctrine'));
        }

        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $versionId = (int) ($params['versionId'] ?? 0);
        if ($tenantId > 0 && $versionId > 0) {
            $this->doctrineRepository->ensureSchema();
            $ok = $this->doctrineRepository->activateVersion($tenantId, $versionId, (int) ($user['id'] ?? 0));
            if ($ok) {
                Session::flash('success', 'Version activée.');
            } else {
                Session::flash('error', 'Version introuvable.');
            }
        }

        return Response::redirect(url('back-office/doctrine') . '#sop');
    }

    private static function csvCell(string $cell): string
    {
        // Cellule commençant par = + - @ : neutralisée pour le tableur (injection de formule).
        if ($cell !== '' && in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $cell = "'" . $cell;
        }

        return '"' . str_replace('"', '""', $cell) . '"';
    }

    private static function csvDate(string $raw): string
    {
        $ts = $raw !== '' ? strtotime($raw) : false;

        return $ts ? date('d/m/Y H:i', $ts) : '';
    }

    private function denyManage(): bool
    {
        $gate = Gate::getInstance();

        return $gate->deny('doctrine.edit')
            && $gate->deny('doctrine.create')
            && $gate->deny('documents.upload')
            && $gate->deny('admin.access');
    }

    private function denyCreate(): bool
    {
        $gate = Gate::getInstance();

        return $gate->deny('doctrine.create')
            && $gate->deny('doctrine.edit')
            && $gate->deny('documents.upload')
            && $gate->deny('documents.publish')
            && $gate->deny('admin.access');
    }
}
