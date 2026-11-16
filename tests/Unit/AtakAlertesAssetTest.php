<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * App « Avis et vigilance » de l'ATAK natif (ICD-SSE-ALERTES-001) : consulter, émettre, signaler, accuser lecture, renouveler et
 * lever depuis le téléphone. Sources SQF équilibrées, aucune alerte à l'écran d'Arma (seul le centre de notifications du téléphone,
 * en vibreur), colonnes et opérations du contrat du site, câblage (fonctions, app, dossier SSE du lanceur, pastille, démarrage),
 * identifiant client stable au renvoi, cache du niveau le plus bas, QR code, mode de liaison, bouton SIGNALER du pack SSE,
 * dictionnaires stricts. Aucun test ne dépend des fins de ligne du checkout ni des versions des mods.
 */
final class AtakAlertesAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const APP = self::MAIN . 'functions/alertes/';
    private const SSE = 'mod/@COMSPEC_SSE/addons/identity/';

    /** Fichiers SQF nouveaux : jamais d'alerte à l'écran. */
    private const NEW_FILES = [
        self::APP . 'fn_alertesAction.sqf',
        self::APP . 'fn_alertesSend.sqf',
        self::APP . 'fn_alertesDraft.sqf',
        self::APP . 'fn_alertesQr.sqf',
        self::APP . 'fn_pageAlertes.sqf',
        self::APP . 'fn_pageAlertesNew.sqf',
        self::SSE . 'functions/fn_idAlertReport.sqf',
    ];

    /** Fichiers SQF touchés : équilibre des délimiteurs. */
    private const TOUCHED = [
        self::MAIN . 'functions/fiches/fn_pageFiches.sqf',
        self::MAIN . 'functions/ui/fn_sseHub.sqf',
        self::MAIN . 'functions/ui/fn_appSpaces.sqf',
        self::MAIN . 'functions/ui/fn_appVisible.sqf',
        self::MAIN . 'functions/ui/fn_appBadge.sqf',
        self::MAIN . 'XEH_postInitClient.sqf',
        self::SSE . 'functions/fn_idAction.sqf',
        self::SSE . 'functions/fn_idPage.sqf',
    ];

    /** Colonnes d'une alerte, dans l'ordre du contrat (ICD-SSE-ALERTES-001 § 3.2, SseAlertGameService::COLUMNS). */
    private const COLUMNS = [
        'ref', 'doc', 'type', 'color', 'rank', 'level', 'order', 'name', 'alias', 'threat', 'armed', 'conduct', 'reason', 'zone', 'place',
        'issued', 'until', 'soon', 'service', 'svc', 'channel', 'delay', 'url', 'token', 'state', 'acked', 'updated', 'cls', 'mark', 'cut',
    ];

    /** Textes nouveaux qui doivent être dans les deux dictionnaires, une seule fois. */
    private const TEXTS = [
        'Avis et vigilance', 'Avis et vigilance : indisponible sur ce serveur', 'Conduite à tenir', 'Service à informer', 'Adresse de signalement',
        'JE L\'AI VU', 'ACCUSER LECTURE', 'ENVOYER LE SIGNALEMENT', 'ÉMETTRE L\'ALERTE', 'PROPOSER AU BUREAU', 'NE PAS APPRÉHENDER — SIGNALER',
        'Proposé au bureau · %1 · en attente de validation', 'Non reçu : %1 (RENVOYER dans Mes envois).', 'Motif de la levée (obligatoire)',
        'Étape %1 sur %2 · %3', 'Signalement %1 reçu par le bureau.', 'Alertes en cours, à consulter, émettre ou signaler', 'RENVOYER LE SIGNALEMENT',
        'Scannez ce code avec votre téléphone pour signaler depuis le portail.',
    ];

    /** Fichier lu sans dépendre des fins de ligne du checkout. */
    private static function read(string $rel): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . $rel));
    }

    /** Code SQF sans commentaires ni chaînes (guillemets doublés, apostrophes doublées). */
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

    /** Bloc d'un « case "nom": { … } » d'un switch SQF (jusqu'au case suivant du même niveau). */
    private static function caseBlock(string $src, string $name): string
    {
        $start = strpos($src, "\n    case \"" . $name . '": {');
        self::assertNotFalse($start, 'case ' . $name);
        $next = strpos($src, "\n    case \"", (int) $start + 10);
        $default = strpos($src, "\n    default {", (int) $start + 10);
        $end = min($next === false ? PHP_INT_MAX : $next, $default === false ? PHP_INT_MAX : $default);

        return substr($src, (int) $start, ($end === PHP_INT_MAX ? strlen($src) : $end) - (int) $start);
    }

    /**
     * Dictionnaire au format strict de parseSimpleArray : « [ » seul, une entrée par ligne, une virgule entre deux entrées, aucune après
     * la dernière, « "" » pour un guillemet. Rend les entrées (listes de textes).
     *
     * @return list<list<string>>
     */
    private static function dictionary(string $rel, int $fields): array
    {
        $lines = explode("\n", trim(self::read($rel)));
        self::assertSame('[', array_shift($lines), $rel);
        self::assertSame(']', array_pop($lines), $rel);
        $rows = [];
        $last = count($lines) - 1;
        $one = '"((?:[^"]|"")*)"';
        $re = '/^\[' . implode(', ', array_fill(0, $fields, $one)) . '\](,?)$/';
        foreach ($lines as $i => $line) {
            self::assertSame(1, preg_match($re, $line, $m), $rel . ' ligne ' . ($i + 2) . ' : ' . substr($line, 0, 80));
            self::assertSame($i === $last ? '' : ',', $m[$fields + 1], $rel . ' virgule ligne ' . ($i + 2));
            $rows[] = array_map(static fn (string $s): string => str_replace('""', '"', $s), array_slice($m, 1, $fields));
        }

        return $rows;
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
        self::assertFileExists(self::ROOT . $rel);
        $code = self::strip(self::read($rel));
        $depth = ['{' => 0, '[' => 0, '(' => 0];
        $close = ['}' => '{', ']' => '[', ')' => '('];
        foreach (str_split($code) as $ch) {
            if (isset($depth[$ch])) {
                $depth[$ch]++;
            } elseif (isset($close[$ch])) {
                $depth[$close[$ch]]--;
                self::assertGreaterThanOrEqual(0, $depth[$close[$ch]], $rel . ' : ' . $ch . ' sans ouverture');
            }
        }
        self::assertSame(['{' => 0, '[' => 0, '(' => 0], $depth, $rel);
    }

    #[DataProvider('newFiles')]
    public function testNothingIsShownOnTheArmaScreen(string $rel): void
    {
        $code = self::strip(self::read($rel));
        foreach (['systemChat', 'hintSilent', 'hintC', 'cutText', 'cutRsc', 'titleText', 'titleRsc', 'BIS_fnc_showNotification', 'BIS_fnc_dynamicText', 'BIS_fnc_textTiles',
            'BIS_fnc_guiMessage', 'comspec_sse_fnc_notify', 'comspec_atak_native_fnc_toast', 'comspec_overwatch_connect_fnc_notify', 'sideChat', 'globalChat', 'groupChat',
            'vehicleChat', 'commandChat', 'sideRadio', 'groupRadio', 'globalRadio', 'ctrlSetText'] as $banned) {
            self::assertSame(0, preg_match('/\b' . preg_quote($banned, '/') . '\b/', $code), $rel . ' : ' . $banned);
        }
        self::assertSame(0, preg_match('/\bhint\b/', $code), $rel . ' : hint');
        // Fonctions BIS : une heure et une grille, aucune fonction d'affichage.
        preg_match_all('/BIS_fnc_\w+/', $code, $bis);
        foreach ($bis[0] as $fn) {
            self::assertContains($fn, ['BIS_fnc_timeToString', 'BIS_fnc_gridToPos'], $rel);
        }
    }

    public function testPhoneNotificationsAreDiscreetAndOnlyInTheNotificationCentre(): void
    {
        $calls = 0;
        foreach (self::NEW_FILES as $rel) {
            foreach (explode("\n", self::read($rel)) as $line) {
                if (!str_contains($line, 'call comspec_atak_native_fnc_notify')) {
                    continue;
                }
                $calls++;
                // Vibreur seul : aucun bandeau, la notification est rangée au centre et ouvre l'app.
                self::assertStringContainsString('"SSEALERT", "vibrate"] call comspec_atak_native_fnc_notify', $line, $rel);
                self::assertContains(basename($rel), ['fn_alertesAction.sqf', 'fn_alertesSend.sqf']);
            }
        }
        // Nouvel avis de niveau élevé (et son résumé au-delà de trois), réponse du bureau à une proposition.
        self::assertSame(3, $calls);
        $action = self::read(self::APP . 'fn_alertesAction.sqf');
        self::assertStringContainsString('(_x get "rank") >= 3', $action);
        self::assertStringContainsString('_zoneF isEqualTo "" || {(_x get "zone") isEqualTo _zoneF}', $action);
        self::assertStringContainsString('["proposalCheck", _fresh] call comspec_atak_native_fnc_alertesSend;', $action);
        self::assertStringContainsString('Proposition %1 acceptée par le bureau : %2', self::read(self::APP . 'fn_alertesSend.sqf'));
    }

    public function testTheStatusLineCarriesTheFeedback(): void
    {
        foreach (['fn_alertesAction.sqf', 'fn_alertesSend.sqf', 'fn_pageAlertes.sqf'] as $file) {
            self::assertStringContainsString('"alertesStatus"', self::read(self::APP . $file), $file);
        }
        $page = self::read(self::APP . 'fn_pageAlertes.sqf');
        self::assertStringContainsString('private _statusRow = {', $page);
        self::assertStringNotContainsString('eyebrow', strtolower($page . self::read(self::APP . 'fn_pageAlertesNew.sqf')));
    }

    public function testColumnsAndOperationsAreTheOnesOfTheSiteContract(): void
    {
        $action = self::read(self::APP . 'fn_alertesAction.sqf');
        self::assertSame(1, preg_match('/private _cols = \[(.*?)\];/s', $action, $m));
        preg_match_all('/"([a-z]+)"/', $m[1], $cols);
        self::assertSame(self::COLUMNS, $cols[1]);
        // Le service du site, quand il est livré dans la même branche, fait foi sur l'ordre des colonnes.
        $service = 'App\\Services\\Sse\\SseAlertGameService';
        if (class_exists($service) && defined($service . '::COLUMNS')) {
            self::assertSame(constant($service . '::COLUMNS'), $cols[1]);
        }
        // Lectures : genre du canal, opération, révision connue, page, langue.
        self::assertStringContainsString('\'{"kind":"sse_alertes","op":"catalog","rev":"%1","page":%2,"lang":"%3"}\'', $action);
        self::assertStringContainsString('\'{"kind":"sse_alertes","op":"list","rev":"%1","page":%2,"lang":"%3","doc":""}\'', $action);
        self::assertStringContainsString('\'{"kind":"sse_alertes","op":"detail","ref":"%1","lang":"%2"}\'', $action);
        self::assertStringContainsString('"SubmitIdCheck"', $action);
        // Positions lues dans les pages : liste [1, "list", rév, généré, ttl, page, suivante, alertes, méta, inchangé], détail, catalogue.
        self::assertStringContainsString('_rows append (_p param [7, []]);', $action);
        self::assertStringContainsString('private _next = _p param [6, 0];', $action);
        self::assertStringContainsString('if ((_p param [9, false]) isEqualTo true) exitWith { _unchanged = true; _ok = true; };', $action);
        self::assertStringContainsString('_lines append (_p param [5, []]);', $action);
        self::assertStringContainsString('private _r = _cHead param [6, []];', $action);
        self::assertStringContainsString('["reasons", [_p param [3, []]] call _list], ["vehicles", [_p param [4, []]] call _list], ["people", [_p param [5, []]] call _list]', $action);
        // Écritures : les cinq opérations, par le même canal, le genre posé une fois pour toutes.
        $send = self::read(self::APP . 'fn_alertesSend.sqf');
        self::assertStringContainsString('if !(_op in ["create", "report", "renew", "lift", "ack"]) exitWith { "" };', $send);
        self::assertStringContainsString('_data set ["kind", "sse_alertes"];', $send);
        self::assertStringContainsString('_data set ["op", _op];', $send);
        foreach (['case "create": {', 'case "report": {', 'case "renew": {', 'case "lift": {', 'case "ack": {'] as $case) {
            self::assertStringContainsString($case, $send, $case);
        }
        // Champs du corps de « create » et de « report » (ICD § 3.4 et 3.5).
        $draft = self::read(self::APP . 'fn_alertesDraft.sqf');
        foreach (['"doc"', '"threat"', '"armed"', '"reasons"', '"measures"', '"zone"', '"grid"', '"place"', '"days"', '"text"', '"service"', '"delay"', '"type"', '"subject"', '"unknown"'] as $field) {
            self::assertStringContainsString('[' . $field . ', ', self::caseBlock($draft, 'body'), $field);
        }
        foreach (['"ref"', '"what"', '"when"', '"place"', '"grid"', '"pos"', '"text"', '"dir"', '"vehicle"', '"sure"'] as $field) {
            self::assertStringContainsString('[' . $field . ', ', self::caseBlock($draft, 'reportSend'), $field);
        }
        // Durées acceptées par le site, motif de levée obligatoire.
        self::assertStringContainsString('[7, 14, 30, 60, 90, 180]', self::read(self::APP . 'fn_pageAlertesNew.sqf'));
        self::assertStringContainsString('if ((count _reason) < 3) exitWith {', self::caseBlock($action, 'liftSend'));
        // Aucune nouvelle DLL : pas de commande ajoutée au C#.
        self::assertStringNotContainsString('sse_alertes', self::read('mod/UptoDate/COMSPECExtension/Extension_Identity.cs'));
    }

    public function testTheClientIdIsDrawnOnceAndReusedOnResend(): void
    {
        $send = self::read(self::APP . 'fn_alertesSend.sqf');
        // Un seul endroit tire l'identifiant : la création de la fiche d'envoi.
        self::assertSame(1, substr_count($send, 'format ["AV-%1-'));
        self::assertSame(1, substr_count($send, '"COMSPEC_ATAK_AlertesSeq"') - 1, 'compteur lu puis écrit, une fois');
        $new = self::caseBlock($send, 'new');
        self::assertStringContainsString('format ["AV-%1-', $new);
        self::assertStringContainsString('if (_op isNotEqualTo "ack") then { _send set ["cid", _cid]; };', $new);
        self::assertStringContainsString('["id", _cid], ["cid", _cid]', $new);
        // L'envoi et le renvoi ne touchent jamais à l'identifiant : ils reprennent le corps gardé sur la fiche.
        foreach (['send', 'retry', 'flush'] as $case) {
            $block = self::caseBlock($send, $case);
            self::assertStringNotContainsString('"AV-', $block, $case);
            self::assertStringNotContainsString('set ["cid"', $block, $case);
            self::assertStringNotContainsString('AlertesSeq', $block, $case);
        }
        self::assertStringContainsString('private _data = +(_rec get "body");', self::caseBlock($send, 'send'));
        self::assertStringContainsString('["send", _arg] call comspec_atak_native_fnc_alertesSend', self::caseBlock($send, 'retry'));
        // Un brouillon déjà envoyé et pas encore arrivé se renvoie, il n'est jamais émis une seconde fois.
        $draft = self::read(self::APP . 'fn_alertesDraft.sqf');
        self::assertStringContainsString('["retry", _prev get "id"] call comspec_atak_native_fnc_alertesSend;', self::caseBlock($draft, 'submit'));
        // Parcours partagé avec les apps BDA et Comptes rendus ; file gardée sur le poste ; envoi interrompu remis en file.
        self::assertStringContainsString('call comspec_atak_native_fnc_reportWebState', $send);
        foreach (['"EN ATTENTE"', '"OK"', '"ÉCHEC"', '"HORS LIGNE"', '"webWhy"'] as $state) {
            self::assertStringContainsString($state, $send, $state);
        }
        self::assertStringContainsString('"COMSPEC_ATAK_AlertesOutbox"', $send);
        self::assertStringContainsString('"Indisponible sur ce serveur"', $send);
        // Pack SSE : même règle pour l'envoi direct depuis le contrôle d'identité.
        $pack = self::read(self::SSE . 'functions/fn_idAlertReport.sqf');
        self::assertStringContainsString('private _cid = _rec getOrDefault ["alertCid", ""];', $pack);
        self::assertStringContainsString('_rec set ["alertCid", _cid];', $pack);
    }

    public function testTheAppIsWiredInTheSseFolderWithoutRenamingAnything(): void
    {
        $cfg = self::read(self::MAIN . 'config.cpp');
        self::assertStringContainsString('functions\alertes";', $cfg);
        self::assertStringContainsString('class alertesAction {}; class alertesSend {}; class alertesDraft {}; class alertesQr {}; class pageAlertes {}; class pageAlertesNew {};', $cfg);
        self::assertMatchesRegularExpression('/class SseAlert\s+\{ name="Avis et vigilance";[^}]*page="SSEALERT";[^}]*function="comspec_atak_native_fnc_pageAlertes";[^}]*section="Renseignement"/', $cfg);
        // Une page à elle : aucune autre app ne porte cet identifiant, et les apps voisines gardent le leur.
        self::assertSame(1, substr_count($cfg, 'page="SSEALERT"'));
        self::assertMatchesRegularExpression('/class Fiches\s+\{ name="Fiches SSE";[^}]*page="FICHES";/', $cfg);
        self::assertMatchesRegularExpression('/class Wanted\s+\{ name="Recherchés";[^}]*page="WANTED";/', $cfg);
        self::assertMatchesRegularExpression('/class SseHub\s+\{ name="SSE"; page="SSEHUB";/', $cfg);
        // Dossier SSE du lanceur, vue regroupée, règle « ATAK local », pastille des non lus, lecture au démarrage.
        self::assertStringContainsString('["SseAlert", "Avis et vigilance", "Alertes en cours, à consulter, émettre ou signaler"]', self::read(self::MAIN . 'functions/ui/fn_sseHub.sqf'));
        self::assertStringContainsString('"SseAlert", "Wanted", "Fiches"]', self::read(self::MAIN . 'functions/ui/fn_appSpaces.sqf'));
        self::assertStringContainsString('"SseAlert", "Wanted", "Fiches"] &&', self::read(self::MAIN . 'functions/ui/fn_appVisible.sqf'));
        self::assertStringContainsString('case "SSEALERT": { ["badge"] call comspec_atak_native_fnc_alertesAction };', self::read(self::MAIN . 'functions/ui/fn_appBadge.sqf'));
        self::assertStringContainsString('["ensure"] call comspec_atak_native_fnc_alertesAction', self::read(self::MAIN . 'XEH_postInitClient.sqf'));
        // La page se dessine par sa fonction déclarée (fn_pageRender, apps « function= ») et ses sous-écrans se quittent par le retour.
        $page = self::read(self::APP . 'fn_pageAlertes.sqf');
        self::assertStringContainsString('if (_view isEqualTo "new") exitWith { [_page, _rect] call comspec_atak_native_fnc_pageAlertesNew };', $page);
        self::assertStringContainsString('_s set ["backSub", ["SSEALERT", { ["back"] call comspec_atak_native_fnc_alertesAction; }]];', $page);
        foreach (['sent', 'share', 'report', 'renew', 'lift'] as $view) {
            self::assertStringContainsString('_view isEqualTo "' . $view . '"', $page, $view);
        }
        // Liens croisés plutôt qu'un doublon : Fiches et Recherchés depuis l'app, et l'app depuis une fiche d'avis ou de vigilance.
        self::assertStringContainsString('{ ["FICHES"] call comspec_atak_native_fnc_navigate; }', $page);
        self::assertStringContainsString('{ ["WANTED"] call comspec_atak_native_fnc_navigate; }', $page);
        self::assertStringContainsString("['openRef', %1] call comspec_atak_native_fnc_alertesAction;", self::read(self::MAIN . 'functions/fiches/fn_pageFiches.sqf'));
        // Le site connaît l'app (règles d'installation de la communauté).
        self::assertContains('SseAlert', array_column((new \App\Services\Tactical\AtakExperienceService())->phoneApps(), 'id'));
    }

    public function testTheScreensHaveWhatTheRequestAsks(): void
    {
        $page = self::read(self::APP . 'fn_pageAlertes.sqf');
        foreach (['"banner"', '"strip"', '"tabs"', '"notif"', '"qr"', '"big"', 'UNCLASSIFIED', 'SECRET', 'Conduite à tenir', 'Motifs', 'Signalement', 'Dernier lieu connu',
            'VOIR SUR LA CARTE', 'Service à informer', 'Adresse de signalement', 'JE L\'AI VU', 'ACCUSER LECTURE', 'PARTAGER', 'RENOUVELER', 'LEVER', 'NON LU', 'ARMÉ ET DANGEREUX',
            'Armé et dangereux seulement', 'alertesF_type', 'alertesF_zone', 'alertesF_armed', 'NE PAS APPRÉHENDER — SIGNALER', 'NIVEAU %4/4', 'Mes envois', 'RENVOYER',
            'Avis et vigilance : indisponible sur ce serveur', 'ENVOYER LE SIGNALEMENT', 'Certitude', 'Direction prise', 'il y a %1 min'] as $needle) {
            self::assertStringContainsString($needle, $page, $needle);
        }
        // Le QR code est calculé sur le téléphone à partir de l'adresse du site, et dessiné par la ligne « qr » de fn_formRender.
        // Part de la largeur de sa colonne : fn_formRender le réduit pour qu'il tienne entier dans la zone visible.
        self::assertStringContainsString('_rows pushBack ["qr", _qr, 0.62];', $page);
        self::assertStringContainsString('case "qr": {', self::read(self::MAIN . 'functions/ui/fn_formRender.sqf'));
        // Consignes numérotées, liseré à la couleur de la notice ou du niveau, gestion selon les droits du compte.
        self::assertStringContainsString('_forEachIndex + 1, [["item", _x] call comspec_atak_native_fnc_sseHub] call _escBr', $page);
        self::assertStringContainsString('private _c = ["color", _f get "color"] call comspec_atak_native_fnc_alertesAction;', $page);
        self::assertStringContainsString('if (!_closed && {_canIssue}) then { _btns pushBack ["RENOUVELER"', $page);
        self::assertStringContainsString('if (!_closed && {(_rights param [2, false]) isEqualTo true}) then { _btns pushBack ["LEVER"', $page);
        // Signalement en trois gestes : ouvrir l'alerte, JE L'AI VU (position et heure prises d'office), ENVOYER.
        $draft = self::read(self::APP . 'fn_alertesDraft.sqf');
        $open = self::caseBlock($draft, 'reportOpen');
        self::assertStringContainsString('_pos = getPosASL player;', $open);
        self::assertStringContainsString('systemTimeUTC params', $open);
        self::assertStringContainsString('["reportOpen", [_open, [], ""]] call _dact', $page);
        // Assistant : sept étapes, tuiles de couleur, sujet connu ou inconnu décrit, codes cherchables et récents, zone, validité, récapitulatif.
        $wizard = self::read(self::APP . 'fn_pageAlertesNew.sqf');
        self::assertStringContainsString('private _steps = ["TYPE", "SUJET", "NIVEAU", "MOTIFS", "ZONE", "VALIDITÉ", "ENVOI"];', $draft);
        foreach (['"steps"', '"notif"', '"chips"', '"tabs"', '"field"', 'PERSONNE CONNUE', 'INCONNU À DÉCRIRE', 'Sexe', 'Corpulence', 'Taille (ex. 1,80 m)', 'ARMÉ ET DANGEREUX',
            'MOTIFS', 'MESURES', 'CONSIGNE', 'MA POSITION', 'POINTER SUR LA CARTE', 'Service à informer', 'ÉMETTRE L\'ALERTE', 'PROPOSER AU BUREAU', '‹ PRÉCÉDENT', 'SUIVANT ›',
            '"COMSPEC_ATAK_AlertesRecents"'] as $needle) {
            self::assertStringContainsString($needle, $wizard, $needle);
        }
        // Brouillon gardé si on quitte : relu à l'ouverture, écrit à chaque geste.
        self::assertGreaterThanOrEqual(3, substr_count($draft, '"COMSPEC_ATAK_AlertesDraft"'));
        self::assertStringContainsString('private _keep = { profileNamespace setVariable ["COMSPEC_ATAK_AlertesDraft"', $draft);
        // Aucune mention de jeu sur les documents affichés.
        foreach ([$page, $wizard, $draft, self::read(self::APP . 'fn_alertesAction.sqf'), self::read(self::APP . 'fn_alertesSend.sqf')] as $src) {
            self::assertNull(\App\Support\SseFictionMentions::find($src));
        }
    }

    public function testOfflineReadingKeepsOnlyTheLowestLevelForADay(): void
    {
        $action = self::read(self::APP . 'fn_alertesAction.sqf');
        self::assertStringContainsString('"COMSPEC_ATAK_AlertesCache"', $action);
        self::assertStringContainsString('_age <= 1440', $action);
        // Colonne « cls » (27) : seules les alertes du niveau le plus bas vont sur le disque.
        self::assertSame(27, array_search('cls', self::COLUMNS, true));
        self::assertStringContainsString('private _floor = _rows select { (_x param [27, ""]) isEqualTo "interne" };', $action);
        self::assertSame(1, substr_count($action, 'profileNamespace setVariable ["COMSPEC_ATAK_AlertesCache", [systemTimeUTC'));
        // Synchro au démarrage, à l'ouverture et sur ACTUALISER.
        self::assertStringContainsString('["ensure"] call comspec_atak_native_fnc_alertesAction;', self::read(self::APP . 'fn_pageAlertes.sqf'));
        self::assertStringContainsString('{ ["load", true] call comspec_atak_native_fnc_alertesAction; }', self::read(self::APP . 'fn_pageAlertes.sqf'));
    }

    public function testLinkModeAndUnlinkedAccountsAreRespected(): void
    {
        $action = self::read(self::APP . 'fn_alertesAction.sqf');
        $gate = substr($action, (int) strpos($action, 'private _gate = {'), 700);
        self::assertStringContainsString('["online"] call comspec_atak_native_fnc_offline', $gate);
        self::assertStringContainsString('["active"] call comspec_overwatch_connect_fnc_linkGate', $gate);
        self::assertStringContainsString('comspec_atak_native_fnc_athenaMode', $gate);
        self::assertStringContainsString('["why"] call comspec_atak_native_fnc_linkMode', $action);
        self::assertStringContainsString('["get"] call comspec_atak_native_fnc_linkMode', self::read(self::APP . 'fn_pageAlertes.sqf'));
        // Hors réseau ou liaison en pause : l'envoi attend en file et repart seul.
        $send = self::read(self::APP . 'fn_alertesSend.sqf');
        self::assertStringContainsString('_rec set ["queued", _why in ["offline", "paused"]];', self::caseBlock($send, 'send'));
        self::assertStringContainsString('["flush"] call comspec_atak_native_fnc_alertesSend;', self::caseBlock($action, 'tick'));
        // Serveur sans le canal des alertes : état « indisponible », pas une erreur.
        self::assertStringContainsString('_store set ["avail", "no"];', $action);
        self::assertStringContainsString('_store set ["err", ""];', $action);
    }

    public function testTheQrEncoderFollowsTheStandardTables(): void
    {
        $qr = self::read(self::APP . 'fn_alertesQr.sqf');
        // Niveau L, versions 1 à 5 : mots de données et de correction, format « L, masque 0 », polynôme 285, octets de remplissage.
        self::assertStringContainsString('private _DATA = [19, 34, 55, 80, 108];', $qr);
        self::assertStringContainsString('private _EC = [7, 10, 15, 20, 26];', $qr);
        self::assertStringContainsString('private _FMT = toArray "111011111000100";', $qr);
        self::assertStringContainsString('[_g - 256, 29] call _xor', $qr);
        self::assertStringContainsString('[236, 17] select (_padI mod 2)', $qr);
        self::assertStringContainsString('private _size = 21 + 4 * _v;', $qr);
        self::assertStringContainsString('if (_right isEqualTo 6) then { _right = 5; };', $qr);
        // Sortie : les rangées de « 0 » / « 1 » de la ligne « qr » ; adresse trop longue ou hors ASCII : rien, l'adresse reste en clair.
        self::assertStringContainsString('_m apply { (_x apply { str _x }) joinString "" }', $qr);
        self::assertStringContainsString('if (_v < 0) exitWith { [] };', $qr);
        self::assertStringContainsString('[_arg] call comspec_atak_native_fnc_alertesQr', self::read(self::APP . 'fn_alertesAction.sqf'));
    }

    public function testSharingCarriesOnlyTheReferenceThroughTheExistingChannels(): void
    {
        $action = self::read(self::APP . 'fn_alertesAction.sqf');
        self::assertStringContainsString('case "shareBt";', $action);
        self::assertStringContainsString('"sse_fiche_share"', $action);
        self::assertStringContainsString('_stamps) >= 6', $action);
        self::assertStringContainsString('["ficheSend", [_ref, _uid]] call comspec_atak_native_fnc_btAction', $action);
        self::assertStringContainsString('[FICHE] %1 · %2%3', $action);
        // Le message ne porte ni motif, ni consigne, ni lieu.
        $share = self::caseBlock($action, 'shareSms');
        foreach (['"reason"', '"conduct"', '"place"', '"zone"', '"url"'] as $content) {
            self::assertStringNotContainsString($content, $share, $content);
        }
    }

    public function testTheIdentityCheckCardOffersToReport(): void
    {
        $page = self::read(self::SSE . 'functions/fn_idPage.sqf');
        self::assertStringContainsString('["alertReport"] call _act', $page);
        self::assertStringContainsString('default { "SIGNALER" };', $page);
        self::assertStringContainsString('case "alertReport": { [_rec] call comspec_sse_fnc_idAlertReport; call _render; };', self::read(self::SSE . 'functions/fn_idAction.sqf'));
        self::assertStringContainsString('class idAlertReport {};', self::read(self::SSE . 'config.cpp'));
        $fn = self::read(self::SSE . 'functions/fn_idAlertReport.sqf');
        // App de l'ATAK chargée : son écran de signalement, avec la position du contrôle. Sinon : envoi direct par le même canal.
        self::assertStringContainsString('if (!isNil "comspec_atak_native_fnc_alertesDraft") exitWith {', $fn);
        self::assertStringContainsString('["reportOpen", [_ref, [_pos select 0, _pos select 1], _grid]] call comspec_atak_native_fnc_alertesDraft;', $fn);
        self::assertStringContainsString('private _pos = _rec getOrDefault ["pos", getPosASL player];', $fn);
        self::assertStringContainsString('{"kind":"sse_alertes","op":"report","ref":"%1","what":"seen"', $fn);
        self::assertStringContainsString('["SubmitIdCheck", [_json]] call comspec_sse_fnc_extensionCall', $fn);
    }

    public function testDictionariesAreStrictAndHoldTheNewTexts(): void
    {
        $en = self::dictionary(self::MAIN . 'i18n/en.txt', 2);
        $xx = self::dictionary('mod/COMSPEC_ATAK_Native/localisation/template/xx.txt', 3);
        $enKeys = array_count_values(array_column($en, 0));
        $xxKeys = array_count_values(array_column($xx, 0));
        foreach (self::TEXTS as $fr) {
            self::assertSame(1, $enKeys[$fr] ?? 0, 'en.txt : ' . $fr);
            self::assertSame(1, $xxKeys[$fr] ?? 0, 'xx.txt : ' . $fr);
        }
        // Les textes cités sont bien ceux du code.
        $code = '';
        foreach (array_merge(self::NEW_FILES, self::TOUCHED) as $rel) {
            $code .= self::read($rel);
        }
        foreach (self::TEXTS as $fr) {
            self::assertStringContainsString($fr, $code, $fr);
        }
        // Une traduction anglaise pour chaque entrée nouvelle, et la même dans le gabarit des traducteurs.
        $enMap = array_column($en, 1, 0);
        $xxMap = array_column($xx, 2, 0);
        foreach (self::TEXTS as $fr) {
            self::assertNotSame('', $enMap[$fr]);
            self::assertSame($enMap[$fr], $xxMap[$fr], $fr);
        }
    }
}
