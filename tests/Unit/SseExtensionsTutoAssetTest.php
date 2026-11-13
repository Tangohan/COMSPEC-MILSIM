<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Fenêtre SSE « SSE ROLEPLAY · Extensions », tuto « Comment ça marche » : en SSE 1.9.0 la liste des étapes et la
 * lecture restaient vides. Cause : en SQF les noms de variables ignorent la casse ; « private _l = <liste> »
 * écrasait le raccourci de traduction « _L », et l'appel suivant échouait avant le moindre lbAdd.
 * Ce test garde : textes FR / EN de chaque étape, clés uniques, texte structuré valide, IDC de la fenêtre,
 * étape 1 choisie à l'ouverture, aucune variable SQF qui ne diffère que par la casse.
 */
final class SseExtensionsTutoAssetTest extends TestCase
{
    private const STEPS = 10;

    private static function root(): string
    {
        return dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons';
    }

    private static function read(string $rel): string
    {
        $path = self::root() . '/' . $rel;
        self::assertFileExists($path);
        return (string) file_get_contents($path);
    }

    /** @return array<string, array{French: string, English: string}> */
    private static function keys(): array
    {
        $xml = simplexml_load_string(self::read('extensions/stringtable.xml'));
        self::assertNotFalse($xml, 'stringtable.xml de l\'addon extensions : XML invalide');
        $out = [];
        foreach ($xml->xpath('//Key') ?: [] as $key) {
            $out[(string) $key['ID']] = ['French' => (string) $key->French, 'English' => (string) $key->English];
        }
        return $out;
    }

    /** Code SQF sans commentaires ni chaînes (les noms de variables seuls). */
    private static function sqfCode(string $sqf): string
    {
        $sqf = (string) preg_replace('~/\*.*?\*/~s', ' ', $sqf);
        $sqf = (string) preg_replace('~"(?:[^"]|"")*"~', '""', $sqf);
        $sqf = (string) preg_replace("~'(?:[^']|'')*'~", "''", $sqf);
        return (string) preg_replace('~//[^\n]*~', ' ', $sqf);
    }

    public function testEveryStepHasNonEmptyFrenchAndEnglishTitleAndBody(): void
    {
        $keys = self::keys();
        for ($n = 1; $n <= self::STEPS; $n++) {
            foreach (['T', 'B'] as $part) {
                $id = "STR_COMSPEC_SSE_EXT_T{$n}_{$part}";
                self::assertArrayHasKey($id, $keys, "$id absent du stringtable");
                self::assertNotSame('', trim($keys[$id]['French']), "$id : français vide");
                self::assertNotSame('', trim($keys[$id]['English']), "$id : anglais vide");
            }
        }
    }

    public function testNoExtensionKeyIsDefinedTwiceAcrossSseStringtables(): void
    {
        $seen = [];
        foreach (glob(self::root() . '/*/stringtable.xml') ?: [] as $file) {
            preg_match_all('~<Key ID="(STR_COMSPEC_SSE_EXT_[^"]+)"~', (string) file_get_contents($file), $m);
            foreach ($m[1] as $id) {
                $seen[$id][] = basename(dirname($file));
            }
        }
        self::assertNotEmpty($seen);
        foreach ($seen as $id => $where) {
            self::assertCount(1, $where, "$id défini plusieurs fois : " . implode(', ', $where));
        }
    }

    public function testEveryStepBodyIsValidStructuredText(): void
    {
        $keys = self::keys();
        $allowed = ['root', 't', 'br', 'img', 'a'];
        for ($n = 1; $n <= self::STEPS; $n++) {
            foreach (['French', 'English'] as $lang) {
                foreach (['T', 'B'] as $part) {
                    $id = "STR_COMSPEC_SSE_EXT_T{$n}_{$part}";
                    $doc = new \DOMDocument();
                    $ok = @$doc->loadXML('<root>' . $keys[$id][$lang] . '</root>');
                    self::assertTrue($ok, "$id ($lang) : texte structuré invalide (« < » ou « & » isolé ?)");
                    foreach ($doc->getElementsByTagName('*') as $el) {
                        self::assertContains($el->nodeName, $allowed, "$id ($lang) : balise <{$el->nodeName}> inconnue");
                    }
                    self::assertNotSame('', trim($doc->textContent), "$id ($lang) : aucun texte visible");
                }
            }
        }
    }

    public function testDialogHasTheIdcsTheFunctionUses(): void
    {
        $hpp = self::read('extensions/dialogs/extensions.hpp');
        $sqf = self::read('extensions/functions/fn_docExtWindow.sqf');
        // 93960 liste des étapes, 93961 groupe de lecture, 93962 texte lu, 93963 Copier, 93965 / 93966 précédente / suivante.
        foreach ([93910, 93924, 93960, 93961, 93962, 93963, 93964, 93965, 93966] as $idc) {
            self::assertMatchesRegularExpression("~idc\\s*=\\s*$idc\\s*;~", $hpp, "IDC $idc absent de la fenêtre");
            self::assertStringContainsString((string) $idc, $sqf, "IDC $idc non utilisé par fn_docExtWindow");
        }
        self::assertMatchesRegularExpression('~class Steps: COMSPEC_SSE_RscListBox \{\s*idc = 93960;~', $hpp);
        self::assertMatchesRegularExpression('~class Text: COMSPEC_SSE_RscStructuredText \{\s*idc = 93962;~', $hpp);
        self::assertStringContainsString("onLBSelChanged = \"['step'] call comspec_sse_fnc_docExtWindow\"", $hpp);
        self::assertStringContainsString("action = \"['prev'] call comspec_sse_fnc_docExtWindow\"", $hpp);
        self::assertStringContainsString("action = \"['next'] call comspec_sse_fnc_docExtWindow\"", $hpp);
        // Toutes les clés $STR_ de la fenêtre existent.
        $keys = self::keys();
        preg_match_all('~\$(STR_COMSPEC_SSE_EXT_[A-Za-z0-9_]+)~', $hpp, $m);
        foreach (array_unique($m[1]) as $id) {
            self::assertArrayHasKey($id, $keys, "$id (extensions.hpp) absent du stringtable");
        }
    }

    public function testFunctionFillsStepsAndSelectsFirstStepOnOpen(): void
    {
        $sqf = self::read('extensions/functions/fn_docExtWindow.sqf');
        self::assertStringContainsString('private _steps = ' . self::STEPS . ';', $sqf);
        self::assertStringContainsString('_lst lbAdd format ["%1. %2", _n, format ["T%1_T", _n] call _loc];', $sqf);
        self::assertStringContainsString('_lst lbSetCurSel (_keep min ((lbSize _lst) - 1));', $sqf);
        self::assertStringContainsString('ctrlSetFocus _lst;', $sqf);
        self::assertStringContainsString('format ["T%1_B", _n] call _loc', $sqf);
        // Clé absente : jamais de ligne vide (nom de la clé affiché, journal RPT).
        self::assertStringContainsString('if (_s isEqualTo "") then { diag_log', $sqf);
        // Contrôle interne à l'ouverture, au journal seulement.
        self::assertStringContainsString('case "audit": { call _audit };', $sqf);
        self::assertStringContainsString('forEach (call _audit);', $sqf);
        // Copier : exactement le texte de l'exemple, confirmation sur la ligne d'état de la fenêtre.
        self::assertStringContainsString('copyToClipboard ((call _skeleton) joinString _nl);', $sqf);
        foreach (['hint ', 'hintSilent', 'systemChat', 'titleText', 'cutText', 'BIS_fnc_showNotification'] as $alert) {
            self::assertStringNotContainsString($alert, self::sqfCode($sqf), "alerte Arma interdite : $alert");
        }
    }

    public function testEveryLocalizedShortKeyExists(): void
    {
        $sqf = self::sqfCode(self::read('extensions/functions/fn_docExtWindow.sqf'));
        $raw = self::read('extensions/functions/fn_docExtWindow.sqf');
        $keys = self::keys();
        preg_match_all('~"([A-Za-z0-9_]+)" call _loc~', $raw, $m);
        preg_match_all('~\["([A-Za-z0-9_]+)", "([A-Za-z0-9_]+)"\] select [^;\n]*?\) call _loc~', $raw, $m2);
        $short = array_unique(array_merge($m[1], $m2[1], $m2[2]));
        self::assertGreaterThan(20, count($short));
        foreach ($short as $k) {
            self::assertArrayHasKey("STR_COMSPEC_SSE_EXT_$k", $keys, "« $k » call _loc : clé absente");
        }
        self::assertNotSame('', $sqf);
    }

    public function testNoSqfVariableDiffersOnlyByCase(): void
    {
        $code = self::sqfCode(self::read('extensions/functions/fn_docExtWindow.sqf'));
        preg_match_all('~(?<![A-Za-z0-9_])_[A-Za-z][A-Za-z0-9_]*~', $code, $m);
        $byLower = [];
        foreach (array_unique($m[0]) as $name) {
            $byLower[strtolower($name)][] = $name;
        }
        foreach ($byLower as $names) {
            self::assertCount(1, $names, 'variables SQF identiques à la casse près (même variable) : ' . implode(' / ', $names));
        }
    }
}
