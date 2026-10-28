<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
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
 * File d'attente modération contenus (quarantaine, documents, courrier texte).
 */
final class ContentModerationController
{
    public function __construct(
        private ModerationArtifactRepository $artifactRepository,
        private ModerationDecisionRepository $decisionRepository,
        private DocumentUploadService $documentUploadService,
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
                'moduleLabels' => ModerationRestrictionsCatalog::moduleLabels(),
            ]);
        }
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 30;
        $artifacts = $this->artifactRepository->listQueue($tenantId, null, $page, $perPage);
        $total = $this->artifactRepository->countQueue($tenantId, null);
        $userIds = [];
        foreach ($artifacts as $a) {
            if ((int) ($a['user_id'] ?? 0) > 0) {
                $userIds[] = (int) $a['user_id'];
            }
        }
        $usersById = $this->userRepository->findByIdsForTenant($tenantId, $userIds);
        foreach ($artifacts as &$a) {
            if (!empty($a['reason_codes']) && is_string($a['reason_codes'])) {
                $a['reason_codes'] = json_decode($a['reason_codes'], true) ?: [];
            }
            if (!empty($a['scan_log']) && is_string($a['scan_log'])) {
                $a['scan_log'] = json_decode($a['scan_log'], true) ?: [];
            }
            $this->attachModerationContext($a, $usersById);
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
            'moduleLabels' => ModerationRestrictionsCatalog::moduleLabels(),
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

            return Response::redirect(url('back-office/content-moderation'));
        }
        $id = (int) ($params['id'] ?? 0);
        $artifact = $this->artifactRepository->findById($id, $tenantId);
        if (!$artifact || ($artifact['state'] ?? '') !== ModerationArtifactState::QUARANTINED) {
            Session::flash('error', 'Artefact introuvable ou déjà traité.');

            return Response::redirect(url('back-office/content-moderation'));
        }
        $type = (string) ($artifact['source_type'] ?? '');
        try {
            if ($type === ModerationSourceType::DOCUMENT_VERSION) {
                $result = $this->documentUploadService->approveQuarantinedDocumentArtifact($artifact, $tenantId, $userId, 'Approbation modérateur');
                $vid = (int) ($result['version_id'] ?? 0);
                $fp = (string) ($result['file_path'] ?? '');
                if ($vid > 0 && $fp !== '') {
                    $this->artifactRepository->markApprovedOverride($id, $tenantId, $vid, 'documents/' . $fp);
                }
            } else {
                Session::flash('error', 'Type d\'artefact non géré pour approbation automatique.');

                return Response::redirect(url('back-office/content-moderation'));
            }
            $this->decisionRepository->insert($id, $userId, 'approve_override', 'moderator_ok', null);
            Session::flash('success', 'Contenu approuvé.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Erreur : ' . $e->getMessage());
        }

        return Response::redirect(url('back-office/content-moderation'));
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

            return Response::redirect(url('back-office/content-moderation'));
        }
        $id = (int) ($params['id'] ?? 0);
        $artifact = $this->artifactRepository->findById($id, $tenantId);
        if (!$artifact) {
            Session::flash('error', 'Artefact introuvable.');

            return Response::redirect(url('back-office/content-moderation'));
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

        return Response::redirect(url('back-office/content-moderation'));
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
        $redirect = Response::redirect(url('back-office/content-moderation'));
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
     * @param array<int, array<string, mixed>> $usersById
     */
    private function attachModerationContext(array &$artifact, array $usersById): void
    {
        $uid = (int) ($artifact['user_id'] ?? 0);
        $artifact['uploader_label'] = null;
        if ($uid > 0 && isset($usersById[$uid])) {
            $artifact['uploader_label'] = $this->memberDisplayLabel($usersById[$uid]);
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
}
