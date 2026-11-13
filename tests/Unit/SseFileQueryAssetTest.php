<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * App Fichiers (SSE, téléphone ATAK) : « Réponse du fichier en attente… » restait affiché pour toujours. En
 * multijoueur la demande part au serveur par remoteExecCall et rien ne bornait l'attente ; une réponse perdue (serveur
 * plus ancien, remoteExec refusé, sortie muette de fn_fileServer) bloquait l'app. Ces tests verrouillent la machine
 * d'états (attente → réponse | aucun enregistrement | erreur, toujours bornée), la normalisation des numéros et des
 * pays, l'inscription des papiers délivrés au registre, et l'absence de toute alerte hors de l'app.
 */
final class SseFileQueryAssetTest extends TestCase
{
    private const DIR = 'mod/@COMSPEC_SSE/addons/identity/functions/';

    private static function src(string $file): string
    {
        $path = dirname(__DIR__, 2) . '/' . self::DIR . $file;
        self::assertFileExists($path);

        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    public function testEveryQueryIsRegisteredAndBoundedByATimeout(): void
    {
        $q = self::src('fn_fileQuery.sqf');
        self::assertStringContainsString('comspec_sse_filePending', $q);
        self::assertMatchesRegularExpression('/\["timeout", _this\] call comspec_sse_fnc_fileClient; \}, \[_tag\], 8\] call CBA_fnc_waitAndExecute/', $q);
        // Le minuteur est armé AVANT l'envoi : une erreur de l'envoi ne peut pas le sauter.
        self::assertLessThan(strpos($q, '["send", ["query"'), strpos($q, 'CBA_fnc_waitAndExecute'));

        $c = self::src('fn_fileClient.sqf');
        foreach (['case "result":', 'case "timeout":', 'case "fail";', 'case "cancel":'] as $case) {
            self::assertStringContainsString($case, $c);
        }
        // Réponse, erreur et réponse tardive passent toutes par le journal (_land).
        self::assertSame(2, substr_count($c, '[_tag, _res] call _land;'));
        self::assertStringContainsString('_h findIf { (_x getOrDefault ["tag", ""]) isEqualTo _tag }', $c);
        self::assertStringContainsString('["error", _why]', $c);
        self::assertStringContainsString('comspec_sse_fileError', $c);
    }

    public function testLocalAnswerFirstAndAthenaBoundedBehindTheLicence(): void
    {
        $c = self::src('fn_fileClient.sqf');
        $result = substr($c, (int) strpos($c, 'case "result":'));
        self::assertLessThan(strpos($result, '[_tag, _res] call _athena;'), strpos($result, '[_tag, _res] call _land;'));
        $ath = substr($c, (int) strpos($c, 'private _athena = {'), 2600);
        // Aucune requête Athena sans licence acceptée, et 15 s au plus.
        self::assertLessThan(strpos($ath, 'QueryIdDocument'), strpos($ath, 'licenceStatus'));
        self::assertStringContainsString('if (!_lic) exitWith {', $ath);
        self::assertStringContainsString('}, [_res, _render], 15] call CBA_fnc_waitAndExecute;', $ath);
        foreach (['"pending"', '"ok"', '"none"', '"offline"', '"unlinked"', '"timeout"'] as $state) {
            self::assertStringContainsString($state, $c . self::src('fn_filePage.sqf'));
        }
    }

    public function testServerNeverStaysSilentOnAQuery(): void
    {
        $s = self::src('fn_fileServer.sqf');
        self::assertStringContainsString('if (isNull _me) exitWith { "Demande sans unité : relancez la requête." call _refuse; };', $s);
        self::assertStringContainsString('"Demande refusée par le serveur : unité d\'un autre poste." call _refuse;', $s);
        self::assertStringContainsString('["fail", [_args param [4, ""], _this]] call _reply;', $s);
        self::assertStringContainsString('["result", [_tag, _res]] call _reply;', $s);
        // _reply défini avant toute sortie de la consultation.
        self::assertLessThan(strpos($s, 'if (isNull _me) exitWith'), strpos($s, 'private _reply = {'));
        foreach (['["match", _match]', '"possible"', '"doubtful"', '["numChecks"'] as $needle) {
            self::assertStringContainsString($needle, $s);
        }
    }

    public function testStaleStateFromAPreviousMissionIsDropped(): void
    {
        $p = self::src('fn_filePage.sqf');
        self::assertStringContainsString('if !(_pending in (missionNamespace getVariable ["comspec_sse_filePending", createHashMap])) then { _st set ["pending", ""];', $p);
        self::assertStringContainsString('_scans deleteAt "files";', $p);
        self::assertStringContainsString('_scans deleteAt "files";', self::src('fn_fileAction.sqf'));
    }

    public function testNumbersAndCountriesAreNormalisedOnBothSides(): void
    {
        $n = self::src('fn_fileNorm.sqf');
        self::assertStringContainsString('(toUpper _v) regexReplace ["[^A-Z0-9]", ""]', $n);
        preg_match_all('/\["([A-Z]+)", "([A-Z]{3})"\]/', $n, $m, PREG_SET_ORDER);
        $alias = [];
        foreach ($m as $row) {
            $alias[$row[1]] = $row[2];
        }
        $expected = ['TAN' => 'TNA', 'LIV' => 'LVN', 'MAL' => 'MLD', 'AL' => 'ALT', 'ALTIS' => 'ALT', 'GB' => 'GBR', 'DE' => 'DEU', 'D' => 'DEU', 'FR' => 'FRA', 'US' => 'USA'];
        foreach ($expected as $in => $out) {
            self::assertSame($out, $alias[$in] ?? null, $in);
        }
        // Même règle que le SQF, en PHP : le numéro imprimé est retrouvé quelle que soit la saisie.
        $norm = static fn (string $v): string => (string) preg_replace('/[^A-Z0-9]/', '', strtoupper($v));
        self::assertSame('ALZ036925', $norm(' alz 036-925 '));
        self::assertSame('ALZ036925', $norm('ALZ.036.925'));

        self::assertStringContainsString('call comspec_sse_fnc_fileNorm', self::src('fn_fileQuery.sqf'));
        $s = self::src('fn_fileServer.sqf');
        self::assertStringContainsString('private _num = { ["number", _this] call comspec_sse_fnc_fileNorm };', $s);
        self::assertStringContainsString('private _cty = { ["country", _this] call comspec_sse_fnc_fileNorm };', $s);
        $cfg = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons/identity/config.cpp');
        self::assertStringContainsString('class fileNorm {};', $cfg);
    }

    public function testIssuedPapersAreRegisteredByNumberAndQueried(): void
    {
        // Chaque papier délivré ou généré (IA comprises) entre au registre « issued » sous PAYS|type|numéro.
        $issue = self::src('fn_idIssue.sqf');
        self::assertStringContainsString('(_reg get "issued") set [format ["%1|%2|%3", _country, _type, _number], [_id, _baseName select 0, _baseName select 1, _baseReport]];', $issue);
        $s = self::src('fn_fileServer.sqf');
        $qa = substr($s, (int) strpos($s, 'case "QA": {'), 3500);
        self::assertStringContainsString('} forEach (_reg getOrDefault ["issued", createHashMap]);', $qa);
        self::assertStringContainsString('((_p param [2, ""]) call _num) isEqualTo _n', $qa);
        // Relevé d'un numéro inconnu : ses contrôles restent au fichier.
        self::assertStringContainsString('["note", [_me, _id, 0, _r getOrDefault ["visual", "releve"], "", _n]]', $s);
    }

    public function testFichiersAppNeverUsesAnAlertChannel(): void
    {
        foreach (['fn_filePage.sqf', 'fn_fileAction.sqf', 'fn_fileClient.sqf', 'fn_fileQuery.sqf', 'fn_fileServer.sqf', 'fn_fileMerge.sqf', 'fn_fileNorm.sqf'] as $file) {
            $src = self::src($file);
            foreach (['comspec_atak_native_fnc_toast', 'comspec_sse_fnc_toast', 'comspec_sse_fnc_notify', 'comspec_atak_native_fnc_notify', 'systemChat', 'BIS_fnc_showNotification', 'hintSilent'] as $bad) {
                self::assertStringNotContainsString($bad, $src, $file . ' : ' . $bad);
            }
            self::assertDoesNotMatchRegularExpression('/\bhint\s/', $src, $file);
        }
    }

    public function testResultCardCoversEveryOutcome(): void
    {
        $p = self::src('fn_filePage.sqf');
        foreach (['"ERREUR"', '"AUCUN ENREGISTREMENT"', '"CORRESPONDANCE POSSIBLE"', '"HIT · %1"', '"Réessayer"', '"Annuler"', '"Copier le numéro"', '"LOCAL"', '"ATHENA%1"', '"Aucun enregistrement : numéro inconnu des fichiers."', '"Document douteux"'] as $needle) {
            self::assertStringContainsString($needle, $p);
        }
    }

    public function testNewPhoneStringsAreTranslated(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native';
        $en = (string) file_get_contents($root . '/Sources/addons/main/i18n/en.txt');
        $xx = (string) file_get_contents($root . '/localisation/template/xx.txt');
        $strings = [
            'Abandonne cette consultation.', 'ERREUR', 'POSSIBLE', 'AUCUN ENREGISTREMENT', 'CORRESPONDANCE POSSIBLE', 'Document douteux',
            'Aucun enregistrement : numéro inconnu des fichiers.', 'Aucun enregistrement : personne inconnue des fichiers.',
            'Aucun enregistrement : identifiant inconnu des fichiers.', 'Aucun enregistrement : aucun fichier des véhicules pour cette plaque.',
            'interrogation en cours…', 'fiche ajoutée', 'aucune fiche', 'sans réponse du serveur', 'Relance la même consultation.',
            'Contrôles subis de ce numéro', 'Copier le numéro', 'Copie le numéro consulté.', 'Numéro copié : %1', 'Requête annulée.',
            'Réponse du serveur illisible.', 'Demande sans unité : relancez la requête.', "Demande refusée par le serveur : unité d'un autre poste.",
            'Le serveur de la mission ne répond pas : Fichiers absent ou plus ancien sur le serveur.',
            // Déjà au dictionnaire, réutilisés.
            'Annuler', 'Réessayer', 'non lié', 'sans réponse', 'hors ligne', 'Fichier local', 'Réponse du fichier en attente…',
        ];
        foreach ($strings as $fr) {
            self::assertStringContainsString('["' . $fr . '", "', $en, $fr);
            self::assertStringContainsString('["' . $fr . '", "', $xx, $fr);
        }
    }
}
