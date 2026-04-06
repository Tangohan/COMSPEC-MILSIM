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

    private const ALLOWED_MIMES = [
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
            if (!preg_match('/^forum_[a-zA-Z0-9_.]+\.(jpe?g|png|gif|webp|pdf)$/i', $key)) {
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
                if (!in_array($mime, self::ALLOWED_MIMES, true)) {
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
            $full = base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $publicRel));
            if (!is_file($full)) {
                continue;
            }
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->file($full);
            if (!is_string($detected) || !in_array($detected, self::ALLOWED_MIMES, true)) {
                continue;
            }
            $size = (int) filesize($full);
            $this->attachmentRepository->insert($tenantId, $postId, $publicRel, $detected, $size);
        }
    }
}
