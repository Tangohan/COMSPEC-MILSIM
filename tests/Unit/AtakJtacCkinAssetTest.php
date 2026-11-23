<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * App JTAC de l'ATAK natif, onglet CKIN : fiche de check-in CAS à compléter sur le modèle de la fiche blanche
 * « 9 LINE CLOSE AIR SUPPORT BRIEF » (en-tête, 7 champs de check-in, situation, 9 lignes, remarques, FAH, ligne laser-cible,
 * menaces, ligne canon-cible, TOT / TTT ou PUSH ASAP), brouillon au profil (écriture différée), copie en texte lu en phonie,
 * envoi au pilote (espace BRIEFS AIR, format "C"), versement au compte rendu (pièce jointe en données structurées), historique,
 * et reprise des 9 / 5-LINE du TOC web ou de la version mobile. Sources SQF lues sans dépendre des fins de ligne : délimiteurs
 * équilibrés, aucun affichage à l'écran d'Arma hors téléphone, textes dans le dictionnaire anglais, câblage.
 */
final class AtakJtacCkinAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private const TAC = self::MAIN . 'functions/tactical/';

    private const NEW_FILES = [self::TAC . 'fn_jtacCkin.sqf', self::TAC . 'fn_pageJtacCkin.sqf', self::TAC . 'fn_jtacTocImport.sqf'];

    private const TOUCHED = [self::TAC . 'fn_pageJtac.sqf', self::TAC . 'fn_jtacAction.sqf', self::TAC . 'fn_airBrief.sqf', self::MAIN . 'functions/reports/fn_reportAction.sqf'];

    /** Textes nouveaux affichés dans le téléphone : une entrée anglaise chacun. */
    private const TEXTS = [
        'En-tête et check-in', 'Situation et game plan', '9 lignes', 'Lecture en phonie', 'PRÉ-REMPLIR DEPUIS LE JEU', 'ENREGISTRER LE BROUILLON', 'COPIER EN TEXTE',
        'VERSER AU COMPTE RENDU', 'ENVOYER AU PILOTE / À L\'ÉQUIPAGE', 'Historique de la partie', 'DUPLIQUER POUR UN NOUVEAU CAS', 'Indicatif pilote / n° de mission',
        'Nombre et type d\'appareils', 'Position et altitude', 'Temps sur zone', 'Code d\'annulation', 'Remarques complémentaires', 'Axe final d\'attaque',
        'Ligne canon-cible active', 'Brouillon du check-in enregistré', 'Check-in copié en texte (Ctrl+V)', 'RÉCUPÉRER DEPUIS LE TOC / MOBILE', 'IMPORTÉE DU TOC',
        'Votre fiche diffère de la version du serveur. Appuyez de nouveau pour la remplacer', 'Mettre à jour depuis le serveur', 'Fiche de check-in CAS de cet appareil (onglet CKIN)',
    ];

    private static function read(string $rel): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel));
    }

    /** Code SQF sans commentaires ni chaînes. */
    private static function strip(string $src): string
    {
        $out = '';
        $n = strlen($src);
        for ($i = 0; $i < $n; $i++) {
            $c = $src[$i];
            if ($c === '/' && ($src[$i + 1] ?? '') === '/') {
                while ($i < $n && $src[$i] !== "\n") {
                    $i++;
                }
                $out .= "\n";
                continue;
            }
            if ($c === '/' && ($src[$i + 1] ?? '') === '*') {
                $end = strpos($src, '*/', $i + 2);
                $i = $end === false ? $n : $end + 1;
                continue;
            }
            if ($c === '"' || $c === "'") {
                $i++;
                while ($i < $n) {
                    if ($src[$i] === $c) {
                        if (($src[$i + 1] ?? '') === $c) {
                            $i += 2;
                            continue;
                        }
                        break;
                    }
                    $i++;
                }
                $out .= '""';
                continue;
            }
            $out .= $c;
        }

        return $out;
    }

    /** @return iterable<string, array{string}> */
    public static function allFiles(): iterable
    {
        foreach (array_merge(self::NEW_FILES, self::TOUCHED) as $f) {
            yield basename($f) => [$f];
        }
    }

    /** @return iterable<string, array{string}> */
    public static function newFiles(): iterable
    {
        foreach (self::NEW_FILES as $f) {
            yield basename($f) => [$f];
        }
    }

    #[DataProvider('allFiles')]
    public function testDelimitersAreBalanced(string $rel): void
    {
        $code = self::strip(self::read($rel));
        $depth = ['{' => 0, '[' => 0, '(' => 0];
        $close = ['}' => '{', ']' => '[', ')' => '('];
        foreach (str_split($code) as $ch) {
            if (isset($depth[$ch])) {
                $depth[$ch]++;
            } elseif (isset($close[$ch])) {
                $depth[$close[$ch]]--;
                self::assertGreaterThanOrEqual(0, $depth[$close[$ch]], $rel . ' : ' . $ch);
            }
        }
        self::assertSame(['{' => 0, '[' => 0, '(' => 0], $depth, $rel);
    }

    #[DataProvider('newFiles')]
    public function testNothingIsShownOutsideThePhone(string $rel): void
    {
        $code = self::strip(self::read($rel));
        foreach (['systemChat', 'hint', 'hintSilent', 'hintC', 'cutText', 'cutRsc', 'titleText', 'titleRsc', 'BIS_fnc_showNotification', 'BIS_fnc_dynamicText',
            'BIS_fnc_guiMessage', 'comspec_atak_native_fnc_toast', 'comspec_overwatch_connect_fnc_notify', 'sideChat', 'globalChat', 'groupChat', 'vehicleChat',
            'createDialog', 'createDisplay'] as $banned) {
            self::assertSame(0, preg_match('/\b' . preg_quote($banned, '/') . '\b/', $code), $rel . ' : ' . $banned);
        }
        // Retours au joueur : seulement les notifications du téléphone.
        foreach (explode("\n", self::read($rel)) as $line) {
            if (preg_match('/\["(INFO|SUCCESS|WARNING|ERROR)", /', $line) === 1) {
                self::assertStringContainsString('call comspec_atak_native_fnc_notify', $line, $rel);
            }
        }
        self::assertSame(0, preg_match('/\b(onEachFrame|addMissionEventHandler|while\s*\{)/', $code), $rel . ' : ni boucle ni gestionnaire par image');
    }

    public function testCkinButtonIsInTheJtacAppAndOnEachAircraft(): void
    {
        $page = self::read(self::TAC . 'fn_pageJtac.sqf');
        self::assertStringContainsString('[["CKIN", "CKIN"], ["SITU", "SIT"], ["9/5-LINE", "BRIEF"]', $page, 'onglet CKIN en tête');
        self::assertStringContainsString('case "CKIN": { _rows append ([] call comspec_atak_native_fnc_pageJtacCkin); };', $page);
        self::assertStringContainsString('["CKIN", compile format ["[\'pickAir\', %1] call comspec_atak_native_fnc_jtacCkin;"', $page, 'bouton CKIN sur chaque appareil en station');
        self::assertStringContainsString('case "C": { ["labels"] call comspec_atak_native_fnc_jtacCkin };', $page, 'l’équipage lit la fiche dans la même page');
        $cfg = self::read(self::MAIN . 'config.cpp');
        foreach (['pageJtacCkin', 'jtacCkin', 'jtacTocImport'] as $fn) {
            self::assertStringContainsString('class ' . $fn . ' {};', $cfg);
        }
        self::assertStringContainsString('["save"] call comspec_atak_native_fnc_jtacCkin;', self::read(self::TAC . 'fn_jtacAction.sqf'), 'saisie gardée au changement d’onglet');
    }

    public function testFormFieldsAreThoseOfTheBlankBrief(): void
    {
        $src = self::read(self::TAC . 'fn_jtacCkin.sqf');
        preg_match_all('/^\s+\["(ck[A-Za-z0-9]+)", "[^"]+", "([a-z0-9]+)", "([^"]+)", "(hdr|ckin|sit|nine|rem)"/m', $src, $m);
        $groups = [];
        foreach ($m[1] as $i => $key) {
            $groups[$m[4][$i]][] = $m[3][$i];
        }
        self::assertSame(['AIRCRAFT CALLSIGN', 'FAC CALLSIGN'], $groups['hdr']);
        self::assertSame(['PILOT CALLSIGN / MISSION #', 'NUMBER AND TYPE OF AIRCRAFT', 'POSITION AND ALTITUDE', 'ORDNANCE', 'TIME ON STATION', 'ABORT CODE', 'ADDITIONAL REMARKS'], $groups['ckin'], '7 champs de check-in');
        self::assertSame(['IP / BP', 'HEADING', 'OFFSET', 'DISTANCE', 'TARGET ELEVATION', 'TARGET DESCRIPTION', 'TARGET LOCATION', 'TYPE MARK', 'LASER CODE', 'FRIENDLIES', 'EGRESS'], $groups['nine'], '9 lignes, décalage et code laser');
        self::assertSame(['REMARKS (STAY ABOVE / BELOW, NO FLY AREAS)', 'FINAL ATTACK HEADING', 'LASER TARGET LINE', 'THREATS', 'ACTIVE GUN TARGET LINE', 'TOT / TTT', 'PUSH ASAP', 'AMPLIFYING INFORMATION'], $groups['rem']);
        // Les clés Athena sont celles que lit la fiche imprimable du poste TOC.
        foreach (array_unique($m[2]) as $ak) {
            self::assertArrayHasKey($ak, \App\Services\AtakToc\CasCheckinSheet::FIELDS, $ak);
        }
        self::assertCount(count(\App\Services\AtakToc\CasCheckinSheet::FIELDS), $m[2], 'tous les champs de la fiche imprimable');
        foreach (['STAND BY FOR 9 LINE, I AM NOT A JTAC', 'SAY WHEN READY TO COPY REMARKS', 'SAY WHEN READY FOR AMPLIFYING INFORMATION', 'STANDING BY FOR AIRCRAFT CHECK-IN'] as $phrase) {
            self::assertStringContainsString($phrase, $src, 'phrase du FAC dans le texte lu en phonie');
        }
        $page = self::read(self::TAC . 'fn_pageJtacCkin.sqf');
        foreach (['"fold", "1 · En-tête et check-in"', '"fold", "2 · Situation et game plan"', '"fold", "3 · 9 lignes"', '"fold", "4 · Remarques"', '"fold", "5 · Lecture en phonie"'] as $fold) {
            self::assertStringContainsString($fold, $page, 'sections repliables');
        }
    }

    public function testDraftCopySendAndReportReuseThePhoneServices(): void
    {
        $src = self::read(self::TAC . 'fn_jtacCkin.sqf');
        self::assertStringContainsString('private _draftKey = "COMSPEC_ATAK_JtacCkinDraft";', $src);
        self::assertStringContainsString('profileNamespace setVariable [_draftKey, (keys _h) apply { [_x, _h get _x] }];', $src);
        self::assertStringContainsString('[] call comspec_atak_native_fnc_saveProfile;', $src, 'écriture du profil différée');
        self::assertStringNotContainsString('saveProfileNamespace', $src);
        self::assertStringContainsString('copyToClipboard _t;', $src);
        self::assertStringContainsString('["ClipBegin"] call comspec_atak_native_fnc_extensionCall', $src, 'presse-papiers Windows en multijoueur');
        self::assertStringContainsString('["send", ["C", _lines, [_h, "ckSit"] call _val, _dest, "", true]] call comspec_atak_native_fnc_airBrief', $src);
        self::assertStringContainsString('["prefill", "OTHER", _v] call comspec_atak_native_fnc_reportAction;', $src);
        self::assertStringContainsString('createHashMapFromArray [["form", "cas_ckin"]]', $src);
        self::assertStringContainsString('missionNamespace setVariable [_histKey, ([_e] + _l) select [0, 20]];', $src, 'historique de la partie');
        foreach (['"histOpen"', '"histDup"', '"histDel"', '"histCopy"', '"histReport"'] as $case) {
            self::assertStringContainsString('case ' . $case, $src);
        }
        $air = self::read(self::TAC . 'fn_airBrief.sqf');
        self::assertStringContainsString('["C", "CHECK-IN"]', $air);
        self::assertStringContainsString('if (_fmt in ["9", "5"] && {[] call comspec_atak_native_fnc_bridge}) then {', $air, 'la fiche CKIN ne part pas sur la page /9-line');
        $rep = self::read(self::MAIN . 'functions/reports/fn_reportAction.sqf');
        self::assertStringContainsString('if (_annex isEqualType createHashMap) then { { _sd set [_x, _y]; } forEach _annex; };', $rep);
        self::assertStringContainsString('["annex", +(_vals getOrDefault ["annex", createHashMap])]', $rep);
    }

    public function testNewTextsHaveAnEnglishEntry(): void
    {
        $dict = [];
        foreach (explode("\n", self::read(self::MAIN . 'i18n/en.txt')) as $line) {
            if (preg_match('/^\["((?:[^"]|"")*)", "((?:[^"]|"")*)"\],?$/', $line, $mm) === 1) {
                $fr = str_replace('""', '"', $mm[1]);
                if (in_array($fr, self::TEXTS, true)) {
                    self::assertArrayNotHasKey($fr, $dict, 'une seule entrée : ' . $fr);
                }
                $dict[$fr] = str_replace('""', '"', $mm[2]);
            }
        }
        foreach (self::TEXTS as $fr) {
            self::assertArrayHasKey($fr, $dict, $fr);
            self::assertNotSame('', $dict[$fr], $fr);
        }
    }
}
