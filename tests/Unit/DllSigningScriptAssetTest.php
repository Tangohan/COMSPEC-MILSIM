<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Procédure de signature des DLL du mod (réponse au signalement antivirus du Workshop). Le dépôt est public : le script ne
 * doit jamais embarquer de secret ni contourner la politique d'exécution de PowerShell.
 */
final class DllSigningScriptAssetTest extends TestCase
{
    private function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testScriptSignsBothDllsAndNeverTakesThePasswordAsAnArgument(): void
    {
        $ps = $this->read('tools/sign-mod-dlls.ps1');

        self::assertStringContainsString('COMSPECATAKNativeExtension_x64.dll', $ps);
        self::assertStringContainsString('COMSPECExtension_x64.dll', $ps);
        self::assertStringContainsString("'/fd', 'SHA256'", $ps);
        self::assertStringContainsString("'/td', 'SHA256'", $ps);
        self::assertStringContainsString('COMSPEC_SIGN_PFX_PASSWORD', $ps);
        self::assertStringContainsString('Get-AuthenticodeSignature', $ps);
        // Pas de paramètre mot de passe : il resterait dans l'historique du terminal.
        self::assertDoesNotMatchRegularExpression('/\[string\]\s*\$Password/i', $ps);
        self::assertStringNotContainsString('ExecutionPolicy', $ps);
        self::assertStringNotContainsString('Bypass', $ps);
        self::assertStringNotContainsString('.pfx"', $ps, 'aucun chemin de certificat en dur');
    }

    public function testNoCertificateFileIsTracked(): void
    {
        $gitignore = $this->read('.gitignore');

        foreach (['*.pfx', '*.p12'] as $pattern) {
            self::assertStringContainsString($pattern, $gitignore, $pattern . ' doit être ignoré');
        }
        self::assertFileExists(dirname(__DIR__, 2) . '/docs/securite/signature-dll.md');
    }

    public function testMailboxDefaultFollowsThePortalDomain(): void
    {
        $cfg = $this->read('config/contact_mail.php');

        self::assertStringContainsString("'contact@athena-comspec.eu'", $cfg);
        self::assertStringNotContainsString('contact@athena.ttrd.fr', $cfg);
    }
}
