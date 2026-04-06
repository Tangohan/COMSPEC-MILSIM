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
            ]);
        }
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 30;
        $artifacts = $this->artifactRepository->listQueue($tenantId, null, $page, $perPage);
        $total = $this->artifactRepository->countQueue($tenantId, null);
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
        ]);
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
}
