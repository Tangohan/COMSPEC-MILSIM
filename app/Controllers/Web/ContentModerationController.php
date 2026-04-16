<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ModerationArtifactRepository;
use App\Repositories\ModerationDecisionRepository;
use App\Services\Documents\DocumentUploadService;
use App\Services\Moderation\ModerationArtifactState;
use App\Services\Moderation\ModerationSourceType;

/**
 * File d'attente modération contenus (quarantaine, fichiers forum, courrier texte).
 */
final class ContentModerationController
{
    public function __construct(
        private ModerationArtifactRepository $artifactRepository,
        private ModerationDecisionRepository $decisionRepository,
        private DocumentUploadService $documentUploadService
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        if (!$this->artifactRepository->tableExists()) {
            return Response::view('layout.main', [
                'content' => 'admin.content_moderation_index',
                'title' => 'Modération fichiers',
                'artifacts' => [],
                'total' => 0,
                'page' => 1,
                'perPage' => 30,
                'missingTables' => true,
                'recentForumPublished' => [],
            ]);
        }
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 30;
        $artifacts = $this->artifactRepository->listQueue($tenantId, null, $page, $perPage);
        $total = $this->artifactRepository->countQueue($tenantId, null);
        $recentForumPublished = $this->artifactRepository->listRecentPublishedForumUploads($tenantId, 15);
        foreach ($artifacts as &$a) {
            if (!empty($a['reason_codes']) && is_string($a['reason_codes'])) {
                $a['reason_codes'] = json_decode($a['reason_codes'], true) ?: [];
            }
            if (!empty($a['scan_log']) && is_string($a['scan_log'])) {
                $a['scan_log'] = json_decode($a['scan_log'], true) ?: [];
            }
        }
        unset($a);

        return Response::view('layout.main', [
            'content' => 'admin.content_moderation_index',
            'title' => 'Modération fichiers & quarantaine',
            'artifacts' => $artifacts,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'missingTables' => false,
            'recentForumPublished' => $recentForumPublished,
        ]);
    }

    /**
     * Aperçu sécurisé d’une pièce encore en stockage (quarantaine), réservé aux modérateurs.
     */
    public function preview(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId <= 0) {
            return $this->previewDenied();
        }
        $id = (int) ($params['id'] ?? 0);
        $artifact = $this->artifactRepository->findById($id, $tenantId);
        if ($artifact === null) {
            return $this->previewDenied();
        }
        $state = (string) ($artifact['state'] ?? '');
        if (!in_array($state, [ModerationArtifactState::QUARANTINED, ModerationArtifactState::PENDING_SCAN], true)) {
            return $this->previewDenied();
        }
        if (($artifact['source_type'] ?? '') !== ModerationSourceType::FORUM_UPLOAD) {
            return $this->previewDenied();
        }
        $rel = str_replace('\\', '/', (string) ($artifact['file_path'] ?? ''));
        $expectedPrefix = 'quarantine/' . $tenantId . '/';
        if ($rel === '' || !str_starts_with($rel, $expectedPrefix) || str_contains($rel, '..')) {
            return $this->previewDenied();
        }
        $full = base_path('storage/' . $rel);
        $storageRoot = realpath(base_path('storage/quarantine/' . $tenantId));
        $resolved = realpath($full);
        if ($storageRoot === false || $resolved === false || !str_starts_with($resolved, $storageRoot)) {
            return $this->previewDenied();
        }
        if (!is_file($resolved) || !is_readable($resolved)) {
            return $this->previewDenied();
        }
        $mime = (string) ($artifact['mime'] ?? 'application/octet-stream');
        if (!preg_match('#^(image/(jpeg|png|gif|webp)|application/pdf)$#', $mime)) {
            return $this->previewDenied();
        }
        $filename = basename($rel);
        $disp = 'inline; filename="' . str_replace(['"', "\r", "\n"], '', $filename) . '"';

        $response = new Response();
        $response->setStatusCode(200)
            ->header('Content-Type', $mime)
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'private, max-age=120')
            ->header('Content-Disposition', $disp)
            ->setBodyStream(static function () use ($resolved): void {
                $h = fopen($resolved, 'rb');
                if ($h !== false) {
                    fpassthru($h);
                    fclose($h);
                }
            });

        return $response;
    }

    public function approve(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($tenantId <= 0 || $userId <= 0) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/content-moderation'));
        }
        $id = (int) ($params['id'] ?? 0);
        $artifact = $this->artifactRepository->findById($id, $tenantId);
        if (!$artifact || ($artifact['state'] ?? '') !== ModerationArtifactState::QUARANTINED) {
            Session::flash('error', 'Artefact introuvable ou déjà traité.');

            return Response::redirect(url('admin/content-moderation'));
        }
        $type = (string) ($artifact['source_type'] ?? '');
        try {
            if ($type === ModerationSourceType::FORUM_UPLOAD) {
                $this->releaseForumFile($artifact, $tenantId);
            } elseif ($type === ModerationSourceType::DOCUMENT_VERSION) {
                $result = $this->documentUploadService->approveQuarantinedDocumentArtifact($artifact, $tenantId, $userId, 'Approbation modérateur');
                $vid = (int) ($result['version_id'] ?? 0);
                $fp = (string) ($result['file_path'] ?? '');
                if ($vid > 0 && $fp !== '') {
                    $this->artifactRepository->markApprovedOverride($id, $tenantId, $vid, 'documents/' . $fp);
                }
            } else {
                Session::flash('error', 'Type d\'artefact non géré pour approbation automatique.');

                return Response::redirect(url('admin/content-moderation'));
            }
            $this->decisionRepository->insert($id, $userId, 'approve_override', 'moderator_ok', null);
            Session::flash('success', 'Contenu approuvé.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Erreur : ' . $e->getMessage());
        }

        return Response::redirect(url('admin/content-moderation'));
    }

    public function reject(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($tenantId <= 0 || $userId <= 0) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/content-moderation'));
        }
        $id = (int) ($params['id'] ?? 0);
        $artifact = $this->artifactRepository->findById($id, $tenantId);
        if (!$artifact) {
            Session::flash('error', 'Artefact introuvable.');

            return Response::redirect(url('admin/content-moderation'));
        }
        $note = trim((string) $request->input('note', ''));
        $rel = (string) ($artifact['file_path'] ?? '');
        $full = str_starts_with($rel, 'public/')
            ? base_path($rel)
            : base_path('storage/' . $rel);
        if (is_file($full)) {
            @unlink($full);
        }
        $this->artifactRepository->updateState($id, $tenantId, ModerationArtifactState::REJECTED);
        $this->decisionRepository->insert($id, $userId, 'reject', 'moderator_reject', $note !== '' ? $note : null);
        Session::flash('success', 'Contenu rejeté.');

        return Response::redirect(url('admin/content-moderation'));
    }

    /**
     * @param array<string, mixed> $artifact
     */
    private function releaseForumFile(array $artifact, int $tenantId): void
    {
        $rel = (string) ($artifact['file_path'] ?? '');
        $key = (string) ($artifact['source_key'] ?? '');
        if ($rel === '' || $key === '') {
            throw new \RuntimeException('Chemin artefact forum invalide.');
        }
        $src = base_path('storage/' . $rel);
        $destDir = base_path('public/uploads/forum');
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $dest = $destDir . DIRECTORY_SEPARATOR . $key;
        if (!is_file($src)) {
            throw new \RuntimeException('Fichier source absent.');
        }
        if (!@copy($src, $dest)) {
            throw new \RuntimeException('Impossible de publier le fichier.');
        }
        @unlink($src);
        $this->artifactRepository->markApprovedOverride(
            (int) $artifact['id'],
            $tenantId,
            0,
            'public/uploads/forum/' . $key
        );
    }

    private function previewDenied(): Response
    {
        return (new Response())
            ->setStatusCode(404)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('X-Content-Type-Options', 'nosniff')
            ->setBody('Aperçu indisponible.');
    }
}
