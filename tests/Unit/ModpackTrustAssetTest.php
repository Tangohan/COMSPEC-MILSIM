<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Page publique du modpack : explication non alarmante du signalement antivirus, vérification (empreinte et
 * VirusTotal) et soutien. Les liens sortants portent rel="noopener noreferrer nofollow" et l'empreinte n'est
 * injectée dans l'adresse VirusTotal que si elle a bien la forme d'un SHA-256.
 */
final class ModpackTrustAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents($this->root() . '/' . $path);
    }

    public function testBothLanguagesCarryTheSameKeys(): void
    {
        $fr = require $this->root() . '/lang/fr/modpack.php';
        $en = require $this->root() . '/lang/en/modpack.php';

        self::assertSame([], array_keys(array_diff_key($fr, $en)), 'clés absentes de l’anglais');
        self::assertSame([], array_keys(array_diff_key($en, $fr)), 'clés absentes du français');
        foreach (['trust', 'flag_q', 'flag_a1', 'flag_a2', 'flag_a3', 'vt_title', 'vt_hash', 'vt_upload', 'vt_read', 'donate_title', 'donate_cta', 'check', 'check_detail'] as $key) {
            self::assertNotSame('', trim((string) ($fr[$key] ?? '')), $key);
            self::assertNotSame($fr[$key], $en[$key], $key . ' est identique dans les deux langues');
        }
    }

    public function testTheFlagExplanationReassuresWithoutDenyingTheFacts(): void
    {
        $fr = require $this->root() . '/lang/fr/modpack.php';

        // Le motif n'est plus présenté comme « signalements en masse » : il s'agit d'un contrôle antivirus automatique.
        self::assertStringNotContainsString('signalements en masse', $fr['workshop_cause']);
        self::assertStringContainsString('antivirus', $fr['workshop_cause']);
        self::assertStringContainsString('pas la preuve d’un virus', $fr['workshop_cause']);
        self::assertStringContainsString('faux positif', $fr['flag_a2']);
        // Honnêteté : le fichier est bien non signé et se connecte à un service en ligne.
        self::assertStringContainsString('pas signé', $fr['flag_a2']);
        self::assertStringContainsString('prévenez-nous', $fr['vt_read']);
    }

    public function testViewOffersVerificationAndSupportSafely(): void
    {
        $view = $this->read('views/site/public_modpack.php');

        self::assertStringContainsString('id="securite"', $view);
        self::assertStringContainsString('https://www.virustotal.com/gui/file/', $view);
        self::assertStringContainsString('https://www.virustotal.com/gui/home/upload', $view);
        self::assertSame(3, substr_count($view, 'rel="noopener noreferrer nofollow"'));
        self::assertStringContainsString("preg_match('/\\A[a-f0-9]{64}\\z/'", $view);
        self::assertStringContainsString("url('soutenir-atak')", $view);
        self::assertStringContainsString("__('modpack.check')", $view);
        // Rien n'est écrit sans échappement dans ces blocs.
        self::assertSame(0, preg_match('/<\?=\s*__\(/', $view), 'toute sortie passe par $e()');
    }

    public function testDownloadKeepsItsHardeningHeaders(): void
    {
        $controller = $this->read('app/Controllers/Web/PublicModpackController.php');

        self::assertStringContainsString("preg_match('/\\A[a-f0-9]{32}\\z/'", $controller);
        self::assertStringContainsString("'Cache-Control', 'no-store, no-transform'", $controller);
        self::assertStringContainsString('ModpackVersion::downloadDisposition', $controller);
        self::assertStringContainsString("'ETag', '\"' . \$pack['sha256'] . '\"'", $controller);
    }
}
