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
    /** Garde du déploiement du build hérité : seul le pack de l'ATAK natif a cette DLL (N3). */
    private const NATIVE_TARGET_GUARD = 'if exist "%%~T\COMSPECATAKNativeExtension_x64.dll" (';

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

    /**
     * Membres de droite des affectations d'une variable PowerShell, dans l'ordre du fichier. Comme en PowerShell, le nom
     * est insensible à la casse ; « $script:<nom> », « ${<nom>} » et « $<nom> += … » comptent aussi.
     *
     * @return list<string>
     */
    private static function psAssignments(string $ps, string $name): array
    {
        $scope = '(?:(?:script|global|local|private):)?';
        $variable = '\$(?:\{' . $scope . preg_quote($name, '/') . '\}|' . $scope . preg_quote($name, '/') . '\b)';
        preg_match_all('/' . $variable . '\s*(?:[-+*\/%]|\?\?)?=(?!=)\s*([^\n;},]*)/i', $ps, $matches);

        return array_map('trim', $matches[1]);
    }

    /**
     * Lignes de code d'un script PowerShell, espaces de tête retirés : sans le bloc d'aide « <# … #> » ni les lignes de
     * commentaire, et sans le texte des messages. Sur une ligne qui commence par Write-Host, Write-Step, Write-Warn ou
     * Write-Fail, et sur ses lignes de suite (ligne précédente finie par « + »), chaque chaîne devient « "" ».
     *
     * @return list<string>
     */
    private static function psCodeLines(string $ps): array
    {
        $lines = [];
        $message = false;
        $ps = (string) preg_replace('/^\xEF\xBB\xBF/', '', $ps);
        foreach (explode("\n", (string) preg_replace('/<#.*?#>/s', '', $ps)) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $message = $message || preg_match('/^Write-(?:Host|Step|Warn|Fail)\b/i', $line) === 1;
            $lines[] = $message ? (string) preg_replace('/"(?:[^"`]|`.)*"|\'(?:[^\']|\'\')*\'/', '""', $line) : $line;
            $message = $message && str_ends_with($line, '+');
        }

        return $lines;
    }

    /**
     * Profondeur d'accolades avant chaque ligne de code (psCodeLines), comptée hors chaînes ; première occurrence de
     * chaque ligne. Une ligne de profondeur 0 est au niveau du script, hors de tout bloc.
     *
     * @return array<string, int>
     */
    private static function psDepths(string $ps): array
    {
        $depths = [];
        $depth = 0;
        foreach (self::psCodeLines($ps) as $line) {
            $depths[$line] ??= $depth;
            $bare = (string) preg_replace('/"(?:[^"`]|`.)*"|\'(?:[^\']|\'\')*\'/', '""', $line);
            $depth += substr_count($bare, '{') - substr_count($bare, '}');
        }

        return $depths;
    }

    /**
     * Lignes de commande d'un .bat, sans les commentaires « :: » ou « rem » ni les espaces de tête.
     *
     * @return list<string>
     */
    private static function batCommands(string $bat): array
    {
        $commands = [];
        foreach (explode("\n", $bat) as $line) {
            $line = trim($line);
            if ($line !== '' && !str_starts_with($line, '::') && preg_match('/^@?rem(\s|$)/i', $line) !== 1) {
                $commands[] = $line;
            }
        }

        return $commands;
    }

    /**
     * Profondeur de bloc avant chaque ligne de commande : parenthèses hors guillemets, « ^ » échappe. Celles d'un
     * echo ne comptent pas ; une « ) » d'echo dans un bloc est refusée par ailleurs.
     *
     * @param list<string> $commands
     * @return list<int>
     */
    private static function batDepths(array $commands): array
    {
        $depths = [];
        $depth = 0;
        foreach ($commands as $line) {
            $depths[] = $depth;
            if (preg_match('/^echo[\s.]/i', $line) === 1) {
                continue;
            }
            $quoted = false;
            for ($i = 0, $n = strlen($line); $i < $n; $i++) {
                if ($line[$i] === '^' && !$quoted) {
                    $i++;
                } elseif ($line[$i] === '"') {
                    $quoted = !$quoted;
                } elseif ($line[$i] === '(' && !$quoted) {
                    $depth++;
                } elseif ($line[$i] === ')' && !$quoted) {
                    $depth = max(0, $depth - 1);
                }
            }
        }

        return $depths;
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

        // meta.cpp : tout meta.cpp qui vise l'objet de l'ATAK natif est refusé. Le contrôle suit directement le seul
        // test d'existence du fichier : aucune condition ajoutée ne peut le sauter.
        self::assertStringContainsString("\$AtakNativeWorkshopId = '" . SteamWorkshop::ATAK_NATIVE_ID . "'", $ps);
        self::assertMatchesRegularExpression('/^if \(Test-Path -LiteralPath \$metaCpp\) \{\n    \$metaText = \[string\]\(Get-Content -LiteralPath \$metaCpp -Raw\)\n    if \(\$metaText -match "\\\\b\$AtakNativeWorkshopId\\\\b"\) \{\n        Write-Fail /m', $ps);

        // Chaque refus passe par Write-Fail, qui sort avec un code non nul.
        self::assertMatchesRegularExpression('/\bexit 1\b/', self::psFunctionBody($ps, 'Write-Fail'));

        // Les contrôles sont au premier niveau du try du script (W23), hors de tout autre bloc : aucune condition ajoutée
        // autour ne peut les sauter.
        $code = self::psCodeLines($ps);
        $depths = self::psDepths($ps);
        $try = array_search('try {', $code, true);
        $finally = array_search('} finally {', $code, true);
        self::assertIsInt($try);
        self::assertIsInt($finally);
        self::assertSame(0, $depths['try {'] ?? null, 'try du script dans un bloc');
        foreach (['if (Test-Path -LiteralPath $metaCpp) {', 'if (-not $dll) {', 'if ($null -eq $dllVersion) {', 'if ($dllVersion -lt $MinLinkVersion) {'] as $control) {
            $at = array_search($control, $code, true);
            self::assertIsInt($at, 'contrôle absent : ' . $control);
            self::assertTrue($at > $try && $at < $finally, 'contrôle hors du try : ' . $control);
            self::assertSame(1, $depths[$control] ?? null, 'contrôle dans un bloc : ' . $control);
        }

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

    public function testWorkshopPackChecksTheValuesItReadsAndShipsTheCheckedDll(): void
    {
        $ps = $this->read('workshop-pack.ps1');

        // Chaîne de données : chaque variable n'a que ces valeurs. La version comparée est celle lue dans la DLL
        // retenue, le texte testé est tout le meta.cpp du dossier source.
        self::assertSame(['""', "Join-Path \$ModRoot '@COMSPECOverwatch'"], self::psAssignments($ps, 'SourceMod'));
        // Le pack ne va que dans le dossier choisi par -OutDir, ou par défaut dans publisher\@COMSPECOverwatch.
        self::assertSame(['""', "Join-Path \$ModRoot 'publisher\\@COMSPECOverwatch'"], self::psAssignments($ps, 'OutDir'));
        self::assertSame(['$null', '$c'], self::psAssignments($ps, 'dll'));
        self::assertSame(['[string]([System.Diagnostics.FileVersionInfo]::GetVersionInfo((Get-Item -LiteralPath $dll).FullName).ProductVersion)'], self::psAssignments($ps, 'dllProductVersion'));
        self::assertSame(['$null', '[version]($Matches[1])'], self::psAssignments($ps, 'dllVersion'));
        self::assertStringContainsString("if (\$dllProductVersion -match '^\\s*(\\d+\\.\\d+(\\.\\d+){0,2})') { \$dllVersion = [version](\$Matches[1]) }", $ps);
        self::assertSame(["[version]'2.0.96'"], self::psAssignments($ps, 'MinLinkVersion'));
        self::assertSame(["'" . SteamWorkshop::ATAK_NATIVE_ID . "'"], self::psAssignments($ps, 'AtakNativeWorkshopId'));
        self::assertSame(['Join-Path $SourceMod "meta.cpp"', 'Join-Path $SourceMod "meta.cpp"'], self::psAssignments($ps, 'metaCpp'));
        self::assertSame(['[string](Get-Content -LiteralPath $metaCpp -Raw)'], self::psAssignments($ps, 'metaText'));

        // Ordre, pour meta.cpp comme pour la DLL : lecture, contrôle, puis seulement préparation du pack et copie de la
        // DLL contrôlée. Les deux contrôles peuvent venir dans un ordre ou dans l'autre.
        $staging = 'Write-Step "Nettoyage staging';
        $chains = [
            ['$metaCpp = Join-Path $SourceMod "meta.cpp"', '$metaText = ', '$metaText -match ', $staging],
            ['if (-not $dll) {', '$dllProductVersion = ', '$dllProductVersion -match ', 'if ($null -eq $dllVersion) {',
                'if ($dllVersion -lt $MinLinkVersion) {', $staging, 'Copy-Item -LiteralPath $dll -Destination'],
        ];
        foreach ($chains as $order) {
            $previous = -1;
            foreach ($order as $marker) {
                $at = strpos($ps, $marker);
                self::assertNotFalse($at, 'absent : ' . $marker);
                self::assertGreaterThan($previous, $at, 'hors d\'ordre : ' . $marker);
                $previous = $at;
            }
        }

        // La seule DLL copiée dans le pack est $dll, celle dont la version vient d'être contrôlée ; aucune copie par
        // motif ni récursive ne peut en glisser une autre. Les commentaires et le texte des messages ne comptent pas.
        $copies = array_values(array_filter(self::psCodeLines($ps), static fn (string $line): bool => stripos($line, 'Copy-Item') !== false));
        self::assertNotEmpty($copies);
        $dllCopies = array_values(array_filter($copies, static fn (string $line): bool => stripos($line, 'dll') !== false));
        self::assertSame(['Copy-Item -LiteralPath $dll -Destination (Join-Path $OutDir "COMSPECExtension_x64.dll") -Force'], $dllCopies);
        foreach ($copies as $copy) {
            self::assertMatchesRegularExpression('/Copy-Item -LiteralPath /', $copy);
            self::assertDoesNotMatchRegularExpression('/-Recurse\b/i', $copy);
        }

        // Un refus sort avec un code non nul : Write-Fail, définie une seule fois, finit par « exit 1 », et le script
        // n'a aucun autre « exit » qui rendrait 0.
        self::assertSame(1, substr_count($ps, 'function Write-Fail'));
        self::assertDoesNotMatchRegularExpression('/\breturn\b/i', self::psFunctionBody($ps, 'Write-Fail'));
        preg_match_all('/\bexit\b[ \t]*(\S*)/i', $ps, $exits);
        self::assertNotEmpty($exits[1]);
        foreach ($exits[1] as $code) {
            self::assertMatchesRegularExpression('/^[1-9]\d*$/', $code, 'exit sans code non nul');
        }
    }

    public function testWorkshopPackWritesOnlyThroughItsCheckedCopies(): void
    {
        $code = self::psCodeLines($this->read('workshop-pack.ps1'));
        self::assertGreaterThan(100, count($code), 'lignes de code du script introuvables');

        // Hors commentaires et messages : aucune copie hors Copy-Item (dont les contrôles précédents vérifient la
        // source), aucune autre écriture de fichier, aucun publishedid. Aucune variable ni fonction de contrôle
        // redéfinie par une autre syntaxe, et aucun autre script ou commande lancé depuis celui-ci.
        $forbidden = [
            'copie hors Copy-Item' => '/(?<![\w$.-])(?:copy|cp|cpi|robocopy|xcopy)(?![\w-])/i',
            'écriture de fichier' => '/(?<![\w$.-])(?:Set-Content|Add-Content|Out-File|sc|ac|Tee-Object|tee|Expand-Archive)(?![\w-])|-OutFile\b|>/i',
            'API de fichier .NET' => '/\[(?:System\.)?IO\.(?:File|FileInfo|FileStream|StreamWriter|Directory|DirectoryInfo)\]|\.(?:CopyTo|MoveTo)\s*\(/i',
            'publishedid' => '/publishedid\s*=/i',
            'déplacement' => '/(?<![\w$.-])(?:Move-Item|mv|mi|move|Rename-Item|ren|rni|Set-Item|si)(?![\w-])/i',
            'redéfinition' => '/(?<![\w$.-])(?:Set-Variable|New-Variable|Clear-Variable|Remove-Variable|sv|nv|clv|rv)(?![\w-])|\b(?:function|variable):/i',
            'appel' => '/(?<![\w$.-])(?:Invoke-Expression|iex|Start-Process|saps|start|Invoke-Item|ii|Invoke-Command|icm)(?![\w-])|&/i',
            'New-Item hors dossier' => '/(?<![\w$.-])(?:New-Item|ni)(?![\w-])(?![^\n]*-ItemType Directory\b)/i',
            // Au niveau du script, return et continue sortent avec le code 0, avant les contrôles qui suivent.
            'sortie anticipée' => '/(?<![\w$.-])(?:return|continue)(?![\w-])/i',
        ];
        $offending = [];
        foreach ($code as $line) {
            foreach ($forbidden as $what => $pattern) {
                if (preg_match($pattern, $line) === 1) {
                    $offending[] = $what . ' : ' . $line;
                }
            }
        }
        self::assertSame([], $offending, 'workshop-pack.ps1 écrit ou redéfinit hors des contrôles');
        // Un seul break : celui de la boucle des DLL candidates (hors boucle, break sort aussi du script).
        self::assertSame(['break'], array_values(array_filter($code, static fn (string $line): bool => preg_match('/(?<![\w$.-])break(?![\w-])/i', $line) === 1)));

        // L'objet de l'ATAK natif n'apparaît dans le code qu'à sa définition et dans les deux contrôles de meta.cpp :
        // celui de la source, puis celui de la sortie.
        $uses = array_values(array_filter($code, static fn (string $line): bool => stripos($line, 'AtakNativeWorkshopId') !== false
            || str_contains($line, SteamWorkshop::ATAK_NATIVE_ID)));
        self::assertSame([
            "\$AtakNativeWorkshopId = '" . SteamWorkshop::ATAK_NATIVE_ID . "'",
            'if ($metaText -match "\b$AtakNativeWorkshopId\b") {',
            'if ($packMetaText -match "\b$AtakNativeWorkshopId\b") {',
        ], $uses);
    }

    public function testWorkshopPackGuardsItsOutputFolderAndClearsARefusedOne(): void
    {
        $ps = $this->read('workshop-pack.ps1');

        // N8 (revue v2) : « Nettoyage staging » vide $OutDir. La sortie est refusée, avant tout autre contrôle, dans
        // le dossier de l'ATAK natif ou au-dessus de lui, et sous steamapps\workshop ou le !Workshop d'Arma 3.
        $nativeGuard = <<<'PS'
            $outDirKey = [System.IO.Path]::GetFullPath($ExecutionContext.SessionState.Path.GetUnresolvedProviderPathFromPSPath($OutDir)).TrimEnd('\', '/') + '\'
            $atakNativeKey = [System.IO.Path]::GetFullPath((Join-Path $ModRoot '..\COMSPEC_ATAK_Native')).TrimEnd('\', '/') + '\'
            $ignoreCase = [System.StringComparison]::OrdinalIgnoreCase
            if ($outDirKey.StartsWith($atakNativeKey, $ignoreCase) -or $atakNativeKey.StartsWith($outDirKey, $ignoreCase)) {
                Write-Fail (
            PS;
        $steamGuard = <<<'PS'
            if ($outDirKey -match '(?i)[\\/](steamapps[\\/]workshop|!Workshop)[\\/]') {
                Write-Fail (
            PS;
        self::assertStringContainsString($nativeGuard, $ps);
        self::assertStringContainsString($steamGuard, $ps);
        foreach (['outDirKey', 'atakNativeKey', 'ignoreCase'] as $name) {
            self::assertCount(1, self::psAssignments($ps, $name), '$' . $name . ' réaffectée');
        }
        self::assertSame(['$false', '$true'], self::psAssignments($ps, 'OutDirChecked'));
        $previous = -1;
        foreach (["if (-not \$OutDir) {", '$OutDirChecked = $false', '$outDirKey = ', '$outDirKey -match ', '$OutDirChecked = $true',
            'if (-not (Test-Path -LiteralPath $SourceMod)) {', 'Write-Step "Nettoyage staging'] as $marker) {
            $at = strpos($ps, $marker);
            self::assertNotFalse($at, 'absent : ' . $marker);
            self::assertGreaterThan($previous, $at, 'hors d\'ordre : ' . $marker);
            $previous = $at;
        }
        // Les deux « if » des gardes (lignes 3 et 5 ci-dessus) et le drapeau sont au niveau du script, hors de tout bloc.
        $guardLines = explode("\n", $nativeGuard . "\n" . $steamGuard);
        $depths = self::psDepths($ps);
        foreach ([$guardLines[3], $guardLines[5], '$OutDirChecked = $true'] as $line) {
            self::assertSame(0, $depths[$line] ?? null, 'garde absente ou dans un bloc : ' . $line);
        }

        // Une fois $OutDir contrôlé, un refus retire le meta.cpp de la sortie : un ancien pack, fait avant ARCH-021, y
        // gardait le publishedid de l'ATAK natif. Avant ce contrôle, Write-Fail ne touche pas à $OutDir.
        $removal = <<<'PS'
                if ($OutDirChecked -and (Test-Path -LiteralPath $OutDir)) {
            PS;
        $meta = <<<'PS'
                    $outMeta = Join-Path $OutDir "meta.cpp"
                    if (Test-Path -LiteralPath $outMeta -PathType Leaf) {
                        Remove-Item -LiteralPath $outMeta -Force -ErrorAction SilentlyContinue
            PS;
        $fail = self::psFunctionBody($ps, 'Write-Fail');
        self::assertStringContainsString($removal, $fail);
        self::assertStringContainsString($meta, $fail);
        self::assertLessThan((int) strpos($fail, $meta), (int) strpos($fail, $removal));
        self::assertSame(['Join-Path $OutDir "meta.cpp"'], self::psAssignments($ps, 'outMeta'));

        // Le script ne supprime rien d'autre que ce meta.cpp, le dossier de sortie au staging, l'ancien zip et, dans le
        // finally, le meta.cpp d'une sortie inachevée.
        $code = self::psCodeLines($ps);
        self::assertSame([
            'Remove-Item -LiteralPath $outMeta -Force -ErrorAction SilentlyContinue',
            'Remove-Item -LiteralPath $OutDir -Recurse -Force',
            'if (Test-Path -LiteralPath $zipPath) { Remove-Item -LiteralPath $zipPath -Force }',
            'Remove-Item -LiteralPath $leftMeta -Force -ErrorAction SilentlyContinue',
        ], array_values(array_filter($code, static fn (string $line): bool => stripos($line, 'Remove-Item') !== false)));
        self::assertSame([], array_values(array_filter($code, static fn (string $line): bool => preg_match('/(?<![\w$.-])(?:rm|del|erase|rd|rmdir|ri|Clear-Item|cli)(?![\w-])/i', $line) === 1)));
    }

    public function testWorkshopPackRereadsTheOutputMetaCppAndClearsItOnAnEarlyStop(): void
    {
        $ps = $this->read('workshop-pack.ps1');
        $code = self::psCodeLines($ps);
        $depths = self::psDepths($ps);

        // V3 (revue v3) : après la dernière copie, le meta.cpp de la sortie est relu, quel que soit le chemin qui l'y a
        // mis, et le pack est refusé s'il vise l'objet de l'ATAK natif.
        $check = <<<'PS'
            $packMeta = Join-Path $OutDir "meta.cpp"
            if (Test-Path -LiteralPath $packMeta) {
                $packMetaText = [string](Get-Content -LiteralPath $packMeta -Raw)
                if ($packMetaText -match "\b$AtakNativeWorkshopId\b") {
                    Write-Fail (
            PS;
        self::assertStringContainsString("\n" . $check, $ps);
        self::assertSame(['Join-Path $OutDir "meta.cpp"'], self::psAssignments($ps, 'packMeta'));
        self::assertSame(['[string](Get-Content -LiteralPath $packMeta -Raw)'], self::psAssignments($ps, 'packMetaText'));
        self::assertSame(1, $depths['if (Test-Path -LiteralPath $packMeta) {'] ?? null, 'contrôle final dans un bloc');

        // Il suit toute copie et toute création dans la sortie : après lui, seul le zip est écrit, à côté de $OutDir.
        $at = array_search('if ($packMetaText -match "\b$AtakNativeWorkshopId\b") {', $code, true);
        self::assertIsInt($at);
        self::assertSame(
            ['Compress-Archive -Path $OutDir -DestinationPath $zipPath -CompressionLevel Optimal'],
            array_values(array_filter(array_slice($code, $at), static fn (string $line): bool => preg_match('/(?<![\w$.-])(?:Copy-Item|cpi|New-Item|ni|Compress-Archive)(?![\w-])/i', $line) === 1)),
            'écriture dans la sortie après le contrôle final de meta.cpp',
        );
        // Le pack n'est fini qu'après ce contrôle : entre les deux, seulement le refus et ses accolades.
        self::assertSame(['$false', '$true'], self::psAssignments($ps, 'PackDone'));
        $done = array_search('$PackDone = $true', $code, true);
        self::assertIsInt($done);
        self::assertGreaterThan($at, $done);
        foreach (array_slice($code, $at + 1, $done - $at - 1) as $line) {
            self::assertMatchesRegularExpression('/^(?:Write-Fail \(""(?: \+)?|""\)(?: \+)?|\})$/', $line, 'entre le contrôle final et la fin du pack : ' . $line);
        }

        // W23 (revue v3) : après la garde de sortie, la suite du script tourne dans un seul try, sans catch ni trap. Son
        // finally, en fin de fichier, retire le meta.cpp d'une sortie inachevée : erreur qui arrête le script, refus de
        // Write-Fail ou Ctrl+C.
        $checked = array_search('$OutDirChecked = $true', $code, true);
        self::assertIsInt($checked);
        self::assertSame(['$PackDone = $false', 'try {'], array_slice($code, $checked + 1, 2));
        self::assertSame(0, $depths['try {'] ?? null);
        self::assertSame(['try {'], array_values(array_filter($code, static fn (string $line): bool => preg_match('/(?<![\w$.-])(?:try|catch|trap)(?![\w-])/i', $line) === 1)));
        $finally = array_search('} finally {', $code, true);
        self::assertIsInt($finally);
        self::assertGreaterThan($done, $finally);
        self::assertSame(1, $depths['} finally {'] ?? null);
        self::assertSame([
            '} finally {',
            'if (-not $PackDone -and (Test-Path -LiteralPath $OutDir)) {',
            '$leftMeta = Join-Path $OutDir "meta.cpp"',
            'if (Test-Path -LiteralPath $leftMeta -PathType Leaf) {',
            'Remove-Item -LiteralPath $leftMeta -Force -ErrorAction SilentlyContinue',
            'if (Test-Path -LiteralPath $leftMeta) {',
            'Write-Host "" -ForegroundColor Red',
            '} else {',
            'Write-Host "" -ForegroundColor Red',
            '}',
            '}',
            'Write-Host "" -ForegroundColor Red',
            '}',
            '}',
        ], array_slice($code, $finally), 'finally absent, incomplet ou suivi d\'autre code');
        self::assertSame(['Join-Path $OutDir "meta.cpp"'], self::psAssignments($ps, 'leftMeta'));

        // Seules ces lignes de code nomment meta.cpp : le retrait de Write-Fail, le contrôle et la copie de la source, le
        // contrôle final et le retrait du finally. Aucune ne garde ni ne remet le meta.cpp d'une sortie précédente.
        self::assertSame([
            '$outMeta = Join-Path $OutDir "meta.cpp"',
            '$metaCpp = Join-Path $SourceMod "meta.cpp"',
            '$metaCpp = Join-Path $SourceMod "meta.cpp"',
            'Copy-Item -LiteralPath $metaCpp -Destination (Join-Path $OutDir "meta.cpp") -Force',
            '$packMeta = Join-Path $OutDir "meta.cpp"',
            '$leftMeta = Join-Path $OutDir "meta.cpp"',
        ], array_values(array_filter($code, static fn (string $line): bool => stripos($line, 'meta.cpp') !== false)));
    }

    public function testLegacyBuildNoLongerDeploysIntoTheAtakNativeWorkshopFolder(): void
    {
        $bat = $this->read('build_mod.bat');

        self::assertStringNotContainsString(SteamWorkshop::ATAK_NATIVE_ID, $bat);
        self::assertStringNotContainsString('WORKSHOP_CONTENT', $bat);
        self::assertStringNotContainsString('workshop\content\107410', $bat);
        // !Workshop\@COMSPECOverwatch était la jonction du lanceur vers ce même dossier Workshop.
        self::assertStringNotContainsString('WORKSHOP_MOD', $bat);
        // Cibles gardées : le mod local et la copie SOAR FN, avec laquelle TTRD teste le mod.
        self::assertStringContainsString('    for %%T in ("%LOCAL_MOD%" "%SOAR_FN%") do (', $bat);
    }

    public function testLegacyBuildWritesOnlyIntoItsTwoLocalTargets(): void
    {
        $offending = [];
        $underArma = [];
        foreach (self::batCommands($this->read('build_mod.bat')) as $line) {
            $command = preg_match('/^echo[\s.]/i', $line) !== 1;
            // Aucun dossier Steam d'objet Workshop, aucune jonction !Workshop autre que la copie locale SOAR FN, et
            // aucune commande qui porte le nom du pack natif, sauf la garde qui refuse une cible où est sa DLL.
            if (preg_match('/steamapps\\\\workshop|workshop\\\\content|107410|!Workshop\\\\(?!@# S\.O\.A\.R - FN\b)/i', $line) === 1
                || ($command && $line !== self::NATIVE_TARGET_GUARD && preg_match('/atak[ _-]?native/i', $line) === 1)) {
                $offending[] = $line;
            }
            if ($command && str_contains($line, '%ARMA_PATH%')) {
                $underArma[] = $line;
            }
        }
        self::assertSame([], $offending, 'cibles interdites dans build_mod.bat');
        // Sous Arma 3, seulement les deux cibles du déploiement local.
        self::assertSame([
            'set "LOCAL_MOD=%ARMA_PATH%\%MOD_NAME%"',
            'set "SOAR_FN=%ARMA_PATH%\!Workshop\@# S.O.A.R - FN"',
            'if exist "%ARMA_PATH%" (',
        ], $underArma);
    }

    public function testLegacyBuildTakesTheDllOnlyFromTheDotnetOutput(): void
    {
        $bat = $this->read('build_mod.bat');

        // Candidats DLL : les sorties du dotnet publish qui vient de tourner, jamais une DLL suivie dans git.
        preg_match_all('/\bset "DLL_SRC=([^"]*)"/i', $bat, $sources);
        self::assertSame([
            '',
            '%DOTNET_PUBLISH_DIR%\COMSPECExtension_x64.dll',
            '%DOTNET_BUILD_DIR%\COMSPECExtension_x64.dll',
            '%OUTPUT_DIR%\net8.0\win-x64\native\COMSPECExtension_x64.dll',
        ], $sources[1]);
        self::assertStringContainsString('set "DOTNET_PUBLISH_DIR=%PROJECT_DIR%COMSPECExtension\bin\publish"', $bat);
        self::assertStringContainsString('set "DOTNET_BUILD_DIR=%PROJECT_DIR%COMSPECExtension\bin\Release\net8.0\win-x64\publish"', $bat);
        self::assertLessThan((int) strpos($bat, 'set "DLL_SRC="'), (int) strpos($bat, 'dotnet publish "%CS_PROJ_PATH%"'));

        // Une seule commande écrit %OUTPUT_DIR%\COMSPECExtension_x64.dll : la copie depuis %DLL_SRC%. Les autres
        // commandes qui nomment une DLL ou COMSPECExtension_x64 sont connues : les candidats, le nettoyage du .pdb, la
        // garde du pack natif et la copie de déploiement, qui lit cette DLL. Aucun xcopy, robocopy, move ni mklink, et
        // aucun chemin qui remonte au-dessus du dossier du projet par « ..\ ».
        $code = array_values(array_filter(self::batCommands($bat), static fn (string $line): bool => preg_match('/^echo[\s.]/i', $line) !== 1));
        self::assertSame([
            'if exist "%DOTNET_PUBLISH_DIR%\COMSPECExtension_x64.dll" set "DLL_SRC=%DOTNET_PUBLISH_DIR%\COMSPECExtension_x64.dll"',
            'if not defined DLL_SRC if exist "%DOTNET_BUILD_DIR%\COMSPECExtension_x64.dll" set "DLL_SRC=%DOTNET_BUILD_DIR%\COMSPECExtension_x64.dll"',
            'if not defined DLL_SRC if exist "%OUTPUT_DIR%\net8.0\win-x64\native\COMSPECExtension_x64.dll" set "DLL_SRC=%OUTPUT_DIR%\net8.0\win-x64\native\COMSPECExtension_x64.dll"',
            'copy /Y "%DLL_SRC%" "%OUTPUT_DIR%\COMSPECExtension_x64.dll" >> "%BUILD_LOG%" 2>&1 || (',
            'if exist "%OUTPUT_DIR%\COMSPECExtension_x64.pdb" del /F /Q "%OUTPUT_DIR%\COMSPECExtension_x64.pdb" >> "%BUILD_LOG%" 2>&1',
            self::NATIVE_TARGET_GUARD,
            'if exist "%OUTPUT_DIR%\COMSPECExtension_x64.dll" copy /Y "%OUTPUT_DIR%\COMSPECExtension_x64.dll" "%%~T\COMSPECExtension_x64.dll" >> "%BUILD_LOG%" 2>&1 || set "DEPLOY_FAIL=1"',
        ], array_values(array_filter($code, static fn (string $line): bool => preg_match('/COMSPECExtension_x64|\.dll\b/i', $line) === 1)));
        self::assertSame([], array_values(array_filter($code, static fn (string $line): bool => preg_match('/(?<![\w-])(?:xcopy|robocopy|move|mklink)(?![\w-])|\.\.\\\\/i', $line) === 1)));

        $ignore = str_replace("\r\n", "\n", (string) file_get_contents($this->rootPath('.gitignore')));
        // La DLL du dossier du mod est elle aussi une sortie de build : git ne suit plus l'ancienne 2.0.57.
        $rules = ['mod/UptoDate/COMSPECExtension/bin/', 'mod/UptoDate/@COMSPECOverwatch/net8.0/', 'mod/UptoDate/@COMSPECOverwatch/COMSPECExtension_x64.dll'];
        foreach ($rules as $rule) {
            self::assertMatchesRegularExpression('/^' . preg_quote($rule, '/') . '$/m', $ignore, '.gitignore sans ' . $rule);
        }
    }

    public function testGitignoreNoLongerSaysTheLegacyDllIsTracked(): void
    {
        // Témoin de fusion (F1, revue v2) : le lot cleanup ajoute à .gitignore « mod/UptoDate/@COMSPECOverwatch/ (PBO,
        // DLL, logos, mod.cpp) RESTE suivi ». Depuis ARCH-021, git ne suit plus cette DLL (règle plus haut).
        $ignore = str_replace("\r\n", "\n", (string) file_get_contents($this->rootPath('.gitignore')));

        self::assertDoesNotMatchRegularExpression(
            '/^#[^\n]*@COMSPECOverwatch\/[^\n]*\bDLL\b[^\n]*RESTE suivi/m',
            $ignore,
            '.gitignore dit encore la DLL de mod/UptoDate/@COMSPECOverwatch suivie : écrire « (PBO, logos, mod.cpp) » et '
            . 'ajouter « # Exception : la DLL COMSPEC Link de ce dossier n\'est plus suivie depuis ARCH-021 (règle plus haut). »',
        );
    }

    public function testLegacyBuildFailurePathExitsNonZero(): void
    {
        $bat = $this->read('build_mod.bat');
        $fail = strpos($bat, "\n:build_fail\n");
        self::assertNotFalse($fail);

        // Après :build_fail, chaque sortie rend un code non nul et rien ne rejoint le chemin de réussite.
        $failPath = substr($bat, $fail);
        preg_match_all('/\bexit\s+\/b\b[ \t]*(\S*)/i', $failPath, $failExits);
        self::assertNotEmpty($failExits[1]);
        foreach ($failExits[1] as $code) {
            self::assertMatchesRegularExpression('/^[1-9]\d*$/', $code, 'sortie du chemin d\'échec avec un code nul');
        }
        self::assertDoesNotMatchRegularExpression('/\bgoto\s+:?(build_done|eof)\b/i', $failPath);

        // Avant :build_fail, « exit /b 0 » n'existe qu'après :build_done, que l'on ne rejoint qu'après « BUILD REUSSI ».
        $okPath = substr($bat, 0, $fail);
        $done = strpos($okPath, "\n:build_done\n");
        self::assertNotFalse($done);
        preg_match_all('/\bexit\s+\/b\b[ \t]*(\S*)/i', $okPath, $okExits, PREG_OFFSET_CAPTURE);
        foreach ($okExits[1] as [$code, $offset]) {
            self::assertMatchesRegularExpression('/^\d+$/', $code, 'exit /b sans code explicite');
            if ($code === '0') {
                self::assertGreaterThan($done, $offset, 'exit /b 0 avant :build_done');
            }
        }
        preg_match_all('/\bgoto\s+:?build_done\b/i', $bat, $toDone, PREG_OFFSET_CAPTURE);
        foreach ($toDone[0] as [, $offset]) {
            self::assertGreaterThan((int) strpos($bat, 'BUILD REUSSI'), $offset, 'goto :build_done avant BUILD REUSSI');
        }
    }

    public function testLegacyBuildReportsOptionalAddonAndDeployFailures(): void
    {
        $bat = $this->read('build_mod.bat');
        $commands = self::batCommands($bat);
        $depths = self::batDepths($commands);
        self::assertSame(0, $depths[count($depths) - 1], 'parenthèses déséquilibrées avant la dernière ligne');

        // Dans un bloc, %ERRORLEVEL% est développé avant la commande qu'il devait contrôler : valeur figée.
        $frozen = [];
        foreach ($commands as $i => $line) {
            if ($depths[$i] > 0 && stripos($line, '%ERRORLEVEL%') !== false) {
                $frozen[] = $line;
            }
        }
        self::assertSame([], $frozen, '%ERRORLEVEL% lu dans un bloc');

        // Chaque appel d'AddonBuilder a un contrôle lu à l'exécution, qui voit aussi un code négatif (plantage) : « || »
        // en fin de ligne, ou « if %ERRORLEVEL% NEQ 0 ( » sur la ligne suivante, hors bloc. « if errorlevel 1 » laisse
        // passer un code négatif : il est refusé partout.
        $code = array_values(array_filter($commands, static fn (string $line): bool => preg_match('/^echo[\s.]/i', $line) !== 1));
        self::assertSame([], array_values(array_filter($code, static fn (string $line): bool => preg_match('/\bif\s+(?:not\s+)?errorlevel\b/i', $line) === 1)), '« if errorlevel » ne voit pas un code de sortie négatif');
        $builders = 0;
        foreach ($commands as $i => $line) {
            if (!str_starts_with($line, '"%BUILDER_PATH%"')) {
                continue;
            }
            $builders++;
            $next = $commands[$i + 1] ?? '';
            self::assertTrue(
                preg_match('/ \|\| (?:\(|goto :build_fail|exit \/b [1-9]\d*)$/', $line) === 1
                || ($depths[$i] === 0 && preg_match('/^if %ERRORLEVEL% NEQ 0 \($/i', $next) === 1),
                'AddonBuilder sans contrôle d\'échec lu à l\'exécution : ' . $line,
            );
        }
        self::assertGreaterThanOrEqual(5, $builders);

        // Déploiement : chaque copie vers une cible note son échec, et un échec fait échouer le build avant
        // « BUILD REUSSI ». Sans cela, une DLL verrouillée dans Arma 3\@COMSPECOverwatch restait l'ancienne.
        $loop = array_search('for %%T in ("%LOCAL_MOD%" "%SOAR_FN%") do (', $commands, true);
        self::assertIsInt($loop);
        $copies = [];
        for ($i = $loop + 1; $i < count($commands) && $depths[$i] > $depths[$loop]; $i++) {
            if (preg_match('/\bcopy\s/i', $commands[$i]) === 1) {
                $copies[] = $commands[$i];
            }
        }
        self::assertNotEmpty(array_filter($copies, static fn (string $copy): bool => str_contains($copy, '"%%~T\COMSPECExtension_x64.dll"')));
        foreach ($copies as $copy) {
            self::assertStringEndsWith(' >> "%BUILD_LOG%" 2>&1 || set "DEPLOY_FAIL=1"', $copy);
        }
        $reset = strpos($bat, "\nset \"DEPLOY_FAIL=\"\n");
        $check = strpos($bat, "\nif defined DEPLOY_FAIL (\n");
        self::assertNotFalse($reset);
        self::assertNotFalse($check);
        self::assertLessThan((int) strpos($bat, '    for %%T in ('), $reset);
        self::assertGreaterThan((int) strpos($bat, '    for %%T in ('), $check);
        self::assertLessThan((int) strpos($bat, 'BUILD REUSSI'), $check);
        self::assertMatchesRegularExpression('/^if defined DEPLOY_FAIL \(\n(?:    echo [^\n()]*\n)+    goto :build_fail\n\)$/m', $bat);

        // Le drapeau n'est remis à zéro qu'une fois, avant la boucle : une remise à zéro par cible effacerait l'échec
        // de la cible précédente. Ni setlocal ni endlocal : endlocal l'effacerait aussi, et l'expansion retardée
        // mangerait le « ! » de !Workshop.
        preg_match_all('/\bset\s+"?DEPLOY_FAIL=([^"\s]*)/i', implode("\n", $code), $flags);
        self::assertSame(1, count(array_keys($flags[1], '', true)), 'DEPLOY_FAIL remis à zéro plus d\'une fois');
        self::assertSame([], array_values(array_filter($code, static fn (string $line): bool => preg_match('/(?<![\w-])(?:setlocal|endlocal)(?![\w-])/i', $line) === 1)));
    }

    public function testLegacyBuildRefusesATargetThatHoldsTheAtakNativePack(): void
    {
        $bat = $this->read('build_mod.bat');

        // N3 (revue v2) : seul le pack de l'ATAK natif a COMSPECATAKNativeExtension_x64.dll. Une cible qui la contient
        // est refusée avant toute écriture, et le drapeau fait finir le build en échec. Une cible du pack hérité, dont
        // la copie SOAR FN avec laquelle TTRD teste le mod, est mise à jour comme avant.
        $guard = <<<'BAT'
                for %%T in ("%LOCAL_MOD%" "%SOAR_FN%") do (
                    if exist %%~T (
                        echo [DEPLOY] Cible: %%~T >> "%BUILD_LOG%"
                        echo [DEPLOY] Cible: %%~T
                        if exist "%%~T\COMSPECATAKNativeExtension_x64.dll" (
            BAT;
        self::assertStringContainsString($guard . "\n", $bat);
        self::assertMatchesRegularExpression('/^            if exist "%%~T\\\\COMSPECATAKNativeExtension_x64\.dll" \(\n(?:                echo [^\n()]*\n)+                set "DEPLOY_FAIL=1"\n            \) else \($/m', $bat);

        // Toutes les écritures de la boucle sont dans le « else » de la garde : rien n'est écrit dans une cible refusée.
        $commands = self::batCommands($bat);
        $depths = self::batDepths($commands);
        $loop = array_search('for %%T in ("%LOCAL_MOD%" "%SOAR_FN%") do (', $commands, true);
        $at = array_search(self::NATIVE_TARGET_GUARD, $commands, true);
        self::assertIsInt($loop);
        self::assertIsInt($at);
        $else = null;
        for ($i = $at + 1; $i < count($commands) && $else === null; $i++) {
            if ($commands[$i] === ') else (' && $depths[$i] === $depths[$at] + 1) {
                $else = $i;
            }
        }
        self::assertIsInt($else, '« ) else ( » de la garde absent');
        $end = $else + 1;
        while ($end < count($commands) && $depths[$end] > $depths[$at]) {
            $end++;
        }
        $outside = [];
        $inside = 0;
        for ($i = $loop + 1; $i < count($commands) && $depths[$i] > $depths[$loop]; $i++) {
            if (preg_match('/^echo[\s.]/i', $commands[$i]) !== 1
                && preg_match('/(?<![\w-])(?:copy|xcopy|robocopy|move|mkdir|md|rd|rmdir|del|erase|ren|rename|type)(?![\w-])/i', $commands[$i]) === 1) {
                if ($i > $else && $i < $end) {
                    $inside++;
                } else {
                    $outside[] = $commands[$i];
                }
            }
        }
        self::assertSame([], $outside, 'écriture hors du « else » de la garde du pack natif');
        self::assertGreaterThanOrEqual(20, $inside);
    }

    public function testDocsNoLongerSendTheLegacyPackToTheWorkshop(): void
    {
        $docs = [
            'mod/README.md',
            'mod/UptoDate/README.md',
            'mod/UptoDate/docs/compilation-et-publication.md',
            'docs/technique/overwatch-mod/compilation.md',
        ];
        foreach ($docs as $doc) {
            $path = $this->rootPath($doc);
            self::assertFileExists($path);
            $text = str_replace("\r\n", "\n", (string) file_get_contents($path));

            self::assertStringContainsString("n'a plus d'objet Workshop", $text, $doc);
            // Formules de l'ancienne doc, qui faisaient du pack hérité le pack à publier sur cet objet.
            foreach (['référence projet', 'seul dossier à utiliser', 'avant toute publication Workshop'] as $phrase) {
                self::assertDoesNotMatchRegularExpression('/' . preg_quote($phrase, '/') . '/iu', $text, $doc);
            }
            // Chaque paragraphe, élément de liste ou ligne de tableau qui cite l'objet le rattache à l'ATAK natif.
            $unbound = array_filter(
                (array) preg_split('/\n\s*\n|\n(?=\s*(?:[-*+]|\d+\.|\|) )/', $text),
                static fn ($block): bool => str_contains((string) $block, SteamWorkshop::ATAK_NATIVE_ID)
                    && preg_match('/natif|native/i', (string) $block) !== 1,
            );
            self::assertSame([], array_values($unbound), $doc . ' : objet ' . SteamWorkshop::ATAK_NATIVE_ID . ' cité sans l\'ATAK natif');
        }
    }

    public function testLegacyBuildFailsWhenTheFreshDllIsMissing(): void
    {
        $bat = $this->read('build_mod.bat');

        self::assertStringNotContainsString('[WARN] DLL Native AOT non trouvee', $bat);
        // Blocs sans parenthèse dans les echo : une « ) » fermerait le bloc avant la sortie en échec.
        self::assertMatchesRegularExpression('/^if not defined DLL_SRC \(\n(?:    echo [^\n()]*\n)+    (?:goto :build_fail|exit \/b [1-9]\d*)\n\)$/m', $bat);
        self::assertMatchesRegularExpression('/^copy \/Y "%DLL_SRC%" "%OUTPUT_DIR%\\\\COMSPECExtension_x64\.dll" >> "%BUILD_LOG%" 2>&1 \|\| \(\n(?:    echo [^\n()]*\n)+    (?:goto :build_fail|exit \/b [1-9]\d*)\n\)$/m', $bat);

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
