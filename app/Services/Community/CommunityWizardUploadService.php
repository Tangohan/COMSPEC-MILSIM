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
        return $this->processUploadsWithFeedback($userId)['urls'];
    }

    /**
     * @return array{urls: array{wizard_logo_url?: string, wizard_public_banner_url?: string}, warnings: list<string>}
     */
    public function processUploadsWithFeedback(int $userId): array
    {
        if ($userId < 1) {
            return ['urls' => [], 'warnings' => []];
        }
        $urls = [];
        $warnings = [];
        $logo = $this->saveOne($_FILES['wizard_logo_file'] ?? null, $userId, 'logo', 'logo', $warnings);
        if ($logo !== null) {
            $urls['wizard_logo_url'] = $logo;
        }
        $banner = $this->saveOne($_FILES['wizard_public_banner_file'] ?? null, $userId, 'banner', 'bannière', $warnings);
        if ($banner !== null) {
            $urls['wizard_public_banner_url'] = $banner;
        }

        return ['urls' => $urls, 'warnings' => $warnings];
    }

    /**
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int}|null $file
     * @param list<string> $warnings
     */
    private function saveOne(?array $file, int $userId, string $prefix, string $label, array &$warnings): ?string
    {
        if (!$file || !isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $err = (int) $file['error'];
        if ($err !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            $warnings[] = $this->uploadErrorMessage($err, $label);

            return null;
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            $warnings[] = 'Le fichier « ' . $label . ' » est trop volumineux (3 Mo maximum).';

            return null;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string) $file['tmp_name']);
        if (!is_string($mime) || !in_array($mime, self::ALLOWED_MIMES, true)) {
            $warnings[] = 'Le fichier « ' . $label . ' » n’est pas pris en charge. Utilisez une image JPG, PNG, WebP ou GIF.';

            return null;
        }
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'bin',
        };
        $dirFs = public_uploads_path('community-wizard/' . $userId);
        if (!is_dir($dirFs) && !@mkdir($dirFs, 0755, true)) {
            $warnings[] = 'L’image « ' . $label . ' » n’a pas pu être enregistrée. Réessayez plus tard.';

            return null;
        }
        $name = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destFs = $dirFs . DIRECTORY_SEPARATOR . $name;
        if (!@move_uploaded_file((string) $file['tmp_name'], $destFs)) {
            $warnings[] = 'L’image « ' . $label . ' » n’a pas pu être enregistrée. Réessayez avec un autre fichier.';

            return null;
        }
        $rel = 'uploads/community-wizard/' . $userId . '/' . $name;

        return function_exists('url') ? url($rel) : $rel;
    }

    private function uploadErrorMessage(int $code, string $label): string
    {
        if (in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return 'Le fichier « ' . $label . ' » est trop volumineux (3 Mo maximum).';
        }

        return 'Le fichier « ' . $label . ' » n’a pas pu être envoyé. Réessayez avec un autre fichier.';
    }
}
