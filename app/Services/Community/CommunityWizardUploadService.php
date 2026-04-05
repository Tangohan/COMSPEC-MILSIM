<?php

declare(strict_types=1);

namespace App\Services\Community;

/**
 * Images logo / bannière pour l’assistant de création de communauté (fichiers locaux → URL publique).
 */
final class CommunityWizardUploadService
{
    private const MAX_BYTES = 3 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /**
     * Traite $_FILES pour logo et bannière ; retourne des clés wizard_* seulement si un fichier valide a été enregistré.
     *
     * @return array{wizard_logo_url?: string, wizard_public_banner_url?: string}
     */
    public function processUploads(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }
        $out = [];
        $logo = $this->saveOne($_FILES['wizard_logo_file'] ?? null, $userId, 'logo');
        if ($logo !== null) {
            $out['wizard_logo_url'] = $logo;
        }
        $banner = $this->saveOne($_FILES['wizard_public_banner_file'] ?? null, $userId, 'banner');
        if ($banner !== null) {
            $out['wizard_public_banner_url'] = $banner;
        }

        return $out;
    }

    /**
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int}|null $file
     */
    private function saveOne(?array $file, int $userId, string $prefix): ?string
    {
        if (!$file || !isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            return null;
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            return null;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string) $file['tmp_name']);
        if (!is_string($mime) || !in_array($mime, self::ALLOWED_MIMES, true)) {
            return null;
        }
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'bin',
        };
        $dirFs = base_path('public/uploads/community-wizard/' . $userId);
        if (!is_dir($dirFs) && !@mkdir($dirFs, 0755, true)) {
            return null;
        }
        $name = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destFs = $dirFs . DIRECTORY_SEPARATOR . $name;
        if (!@move_uploaded_file((string) $file['tmp_name'], $destFs)) {
            return null;
        }
        $rel = 'uploads/community-wizard/' . $userId . '/' . $name;

        return function_exists('url') ? url($rel) : $rel;
    }
}
