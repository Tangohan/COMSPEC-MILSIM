<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * App SSE du téléphone ATAK, corrections d'interface (retour de jeu 4.31.0, pages Avis et vigilance et Fiches) :
 * QR code entier dans la zone visible, marge basse de la zone de défilement, plus de bandeau de classification vide,
 * plus de guillemets ni de parenthèses vides autour d'une valeur caviardée, marges entre les blocs et les boutons, grilles
 * alignées, barre d'onglets de la coque à la hauteur de ses rangées. Lit les sources : indépendant des fins de ligne.
 */
final class AtakSseAppUiAssetTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private static function read(string $rel): string
    {
        $src = file_get_contents(self::MAIN . $rel);
        self::assertIsString($src, $rel);

        return str_replace("\r\n", "\n", $src);
    }

    /** Code SQF sans commentaires (ni // ni bloc). */
    private static function code(string $src): string
    {
        $src = (string) preg_replace('~/\*.*?\*/~s', '', $src);

        return (string) preg_replace('~^\s*//[^\n]*$~m', '', $src);
    }

    /** Corps d'un « case "x": { » de fn_formRender, jusqu'au case suivant. */
    private static function formCase(string $kind): string
    {
        $src = self::read('functions/ui/fn_formRender.sqf');
        $a = strpos($src, 'case "' . $kind . '": {');
        self::assertNotFalse($a, $kind);
        $b = strpos($src, "\n        case \"", $a + 10);
        self::assertNotFalse($b, $kind);

        return substr($src, $a, $b - $a);
    }

    public function testTheQrCodeIsAlwaysWholeInTheVisibleArea(): void
    {
        $qr = self::formCase('qr');
        // Hauteur bornée par celle du formulaire (marges et ligne d'explication comprises), au moins 2 px par module.
        self::assertStringContainsString('_side = (_side min ((_rh - _pad * 3 - _fs * 2.6) / _ratio)) max ((_n + 8) * pixelW * 2);', $qr);
        // Marge avant le QR : il ne colle plus au bloc d'adresse.
        self::assertMatchesRegularExpression('~_y = _y \+ _pad / 2;\s+private _y0 = _y;~', $qr);
        // Il reste dans la colonne de sa section (plus une barrière pleine largeur loin de son adresse).
        $form = self::read('functions/ui/fn_formRender.sqf');
        self::assertMatchesRegularExpression('~private _flBarrier = \[[^\]]*\];~', $form);
        preg_match('~private _flBarrier = \[([^\]]*)\];~', $form, $m);
        self::assertStringNotContainsString('"qr"', $m[1]);
        self::assertStringContainsString('"qrcard"', $m[1]);
        // L'app Avis et vigilance donne une part de colonne, plus une part de largeur choisie selon l'orientation.
        $page = self::read('functions/alertes/fn_pageAlertes.sqf');
        self::assertStringContainsString('_rows pushBack ["qr", _qr, 0.62];', $page);
        self::assertStringNotContainsString('[0.62, 0.3] select _land', $page);
    }

    public function testTheScrollAreaKeepsABottomMargin(): void
    {
        $form = self::code(self::read('functions/ui/fn_formRender.sqf'));
        self::assertMatchesRegularExpression('~if \(_y > _rh\) then \{\s+private _flEndPad = \["COMSPEC_RscText", \[0, _y, pixelW, _pad\]\] call _mk;~', $form);
        // Avant la hauteur publiée du formulaire, après la mise en page en colonnes.
        self::assertLessThan(strpos($form, 'uiNamespace setVariable ["COMSPEC_ATAK_FormH", _y + _pad];'), strpos($form, '_flEndPad'));
        self::assertGreaterThan(strpos($form, 'call _flFlush;' . "\n" . '};'), strpos($form, '_flEndPad'));
    }

    public function testNoEmptyClassificationBannerIsDrawn(): void
    {
        $banner = self::formCase('banner');
        // Bandeau sans texte visible : un trait fin, jamais une bande pleine vide.
        self::assertStringContainsString('if ((trim (_txt regexReplace ["<[^>]*>", ""])) isEqualTo "") exitWith {', $banner);
        self::assertStringContainsString('[_pad, _y, _w, _pad * 0.3]', $banner);
        $page = self::read('functions/alertes/fn_pageAlertes.sqf');
        // Plus de filet « vide » de notice au pied du document, collé au bandeau de classification du bas.
        self::assertStringNotContainsString("<t size='0.3'> </t>", $page);
        self::assertSame(1, substr_count($page, '_rows pushBack ["banner", "", _notice select 2];'));
        // Le bandeau de classification a toujours son libellé (marquage du document, sinon celui du porteur, sinon CUI).
        self::assertStringContainsString('if (_mark isEqualTo "") then { _mark = _viewerMark; };', $page);
        self::assertStringContainsString('if (!(_viewerMark isEqualType "") || {_viewerMark isEqualTo ""}) then { _viewerMark = "CUI"; };', $page);
        // Document : un bandeau en tête, un en pied, avec le même marquage.
        $from = strpos($page, '// ---------- Document d\'alerte');
        self::assertNotFalse($from);
        $to = strpos($page, '- Liste' . "\n", $from);
        self::assertNotFalse($to);
        $doc = substr($page, $from, $to - $from);
        self::assertSame(2, substr_count($doc, '([_mark] call _banner)'));
    }

    public function testRedactedValuesNeverLeaveEmptyQuotesOrParentheses(): void
    {
        $hub = self::read('functions/ui/fn_sseHub.sqf');
        // Même règle que le PDF du portail : la barre de caviardage devient « [CAVIARDÉ] » (« [REDACTED] » en anglais).
        self::assertStringContainsString('_arg regexReplace ["(█)+", ["[CAVIARDÉ]", "[REDACTED]"] select _en]', $hub);
        self::assertStringContainsString('regexReplace [format [" ?\((alias)?( |%1)*\)", _nb], ""]', $hub);
        self::assertStringContainsString('regexReplace [format [" ?«( |%1)*»", _nb], ""]', $hub);
        // Nom entièrement caviardé : libellé de repli ; alias caviardés ou vides retirés.
        self::assertStringContainsString('exitWith { "Identité protégée" };', $hub);
        self::assertStringContainsString('_out joinString ", "', $hub);
        // Sortie avant la table de l'app : aucun calcul de l'app pour un texte.
        self::assertLessThan(strpos($hub, 'private _table = ['), strpos($hub, 'if (_act in ["text", "name", "alias", "item"]) exitWith {'));
        // Pas d'espace insécable littérale dans le source (le préprocesseur de HEMTT la refuse).
        self::assertStringNotContainsString("\u{00A0}", $hub);

        $alertes = self::read('functions/alertes/fn_pageAlertes.sqf');
        self::assertStringContainsString('private _alias = ["alias", _f get "alias"] call comspec_atak_native_fnc_sseHub;', $alertes);
        self::assertStringContainsString('private _name = ["name", [_f get "name", "Sujet non identifié"]] call comspec_atak_native_fnc_sseHub;', $alertes);
        self::assertStringContainsString('[[_f get "reason"] call _clean] call _escBr', $alertes);
        $fiches = self::read('functions/fiches/fn_pageFiches.sqf');
        self::assertSame(2, substr_count($fiches, '["alias", _f get "alias"] call comspec_atak_native_fnc_sseHub'));
        self::assertStringContainsString('["name", [_f get "name", "Identité inconnue"]] call comspec_atak_native_fnc_sseHub', $fiches);
        self::assertStringContainsString('_text = [_text] call _clean;', $fiches);
        $wanted = self::read('functions/pages/fn_pageWanted.sqf');
        self::assertSame(2, substr_count($wanted, '_alias = ["alias", _alias] call comspec_atak_native_fnc_sseHub;'));
        // Chaque « dit « … » » ou « « … » » d'alias reste conditionné à un alias non vide.
        foreach (['alertes/fn_pageAlertes.sqf', 'fiches/fn_pageFiches.sqf', 'pages/fn_pageWanted.sqf'] as $rel) {
            $src = self::read('functions/' . $rel);
            preg_match_all('~^[^\n]*«\s*%\d\s*»[^\n]*_alias[^\n]*$~mu', $src, $m);
            self::assertNotEmpty($m[0], $rel);
            foreach ($m[0] as $line) {
                self::assertStringContainsString('select (_alias isNotEqualTo "")', $line, $rel);
            }
        }
        // Libellés de repli traduits.
        $en = self::read('i18n/en.txt');
        self::assertStringContainsString('["Identité protégée", "Protected identity"]', $en);
        self::assertStringContainsString('["[CAVIARDÉ]", "[REDACTED]"]', $en);
    }

    public function testNumberedInstructionsHaveASingleNumber(): void
    {
        $hub = self::read('functions/ui/fn_sseHub.sqf');
        self::assertStringContainsString('_arg regexReplace ["^\s*(•|·|-|–|—|\*|[0-9]+[.)])\s+", ""]', $hub);
        $page = self::read('functions/alertes/fn_pageAlertes.sqf');
        self::assertStringContainsString('_forEachIndex + 1, [["item", _x] call comspec_atak_native_fnc_sseHub] call _escBr', $page);
        self::assertStringContainsString('"· " + ([["item", _x] call comspec_atak_native_fnc_sseHub] call _esc)', $page);
    }

    public function testBlocksAndButtonsKeepTheirMargins(): void
    {
        $form = self::read('functions/ui/fn_formRender.sqf');
        // Lignes au ras de leur filet : la ligne d'un autre genre qui suit reçoit une marge haute.
        self::assertStringContainsString('private _flFlushK = ["info", "line", "lineseg", "linecombo", "row", "check"];', $form);
        self::assertStringContainsString('if (_flPrevK in _flFlushK && {!(_flK0 in _flFlushK)} && {!(_flK0 in ["section", "fold", "gap"])}) then { _flY = _flY + _pad / 2; };', $form);
        // Une grille reçoit cette marge une fois, toutes ses cases partent de la même hauteur.
        self::assertStringContainsString('if (_flPrevK in _flFlushK && {!(_flK in _flFlushK)}) then { _flY = _flY + _pad / 2; };', $form);
        // Gros boutons : écart sp2 entre eux, dans les deux sens.
        $big = self::formCase('big');
        self::assertStringContainsString('private _bigGap = _pad * 0.75;', $big);
        self::assertStringContainsString('(_bw2 + _bigGap)', $big);
        self::assertStringContainsString('(_bh2 + _bigGap)', $big);
        // Boutons ordinaires : écart horizontal et vertical gardés.
        $buttons = self::formCase('buttons');
        self::assertStringContainsString('(_bw + _pad / 2)', $buttons);
        self::assertStringContainsString('(_bRowH + _pad / 4)', $buttons);
    }

    public function testGridsAlignTheirCellsAndWrapCleanly(): void
    {
        $form = self::read('functions/ui/fn_formRender.sqf');
        // Grille équilibrée : 4 cases sur 2 x 2, pas 3 + 1.
        self::assertStringContainsString('while { _flNc > 2 && {(ceil (_flRun / (_flNc - 1))) isEqualTo (ceil (_flRun / _flNc))} } do { _flNc = _flNc - 1; };', $form);
        // Filets d'une rangée alignés sur la case la plus haute.
        self::assertStringContainsString('{ if (_x getVariable ["cs_rule", false]) then { _x ctrlSetPositionY (_yb - pixelH); _x ctrlCommit 0; }; } forEach _cs;', $form);
        self::assertStringContainsString('_flRowMade append _flMade;', $form);
        self::assertMatchesRegularExpression('~_flInGrid = true;.*_flInGrid = false;~s', $form);
        // Case étroite : libellé au-dessus de la valeur (plus de date coupée sur deux lignes à 38 % de la case).
        $info = self::formCase('info');
        self::assertStringContainsString('if (_flInGrid && {_w < (_l get "colChar") * 34}) exitWith {', $info);
        self::assertSame(2, substr_count($info, '_ln setVariable ["cs_rule", true];'));
        // Bloc sujet : texte centré sur la vignette quand il est plus court qu'elle.
        self::assertStringContainsString('_y + _pad / 4 + (_ih - _tH) / 2', self::formCase('person'));
    }

    public function testTheShellStripReservesEveryRowOfTabs(): void
    {
        $layout = self::read('functions/ui/fn_layoutGet.sqf');
        self::assertStringContainsString('while { _ssePer > 1 && {_sseNeed > (_sseW / _ssePer) * 0.86} } do { _ssePer = _ssePer - 1; };', $layout);
        self::assertStringContainsString('private _sseRows = ceil (4 / _ssePer);', $layout);
        self::assertStringContainsString('_sseRows * (_sseTabH + pixelH * 2) - pixelH * 2 + _pad * 3.6', $layout);
        // Même règle de répartition que le composant « tabs ».
        self::assertStringContainsString('while { _per > 1 && {_needW > (_w / _per) * 0.86} } do { _per = _per - 1; };', self::formCase('tabs'));
        // Les pages se placent sous la barre : zone de contenu décalée de la même hauteur.
        self::assertStringContainsString('["body", [_sx + _railW, _bodyY + _sseH, _sw - _railW, _bodyH - _sseH]]', $layout);
    }

    public function testTheStatusLineSeparatesFeedbackFromTheLink(): void
    {
        $page = self::read('functions/alertes/fn_pageAlertes.sqf');
        self::assertStringContainsString('_left = format ["<t color=\'%1\'>%2</t>", _okHex, [_msg select 0] call _esc];', $page);
        self::assertStringContainsString('_right = format ["<t color=\'%1\'>Liaison</t> %2", _muted, ["satellite", "relais"] select', $page);
        self::assertStringNotContainsString('"LIAISON SATELLITE"', $page);
    }

    public function testTouchedFilesShowNothingOutsideThePhone(): void
    {
        foreach (['functions/ui/fn_formRender.sqf', 'functions/ui/fn_layoutGet.sqf', 'functions/ui/fn_sseHub.sqf', 'functions/alertes/fn_pageAlertes.sqf',
            'functions/fiches/fn_pageFiches.sqf', 'functions/pages/fn_pageWanted.sqf'] as $rel) {
            $code = self::code(self::read($rel));
            self::assertDoesNotMatchRegularExpression('~\b(hint|hintSilent|hintC|systemChat|cutText|titleText|titleRsc|cutRsc)\b~', $code, $rel);
            self::assertDoesNotMatchRegularExpression('~(?i)\b(fiction|simulation|spécimen|specimen)\b~u', $code, $rel);
        }
    }
}
