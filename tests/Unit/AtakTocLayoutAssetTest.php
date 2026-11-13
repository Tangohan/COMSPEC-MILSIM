<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Dispositions des écrans TOC à plusieurs dalles (demande de TTRD, 11/11 : « créer d'autres vues pour l'incurvé, pour le
 * multitasking ») : fn_tocLayout est la seule source des dispositions (table _catalog) et des jumeaux par format (table
 * _twins) ; les colonnes 8:9 d'un écran incurvé ont leurs propres affichages (COLC caméras, COLD main courante, COLE
 * exécution, COLF demandes et pertes). Lecture du SQF seulement : le rendu se vérifie en jeu (fn_tocScreen « audit »).
 */
final class AtakTocLayoutAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const TOC = self::MAIN . 'functions/toc/';
    private const KEYS = ['SITNET', 'WIDE', 'MAP2', 'MAPCAM', 'MAPLOG', 'QUAD', 'WATCH', 'ORDERS', 'EXCHECK', 'HEALTH', 'RELAY', 'CLOCKBC'];
    private const COLS = ['COLC', 'COLD', 'COLE', 'COLF'];

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    /** Table « private _nom = [ … ]; » suivie de « // ---- FIN » : du JSON strict. */
    private static function table(string $src, string $name): array
    {
        self::assertSame(1, preg_match('/private ' . preg_quote($name, '/') . ' = (\[.*?\]);\n\/\/ ---- FIN/s', $src, $m), 'table ' . $name . ' absente');
        $data = json_decode($m[1], true);
        self::assertIsArray($data, 'table ' . $name . ' : JSON invalide (' . json_last_error_msg() . ')');

        return $data;
    }

    /** @return list<string> */
    private static function list(string $src, string $var): array
    {
        self::assertSame(1, preg_match('/private ' . preg_quote($var, '/') . ' = \[([^\]]*)\];/', $src, $m), $var);
        preg_match_all('/"([A-Z]+)"/', $m[1], $v);

        return $v[1];
    }

    public function testCatalogHasTheTwelveLayoutsWithHelpAndKnownTokens(): void
    {
        $lay = self::read(self::TOC . 'fn_tocLayout.sqf');
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        $en = self::read(self::MAIN . 'i18n/en.txt');
        $xx = self::read('mod/COMSPEC_ATAK_Native/localisation/template/xx.txt');
        $catalog = self::table($lay, '_catalog');
        self::assertSame(self::KEYS, array_column($catalog, 0));
        $labels = array_column($catalog, 1);
        foreach (['Situation + réseau', 'Carte large', '2 cartes', 'Carte + caméras', 'Carte + main courante + messages', 'Quad', 'Surveillance',
            'Ordres et transmissions', 'EXCHECK / chronologie', 'Santé / pertes', 'Réseau / relais', 'Horloges + BROADCAST'] as $label) {
            self::assertContains($label, $labels);
        }
        // Vocabulaire de fn_tocScreen « panelSpec » : affichages connus, SAT1 / SAT2, MOS2 / MOS3, OFF, SQ:…, TXT:… ;
        // BROADCAST est gardé (horloge à la place s'il n'est pas dans la liste des affichages).
        $views = self::list($scr, '_views');
        $known = array_merge($views, ['SAT1', 'SAT2', 'MOS2', 'MOS3', 'OFF', 'BROADCAST']);
        foreach ($catalog as [$key, $label, $help, $spec]) {
            self::assertMatchesRegularExpression('/^[A-Z0-9]+$/', $key);
            self::assertStringContainsString('Exemple : ', $help, "disposition {$key} : aide sans exemple");
            self::assertStringNotContainsString("\n", $help);
            foreach ([$label, $help] as $fr) {
                self::assertStringContainsString('["' . $fr . '", "', $en, "« {$fr} » sans anglais");
                self::assertStringContainsString('["' . $fr . '", "", "', $xx, "« {$fr} » absent du modèle de traduction");
            }
            $tokens = array_map('trim', explode(',', $spec));
            self::assertGreaterThanOrEqual(2, count($tokens), "disposition {$key} : une seule dalle");
            foreach ($tokens as $t) {
                $ok = in_array(strtoupper($t), $known, true) || str_starts_with(strtoupper($t), 'SQ:') || str_starts_with(strtoupper($t), 'TXT:');
                self::assertTrue($ok, "disposition {$key} : contenu « {$t} » hors du vocabulaire");
                // Une disposition s'écrit en affichages 16:9 : les colonnes viennent des jumeaux.
                self::assertNotContains(strtoupper($t), ['STRIP', 'PLATE', 'COLA', 'COLB', ...self::COLS], "disposition {$key} : « {$t} » n'est pas un affichage 16:9");
            }
        }
        self::assertStringContainsString('(toUpper _x) isEqualTo "BROADCAST" && {!("BROADCAST" in _known)}', $lay);
        self::assertStringContainsString('["panelSpec", _tk] call _ts', $lay);
        self::assertStringContainsString('class tocLayout {};', self::read(self::MAIN . 'config.cpp'));
    }

    public function testTwinsOnlyNameKnownViewsAllowedByTheirFormat(): void
    {
        $lay = self::read(self::TOC . 'fn_tocLayout.sqf');
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        $views = self::list($scr, '_views');
        $formats = self::table($scr, '_formats');
        $allowed = [];
        foreach ($formats as $f) {
            $allowed[$f[1]] = array_merge($allowed[$f[1]] ?? [], $f[4]);
        }
        $twins = array_column(self::table($lay, '_twins'), 1, 0);
        self::assertSame(['COL', 'STD', 'STRIP'], array_keys($twins));
        foreach ($twins as $kind => $pairs) {
            foreach ($pairs as [$from, $to]) {
                self::assertContains($from, $views, "jumeau {$kind} : {$from} inconnu");
                self::assertContains($to, $views, "jumeau {$kind} : {$to} inconnu");
                if ($kind !== 'STD') {
                    self::assertContains($to, $allowed[$kind], "jumeau {$to} interdit sur le format {$kind}");
                    self::assertNotContains($from, $allowed[$kind], "{$from} est déjà permis sur le format {$kind}");
                } else {
                    self::assertNotContains($to, ['STRIP', 'PLATE', 'COLA', 'COLB', ...self::COLS]);
                }
            }
        }
        // Chaque colonne nouvelle a son affichage 16:9 d'origine, et retour.
        $col = array_column($twins['COL'], 0, 1);
        $std = array_column($twins['STD'], 1, 0);
        foreach (self::COLS as $v) {
            self::assertArrayHasKey($v, $col, "aucun affichage 16:9 ne mène à {$v}");
            self::assertArrayHasKey($v, $std, "{$v} sans jumeau 16:9");
        }
        // Jamais d'image 16:9 étirée : l'affichage posé est permis par le format de la dalle, sinon jumeau, sinon défaut.
        self::assertStringContainsString('_out pushBack [_x, [[_def, _tw] select (_tw in _ok), _v] select (_v in _ok), _e, _kept];', $lay);
        self::assertStringContainsString('private _tw = ["twin", [_v, _kind]] call comspec_atak_native_fnc_tocLayout;', $scr);
    }

    public function testNewColumnViewsAreRegisteredAndDrawn(): void
    {
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        $brd = self::read(self::TOC . 'fn_tocBoard.sqf');
        $thm = self::read(self::TOC . 'fn_tocTheme.sqf');
        $en = self::read(self::MAIN . 'i18n/en.txt');
        $views = self::list($scr, '_views');
        $boards = self::list($scr, '_boards');
        $colRow = self::table($scr, '_formats')[0];
        self::assertSame('COL', $colRow[1]);
        self::assertSame(1, preg_match('/case "title": \{(.*?)\n    case "views":/s', $scr, $t));
        self::assertSame(1, preg_match('/private _walls = (\[.*?\]);/s', $thm, $w));
        $walls = array_column((array) json_decode($w[1], true), 1, 0);
        $std = self::list($scr, '_views');
        foreach (self::COLS as $v) {
            self::assertContains($v, $views, "{$v} absent de _views");
            self::assertContains($v, $colRow[4], "{$v} absent du format COL");
            self::assertContains($v, $boards, "{$v} absent de _boards");
            self::assertMatchesRegularExpression('/\["' . $v . '", \["([^"]+)", "([^"]+)"\]\]/', $t[1], "{$v} sans titre");
            preg_match('/\["' . $v . '", \["([^"]+)", "([^"]+)"\]\]/', $t[1], $ttl);
            self::assertStringContainsString('["' . $ttl[2] . '", "', $en, "titre « {$ttl[2]} » sans anglais");
            self::assertMatchesRegularExpression('/case "' . $v . '": \{/', $brd, "{$v} sans dessin dans fn_tocBoard");
            self::assertArrayHasKey($v, $walls, "{$v} sans régions dans le thème");
            foreach ($walls[$v] as [$name, $x, $y, $wd, $h]) {
                self::assertTrue($x >= 0 && $y >= 0 && $x + $wd <= 800 && $y + $h <= 900, "{$v}.{$name} hors de la colonne 800 x 900");
            }
            // Jamais sur un écran 16:9 : retirée des affichages du format STD.
            self::assertSame(2, preg_match_all('/_views - \[[^\]]*"' . $v . '"[^\]]*\]/', $scr), "{$v} resterait permis sur un écran 16:9");
        }
        self::assertStringContainsString('private _col = _view in ["COLA", "COLB", "COLC", "COLD", "COLE", "COLF"];', $brd);
        // Vignettes caméra de la colonne : 16:9, jamais étirées.
        foreach (['cam1', 'cam2'] as $k) {
            $r = array_values(array_filter($walls['COLC'], static fn (array $row): bool => $row[0] === $k))[0];
            self::assertEqualsWithDelta(16 / 9, $r[3] / $r[4], 0.01, "COLC.{$k} n'est pas en 16:9");
        }
        // Image vivante par la boucle « live » existante (aucune boucle par image ajoutée), redessin chaque seconde.
        self::assertSame(1, substr_count($brd, 'CBA_fnc_addPerFrameHandler'));
        self::assertMatchesRegularExpression('/case "COLC": \{.*?_o setVariable \["COMSPEC_ATAK_TocVideo", true\];.*?case "COLD": \{/s', $brd);
        self::assertMatchesRegularExpression('/in \["CLOCK", "SAT", "STRIP", "MOSAIC", "TEXT",[^\]]*"COLC", "COLE"\]/', $scr);
        self::assertNotEmpty($std);
    }

    public function testApiAppliesThroughTheViewPathAndKeepsPresetsInTheProfile(): void
    {
        $lay = self::read(self::TOC . 'fn_tocLayout.sqf');
        foreach (['catalog', 'list', 'apply', 'current', 'save', 'presets', 'applyPreset', 'kit', 'twin'] as $mode) {
            self::assertTrue(str_contains($lay, 'case "' . $mode . '": {') || str_contains($lay, '_mode isEqualTo "' . $mode . '"'), "mode {$mode} absent");
        }
        // Pose par le chemin habituel (serveur, ou poste du joueur en repli) : aucune variable d'écran écrite ici.
        self::assertStringContainsString('["view", [_po, "mode", _v]] call _ts;', $lay);
        self::assertStringContainsString('["view", [_po, "sat", _sl]] call _ts;', $lay);
        self::assertStringContainsString('["view", [_po, "mosaic", _mos]] call _ts;', $lay);
        self::assertStringNotContainsString('setVariable ["COMSPEC_ATAK_TocScr"', $lay);
        self::assertStringNotContainsString('CBA_fnc_addPerFrameHandler', $lay);
        // Écran à une seule dalle : rien à proposer.
        self::assertStringContainsString('if ((count _panes) < 2) exitWith { [] };', $lay);
        // Dispositions gardées : profil du joueur, 20 au plus, nom de 24 caractères.
        self::assertStringContainsString('profileNamespace getVariable ["COMSPEC_ATAK_TocLayouts", []]', $lay);
        self::assertStringContainsString('profileNamespace setVariable ["COMSPEC_ATAK_TocLayouts", _all];', $lay);
        self::assertStringContainsString('saveProfileNamespace;', $lay);
        self::assertStringContainsString('_nm = trim (_nm select [0, 24]);', $lay);
        self::assertStringContainsString('if ((count _all) > 20) then { _all = _all select [(count _all) - 20]; };', $lay);
        // Poste entier : écrans de la même salle à 6 m au plus.
        self::assertStringContainsString('{(_x distance _h) <= 6}', $lay);
        // Jamais de fenêtre ni de message du jeu.
        self::assertDoesNotMatchRegularExpression('/\b(hint|hintSilent|systemChat|titleText|cutText|BIS_fnc_showNotification)\b/', $lay);
    }
}
