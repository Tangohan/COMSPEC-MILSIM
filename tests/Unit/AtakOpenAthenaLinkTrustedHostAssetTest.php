<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ARCH-005 — OpenAthenaLink (COMSPEC Link et DLL native : même Extension.cs) ouvre une adresse d'un hôte Athena de
 * la liste fermée de SEC-001 (IsSecretSafeUri), et non plus seulement l'hôte de _baseUrl. Avant, le bouton
 * « OUVRIR LA PAGE 9-LINE » du natif (athena-comspec.eu) rendait ERR|foreign_host quand connect avait posé
 * _baseUrl sur athena.ttrd.fr.
 *
 * Test statique (lecture des sources, bloc découpé par comptage d'accolades, commentaires retirés). Il échoue si
 * la comparaison avec l'hôte de _baseUrl revient, si le contrôle n'exige plus https et une adresse sans
 * identifiants (IsSecretSafeUri remplacé par un simple contrôle d'hôte), si l'adresse s'ouvre sans portail
 * configuré ou avant le contrôle, si un autre schéma que http(s) passe, si une adresse fixe passée par le jeu
 * n'est plus admise par la nouvelle règle, ou si un appelant apparaît ou disparaît.
 *
 * Cinq appelants dans mod/ : fn_pageJtac (9-LINE, adresse fixe), connect fn_webBrowserOpenSystem et
 * fn_webBrowserOpenAthena (portail fixe de fn_portalUrl), fn_athenaLink du natif et fn_athenaEden du guide SSE (Eden).
 * Ces deux derniers ouvrent l'adresse rendue par le site après PairStart (verification_uri_complete, construite sur
 * APP_URL : https://athena-comspec.eu dans .env.example) et, à défaut, « /lier » sur leur portail fixe en https.
 */
final class AtakOpenAthenaLinkTrustedHostAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $rel): string
    {
        $src = file_get_contents($this->root() . '/' . $rel);
        self::assertNotFalse($src, 'Source illisible : ' . $rel);

        return (string) $src;
    }

    /** Bloc `if (function == "OpenAthenaLink" ...) { ... }` de Extension.cs, sans commentaires. */
    private function openLinkBlock(): string
    {
        $src = (string) preg_replace('~//[^\n]*~', '', $this->read('mod/UptoDate/COMSPECExtension/Extension.cs'));
        self::assertSame(1, preg_match('~if\s*\(\s*function\s*==\s*"OpenAthenaLink"[^{]*\{~', $src, $m, PREG_OFFSET_CAPTURE), 'Commande OpenAthenaLink introuvable');
        $start = $m[0][1];
        $depth = 0;
        $len = strlen($src);
        for ($i = $start + strlen($m[0][0]) - 1; $i < $len; $i++) {
            if ($src[$i] === '{') {
                $depth++;
            } elseif ($src[$i] === '}' && --$depth === 0) {
                return substr($src, $start, $i - $start + 1);
            }
        }
        self::fail('Bloc OpenAthenaLink non refermé');
    }

    public function testHostIsCheckedAgainstTheSec001ClosedList(): void
    {
        $b = $this->openLinkBlock();
        self::assertMatchesRegularExpression('~_baseUrl\.Length\s*==\s*0\s*\|\|\s*!IsSecretSafeUri\(\s*targetUri\s*\)\s*\)\s*return\s+"ERR\|foreign_host"\s*;~', $b, 'Le contrôle « portail configuré + IsSecretSafeUri » a changé');
        self::assertStringNotContainsString('portalUri', $b, 'La comparaison avec l\'hôte de _baseUrl est revenue');
        self::assertDoesNotMatchRegularExpression('~\.Host\b~', $b, 'Contrôle d\'hôte hors IsSecretSafeUri (sans https ni refus des identifiants)');
        self::assertStringNotContainsString('IsTrustedPortalHost', $b, 'Contrôle d\'hôte seul : https et adresse sans identifiants ne seraient plus exigés');
    }

    public function testOnlyHttpSchemesAndOpenAfterTheChecks(): void
    {
        $b = $this->openLinkBlock();
        self::assertMatchesRegularExpression('~targetUri\.Scheme\s*!=\s*Uri\.UriSchemeHttps\s*&&\s*targetUri\.Scheme\s*!=\s*Uri\.UriSchemeHttp\s*\)\s*\)\s*return\s+"ERR\|invalid_url"~', $b, 'Le refus des schémas autres que http(s) a changé');
        $check = strpos($b, 'IsSecretSafeUri(targetUri)');
        $open = strpos($b, 'Process.Start(');
        self::assertNotFalse($check);
        self::assertNotFalse($open);
        self::assertLessThan($open, $check, 'L\'adresse s\'ouvre avant le contrôle d\'hôte');
        self::assertSame(1, substr_count($b, 'Process.Start('));
        self::assertStringContainsString('ProcessStartInfo(targetUri.AbsoluteUri)', $b);
    }

    public function testNativeDllCompilesTheSameExtension(): void
    {
        self::assertMatchesRegularExpression(
            '~Include="\.\.\\\\\.\.\\\\UptoDate\\\\COMSPECExtension\\\\Extension\.cs"~',
            $this->read('mod/COMSPEC_ATAK_Native/COMSPECATAKNativeExtension/COMSPECATAKNativeExtension.csproj')
        );
    }

    public function testFixedUrlsOpenedByTheGameAreAdmitted(): void
    {
        // Adresses fixes passées à OpenAthenaLink : 9-LINE du natif, portail de connect (+ « /atak »). Toutes https,
        // sans identifiants, sur un hôte de la liste fermée : sinon le bouton retombe sur « Ouvrez … ».
        $ext = $this->read('mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertSame(1, preg_match('~static bool IsTrustedPortalHost\(string\? host\)\s*\{(.*?)\n    \}~s', $ext, $m), 'IsTrustedPortalHost introuvable');
        self::assertSame(1, preg_match('~PortalHostKey\(host\)\s+is\s+((?:"[^"]+"(?:\s+or\s+)?)+)~', $m[1], $hm), 'Liste des hôtes de confiance introuvable');
        preg_match_all('~"([^"]+)"~', $hm[1], $hosts);
        $trusted = $hosts[1];

        self::assertSame(1, preg_match('~private _url = "([^"]+)";\s*private _r = [^;]*callExtension \["OpenAthenaLink"~', $this->read('mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/tactical/fn_pageJtac.sqf'), $jt), 'Adresse 9-LINE introuvable');
        self::assertSame(1, preg_match('~^\s*"(https?://[^"]+)"\s*$~m', $this->read('mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_portalUrl.sqf'), $pu), 'Adresse du portail de connect introuvable');

        // Liaison par code (natif et guide Eden) : adresse de secours « /lier » construite sur le portail fixe.
        $pairing = [];
        foreach (['mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/network/fn_athenaLink.sqf', 'mod/@COMSPEC_SSE/addons/guide/functions/fn_athenaEden.sqf'] as $rel) {
            $src = $this->read($rel);
            self::assertSame(1, preg_match_all('~private _url = "([^"]+)";~', $src, $pm), $rel . ' : portail fixe introuvable');
            self::assertStringContainsString('then { _uri = _url + "/lier"; };', $src, $rel . ' : adresse de secours changée');
            $pairing[] = $pm[1][0] . '/lier';
        }

        foreach ([$jt[1], rtrim($pu[1], '/') . '/atak', ...$pairing] as $url) {
            $p = parse_url($url);
            self::assertIsArray($p, $url);
            self::assertSame('https', $p['scheme'] ?? '', $url);
            self::assertArrayNotHasKey('user', $p, $url);
            self::assertContains(strtolower($p['host'] ?? ''), $trusted, $url . ' : hôte hors de la liste de SEC-001');
        }
    }

    public function testEveryCallerIsKnown(): void
    {
        // Un nouvel appelant doit passer une adresse en https sur un hôte de la liste de SEC-001 : la vérifier, puis
        // l'ajouter ici. Toutes les sources texte de mod/ (natif, connect, guide SSE, anciens paquets).
        $base = $this->root() . '/';
        $callers = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base . 'mod', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (preg_match('~\.(sqf|hpp|cpp|h|inc|ext)$~i', $file->getFilename()) !== 1) {
                continue;
            }
            $n = preg_match_all('~"OpenAthenaLink"~i', (string) file_get_contents($file->getPathname()));
            if ($n > 0) {
                $callers[str_replace('\\', '/', substr($file->getPathname(), strlen($base)))] = $n;
            }
        }
        ksort($callers);
        self::assertSame([
            'mod/@COMSPEC_SSE/addons/guide/functions/fn_athenaEden.sqf' => 1,
            'mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/network/fn_athenaLink.sqf' => 1,
            'mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/tactical/fn_pageJtac.sqf' => 1,
            'mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_webBrowserOpenAthena.sqf' => 1,
            'mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_webBrowserOpenSystem.sqf' => 1,
        ], $callers, 'Appelants d\'OpenAthenaLink');
    }
}
