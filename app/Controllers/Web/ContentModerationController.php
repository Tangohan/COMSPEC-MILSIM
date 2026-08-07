<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ForumPostRepository;
use App\Repositories\ModerationArtifactRepository;
use App\Repositories\ModerationDecisionRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Documents\DocumentUploadService;
use App\Services\Moderation\ModerationArtifactState;
use App\Services\Moderation\ModerationRestrictionsCatalog;
use App\Services\Moderation\ModerationService;
use App\Services\Moderation\ModerationSourceType;

/**
 * File d'attente modération contenus (quarantaine, fichiers forum, courrier texte).
 */
final class ContentModerationController
{
    public function __construct(
        private ModerationArtifactRepository $artifactRepository,
        private ModerationDecisionRepository $decisionRepository,
        private DocumentUploadService $documentUploadService,
        private ForumPostRepository $forumPostRepository,
        private UserRepository $userRepository,
        private ModerationService $moderationService,
        private AuthService $authService
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
                'moduleLabels' => ModerationRestrictionsCatalog::moduleLabels(),
            ]);
        }
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 30;
        $artifacts = $this->artifactRepository->listQueue($tenantId, null, $page, $perPage);
        $total = $this->artifactRepository->countQueue($tenantId, null);
        $recentForumPublished = $this->artifactRepository->listRecentPublishedForumUploads($tenantId, 15);
        $postIds = [];
        $userIds = [];
        foreach ($artifacts as $a) {
            if (($a['source_type'] ?? '') === ModerationSourceType::FORUM_UPLOAD && (int) ($a['source_id'] ?? 0) > 0) {
                $postIds[] = (int) $a['source_id'];
            }
            if ((int) ($a['user_id'] ?? 0) > 0) {
                $userIds[] = (int) $a['user_id'];
            }
        }
        foreach ($recentForumPublished as $r) {
            if (($r['source_type'] ?? '') === ModerationSourceType::FORUM_UPLOAD && (int) ($r['source_id'] ?? 0) > 0) {
                $postIds[] = (int) $r['source_id'];
            }
        }
        $briefsByPost = $this->forumPostRepository->findTopicBriefsForPosts($postIds, $tenantId);
        $usersById = $this->userRepository->findByIdsForTenant($tenantId, $userIds);
        foreach ($artifacts as &$a) {
            if (!empty($a['reason_codes']) && is_string($a['reason_codes'])) {
                $a['reason_codes'] = json_decode($a['reason_codes'], true) ?: [];
            }
            if (!empty($a['scan_log']) && is_string($a['scan_log'])) {
                $a['scan_log'] = json_decode($a['scan_log'], true) ?: [];
            }
            $this->attachModerationContext($a, $briefsByPost, $usersById);
        }
        unset($a);
        foreach ($recentForumPublished as &$r) {
            $pid = (int) ($r['source_id'] ?? 0);
            $r['forum_context'] = ($pid > 0 && isset($briefsByPost[$pid])) ? $briefsByPost[$pid] : null;
        }
        unset($r);

        return Response::view('layout.main', [
            'content' => 'admin.content_moderation_index',
            'title' => 'Modération fichiers & quarantaine',
            'artifacts' => $artifacts,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'missingTables' => false,
            'recentForumPublished' => $recentForumPublished,
            'moduleLabels' => ModerationRestrictionsCatalog::moduleLabels(),
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

    public function warnUploader(Request $request, array $params = []): Response
    {
        return $this->applyMemberSanction($request, $params, 'warn');
    }

    public function restrictUploader(Request $request, array $params = []): Response
    {
        return $this->applyMemberSanction($request, $params, 'restriction');
    }

    private function applyMemberSanction(Request $request, array $params, string $mode): Response
    {
        $redirect = Response::redirect(url('admin/content-moderation'));
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return $redirect;
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $actor = $this->authService->user();
        if ($tenantId <= 0 || !$actor) {
            return Response::redirect(url('login'));
        }
        $actorId = (int) ($actor['id'] ?? 0);
        $id = (int) ($params['id'] ?? 0);
        $artifact = $this->artifactRepository->findById($id, $tenantId);
        $state = (string) ($artifact['state'] ?? '');
        if (
            !$artifact
            || !in_array($state, [ModerationArtifactState::QUARANTINED, ModerationArtifactState::PENDING_SCAN], true)
        ) {
            Session::flash('error', 'Élément introuvable ou déjà traité.');

            return $redirect;
        }
        $targetId = (int) ($artifact['user_id'] ?? 0);
        if ($targetId <= 0 || $targetId === $actorId) {
            Session::flash('error', 'Auteur du fichier introuvable ou action impossible sur vous-même.');

            return $redirect;
        }
        $reason = trim((string) $request->input('member_sanction_reason', ''));

        if ($mode === 'warn') {
            try {
                $this->moderationService->applySanction(
                    $tenantId,
                    $actorId,
                    $targetId,
                    'warn',
                    $reason !== '' ? $reason : null,
                    null,
                    [],
                    'tenant'
                );
                $this->decisionRepository->insert($id, $actorId, 'warn_uploader', 'tenant_warn', $reason !== '' ? $reason : null);
                Session::flash('success', 'Avertissement enregistré sur le dossier du membre.');
            } catch (\Throwable $e) {
                Session::flash('error', $e->getMessage());
            }

            return $redirect;
        }

        if ($mode !== 'restriction') {
            Session::flash('error', 'Type de mesure non reconnu.');

            return $redirect;
        }

        $modsIn = $request->input('modules_blocked');
        if (!is_array($modsIn)) {
            $modsIn = [];
        }
        $modsClean = array_values(array_intersect(
            array_map('strval', $modsIn),
            ModerationRestrictionsCatalog::moduleKeys()
        ));
        if ($modsClean === []) {
            Session::flash('error', 'Cochez au moins un domaine à restreindre (formations, documents, etc.).');

            return $redirect;
        }

        $expires = null;
        $durationMode = $request->input('duration_mode') === 'temporary' ? 'temporary' : 'permanent';
        if ($durationMode === 'temporary') {
            $days = max(1, (int) $request->input('duration_days'));
            $expires = (new \DateTimeImmutable())->modify('+' . $days . ' days');
        }

        $restrictions = [
            'account_lock' => false,
            'forum' => 'full_access',
            'messages_blocked' => false,
            'join_blocked' => false,
            'modules_blocked' => $modsClean,
        ];

        try {
            $this->moderationService->applySanction(
                $tenantId,
                $actorId,
                $targetId,
                'mute',
                $reason !== '' ? $reason : null,
                $expires,
                $restrictions,
                'tenant'
            );
            $this->decisionRepository->insert($id, $actorId, 'restrict_uploader', 'tenant_mute', $reason !== '' ? $reason : null);
            Session::flash('success', 'Restriction d’activité enregistrée sur le membre.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return $redirect;
    }

    /**
     * @param array<string, mixed> $artifact
     * @param array<int, array{post_id: int, topic_id: int, topic_title: string}> $briefsByPost
     * @param array<int, array<string, mixed>> $usersById
     */
    private function attachModerationContext(array &$artifact, array $briefsByPost, array $usersById): void
    {
        $uid = (int) ($artifact['user_id'] ?? 0);
        $artifact['uploader_label'] = null;
        if ($uid > 0 && isset($usersById[$uid])) {
            $artifact['uploader_label'] = $this->memberDisplayLabel($usersById[$uid]);
        }
        $artifact['forum_context'] = null;
        if (($artifact['source_type'] ?? '') === ModerationSourceType::FORUM_UPLOAD) {
            $pid = (int) ($artifact['source_id'] ?? 0);
            if ($pid > 0 && isset($briefsByPost[$pid])) {
                $artifact['forum_context'] = $briefsByPost[$pid];
            }
        }
    }

    /**
     * @param array<string, mixed> $userRow
     */
    private function memberDisplayLabel(array $userRow): string
    {
        $d = trim((string) ($userRow['display_name'] ?? ''));
        if ($d !== '') {
            return $d;
        }

        return trim((string) ($userRow['email'] ?? '')) !== '' ? (string) $userRow['email'] : 'Membre';
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
        $destDir = public_uploads_path('forum');
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
