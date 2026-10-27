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

    /**
     * Membres de droite des affectations « $<nom> = … » d'un script PowerShell, dans l'ordre du fichier.
     *
     * @return list<string>
     */
    private static function psAssignments(string $ps, string $name): array
    {
        preg_match_all('/\$' . preg_quote($name, '/') . '\b\s*=(?!=)\s*([^\n;},]*)/', $ps, $matches);

        return array_map('trim', $matches[1]);
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

    public function testWorkshopPackChecksTheValuesItReadsAndShipsTheCheckedDll(): void
    {
        $ps = $this->read('workshop-pack.ps1');

        // Chaîne de données : chaque variable n'a que ces valeurs. La version comparée est celle lue dans la DLL
        // retenue, le texte testé est tout le meta.cpp du dossier source.
        self::assertSame(['""', "Join-Path \$ModRoot '@COMSPECOverwatch'"], self::psAssignments($ps, 'SourceMod'));
        self::assertSame(['$null', '$c'], self::psAssignments($ps, 'dll'));
        self::assertSame(['[string]([System.Diagnostics.FileVersionInfo]::GetVersionInfo((Get-Item -LiteralPath $dll).FullName).ProductVersion)'], self::psAssignments($ps, 'dllProductVersion'));
        self::assertSame(['$null', '[version]($Matches[1])'], self::psAssignments($ps, 'dllVersion'));
        self::assertStringContainsString("if (\$dllProductVersion -match '^\\s*(\\d+\\.\\d+(\\.\\d+){0,2})') { \$dllVersion = [version](\$Matches[1]) }", $ps);
        self::assertSame(["[version]'2.0.96'"], self::psAssignments($ps, 'MinLinkVersion'));
        self::assertSame(["'" . SteamWorkshop::ATAK_NATIVE_ID . "'"], self::psAssignments($ps, 'AtakNativeWorkshopId'));
        self::assertSame(['Join-Path $SourceMod "meta.cpp"', 'Join-Path $SourceMod "meta.cpp"'], self::psAssignments($ps, 'metaCpp'));
        self::assertSame(['[string](Get-Content -LiteralPath $metaCpp -Raw)'], self::psAssignments($ps, 'metaText'));

        // Ordre : lecture, contrôle, puis seulement préparation du pack et copie de la DLL contrôlée.
        $order = [
            '$metaCpp = Join-Path $SourceMod "meta.cpp"',
            '$metaText = ',
            '$metaText -match ',
            'if (-not $dll) {',
            '$dllProductVersion = ',
            '$dllProductVersion -match ',
            'if ($null -eq $dllVersion) {',
            'if ($dllVersion -lt $MinLinkVersion) {',
            'Write-Step "Nettoyage staging',
            'Copy-Item -LiteralPath $dll -Destination',
        ];
        $previous = -1;
        foreach ($order as $marker) {
            $at = strpos($ps, $marker);
            self::assertNotFalse($at, 'absent : ' . $marker);
            self::assertGreaterThan($previous, $at, 'hors d\'ordre : ' . $marker);
            $previous = $at;
        }

        // La seule DLL copiée dans le pack est $dll, celle dont la version vient d'être contrôlée ; aucune copie par
        // motif ni récursive ne peut en glisser une autre.
        $copies = array_values(array_filter(array_map('trim', explode("\n", $ps)), static fn (string $line): bool => str_contains($line, 'Copy-Item')));
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

    public function testLegacyBuildWritesOnlyIntoItsTwoLocalTargets(): void
    {
        $offending = [];
        $underArma = [];
        foreach (self::batCommands($this->read('build_mod.bat')) as $line) {
            $command = preg_match('/^echo[\s.]/i', $line) !== 1;
            // Aucun dossier Steam d'objet Workshop, aucune jonction !Workshop autre que la copie locale SOAR FN, et
            // aucune commande qui porte le nom du pack natif.
            if (preg_match('/steamapps\\\\workshop|workshop\\\\content|107410|!Workshop\\\\(?!@# S\.O\.A\.R - FN\b)/i', $line) === 1
                || ($command && preg_match('/atak[ _-]?native/i', $line) === 1)) {
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
        $ignore = str_replace("\r\n", "\n", (string) file_get_contents($this->rootPath('.gitignore')));
        // La DLL du dossier du mod est elle aussi une sortie de build : git ne suit plus l'ancienne 2.0.57.
        $rules = ['mod/UptoDate/COMSPECExtension/bin/', 'mod/UptoDate/@COMSPECOverwatch/net8.0/', 'mod/UptoDate/@COMSPECOverwatch/COMSPECExtension_x64.dll'];
        foreach ($rules as $rule) {
            self::assertMatchesRegularExpression('/^' . preg_quote($rule, '/') . '$/m', $ignore, '.gitignore sans ' . $rule);
        }
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

        // Chaque appel d'AddonBuilder a un contrôle lu à l'exécution : « || », « if errorlevel 1 », ou %ERRORLEVEL%
        // sur la ligne suivante, hors bloc.
        $builders = 0;
        foreach ($commands as $i => $line) {
            if (!str_starts_with($line, '"%BUILDER_PATH%"')) {
                continue;
            }
            $builders++;
            $next = $commands[$i + 1] ?? '';
            self::assertTrue(
                str_ends_with($line, ' || (')
                || preg_match('/^if errorlevel 1 \($/i', $next) === 1
                || ($depths[$i] === 0 && preg_match('/^if %ERRORLEVEL% NEQ 0 \($/i', $next) === 1),
                'AddonBuilder sans contrôle d\'échec : ' . $line,
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
