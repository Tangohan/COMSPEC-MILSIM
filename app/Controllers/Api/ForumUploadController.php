<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ModerationArtifactRepository;
use App\Services\Moderation\ContentModerationConfig;
use App\Services\Moderation\ContentModerationOrchestrator;
use App\Services\Moderation\ModerationArtifactState;
use App\Services\Moderation\ModerationSourceType;

class ForumUploadController
{
    private const MAX_FILES = 5;
    private const MAX_SIZE = 5 * 1024 * 1024; // 5 Mo
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];

    public function __construct(
        private ContentModerationOrchestrator $moderationOrchestrator,
        private ModerationArtifactRepository $moderationArtifactRepository,
        private ContentModerationConfig $moderationConfig
    ) {
    }

    public function handle(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::json(['success' => false, 'error' => 'Non authentifié'], 401);
        }
        if ($request->method() !== 'POST') {
            return Response::json(['success' => false, 'error' => 'Méthode non autorisée'], 405);
        }
        $csrf = $request->input('_csrf_token') ?? '';
        if (!Csrf::validate($csrf)) {
            return Response::json(['success' => false, 'error' => 'Jeton CSRF invalide'], 403);
        }

        $raw = $_FILES['files'] ?? $_FILES['images'] ?? null;
        if (!$raw || empty($raw['name'])) {
            return Response::json(['success' => false, 'error' => 'Aucun fichier'], 400);
        }
        $files = [
            'name' => is_array($raw['name']) ? $raw['name'] : [$raw['name']],
            'type' => is_array($raw['type']) ? $raw['type'] : [$raw['type']],
            'tmp_name' => is_array($raw['tmp_name']) ? $raw['tmp_name'] : [$raw['tmp_name']],
            'error' => is_array($raw['error']) ? $raw['error'] : [$raw['error']],
            'size' => is_array($raw['size']) ? $raw['size'] : [$raw['size']],
        ];
        $count = count($files['name']);
        if ($count > self::MAX_FILES) {
            return Response::json(['success' => false, 'error' => 'Maximum ' . self::MAX_FILES . ' fichiers'], 400);
        }

        $webDir = base_path('public/uploads/forum');
        if (!is_dir($webDir)) {
            @mkdir($webDir, 0755, true);
        }
        $qDir = base_path('storage/quarantine/' . (int) $tenantId);
        if (!is_dir($qDir)) {
            @mkdir($qDir, 0755, true);
        }

        $maxSize = function_exists('forum_upload_max_bytes') ? forum_upload_max_bytes((int) $tenantId) : self::MAX_SIZE;
        $allowedMimes = function_exists('forum_upload_allowed_mimes') ? forum_upload_allowed_mimes((int) $tenantId) : self::ALLOWED_TYPES;
        $maxMo = max(1, (int) ceil($maxSize / (1024 * 1024)));

        $saved = [];
        $warnings = [];
        for ($i = 0; $i < $count; $i++) {
            $name = $files['name'][$i] ?? '';
            $tmp = $files['tmp_name'][$i] ?? '';
            $error = (int) ($files['error'][$i] ?? 0);
            $size = (int) ($files['size'][$i] ?? 0);
            if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                continue;
            }
            if ($size > $maxSize) {
                return Response::json(['success' => false, 'error' => 'Un fichier dépasse la taille maximale autorisée (' . $maxMo . ' Mo).'], 400);
            }
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->file($tmp);
            if (!is_string($detected) || !in_array($detected, $allowedMimes, true)) {
                return Response::json(['success' => false, 'error' => 'Ce type de fichier n’est pas accepté pour votre unité.'], 400);
            }
            $ext = match ($detected) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'application/pdf' => 'pdf',
                default => 'bin',
            };
            $publicId = uniqid('forum_', true) . '.' . $ext;
            $scanName = 'scan_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $scanFull = $qDir . DIRECTORY_SEPARATOR . $scanName;
            if (!@copy($tmp, $scanFull)) {
                $warnings[] = $name . ': copie échouée';

                continue;
            }

            $scan = $this->moderationOrchestrator->scanBinaryFile($scanFull, $detected, $name);
            if ($scan->state === ModerationArtifactState::REJECTED) {
                @unlink($scanFull);
                $warnings[] = $name . ': refusé par la modération';

                continue;
            }

            if ($scan->state === ModerationArtifactState::QUARANTINED) {
                $quarantineRel = 'quarantine/' . (int) $tenantId . '/' . $publicId;
                $quarantineFull = base_path('storage/' . $quarantineRel);
                if (!@rename($scanFull, $quarantineFull)) {
                    @unlink($scanFull);
                    $warnings[] = $name . ': erreur quarantaine';

                    continue;
                }
                $checksum = is_file($quarantineFull) ? (hash_file('sha256', $quarantineFull) ?: '') : '';
                $expires = (new \DateTimeImmutable())->modify('+' . $this->moderationConfig->quarantineTtlDays . ' days');
                if ($this->moderationArtifactRepository->tableExists()) {
                    $this->moderationArtifactRepository->insert((int) $tenantId, [
                        'user_id' => (int) $userId,
                        'source_type' => ModerationSourceType::FORUM_UPLOAD,
                        'source_id' => 0,
                        'source_key' => $publicId,
                        'file_path' => $quarantineRel,
                        'original_name' => basename((string) $name),
                        'mime' => $detected,
                        'sha256' => $checksum,
                        'state' => ModerationArtifactState::QUARANTINED,
                        'risk_score' => $scan->riskScore,
                        'reason_codes' => $scan->reasonCodes,
                        'scan_log' => $scan->scanLog,
                        'ruleset_version' => $this->moderationConfig->rulesetVersion,
                        'expires_at' => $expires->format('Y-m-d H:i:s'),
                    ]);
                }
                $warnings[] = $name . ': en attente de validation modérateur';

                continue;
            }

            $dest = $webDir . DIRECTORY_SEPARATOR . $publicId;
            if (!@copy($scanFull, $dest)) {
                @unlink($scanFull);
                $warnings[] = $name . ': enregistrement échoué';

                continue;
            }
            @unlink($scanFull);
            $saved[] = ['id' => $publicId, 'url' => url('uploads/forum/' . $publicId)];
            if ($this->moderationArtifactRepository->tableExists()) {
                $checksum = hash_file('sha256', $dest) ?: '';
                $this->moderationArtifactRepository->insert((int) $tenantId, [
                    'user_id' => (int) $userId,
                    'source_type' => ModerationSourceType::FORUM_UPLOAD,
                    'source_id' => 0,
                    'source_key' => $publicId,
                    'file_path' => 'public/uploads/forum/' . $publicId,
                    'original_name' => basename((string) $name),
                    'mime' => $detected,
                    'sha256' => $checksum,
                    'state' => ModerationArtifactState::CLEAN,
                    'risk_score' => $scan->riskScore,
                    'reason_codes' => $scan->reasonCodes,
                    'scan_log' => $scan->scanLog,
                    'ruleset_version' => $this->moderationConfig->rulesetVersion,
                    'expires_at' => null,
                ]);
            }
        }

        $payload = ['success' => true, 'files' => $saved];
        if ($warnings !== []) {
            $payload['warnings'] = $warnings;
        }
        if ($saved === [] && $warnings !== []) {
            $payload['success'] = false;
            $payload['error'] = 'Aucun fichier accepté. ' . implode(' ', $warnings);
        }

        return Response::json($payload);
    }
}
