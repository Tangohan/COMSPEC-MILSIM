<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SteamWorkshop;
use PHPUnit\Framework\TestCase;

/**
 * ARCH-021 : le pack hérité mod/UptoDate/@COMSPECOverwatch n'a plus d'objet Workshop. L'objet 3684656708 est l'ATAK
 * natif, publié seulement depuis mod/COMSPEC_ATAK_Native. Contrôles statiques des scripts hérités : ni cmd.exe ni
 * PowerShell ne les exécutent ici.
 */
final class OverwatchLegacyPackWorkshopGuardAssetTest extends TestCase
{
    private function rootPath(string $relative): string
    {
        return dirname(__DIR__, 2) . '/' . $relative;
    }

    private function legacyPath(string $relative): string
    {
        return $this->rootPath('mod/UptoDate/' . $relative);
    }

    /** Contenu du fichier, fins de ligne ramenées à LF (une copie Windows peut être en CRLF). */
    private function read(string $relative): string
    {
        $path = $this->legacyPath($relative);
        self::assertFileExists($path);

        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    /** Corps d'une fonction PowerShell, accolades comptées depuis « function <nom> ». */
    private static function psFunctionBody(string $ps, string $name): string
    {
        $start = strpos($ps, 'function ' . $name);
        self::assertNotFalse($start, 'fonction ' . $name . ' absente');
        $open = strpos($ps, '{', $start);
        self::assertNotFalse($open, 'corps de ' . $name . ' absent');
        $depth = 0;
        for ($i = $open, $n = strlen($ps); $i < $n; $i++) {
            if ($ps[$i] === '{') {
                $depth++;
            } elseif ($ps[$i] === '}' && --$depth === 0) {
                return substr($ps, $open, $i - $open + 1);
            }
        }
        self::fail('accolade fermante absente pour ' . $name);
    }

    public function testLegacyMetaCppNoLongerTargetsTheAtakNativeWorkshopItem(): void
    {
        // Le Publisher d'Arma 3 met à jour l'objet dont meta.cpp porte le numéro (mod/docs/PUBLICATION.md).
        $path = $this->legacyPath('@COMSPECOverwatch/meta.cpp');
        $meta = is_file($path) ? (string) file_get_contents($path) : '';

        self::assertStringNotContainsString(SteamWorkshop::ATAK_NATIVE_ID, $meta);
        self::assertDoesNotMatchRegularExpression('/publishedid/i', $meta);
    }

    public function testNoMetaCppOutsideTheAtakNativeFolderTargetsItsWorkshopItem(): void
    {
        // Critère de clôture d'ARCH-021 : hors de mod/COMSPEC_ATAK_Native, aucun meta.cpp ne vise l'objet de l'ATAK
        // natif. Cela vaut pour le pack hérité comme pour les instantanés de mod/Workshop, qui sont des dossiers de
        // mod complets, prêts pour le Publisher.
        $mod = $this->rootPath('mod');
        self::assertDirectoryExists($mod);
        $relative = static fn (string $path): string => str_replace('\\', '/', substr($path, strlen($mod) + 1));
        // Dossier de l'ATAK natif, puis dossiers exclus par .gitignore (archives locales, sorties des scripts de
        // pack) : aucun meta.cpp suivi n'y est, et une copie locale ne doit pas faire échouer le test.
        $skipped = ['COMSPEC_ATAK_Native', 'Ancienne version de tout', '_local_pre_pr150_backup', 'UptoDate/publisher', 'publisher'];
        $files = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($mod, \FilesystemIterator::SKIP_DOTS),
            static fn (\SplFileInfo $entry): bool => !in_array($relative($entry->getPathname()), $skipped, true),
        ));

        $visitedLegacyScript = false;
        $offending = [];
        foreach ($files as $file) {
            $path = $relative($file->getPathname());
            $visitedLegacyScript = $visitedLegacyScript || $path === 'UptoDate/workshop-pack.ps1';
            if (strcasecmp($file->getFilename(), 'meta.cpp') === 0
                && str_contains((string) file_get_contents($file->getPathname()), SteamWorkshop::ATAK_NATIVE_ID)) {
                $offending[] = 'mod/' . $path;
            }
        }

        // Le parcours a bien couvert mod/ : sans cela, le test passerait à vide.
        self::assertTrue($visitedLegacyScript, 'parcours de mod/ incomplet : mod/UptoDate/workshop-pack.ps1 non vu');
        sort($offending);
        self::assertSame([], $offending, 'meta.cpp qui visent encore l\'objet ' . SteamWorkshop::ATAK_NATIVE_ID);
    }

    public function testWorkshopPackRefusesAnOldLinkDllAndTheAtakNativeMetaCpp(): void
    {
        $ps = $this->read('workshop-pack.ps1');

        // DLL : ProductVersion lue par FileVersionInfo, refusée sous le plancher SEC-001 ou illisible.
        self::assertStringContainsString("\$MinLinkVersion = [version]'2.0.96'", $ps);
        self::assertStringContainsString('[System.Diagnostics.FileVersionInfo]::GetVersionInfo((Get-Item -LiteralPath $dll).FullName).ProductVersion', $ps);
        self::assertMatchesRegularExpression('/^if \(\$null -eq \$dllVersion\) \{\n    Write-Fail /m', $ps);
        self::assertMatchesRegularExpression('/^if \(\$dllVersion -lt \$MinLinkVersion\) \{\n    Write-Fail /m', $ps);

        // meta.cpp : tout meta.cpp qui vise l'objet de l'ATAK natif est refusé.
        self::assertStringContainsString("\$AtakNativeWorkshopId = '" . SteamWorkshop::ATAK_NATIVE_ID . "'", $ps);
        self::assertMatchesRegularExpression('/^    if \(\$metaText -match "\\\\b\$AtakNativeWorkshopId\\\\b"\) \{\n        Write-Fail /m', $ps);

        // Chaque refus passe par Write-Fail, qui sort avec un code non nul.
        self::assertMatchesRegularExpression('/\bexit 1\b/', self::psFunctionBody($ps, 'Write-Fail'));

        // Les deux refus tombent avant la préparation du pack : rien n'est copié dans le dossier à publier.
        $staging = strpos($ps, 'Write-Step "Nettoyage staging');
        self::assertNotFalse($staging);
        $metaCheck = strpos($ps, '$metaText -match');
        $versionCheck = strpos($ps, '$dllVersion -lt $MinLinkVersion');
        self::assertNotFalse($metaCheck);
        self::assertNotFalse($versionCheck);
        self::assertLessThan($staging, $metaCheck);
        self::assertLessThan($staging, $versionCheck);
    }

    public function testLegacyBuildNoLongerDeploysIntoTheAtakNativeWorkshopFolder(): void
    {
        $bat = $this->read('build_mod.bat');

        self::assertStringNotContainsString(SteamWorkshop::ATAK_NATIVE_ID, $bat);
        self::assertStringNotContainsString('WORKSHOP_CONTENT', $bat);
        self::assertStringNotContainsString('workshop\content\107410', $bat);
        // !Workshop\@COMSPECOverwatch était la jonction du lanceur vers ce même dossier Workshop.
        self::assertStringNotContainsString('WORKSHOP_MOD', $bat);
        self::assertStringContainsString('    for %%T in ("%LOCAL_MOD%" "%SOAR_FN%") do (', $bat);
    }

    public function testLegacyBuildFailsWhenTheFreshDllIsMissing(): void
    {
        $bat = $this->read('build_mod.bat');

        self::assertStringNotContainsString('[WARN] DLL Native AOT non trouvee', $bat);
        // Blocs sans parenthèse dans les echo : une « ) » fermerait le bloc avant le goto.
        self::assertMatchesRegularExpression('/^if not defined DLL_SRC \(\n(?:    echo [^\n()]*\n)+    goto :build_fail\n\)$/m', $bat);
        self::assertMatchesRegularExpression('/^copy \/Y "%DLL_SRC%" "%OUTPUT_DIR%\\\\COMSPECExtension_x64\.dll" >> "%BUILD_LOG%" 2>&1 \|\| \(\n(?:    echo [^\n()]*\n)+    goto :build_fail\n\)$/m', $bat);

        // Le refus tombe avant le déploiement local et avant « BUILD REUSSI ».
        $missing = strpos($bat, 'if not defined DLL_SRC (');
        self::assertNotFalse($missing);
        self::assertLessThan((int) strpos($bat, '[DEPLOY] Synchronisation'), $missing);
        self::assertLessThan((int) strpos($bat, 'BUILD REUSSI'), $missing);
    }

    public function testLegacyBuildGotoTargetsExist(): void
    {
        $bat = $this->read('build_mod.bat');

        preg_match_all('/\bgoto\s+:?(\w+)/i', $bat, $gotos);
        self::assertContains('build_fail', $gotos[1]);
        foreach (array_unique($gotos[1]) as $label) {
            if (strtolower($label) === 'eof') {
                continue;
            }
            self::assertMatchesRegularExpression('/^:' . preg_quote($label, '/') . '$/m', $bat, 'étiquette :' . $label . ' absente');
        }
    }
}
