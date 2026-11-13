<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * App Réseau, section « Radio de données » (MPU5) et « Voix par le maillage » : retour de jeu « à améliorer » (page
 * chargée, 8 boutons de largeurs différentes, trois paragraphes de jargon). Réglages rangés par sens sur une même grille,
 * état en 3 lignes, voix repliée par défaut avec un GUIDE à part. Textes traduits, paragraphes courts, libellés uniques.
 */
final class AtakMpu5PageAssetTest extends TestCase
{
    private const MOD = __DIR__ . '/../../mod/COMSPEC_ATAK_Native';
    private const F = self::MOD . '/Sources/addons/main/functions';
    /** Longueur maximale d'un texte de la page principale (balises retirées) : environ deux lignes en portrait. */
    private const MAX_PARA = 120;
    /** Textes identiques en anglais : fn_tr les garde tels quels, tools/i18n.py ne les range pas dans le dictionnaire. */
    private const SAME = ['WI-FI', 'GUIDE', 'NORMAL', '−', '+', '3 W'];

    private function read(string $p): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents($p));
    }

    private function page(): string
    {
        return $this->read(self::F . '/pages/fn_pageNetwork.sqf');
    }

    /** Sections Radio de données et Voix par le maillage de la page principale. */
    private function section(): string
    {
        $src = $this->page();
        $a = strpos($src, '// --- Radio de données (liaison réaliste');
        $b = strpos($src, '// --- Indicateurs ---');
        self::assertNotFalse($a);
        self::assertNotFalse($b);

        return substr($src, (int) $a, (int) $b - (int) $a);
    }

    /** Volet GUIDE (en tête de la page). */
    private function guide(): string
    {
        $src = $this->page();
        $a = strpos($src, 'if ((_s getOrDefault ["netGuide", false]) isEqualTo true) exitWith {');
        self::assertNotFalse($a);
        $b = strpos($src, "\n};", (int) $a);

        return substr($src, (int) $a, (int) $b - (int) $a);
    }

    /** @return array<string, true> clés d'un dictionnaire ([« français », …]) */
    private function keys(string $path): array
    {
        $out = [];
        foreach (explode("\n", $this->read($path)) as $line) {
            if (preg_match('/^\["((?:[^"]|"")*)",/', trim($line), $m)) {
                $out[str_replace('""', '"', $m[1])] = true;
            }
        }

        return $out;
    }

    /** @return list<string> libellés des cases des rangées de réglage et des boutons */
    private function buttonLabels(string $src): array
    {
        preg_match_all('/\[\s*"([^"%]+)",\s*\{/u', $src, $m);
        $labels = $m[1];
        if (preg_match('/\[\["(MÂT)", "(REPLIER LE MÂT)"\]/u', $src, $mm)) {
            $labels[] = $mm[1];
            $labels[] = $mm[2];
        }

        return $labels;
    }

    /** @return list<string> libellés des rangées (colonne de gauche) */
    private function rowLabels(string $src): array
    {
        preg_match_all('/^\s*\["([^"]+)", \[\s*$/mu', $src, $m);
        preg_match_all('/\["(Plus)", _more\] call _ctlRow;/u', $src, $m2);

        return array_merge($m[1], $m2[1]);
    }

    /** @return list<string> infobulles des cases */
    private function tooltips(string $src): array
    {
        preg_match_all('/, (?:true|_mode isNotEqualTo 1), "([^"]+)"\]/u', $src, $m);

        return $m[1];
    }

    public function testReglagesRangesParSensSurUneMemeGrille(): void
    {
        $src = $this->section();
        $rows = $this->rowLabels($src);
        foreach (['Alimentation', 'Puissance', 'Émission', 'Canal', 'Largeur', 'Liaison', 'Plus'] as $r) {
            self::assertContains($r, $rows);
        }
        // Puissance en 3 crans choisis directement (plus de bouton qui tourne en boucle), canal − n +.
        self::assertStringContainsString('["txpower", [0]]', $src);
        self::assertStringContainsString('["txpower", [2]]', $src);
        self::assertStringContainsString('["width", [0]]', $src);
        self::assertStringContainsString('[format ["%1", _v get "chan"], {}],', $src);
        self::assertStringNotContainsString('"PUISSANCE", { ["txpower"]', $src);
        self::assertStringNotContainsString('CANAL %1', $src);
        $link = $this->read(self::F . '/network/fn_mpuLink.sqf');
        self::assertStringContainsString('((_arg param [0, -1]) max 0) min 2] select ((_arg param [0, -1]) >= 0);', $link);
        // Toutes les rangées : même hauteur de case, même colonne de libellé, largeurs égales dans la rangée.
        $page = $this->page();
        self::assertStringContainsString('private _w1 = (((_cw - 2 * _pad) - _lblW) - (_n - 1) * _gap) / _n;', $page);
        self::assertStringContainsString('private _pos = [_x0 + _forEachIndex * (_w1 + _gap), _y, _w1, _btnH];', $page);
        self::assertStringContainsString('private _lblW = (_cw - 2 * _pad) * ([0.3, 0.2] select (_perRow > 2));', $page);
    }

    public function testLibellesQuiTiennentEtEtatsGrises(): void
    {
        $page = $this->page();
        // Libellé trop long : police réduite (jamais coupé), comme les autres boutons du téléphone.
        self::assertStringContainsString('[_b, (_font * 0.92) max COMSPEC_ATAK_FontMin, true, 0.7] call comspec_atak_native_fnc_textFit;', $page);
        // Case libellé centrée à la hauteur de son texte : rien de rogné.
        self::assertStringContainsString('_lt ctrlSetPosition [_pad, _y + (((_btnH - _lth) / 2) max 0), _lblW - _gap, _lth max _font];', $page);
        // Grisé : désactivé, la raison écrite dans la ligne « à faire ».
        self::assertStringContainsString('default { _b ctrlEnable false;', $page);
        self::assertStringContainsString('"<t size=\'%1\' color=\'%2\'>Grisé : %3</t>"', $page);
        // Le fond de page suit le contenu : le dernier bouton reste visible en faisant défiler.
        self::assertStringContainsString('_bgc ctrlSetPosition [0, 0, _bw, _bh max (_y + _pad)];', $page);
    }

    public function testEtatEnTroisLignesEtLigneAFaire(): void
    {
        $src = $this->section();
        self::assertStringContainsString('format ["<t size=\'%1\'>%2<br/>%3<br/>%4</t>", _zV, _l1, _l2, _l3]', $src);
        self::assertStringContainsString('Aucun autre nœud sur le canal %1 : montez sur un point haut', $src);
        self::assertStringNotContainsString('Aucun autre nœud sur ce canal', $src);
    }

    public function testVoixRepliéeParDefautAvecUnGuide(): void
    {
        $page = $this->page();
        self::assertStringContainsString('private _openDef = ["path", "mpu", "ind"];', $page);
        $src = $this->section();
        self::assertStringContainsString('"désactivée par l\'admin"', $src);
        self::assertStringContainsString('[[_guideBtn, _diagBtn]] call _buttons;', $src);
        // Les trois paragraphes d'avant ont quitté la page principale.
        self::assertStringNotContainsString('Radio ACRE2 / TFAR reliée à la MPU5 par câble audio', $src);
        self::assertStringNotContainsString('Personne ne vous entend ? DÉPANNAGE MPU vérifie chaque maillon (réglage', $src);
        self::assertStringNotContainsString('[[_vs2] call _esc] call _para;', $src);
        // Guide : étapes numérotées, retour du téléphone, DÉPANNAGE MPU.
        $guide = $this->guide();
        self::assertStringContainsString('[1, _gOn, "L\'admin active la voix"', $guide);
        self::assertStringContainsString('[2, _gBr, "Reliez votre radio à votre MPU5"', $guide);
        self::assertStringContainsString('[3, false, "Testez avec un camarade"', $guide);
        self::assertStringContainsString('_s set ["backSub", ["NETWORK", _gClose]];', $guide);
        self::assertStringContainsString('["open"] call comspec_atak_native_fnc_mpuDiag;', $guide);
    }

    public function testParagraphesCourtsSurLaPagePrincipale(): void
    {
        preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/u', $this->section(), $m);
        self::assertNotEmpty($m[1]);
        foreach ($m[1] as $lit) {
            $plain = trim((string) preg_replace('/<[^>]+>/', '', $lit));
            self::assertLessThanOrEqual(self::MAX_PARA, mb_strlen($plain), 'Texte trop long pour la page principale : ' . $plain);
        }
    }

    public function testLibellesUniques(): void
    {
        $labels = $this->buttonLabels($this->section());
        self::assertGreaterThan(15, count($labels));
        self::assertSame($labels, array_values(array_unique($labels)), 'Libellé de bouton en double');
        $rows = $this->rowLabels($this->section());
        self::assertSame($rows, array_values(array_unique($rows)), 'Rangée en double');
    }

    public function testChaqueTexteEstTraduit(): void
    {
        $en = $this->keys(self::MOD . '/Sources/addons/main/i18n/en.txt');
        $xx = $this->keys(self::MOD . '/localisation/template/xx.txt');
        $src = $this->section();
        $texts = array_merge($this->buttonLabels($src), $this->rowLabels($src), $this->tooltips($src), $this->buttonLabels($this->guide()));
        preg_match_all('/"([^"<>%]{6,})"\] call _gStep/u', $this->guide(), $g);
        preg_match_all('/\[\d, (?:_g\w+|false), "([^"]+)"/u', $this->guide(), $gt);
        $texts = array_merge($texts, $g[1], $gt[1], [
            'Voix par le maillage', 'Votre radio ACRE2 / TFAR passe par la MPU5', 'désactivée par l\'admin', 'radio non reliée',
            'Aucun autre nœud sur le canal %1 : montez sur un point haut ou rapprochez-vous d\'une MPU5 ou d\'un relais.',
            'Prenez une MPU5 (arsenal) ou approchez-vous d\'une MPU5 posée.', 'Allumez la radio pour rejoindre le maillage.',
            'Reliez le téléphone : câble EUD ou Wi-Fi ci-dessous.', 'Grisé', 'Personne ne vous entend ? DÉPANNAGE MPU vérifie chaque maillon.',
            'Un admin l\'active dans Échap > Réalisme > Maillage. Les étapes : GUIDE.',
        ]);
        self::assertGreaterThan(40, count($texts));
        foreach (array_unique($texts) as $t) {
            if (in_array($t, self::SAME, true)) {
                continue;
            }
            self::assertArrayHasKey($t, $en, 'Sans anglais (i18n/en.txt) : ' . $t);
            self::assertArrayHasKey($t, $xx, 'Absent du modèle de traduction (localisation/template/xx.txt) : ' . $t);
        }
    }

    public function testRadioGuardEtVoixInchanges(): void
    {
        // La voix ACRE2 / TFAR garde ses actions : relier, débrancher la radio, dépannage.
        $src = $this->section();
        self::assertStringContainsString('\'["bridge", [%1]] call comspec_atak_native_fnc_mpuVoice;\'', $src);
        self::assertStringContainsString('["unbridge"] call comspec_atak_native_fnc_mpuVoice;', $src);
        self::assertStringNotContainsString('fnc_radioGuard', $src);
    }
}
