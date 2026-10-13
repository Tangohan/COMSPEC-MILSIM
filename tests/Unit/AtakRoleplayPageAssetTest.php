<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Page back-office « Mode roleplay » : mêmes champs postés qu’avant la refonte, ancres du catalogue
 * de mises à jour présentes, styles chargés. Garde aussi contre Session::flash() appelé en lecture.
 */
final class AtakRoleplayPageAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testViewKeepsFieldsAnchorsAndStyles(): void
    {
        $root = $this->root();
        $view = (string) file_get_contents($root . '/views/admin/atak/roleplay_enhanced.php');
        $pages = (string) file_get_contents($root . '/config/back_office_pages.php');

        self::assertFileExists($root . '/public/assets/css/back-office-atak-roleplay.css');
        self::assertStringContainsString("'back-office-atak-roleplay.css'", $pages);

        $fields = [
            '_csrf_token', 'network_enabled', 'network_mode', 'packet_loss_percent', 'latency_min_ms', 'latency_max_ms',
            'disconnect_enabled', 'disconnect_min_sec', 'disconnect_max_sec', 'disconnect_interval_sec',
            'link_via_relays', 'sensor_enabled', 'sensor_failure_percent', 'sensor_error_percent', 'sensor_missing_percent',
            'intel_scramble_enabled', 'zones_enabled', 'zone_center_x[]', 'zone_center_y[]', 'zone_radius[]', 'zone_effect[]',
        ];
        foreach ($fields as $field) {
            self::assertStringContainsString('name="' . $field . '"', $view);
        }
        // Liens du catalogue de mises à jour (ConfigurationUpdateCatalog).
        self::assertStringContainsString('id="intel-scramble"', $view);
        self::assertStringContainsString('id="liaison-relais"', $view);
        self::assertStringContainsString('api/atak/roleplay/server-tests', $view);
    }

    public function testNoSessionFlashReadWithOneArgument(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root() . '/app', \FilesystemIterator::SKIP_DOTS));
        $offenders = [];
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $src = (string) file_get_contents($file->getPathname());
            if (preg_match('/Session::flash\(\s*[\'"][^\'"]*[\'"]\s*\)/', $src)) {
                $offenders[] = $file->getFilename();
            }
        }
        self::assertSame([], $offenders, 'Session::flash() exige une valeur ; pour lire, utiliser Session::getFlash().');
    }
}
