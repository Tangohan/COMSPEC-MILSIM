<?php

declare(strict_types=1);

namespace App\Services\Moderation;

/**
 * Scan antivirus via binaire ClamAV (clamscan). Si binaire absent, retourne propre.
 */
final class ClamAvScanner
{
    public function __construct(
        private ContentModerationConfig $config
    ) {
    }

    /**
     * @return array{infected: bool, skipped: bool, detail: string}
     */
    public function scanFile(string $absolutePath): array
    {
        if (!$this->config->enabled || $this->config->clamavBin === null) {
            return ['infected' => false, 'skipped' => true, 'detail' => 'clamav_disabled'];
        }
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return ['infected' => false, 'skipped' => true, 'detail' => 'file_unreadable'];
        }
        $bin = $this->config->clamavBin;
        if (!is_executable($bin)) {
            return ['infected' => false, 'skipped' => true, 'detail' => 'clamav_not_executable'];
        }
        $cmd = sprintf('%s --no-summary %s 2>&1', escapeshellcmd($bin), escapeshellarg($absolutePath));
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);
        $out = implode("\n", $output);
        // 0 = OK, 1 = infected, 2 = error
        if ($code === 1) {
            return ['infected' => true, 'skipped' => false, 'detail' => $out !== '' ? $out : 'infected'];
        }

        return ['infected' => false, 'skipped' => false, 'detail' => $code === 2 ? 'scan_error:' . $out : 'ok'];
    }
}
