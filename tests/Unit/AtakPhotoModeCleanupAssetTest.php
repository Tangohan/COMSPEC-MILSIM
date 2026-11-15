<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ATAK 4.17.1 : la prise de vue (téléphone et plein écran) ne laisse plus la vue, le HUD, le menu d'actions ni les contrôles
 * masqués si elle échoue ; un seul chemin de nettoyage, des phrases claires dans le téléphone, des diagnostics dans le journal.
 */
final class AtakPhotoModeCleanupAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const MAIN = self::ROOT . 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::MAIN . $path));
    }

    /** Corps d'un « case "x": { … };» de fn_photoCam (jusqu'au case suivant). */
    private function camCase(string $name): string
    {
        $s = $this->read('functions/pages/fn_photoCam.sqf');
        $a = strpos($s, "    case \"$name\": {");
        self::assertNotFalse($a, $name);
        $b = strpos($s, "\n    case ", $a + 10);
        $b = $b === false ? strlen($s) : $b;

        return substr($s, $a, $b - $a);
    }

    private function shootSpawn(string $file, string $marker): string
    {
        $s = $this->read($file);
        $a = (int) strpos($s, $marker);
        self::assertGreaterThan(0, $a, $marker);

        return substr($s, $a, 9000);
    }

    // ── Un seul chemin de nettoyage ─────────────────────────────────────────────────────────────────────────────

    public function testRestoreIsTheOnlyCleanupOfAShot(): void
    {
        $restore = $this->camCase('restore');
        foreach ([
            'deleteVehicle _light',
            '_shot cameraEffect ["Terminate", "Back"]; camDestroy _shot;',
            '_x ctrlShow true',
            'showHUD _hud',
            'uiNamespace setVariable ["COMSPEC_ATAK_PhotoShooting", false]',
        ] as $step) {
            self::assertStringContainsString($step, $restore, $step);
        }
        // Chaque étape saute un objet déjà disparu.
        self::assertStringContainsString('if (!isNull _light)', $restore);
        self::assertStringContainsString('if (!isNull _shot)', $restore);
        self::assertStringContainsString('if (!isNull _x)', $restore);
        // Rien de tout cela ne se refait ailleurs : le déclenchement ne nettoie plus lui-même.
        $cam = $this->read('functions/pages/fn_photoCam.sqf');
        self::assertSame(1, substr_count($cam, 'camDestroy _shot;'));
        self::assertSame(1, substr_count($cam, 'showHUD _hud'));
        self::assertSame(1, substr_count($cam, 'deleteVehicle _light'));
        self::assertSame(1, substr_count($cam, 'uiNamespace setVariable ["COMSPEC_ATAK_PhotoShooting", false]'));
        $mode = $this->read('functions/pages/fn_photoMode.sqf');
        self::assertStringNotContainsString('deleteVehicle _light', $mode);
        self::assertStringNotContainsString('setVariable ["COMSPEC_ATAK_PhotoShooting"', $mode);
    }

    public function testEveryExitGoesThroughRestore(): void
    {
        $cam = $this->read('functions/pages/fn_photoCam.sqf');
        $mode = $this->read('functions/pages/fn_photoMode.sqf');
        $keys = $this->read('functions/ui/fn_vehicleKeys.sqf');
        // Fin normale des deux déclenchements.
        self::assertStringContainsString('["restore", "fin"] call comspec_atak_native_fnc_photoCam;', $cam);
        self::assertStringContainsString('["restore", "fin"] call comspec_atak_native_fnc_photoCam;', $mode);
        // Viseur : déclenchement resté bloqué.
        self::assertStringContainsString('["restore", "viseur : déclenchement resté bloqué"]', $cam);
        // Sortie du plein écran (Espace, mort, véhicule, téléphone perdu : tout passe par EXIT).
        $exit = substr($mode, (int) strpos($mode, '    case "EXIT": {'));
        self::assertStringContainsString('["restore", "sortie du plein écran"] call comspec_atak_native_fnc_photoCam;', $exit);
        self::assertLessThan((int) strpos($exit, 'camDestroy _cam'), (int) strpos($exit, '["restore", "sortie du plein écran"]'));
        self::assertStringContainsString('_quit = !alive player || {vehicle player isNotEqualTo player} || {!([] call comspec_atak_native_fnc_hasDevice)};', $mode);
        // Réparation de l'affichage (action ACE).
        $repair = substr($keys, (int) strpos($keys, 'case "repair": {'));
        self::assertStringContainsString('["EXIT", "", true] call comspec_atak_native_fnc_photoMode;', $repair);
        self::assertStringContainsString('["restore", "réparation de l\'affichage"] call comspec_atak_native_fnc_photoCam;', $repair);
    }

    public function testWatchdogCoversAScriptThatDiesMidShot(): void
    {
        $begin = $this->camCase('begin');
        self::assertStringContainsString('uiNamespace setVariable ["COMSPEC_ATAK_PhotoShooting", true];', $begin);
        self::assertStringContainsString('["restore", "chien de garde : le déclenchement ne s\'est pas terminé"]', $begin);
        self::assertStringContainsString('}, [_id], 6] call comspec_atak_native_fnc_waitAndExec;', $begin);
        // Le chien de garde ne touche pas un cliché plus récent.
        self::assertStringContainsString('getOrDefault ["id", -1]) isEqualTo _id', $begin);
    }

    public function testShotSheetIsFilledBeforeEachChange(): void
    {
        $cam = $this->shootSpawn('functions/pages/fn_photoCam.sqf', '[_caption, _fire, _shotPose, _meta] spawn {');
        // Le HUD d'avant, les contrôles masqués et la caméra de prise de vue sont notés avant d'être changés.
        self::assertLessThan((int) strpos($cam, 'showHUD false;'), (int) strpos($cam, '_sh set ["hud", shownHUD];'));
        self::assertLessThan((int) strpos($cam, '{ _x ctrlShow false; } forEach _hidden;'), (int) strpos($cam, 'private _hidden = [];'));
        self::assertStringContainsString('_sh set ["hidden", _hidden];', $cam);
        self::assertLessThan((int) strpos($cam, '_shot cameraEffect ["Internal", "Back"];'), (int) strpos($cam, '_sh set ["cam", _shot];'));
        self::assertLessThan((int) strpos($cam, '_light setPosASL _pos;'), (int) strpos($cam, '_sh set ["light", _light];'));

        $mode = $this->shootSpawn('functions/pages/fn_photoMode.sqf', '[_fire, [_px, _py, _pz], _meta, _st getOrDefault ["selfie", false]] spawn {');
        self::assertStringContainsString('["begin", "FULL"] call comspec_atak_native_fnc_photoCam;', $mode);
        self::assertStringContainsString('_sh set ["hidden", _vis];', $mode);
        self::assertLessThan((int) strpos($mode, '_light setPosASL _phone;'), (int) strpos($mode, '_sh set ["light", _light];'));
        self::assertStringContainsString('["begin", "PHONE"] call comspec_atak_native_fnc_photoCam;', $cam);
    }

    // ── Entrée et sortie du plein écran ─────────────────────────────────────────────────────────────────────────

    public function testFullScreenCannotOpenWithoutAWayOut(): void
    {
        $mode = $this->read('functions/pages/fn_photoMode.sqf');
        $enter = substr($mode, (int) strpos($mode, '    case "ENTER": {'), 6000);
        // Sans fenêtre de jeu (46), ni clic ni Espace : refusé avant de toucher à l'état.
        self::assertLessThan((int) strpos($enter, 'COMSPEC_ATAK_PhotoMode", true]'), (int) strpos($enter, 'if (isNull (findDisplay 46)) exitWith {'));
        self::assertStringContainsString('Plein écran refusé : la fenêtre de jeu (46) est introuvable', $enter);
        // Mode resté actif sans viseur : nettoyé au lieu de refuser toute nouvelle photo.
        self::assertLessThan((int) strpos($enter, 'if (_active) exitWith { false };'), (int) strpos($enter, '["EXIT", "", true] call comspec_atak_native_fnc_photoMode;'));
        self::assertStringContainsString('COMSPEC_ATAK_PhotoEnterAt', $enter);
        // EXIT silencieux : pas de réouverture du téléphone.
        self::assertStringContainsString('if (_arg isNotEqualTo true && {alive player} && {[] call comspec_atak_native_fnc_hasDevice}) then {', $mode);
    }

    // ── Pas de fausse photo, phrase claire dans le téléphone ────────────────────────────────────────────────────

    public function testRefusedCaptureIsNeverAnnouncedAsTaken(): void
    {
        foreach (['functions/pages/fn_photoCam.sqf', 'functions/pages/fn_photoMode.sqf'] as $file) {
            $s = $this->read($file);
            self::assertStringContainsString('= !(_ret isEqualType true && {!_ret});', $s, $file);
            // Le retour de la capture est toujours lu (jamais « screenshot nom; » seul sur sa ligne).
            self::assertSame(0, preg_match('/^\s*screenshot /m', $s), $file);
            self::assertStringContainsString('private _ret = screenshot ', $s, $file);
            self::assertStringContainsString('if (_saved && {_faces isNotEqualTo []})', $s, $file);
            self::assertStringContainsString('Capture refusée par le jeu : passez la qualité HDR au moins sur Moyen (options d\'affichage), puis reprenez la photo.', $s, $file);
        }
        $why = $this->camCase('why');
        foreach (['screenshot_rejected', 'file_not_found', 'not_connected', 'unauthorized', 'queue_full'] as $detail) {
            self::assertStringContainsString($detail, $why, $detail);
        }
        // Aucune alerte du jeu : ni hint, ni systemChat, ni toast hors téléphone dans ce qui a été ajouté.
        foreach (['functions/pages/fn_photoCam.sqf', 'functions/pages/fn_photoMode.sqf'] as $file) {
            $s = $this->read($file);
            self::assertStringNotContainsString('systemChat', $s, $file);
            self::assertStringNotContainsString('hintSilent', $s, $file);
            self::assertStringNotContainsString("\nhint ", $s, $file);
        }
    }

    public function testNewSentencesAreTranslated(): void
    {
        $en = $this->read('i18n/en.txt');
        foreach ([
            'Photos : fenêtre de jeu introuvable, réessayez dans un instant',
            'La prise de vue a été interrompue : photo non enregistrée, reprenez-la.',
            'Capture refusée par le jeu : passez la qualité HDR au moins sur Moyen (options d\'affichage), puis reprenez la photo.',
            'Liaison Athena dégradée : reconnectez-vous, puis reprenez la photo.',
            'File d\'attente des photos pleine : réessayez dans un instant.',
            'Photo gardée sur le poste : à envoyer depuis la Galerie',
        ] as $fr) {
            self::assertStringContainsString('["' . $fr . '", "', $en, $fr);
            self::assertStringContainsString($fr, $this->read('functions/pages/fn_photoCam.sqf') . $this->read('functions/pages/fn_photoMode.sqf'), $fr);
        }
    }

    // ── Diagnostics du journal ──────────────────────────────────────────────────────────────────────────────────

    public function testShotStepsAreLogged(): void
    {
        $cam = $this->read('functions/pages/fn_photoCam.sqf');
        $mode = $this->read('functions/pages/fn_photoMode.sqf');
        foreach ([
            'Cliché %1 (téléphone) : début',
            'Cliché %1 : caméra de prise de vue en place',
            'Cliché %1 : capture lancée vers Athena',
            'Cliché %1 : capture terminée, envoi accepté %2, détail de la liaison %3',
            'Cliché %1 : capture du poste %2, %3 (%4)',
            'Cliché %1 (%2) : nettoyage (%3) après %4 s',
        ] as $line) {
            self::assertStringContainsString($line, $cam, $line);
        }
        foreach ([
            'Cliché %1 (plein écran) : début',
            'Cliché %1 : capture lancée vers Athena',
            'Cliché %1 : capture terminée, envoi accepté %2, détail de la liaison %3',
            'Cliché %1 : capture du poste %2, %3 (%4)',
            'Plein écran : ouvert (',
            'Plein écran : fermeture (',
            'Plein écran refusé : la fenêtre de jeu (46) est introuvable',
            'Plein écran : mode resté actif sans viseur, nettoyé avant la nouvelle ouverture',
        ] as $line) {
            self::assertStringContainsString($line, $mode, $line);
        }
        // Catégorie PHOTO : recopiée dans le journal de session COMSPEC (fn_log).
        self::assertStringContainsString('["INFO", "PHOTO", format ["Cliché %1 (%2) : nettoyage', $cam);
        self::assertStringContainsString('"CAMERA", "PHOTO", "DRONE"', $this->read('functions/core/fn_log.sqf'));
    }

    public function testNoUnbalancedBrackets(): void
    {
        foreach (['functions/pages/fn_photoCam.sqf', 'functions/pages/fn_photoMode.sqf', 'functions/ui/fn_vehicleKeys.sqf'] as $path) {
            $code = preg_replace('~/\*.*?\*/~s', '', $this->read($path)) ?? '';
            $code = preg_replace('~//[^\r\n]*~', '', $code) ?? '';
            $code = preg_replace('~"(?:[^"]|"")*"~s', '""', $code) ?? '';
            $code = preg_replace("~'(?:[^']|'')*'~s", "''", $code) ?? '';
            foreach (['{' => '}', '[' => ']', '(' => ')'] as $open => $close) {
                self::assertSame(substr_count($code, $open), substr_count($code, $close), "équilibre $open$close dans $path");
            }
        }
    }

    // ── Version et notes ────────────────────────────────────────────────────────────────────────────────────────

    public function testVersionAndChangelogs(): void
    {
        $hpp = $this->read('script_component.hpp');
        // Le correctif est sorti dans la 4.18.0 (avec le mode Essentiel) : version 4.17.1 au moins.
        self::assertMatchesRegularExpression('/#define VERSION_STR "4\.(17\.[1-9]|1[89]\.\d+|[2-9]\d\.\d+)"/', $hpp);
        // La DLL COMSPEC Link ne change pas dans ce correctif.
        self::assertStringContainsString('#define LINK_VERSION_STR "2.0.126"', $hpp);

        $cl = str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . 'CHANGELOG-ATAK.md'));
        $title = '(ATAK natif 4.17.1.AG - Washington)';
        self::assertSame(1, substr_count($cl, $title));
        // Entrée en tête du journal, au-dessus de la 4.17.0.
        self::assertLessThan(strpos($cl, '(ATAK natif 4.17.0.AG - Washington)'), strpos($cl, $title));
        $entry = substr($cl, (int) strpos($cl, $title));
        $entry = substr($entry, 0, (int) strpos($entry, "\n## ", 10));
        self::assertStringContainsString("### Résumé joueur\nType : correctif\nFR : ", $entry);
        self::assertStringContainsString("\nEN : ", $entry);
        $summary = substr($entry, 0, (int) strpos($entry, '### Détail technique'));
        $fr = substr($summary, 0, (int) strpos($summary, "\nEN : "));
        $en = substr($summary, (int) strpos($summary, "\nEN : "));
        self::assertSame(substr_count($fr, "\n- "), substr_count($en, "\n- "));
        self::assertGreaterThanOrEqual(2, substr_count($fr, "\n- "));
        // Langage joueur : aucun nom interne.
        foreach (['fn_', 'comspec_atak_native', 'COMSPEC_ATAK_', 'CBA', '.sqf', 'AssetTest', '`', 'RPT', 'PFH'] as $internal) {
            self::assertStringNotContainsString($internal, $summary, $internal);
        }
        self::assertStringContainsString('### Détail technique', $entry);
        self::assertStringContainsString('`fn_photoCam` « restore »', $entry);

        $steam = str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . 'mod/COMSPEC_ATAK_Native/publisher/STEAM_CHANGELOG.txt'));
        // Note Steam de la 4.18.0 : mode Essentiel et ce correctif, dans le même bloc.
        self::assertStringContainsString('[h1]COMSPEC ATAK 4.18.0 Washington', $steam);
        $from = (int) strpos($steam, '[h1]COMSPEC ATAK 4.18.0 Washington');
        $block = substr($steam, $from, (int) strpos($steam, '[h1]COMSPEC ATAK 4.17.0 Washington') - $from);
        self::assertStringContainsString('[b]Appareil photo[/b]', $block);
        self::assertStringContainsString('[b]Camera[/b]', $block);
        self::assertStringContainsString('[h2]Français[/h2]', $block);
        self::assertStringContainsString('[h2]English[/h2]', $block);
        self::assertSame(2, substr_count($block, '[list]'));
        self::assertSame(2, substr_count($block, '[/list]'));
    }
}
