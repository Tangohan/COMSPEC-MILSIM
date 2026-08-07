<?php

declare(strict_types=1);

namespace App\Services\Forum;

use App\Repositories\ForumAttachmentRepository;
use App\Repositories\ModerationArtifactRepository;

/**
 * Rattache à un message les fichiers issus de /api/forum-upload (clés forum_*.ext).
 */
final class ForumPostAttachmentService
{
    private const MAX_ATTACHMENTS = 5;

    private const FALLBACK_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
    ];

    public function __construct(
        private ForumAttachmentRepository $attachmentRepository,
        private ModerationArtifactRepository $moderationArtifactRepository
    ) {}

    /**
     * @param list<string|int|float> $sourceKeys
     */
    public function attachToPost(int $tenantId, int $postId, int $userId, array $sourceKeys): void
    {
        if (!$this->attachmentRepository->tableExists()) {
            return;
        }
        $allowedExt = function_exists('forum_allowed_upload_extensions')
            ? forum_allowed_upload_extensions($tenantId)
            : ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        $allowedMimes = function_exists('forum_upload_allowed_mimes')
            ? forum_upload_allowed_mimes($tenantId)
            : self::FALLBACK_MIMES;
        $keys = [];
        foreach ($sourceKeys as $k) {
            $k = basename((string) $k);
            if ($k !== '' && !in_array($k, $keys, true)) {
                $keys[] = $k;
            }
            if (count($keys) >= self::MAX_ATTACHMENTS) {
                break;
            }
        }
        foreach ($keys as $key) {
            if (!preg_match('/^forum_[a-zA-Z0-9_.]+\.([a-z0-9]+)$/i', $key, $xm)) {
                continue;
            }
            $extRaw = strtolower($xm[1]);
            if ($extRaw === 'jpeg') {
                $extRaw = 'jpg';
            }
            $extOk = in_array($extRaw, $allowedExt, true)
                || ($extRaw === 'jpg' && in_array('jpeg', $allowedExt, true));
            if (!$extOk) {
                continue;
            }
            $artifact = $this->moderationArtifactRepository->tableExists()
                ? $this->moderationArtifactRepository->findForumUploadByUserKey($tenantId, $userId, $key)
                : null;
            $relativePath = null;
            $mime = 'application/octet-stream';
            $size = 0;

            if ($artifact !== null) {
                $relativePath = (string) ($artifact['file_path'] ?? '');
                if ($relativePath === '') {
                    continue;
                }
                $mime = (string) ($artifact['mime'] ?? 'application/octet-stream');
                $full = base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));
                if (!is_file($full)) {
                    continue;
                }
                if (!in_array($mime, $allowedMimes, true)) {
                    continue;
                }
                $size = (int) filesize($full);
                $this->attachmentRepository->insert($tenantId, $postId, $relativePath, $mime, $size);
                $aid = (int) ($artifact['id'] ?? 0);
                if ($aid > 0) {
                    $this->moderationArtifactRepository->updateForumUploadSourcePostId($aid, $tenantId, $postId);
                }

                continue;
            }

            $publicRel = 'public/uploads/forum/' . $key;
            $full = public_uploads_path('forum/' . $key);
            if (!is_file($full)) {
                continue;
            }
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->file($full);
            if (!is_string($detected) || !in_array($detected, $allowedMimes, true)) {
                continue;
            }
            $size = (int) filesize($full);
            $this->attachmentRepository->insert($tenantId, $postId, $publicRel, $detected, $size);
        }
    }
}
