<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * App ATHENA, code en attente d'accord (4.28.0) : plus de grand vide ni de bouton coupé.
 * Avant : le QR code était une ligne « image » 16/9 pleine largeur, plus haute que l'écran en paysage, rejetée sous les
 * colonnes ; le code et ses boutons défilaient hors de vue. Maintenant une carte « code + QR » (ligne « qrcard »),
 * dimensionnée sur la hauteur visible par fn_qrCardGeom, dont le calcul est rejoué ici à partir de ses constantes.
 */
final class AtakAthenaLinkCardAssetTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private static function read(string $rel): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::MAIN . '/' . $rel));
    }

    /** Code SQF sans commentaires ni chaînes : pour compter les délimiteurs et chercher des commandes. */
    private static function code(string $sqf): string
    {
        $sqf = (string) preg_replace('~/\*.*?\*/~s', '', $sqf);
        $sqf = (string) preg_replace('~"(?:[^"]|"")*"~s', '""', $sqf);

        return (string) preg_replace('~//[^\n]*~', '', $sqf);
    }

    public function testPendingCodeIsOneCardBeforeTheStatus(): void
    {
        $page = self::read('functions/pages/fn_pageAthena.sqf');
        // Plus de QR en ligne « image » (16/9 pleine largeur) ni de section séparée loin du code.
        self::assertStringNotContainsString('["image", "\\z\\comspec_atak_native\\addons\\main\\data\\athena_link_qr_ca.paa"]', $page);
        self::assertStringNotContainsString('"Sur votre vrai téléphone"', $page);
        self::assertStringContainsString('["qrcard", "\\z\\comspec_atak_native\\addons\\main\\data\\athena_link_qr_ca.paa"', $page);
        self::assertFileExists(self::MAIN . '/data/athena_link_qr_ca.paa');
        // Ordre des étapes, texte complet et texte court.
        $p1 = strpos($page, '1. Ouvrez cette page, ou scannez le QR code :');
        $p2 = strpos($page, '2. Saisissez ce code :');
        $p3 = strpos($page, '3. Acceptez sur le site, le téléphone se connecte seul.');
        self::assertNotFalse($p1);
        self::assertNotFalse($p2);
        self::assertNotFalse($p3);
        self::assertTrue($p1 < $p2 && $p2 < $p3, 'étapes 1, 2, 3 dans l\'ordre');
        self::assertStringContainsString('1. Ouvrez %1 ou scannez le QR code', $page);
        self::assertStringContainsString('3. Acceptez sur le site · encore %2 min', $page);
        self::assertStringContainsString('En attente de votre accord · valable encore %2 min', $page);
        // Les deux boutons sont dans la carte, avec le code.
        $card = substr($page, (int) strpos($page, '["qrcard"'), 2600);
        self::assertStringContainsString('"OUVRIR LA PAGE", { ["open"] call comspec_atak_native_fnc_athenaLink; }', $card);
        self::assertStringContainsString('"NOUVEAU CODE", { ["start"] call comspec_atak_native_fnc_athenaLink; }', $card);
        self::assertStringContainsString('_lc,', $card);
        // Code en attente : onglets et carte avant la carte d'état, étapes de la liaison repliées d'office.
        self::assertStringContainsString('private _pending = _canLink && {_tab isEqualTo "account"} && {_ls isEqualTo "pending"};', $page);
        self::assertStringContainsString('([_rows, _first] select _pending) pushBack ["segment", "", [', $page);
        self::assertStringContainsString('getVariable ["COMSPEC_ATAK_AthenaSteps", !_logged && {!_pending}]', $page);
        self::assertStringContainsString('[_first + _rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;', $page);
        // Les autres états gardent leur bouton (code expiré, erreur, pas encore de code).
        foreach (['case "asking":', 'case "approved":', 'case "expired":', 'case "error":', '"OBTENIR MON CODE"', '"RÉESSAYER"'] as $needle) {
            self::assertStringContainsString($needle, $page, $needle);
        }
    }

    public function testFormRendersTheCardFullWidthFromTheVisibleHeight(): void
    {
        $form = self::read('functions/ui/fn_formRender.sqf');
        self::assertStringContainsString('case "qrcard": {', $form);
        self::assertMatchesRegularExpression('/private _flBarrier = \[[^\]]*"qrcard"/', $form);
        // Hauteur visible restante sous la ligne, pas une hauteur fixe.
        self::assertStringContainsString('(_rh - _y - _pad) max (_rowH * 3)', $form);
        self::assertStringContainsString('call comspec_atak_native_fnc_qrCardGeom;', $form);
        // QR carré à l'écran : largeur = côté / rapport d'aspect, sur fond blanc.
        self::assertStringContainsString('private _qw = _side / _ratio;', $form);
        self::assertStringContainsString('["COMSPEC_RscSlide", [_qx, _qy, _qw, _side], _pic] call _mk;', $form);
        self::assertStringContainsString('class qrCardGeom {};', self::read('config.cpp'));
        self::assertFileExists(self::MAIN . '/functions/ui/fn_qrCardGeom.sqf');
    }

    public function testTouchedSqfIsBalancedAndSilent(): void
    {
        foreach (['functions/pages/fn_pageAthena.sqf', 'functions/ui/fn_formRender.sqf', 'functions/ui/fn_qrCardGeom.sqf'] as $rel) {
            $code = self::code(self::read($rel));
            foreach (['{' => '}', '[' => ']', '(' => ')'] as $o => $c) {
                self::assertSame(substr_count($code, $o), substr_count($code, $c), "$rel : $o $c");
            }
            // Aucune commande d'affichage à l'écran d'Arma (les variables _hint… ne comptent pas).
            self::assertSame(0, preg_match('/(?<![\w])(systemChat|hint|hintSilent|hintC|cutText|titleText)(?![\w])/', $code), $rel);
        }
    }

    /** @return array<string, float|list<float>> constantes de fn_qrCardGeom.sqf */
    private static function constants(): array
    {
        $src = self::read('functions/ui/fn_qrCardGeom.sqf');
        $out = [];
        foreach (['QR_FRAC', 'QR_FRAC_BELOW', 'QR_MIN_FB', 'SPLIT_CHARS', 'TEXT_REL', 'URL_REL', 'CODE_REL', 'LINE', 'CHAR_W'] as $k) {
            self::assertSame(1, preg_match('/private _' . $k . ' = ([0-9.]+);/', $src, $m), $k);
            $out[$k] = (float) $m[1];
        }
        self::assertSame(1, preg_match('/private _SCALES = \[([0-9., ]+)\];/', $src, $m));
        $out['SCALES'] = array_map('floatval', explode(',', $m[1]));

        return $out;
    }

    /**
     * Même calcul que fn_qrCardGeom.sqf (unités : 1 = 1 px en vertical, $ratio px en horizontal).
     *
     * @param array{list<int>, list<int>} $full
     * @param array{list<int>, list<int>} $short
     * @return array{wide: bool, short: bool, scale: float, fh: float, side: float, tw: float, textH: float, cardH: float, fits: bool}
     */
    private static function geom(float $w, float $avail, float $fb, float $btnH, float $ip, float $ratio, float $fmin, array $full, array $short): array
    {
        $c = self::constants();
        $qrMin = $c['QR_MIN_FB'] * $fb;
        $cands = [[false, $full, 1.0], [false, $full, 0.9]];
        foreach ($c['SCALES'] as $s) {
            $cands[] = [true, $short, $s];
        }
        $out = [];
        foreach ($cands as [$isShort, $paras, $scale]) {
            $fh = max($fb * $c['TEXT_REL'] * $scale, $fmin);
            $fCode = $fb * $c['CODE_REL'] * $scale;
            $cw = $fh * $c['CHAR_W'] / $ratio;
            $side = max(min($w * $c['QR_FRAC'] * $ratio, $avail - 2 * $ip), $qrMin);
            $tw = $w - $side / $ratio - 3 * $ip;
            $wide = $tw >= $c['SPLIT_CHARS'] * $cw;
            if (!$wide) {
                $tw = $w - 2 * $ip;
            }
            $lines = 0.0;
            foreach ($paras[0] as $i => $n) {
                $rel = ($i === 1 && !$isShort) ? $c['URL_REL'] : 1.0;
                $lines += max(ceil(($n * $cw * $rel) / max($tw, 0.01)), 1) * $rel;
            }
            foreach ($paras[1] as $n) {
                $lines += max(ceil(($n * $cw) / max($tw, 0.01)), 1);
            }
            $textH = $lines * $fh * $c['LINE'] + $fCode * $c['LINE'] + $ip + $btnH;
            if ($wide) {
                $side = min($side, max($textH, $qrMin));
                $cardH = max($textH, $side) + 2 * $ip;
            } else {
                $side = max(min($w * $c['QR_FRAC_BELOW'] * $ratio, max($avail - $textH - 3 * $ip, $qrMin)), $qrMin);
                $cardH = $textH + $side + 3 * $ip;
            }
            $fits = $wide ? $cardH <= $avail : $textH + 2 * $ip <= $avail;
            $out = ['wide' => $wide, 'short' => $isShort, 'scale' => $scale, 'fh' => $fh, 'side' => $side, 'tw' => $tw, 'textH' => $textH, 'cardH' => $cardH, 'fits' => $fits];
            if ($fits) {
                break;
            }
        }

        return $out;
    }

    /**
     * Écran du téléphone d'après fn_layoutGet.sqf (coque S7), puis place de la carte sous les onglets de l'app Athena.
     *
     * @return array{w: float, avail: float, fb: float, rowH: float, ip: float, fmin: float, ratio: float}
     */
    private static function screen(int $resW, int $resH, bool $mini, bool $land, float $textScale, float $miniScale = 1.0, float $fullScale = 1.0): array
    {
        $ratio = 4 / 3;
        $szH = (float) $resH;
        $szW = $resW / $ratio;
        [$lx0, $ly0, $lx1, $ly1] = [0.1025, 0.2798, 0.9263, 0.8535];
        [$sx0, $sy0, $sx1, $sy1] = [0.2222, 0.3496, 0.7549, 0.6509];
        $vis = $land ? [$lx0, $ly0, $lx1, $ly1] : [1 - $ly1, $lx0, 1 - $ly0, $lx1];
        $scr = $land ? [$sx0, $sy0, $sx1, $sy1] : [1 - $sy1, $sx0, 1 - $sy0, $sx1];
        $bleed = 0.003;
        $scr = [$scr[0] - $bleed, $scr[1] - $bleed, $scr[2] + $bleed, $scr[3] + $bleed];
        if ($land) {
            $wantW = $mini ? $szW * 0.40 * $miniScale : $szW * 0.94 * $fullScale;
            $dh = min($wantW / ($vis[2] - $vis[0]) * $ratio, ($mini ? min($szH * 0.50 * $miniScale, $szH * 0.9) : $szH * 0.96 * $fullScale) / ($vis[3] - $vis[1]));
        } else {
            $dh = min($szH * 0.62 * $miniScale, $szH * 0.94) / ($vis[3] - $vis[1]);
        }
        $dw = $dh / $ratio;
        $sw = $dw * ($scr[2] - $scr[0]);
        $sh = $dh * ($scr[3] - $scr[1]);
        $k = $mini ? ($land ? 0.060 : 0.040) : 0.036;
        $font = max(max($sh * $k, $szH * 0.015) * $textScale, 16.0);
        $fmin = max($font * 0.70, 14.0);
        $rowH = $font * 1.55;
        $bodyW = $sw - ($land ? $font * 2.9 / $ratio : 0.0);
        $bodyH = $sh - $font * 1.45 - $font * 1.9 - ($land ? 0.0 : $font * 2.9);
        $pad = $sw * 0.012 * 2;
        $w = $bodyW - 0.014 * $szW - 2 * $pad;
        // Sous les onglets CODE / E-MAIL / STEAM (ligne « segment » sans libellé).
        $y = $pad + $rowH * 0.9 + $pad / 2;

        return ['w' => $w, 'avail' => max($bodyH - $y - $pad, $rowH * 3), 'fb' => $font * 1.15, 'rowH' => $rowH, 'ip' => $pad / 2, 'fmin' => $fmin, 'ratio' => $ratio];
    }

    /** Longueurs des paragraphes (le plus long du français et de l'anglais, adresse et minutes comprises). */
    private const FULL = [[46, 22, 22], [51, 55]];
    private const SHORT = [[49], [39]];

    public function testFullScreenLandscapeShowsEverythingWithoutScrolling(): void
    {
        foreach ([[1920, 1080], [2560, 1440], [3840, 2160], [1366, 768], [2560, 1080]] as [$rw, $rh]) {
            foreach ([0.8, 1.0, 1.15, 1.35] as $ts) {
                // Téléphone réduit à 60 % (réglage « Taille du téléphone en plein écran ») : à partir de 1080 lignes.
                foreach ($rh >= 1080 ? [1.0, 0.8, 0.6] : [1.0, 0.8] as $fs) {
                    // Téléphone réduit et très grand texte à la fois : la carte passe en colonne (test suivant).
                    if ($fs < 0.8 && $ts > 1.15) {
                        continue;
                    }
                    $s = self::screen($rw, $rh, false, true, $ts, 1.0, $fs);
                    $g = self::geom($s['w'], $s['avail'], $s['fb'], $s['rowH'], $s['ip'], $s['ratio'], $s['fmin'], self::FULL, self::SHORT);
                    $ctx = "{$rw}x{$rh} texte {$ts} taille {$fs}";
                    self::assertTrue($g['wide'], "QR à côté du texte : $ctx");
                    self::assertTrue($g['fits'], "carte entière dans la hauteur visible : $ctx");
                    self::assertLessThanOrEqual($s['avail'], $g['cardH'], $ctx);
                    // QR lisible : au moins 6 tailles de texte, et jamais plus haut que la carte.
                    self::assertGreaterThanOrEqual(5.9 * $s['fb'], $g['side'], "QR lisible : $ctx");
                    self::assertLessThanOrEqual($g['cardH'] - 2 * $s['ip'] + 0.001, $g['side'], $ctx);
                    // Plus de vide : la carte n'est pas plus haute que son texte et ses boutons (ou que le QR minimal).
                    self::assertLessThanOrEqual(max($g['textH'], 6 * $s['fb']) + 2 * $s['ip'] + 0.001, $g['cardH'], $ctx);
                    // La colonne de texte et le QR tiennent dans la largeur.
                    self::assertLessThanOrEqual($s['w'] + 0.001, $g['tw'] + $g['side'] / $s['ratio'] + 3 * $s['ip'], $ctx);
                }
            }
        }
    }

    public function testSmallPhoneKeepsCodeStateAndButtonsVisible(): void
    {
        // Téléphone en petit format, en main : paysage (QR à côté) et portrait (QR sous les boutons).
        foreach ([[1920, 1080], [2560, 1440]] as [$rw, $rh]) {
            foreach ([true, false] as $land) {
                foreach ([0.8, 1.0] as $ts) {
                    $s = self::screen($rw, $rh, true, $land, $ts);
                    $g = self::geom($s['w'], $s['avail'], $s['fb'], $s['rowH'], $s['ip'], $s['ratio'], $s['fmin'], self::FULL, self::SHORT);
                    $ctx = "{$rw}x{$rh} " . ($land ? 'paysage' : 'portrait') . " texte {$ts}";
                    self::assertTrue($g['fits'], "adresse, code, état et boutons visibles sans défilement : $ctx");
                    self::assertSame($land, $g['wide'], "QR à côté en paysage, dessous en portrait : $ctx");
                    self::assertGreaterThanOrEqual(5.9 * $s['fb'], $g['side'], "QR lisible : $ctx");
                    self::assertGreaterThan(0.0, $g['tw'], $ctx);
                }
            }
        }
    }

    public function testReleaseCarriesVersionChangelogAndDictionary(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertSame(1, preg_match('/#define VERSION_STR "(\d+)\.(\d+)\.(\d+)"/', self::read('script_component.hpp'), $v));
        self::assertTrue([(int) $v[1], (int) $v[2], (int) $v[3]] >= [4, 28, 0], 'ATAK natif 4.28.0 au moins');
        $log = (string) file_get_contents($root . '/CHANGELOG-ATAK.md');
        self::assertStringContainsString('(ATAK natif 4.28.0.AG - Washington)', $log);
        self::assertStringContainsString('FR : Le téléphone marche sans relais, et plusieurs écrans sont rangés', $log);
        self::assertStringContainsString('EN : The phone works without relays, and several screens are tidied up', $log);
        self::assertStringContainsString('[h1]COMSPEC ATAK 4.28.0 Washington', (string) file_get_contents($root . '/mod/COMSPEC_ATAK_Native/publisher/STEAM_CHANGELOG.txt'));
        self::assertFileExists($root . '/docs/dev/ATAK-INVENTAIRE-A-RETIRER.md');
        // Dictionnaire anglais : les textes de la carte et du réglage de la tablette de bord.
        $en = self::read('i18n/en.txt');
        foreach ([
            '["1. Ouvrez cette page, ou scannez le QR code :", "1. Open this page, or scan the QR code:"]',
            '["3. Acceptez sur le site, le téléphone se connecte seul.", "3. Approve on the site, the phone connects by itself."]',
            '["1. Ouvrez %1 ou scannez le QR code", "1. Open %1 or scan the QR code"]',
            '["3. Acceptez sur le site", "3. Approve on the site"]',
            '["encore %1 min", "%1 min left"]',
            '["2. Saisissez ce code :", "2. Enter this code:"]',
            '["Liaison satellite", "Satellite link"]',
            '["Relais détruit", "Relay destroyed"]',
            '["Apps épinglées", "Pinned apps"]',
            '["Console d\'analyse", "Analysis console"]',
        ] as $entry) {
            self::assertStringContainsString($entry, $en, $entry);
        }
    }

    public function testOldLayoutWasTallerThanTheScreen(): void
    {
        // Cause du vide : ligne « image » = largeur × 0,5625 × rapport, soit plus haut que toute la zone de l'app en paysage.
        $form = self::read('functions/ui/fn_formRender.sqf');
        self::assertStringContainsString('private _ih = _w * 0.5625 * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);', $form);
        $s = self::screen(1920, 1080, false, true, 1.0);
        self::assertGreaterThan($s['avail'], $s['w'] * 0.5625 * $s['ratio']);
    }
}
