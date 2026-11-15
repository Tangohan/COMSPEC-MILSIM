<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * COMSPEC Flashbang 1.0.1 : réaction des IA à la flashbang (« les IA réagissent très très peu »).
 * Câblage et actifs des sources SQF : accolades équilibrées, réglages CBA et textes FR / EN, effet appliqué sur la
 * machine qui possède l'IA (local, événement CBA ciblé, aucun remoteExec), restauration des valeurs, aucun message à
 * l'écran, aucune attente non bornée, journal désactivé par défaut, version et note de version.
 * Le comportement en jeu (animations, disableAI, ordres des autres mods) ne se teste pas sans Arma : voir la PR.
 */
final class FlashbangAiReactionAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_Flashbang/Sources/addons/main/';

    /** Fonctions de la réaction des IA. */
    private const AI_FUNCTIONS = ['aiParams', 'aiBurst', 'aiBatch', 'aiHit', 'aiTick', 'aiState', 'aiRestore'];

    /** Réglages ajoutés : [variable, clé de texte, valeur par défaut attendue dans XEH_preInit]. */
    private const SETTINGS = [
        ['comspec_fb_aiReaction', 'AiReaction', '1]]'],
        ['comspec_fb_aiIntensity', 'AiIntensity', '[0, 2, 1, 0, true]'],
        ['comspec_fb_aiRadius', 'AiRadius', '[0.5, 3, 1.5, 0, true]'],
        ['comspec_fb_aiDuration', 'AiDuration', '[0.3, 2, 1, 0, true]'],
        ['comspec_fb_aiImmunity', 'AiImmunity', '[0, 0.5, 0.08, 0, true]'],
        ['comspec_fb_aiMax', 'AiMax', '[1, 80, 20, 0]'],
        ['comspec_fb_aiAnim', 'AiAnim', 'true]'],
        ['comspec_fb_aiDebug', 'AiDebug', 'false]'],
    ];

    private static function path(string $p): string
    {
        return dirname(__DIR__, 2) . '/' . $p;
    }

    private static function read(string $p): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::path($p)));
    }

    private static function sqf(string $name): string
    {
        return self::read(self::MAIN . 'functions/fn_' . $name . '.sqf');
    }

    /** Code SQF sans commentaires ni contenu des chaînes. */
    private static function code(string $src): string
    {
        return (string) preg_replace_callback(
            '~"(?:[^"]|"")*"|\'(?:[^\']|\'\')*\'|//[^\n]*|/\*.*?\*/~s',
            static function (array $m): string {
                $t = $m[0];
                if ($t[0] === '/') {
                    return str_repeat("\n", substr_count($t, "\n"));
                }

                return '""';
            },
            $src
        );
    }

    /** @return list<string> */
    private static function aiSources(): array
    {
        return array_map(static fn (string $f): string => 'functions/fn_' . $f . '.sqf', self::AI_FUNCTIONS);
    }

    /** Réplique PHP de fn_exposure (extérieur, de face) et fn_aiHit : [vision, audition, force]. @return array{float, float, float} */
    private static function strength(float $dist, float $radius, float $pupilFloor, bool $legacy): array
    {
        $table = [[0, 1], [2, 1], [5, 0.6], [10, 0.25], [15, 0.08], [19, 0]];
        $dEff = max($dist / max($radius, 0.3), 0.3);
        $level = 170 - 20 * log10($dEff / 1.5);
        $aud = (max(0, min(1, ($level - 140) / 30))) ** 0.7;
        $dn = $dEff <= 3 ? $dEff : 3 + 22 * ($dEff - 3) / 22;
        $vis = end($table)[1];
        for ($i = 0; $i < count($table) - 1; $i++) {
            if ($dn <= $table[$i + 1][0]) {
                $t = ($dn - $table[$i][0]) / ($table[$i + 1][0] - $table[$i][0]);
                $vis = $table[$i][1] + ($table[$i + 1][1] - $table[$i][1]) * max(0, min(1, $t));
                break;
            }
        }
        $vis *= max(0.3, $pupilFloor); // plein soleil : pupille 0,30
        $s = max($vis, ($legacy ? 0.7 : 0.85) * $aud);

        return [(float) $vis, (float) $aud, min(1.0, (float) $s)];
    }

    public function testToutesLesFonctionsSontDeclareesEtLeursBlocsEquilibres(): void
    {
        $cfg = self::read(self::MAIN . 'CfgFunctions.hpp');
        foreach (self::AI_FUNCTIONS as $f) {
            self::assertStringContainsString("class {$f} {};", $cfg, "{$f} absente de CfgFunctions");
            self::assertFileExists(self::path(self::MAIN . "functions/fn_{$f}.sqf"));
        }
        $files = array_merge(self::aiSources(), [
            'functions/fn_burst.sqf', 'functions/fn_exposure.sqf', 'functions/fn_targets.sqf', 'functions/fn_log.sqf', 'XEH_preInit.sqf',
        ]);
        foreach ($files as $f) {
            $code = self::code(self::read(self::MAIN . $f));
            foreach (['{' => '}', '[' => ']', '(' => ')'] as $open => $close) {
                self::assertSame(substr_count($code, $open), substr_count($code, $close), "{$f} : {$open} {$close} déséquilibrés");
            }
        }
        // Toute fonction ai* appelée existe.
        $all = '';
        foreach (glob(self::path(self::MAIN . 'functions/*.sqf')) ?: [] as $file) {
            $all .= (string) file_get_contents($file);
        }
        preg_match_all('/comspec_fb_fnc_(ai[A-Za-z]+)/', $all, $m);
        foreach (array_unique($m[1]) as $called) {
            self::assertContains($called, self::AI_FUNCTIONS, "comspec_fb_fnc_{$called} appelée mais non déclarée");
        }
    }

    public function testReglagesCbaDeLaReactionDesIaEtLeursTextes(): void
    {
        $pre = self::read(self::MAIN . 'XEH_preInit.sqf');
        $xml = simplexml_load_string(self::read(self::MAIN . 'stringtable.xml'));
        self::assertNotFalse($xml);
        $keys = [];
        foreach ($xml->xpath('//Key') as $k) {
            $keys[(string) $k['ID']] = ['fr' => (string) $k->French, 'en' => (string) $k->English];
        }
        foreach (self::SETTINGS as [$var, $key, $default]) {
            self::assertStringContainsString('["' . $var . '"', $pre, "Réglage {$var} absent");
            self::assertMatchesRegularExpression('/\["' . $var . '", "(?:LIST|SLIDER|CHECKBOX)", "' . $key . '", "Ai", [^;]*?' . preg_quote($default, '/') . '[^;]*call _add;/', $pre, "Défaut de {$var}");
            foreach (["STR_COMSPEC_FB_Set_{$key}", "STR_COMSPEC_FB_Set_{$key}_Tip"] as $id) {
                self::assertArrayHasKey($id, $keys, "Texte manquant : {$id}");
                self::assertNotSame('', trim($keys[$id]['fr']));
                self::assertNotSame('', trim($keys[$id]['en']));
            }
            self::assertStringContainsString('Exemple', $keys["STR_COMSPEC_FB_Set_{$key}_Tip"]['fr'], "{$key} : exemple FR");
            self::assertStringContainsString('Example', $keys["STR_COMSPEC_FB_Set_{$key}_Tip"]['en'], "{$key} : exemple EN");
        }
        foreach (['Soft', 'Real', 'Strong'] as $opt) {
            self::assertArrayHasKey("STR_COMSPEC_FB_AiReaction_{$opt}", $keys);
            self::assertStringContainsString("STR_COMSPEC_FB_AiReaction_{$opt}", $pre);
        }
        // Journal des IA : désactivé par défaut, jamais le journal général forcé.
        self::assertStringContainsString('["comspec_fb_aiDebug", "CHECKBOX", "AiDebug", "Ai", false] call _add;', $pre);
        // Réglages lus par fn_aiParams avec la même variable et des bornes.
        $params = self::sqf('aiParams');
        foreach (['aiReaction', 'aiRadius', 'aiIntensity', 'aiDuration', 'aiImmunity', 'aiAnim', 'aiMax'] as $v) {
            self::assertStringContainsString('"comspec_fb_' . $v . '"', $params);
        }
        self::assertStringContainsString('if (_mode == 0) exitWith { [0, 1, 0, 1, 1, 0, false, _max] };', $params, 'Discrète : comportement d\'avant');
        // Réglage global (serveur) : jamais le drapeau « propre au joueur » (0) sur ces réglages.
        foreach (self::SETTINGS as [$var]) {
            self::assertDoesNotMatchRegularExpression('/\["' . $var . '"[^;]*\], 0\] call _add;/', $pre, "{$var} doit rester un réglage serveur");
        }
    }

    public function testEffetAppliqueSurLaMachineDeLIaSansRemoteExec(): void
    {
        $all = '';
        foreach (self::aiSources() as $f) {
            $all .= self::read(self::MAIN . $f);
        }
        self::assertDoesNotMatchRegularExpression('/remoteExec(Call)?\s*[\[\(]/', self::code($all), 'Événements CBA, jamais remoteExec');
        // Seules les IA locales sont traitées (aiBurst filtre, aiBatch revérifie : la propriété peut changer entre deux images).
        self::assertStringContainsString('local _x && {alive _x} && {!isPlayer _x}', self::sqf('aiBurst'));
        self::assertStringContainsString('{local _u}', self::sqf('aiBatch'));
        // Propriétaire qui change : restauration envoyée à la machine de l'unité.
        $tick = self::sqf('aiTick');
        self::assertStringContainsString('["comspec_fb_aiRestore", [_unit, [_unit] call comspec_fb_fnc_aiState], _unit] call CBA_fnc_targetEvent;', $tick);
        self::assertStringContainsString('["comspec_fb_aiRestore", { _this call comspec_fb_fnc_aiRestore }] call CBA_fnc_addEventHandler;', self::read(self::MAIN . 'XEH_preInit.sqf'));
        self::assertStringContainsString('if (local _unit) then {', self::sqf('aiRestore'));
        // Chaque détonation est aussi envoyée au propriétaire des IA proches (fn_targets), joueurs exclus.
        $targets = self::sqf('targets');
        self::assertStringContainsString('if (!isPlayer _x) then { _owners pushBackUnique (owner _x) }', $targets);
        self::assertStringContainsString('nearEntities [["CAManBase", "LandVehicle", "Air", "Ship", "StaticWeapon"], 80]', $targets);
        // Les IA ne passent plus par la boucle du joueur de fn_burst : un seul traitement.
        $burst = self::sqf('burst');
        self::assertStringContainsString('local _u && {alive _u} && {_isMe}', $burst);
        self::assertStringContainsString('[_ctx, _pos, _env, _g, _units, _center, _me] call comspec_fb_fnc_aiBurst;', $burst);
        self::assertStringNotContainsString('comspec_fb_fnc_aiHit', $burst);
        // Réglage « IA affectée » coupé : aucune IA traitée.
        self::assertStringContainsString('if (_aiOn) then {', $burst);
    }

    public function testEffetsDeLaReactionRealiste(): void
    {
        $hit = self::sqf('aiHit');
        // Aveuglement : ciblage coupé ; arrêt : MOVE ; durées 2 à 10 s aveugle, 5 à 20 s dégradé, plafonnées.
        foreach (['["TARGET", _now + _blind] call _holdAi;', '["AUTOTARGET", _now + _blind] call _holdAi;', '["MOVE", _now + _still] call _holdAi;',
            '(2 + 8 * _vI) * _pd * _durK * _k', '(5 + 15 * _s) * _pd * _durK * _k', '10 * _durK * (_pd max 1)', '20 * _durK * (_pd max 1)',
            'forgetTarget', 'setSuppression', '_unit setSkill', 'playMoveNow', 'setUnitPos', 'checkAIFeature', 'disableAI'] as $needle) {
            self::assertStringContainsString($needle, $hit, $needle);
        }
        self::assertStringContainsString('"commanding"', $hit, 'Surdité : transmission des contacts réduite.');
        // Distance, ligne de vue, orientation, mur et pièce fermée viennent de fn_exposure (même calcul que le joueur).
        $batch = self::sqf('aiBatch');
        self::assertStringContainsString('comspec_fb_fnc_exposure', $batch);
        self::assertStringContainsString('[_cfg select 1, _cfg select 2]', $batch);
        $expo = self::sqf('exposure');
        foreach (['lineIntersectsSurfaces', 'eyeDirection _unit', 'visionTable', '_sameRoom'] as $needle) {
            self::assertStringContainsString($needle, $expo);
        }
        self::assertStringContainsString('private _dEff = (_d / (_aiR max 0.3)) max 0.3;', $expo, 'Portée des IA');
        self::assertStringContainsString('_pupil = _pupil max _aiPupilMin;', $expo, 'Plancher de pupille des IA');
        // Un conducteur / passager garde son véhicule : arrêt et geste seulement à pied.
        self::assertStringContainsString('if (_onFoot && {_s > 0.35}) then {', $hit);
        // Geste vanilla seulement s'il existe dans la config du jeu.
        self::assertStringContainsString('isClass (configFile >> "CfgMovesMaleSdr" >> "States" >> _x)', $hit);
        // Immunité : tirée de l'identifiant de la grenade et de l'unité (même résultat partout), plafonnée à 60 %.
        self::assertStringContainsString('netId _unit', $hit);
        self::assertStringContainsString('(_chance min 0.6)', $hit);
        self::assertStringContainsString('_info set ["id", _ctx get "id"];', self::sqf('aiBatch'));
        // allowFleeing / setBehaviour : jamais touchés (pas de lecture possible pour les restaurer).
        foreach (self::aiSources() as $f) {
            $code = self::code(self::read(self::MAIN . $f));
            self::assertDoesNotMatchRegularExpression('/allowFleeing|setBehaviour|setCombatMode|setCaptive|doStop|disableAI\s+"(?:ANIM|PATH|AUTOCOMBAT|FSM)"/i', $code, $f);
        }
    }

    public function testRestaurationSurePasDeFuiteDeVariables(): void
    {
        $hit = self::sqf('aiHit');
        $restore = self::sqf('aiRestore');
        $tick = self::sqf('aiTick');
        // Toute variable posée sur l'unité par fn_aiHit est effacée par fn_aiRestore.
        preg_match_all('/_unit setVariable \["(comspec_fb_ai[A-Za-z]+)"/', $hit . $tick, $m);
        $set = array_values(array_unique($m[1]));
        // Posées seulement pour les besoins du sursaut : propres, autorisées à vivre (nombre, pas de référence).
        $transient = ['comspec_fb_aiStartleEnd'];
        foreach ($set as $var) {
            if (in_array($var, $transient, true)) {
                continue;
            }
            self::assertStringContainsString('"' . $var . '"', $restore, "{$var} jamais effacée");
        }
        self::assertStringContainsString('_unit setVariable [_x, nil];', $restore);
        // Valeurs d'origine mémorisées une seule fois, relues après pose, rendues sauf si un autre mod les a changées.
        self::assertStringContainsString('if (_st isEqualTo []) then { _st = [_skills, _skills apply { _unit skill _x }, []] };', $hit);
        self::assertStringContainsString('_st set [2, _skills apply { _unit skill _x }];', $hit);
        self::assertStringContainsString('abs ((_unit skill _x) - _was) < 0.02', $restore);
        self::assertStringContainsString('_unit setSkill [_x, _orig param [_forEachIndex, _unit skill _x]];', $restore);
        // disableAI : seulement une fonction qui était active ; rétablissement des seules fonctions coupées par le mod.
        self::assertStringContainsString('if (_pseudo || {_unit checkAIFeature _name}) then {', $hit);
        self::assertStringContainsString('{ _unit enableAI _x } forEach _feat;', $restore);
        self::assertStringContainsString('((_x select 0) select [0, 1]) != "@"', self::sqf('aiState'));
        // Posture : rendue seulement si c'est toujours celle posée par le mod.
        self::assertStringContainsString('toLower (unitPos _unit) == toLower _posSet', $restore);
        // IA morte, supprimée, inconsciente (ACE Médical / KAT), changée de machine : toutes gérées par le réveil.
        self::assertStringContainsString('if (isNull _unit) exitWith {};', $tick);
        self::assertStringContainsString('!alive _unit', $tick);
        self::assertStringContainsString('ace_isunconscious', $tick);
        self::assertStringContainsString('lifeState _unit == "INCAPACITATED"', $tick);
        self::assertStringContainsString('{!local _unit}', $tick);
        // Échéance unique par unité : un réveil périmé s'arrête.
        self::assertStringContainsString('(_unit getVariable ["comspec_fb_aiTickAt", -1]) isNotEqualTo _due', $tick);
    }

    public function testAucunMessageEcranAttenteBorneeEtJournalSousReglage(): void
    {
        foreach (self::aiSources() as $f) {
            $code = self::code(self::read(self::MAIN . $f));
            self::assertDoesNotMatchRegularExpression('/\b(systemChat|hint|hintSilent|hintC|cutText|titleText|titleRsc|cutRsc|cutFadeOut|BIS_fnc_showNotification|ace_common_fnc_displayText|ace_common_displayTextStructured)\b/i', $code, "{$f} : aucun message à l'écran");
            self::assertDoesNotMatchRegularExpression('/\b(waitUntil|sleep|uiSleep|spawn|execVM)\b/i', $code, "{$f} : pas d'attente ni de fil non borné (CBA_fnc_waitAndExecute / execNextFrame)");
            self::assertStringNotContainsString('diag_log', $code, "{$f} : le journal passe par comspec_fb_fnc_log");
            self::assertStringNotContainsString('CBA_fnc_waitUntilAndExecute', $code, "{$f} : condition non bornée");
        }
        // Réveils bornés : 40 au plus, 12 attentes d'inconscience, délais en secondes.
        $tick = self::sqf('aiTick');
        self::assertStringContainsString('_wakes > 40', $tick);
        self::assertStringContainsString('_retry < 12', $tick);
        self::assertStringContainsString('[comspec_fb_fnc_aiTick, [_unit, _next], _next - _now] call CBA_fnc_waitAndExecute;', $tick);
        // Tranches : 6 IA par image, les plus proches d'abord, plafond par détonation.
        $batch = self::sqf('aiBatch');
        self::assertStringContainsString('private _n = 6 min count _queue;', $batch);
        self::assertStringContainsString('CBA_fnc_execNextFrame', $batch);
        $burst = self::sqf('aiBurst');
        self::assertStringContainsString('_keyed sort true;', $burst);
        self::assertStringContainsString('_keyed select [0, _cfg select 7]', $burst);
        // Journal : réglage « Journal des IA », jamais d'écriture sans lui ni sans le journal détaillé.
        $log = self::sqf('log');
        self::assertStringContainsString('comspec_fb_aiDebug', $log);
        self::assertStringContainsString('diag_log text _line;', $log);
        self::assertStringContainsString('if (missionNamespace getVariable ["comspec_fb_aiDebug", false] || {[true] call comspec_fb_fnc_logOn}) then {', self::sqf('aiHit'));
        // Aucun toast, systemChat ni hint dans les effets du joueur ajoutés (rien de nouveau côté joueur).
        self::assertDoesNotMatchRegularExpression('/\b(systemChat|hintSilent|cutText|titleText)\b/', self::code(self::sqf('exposure')));
    }

    public function testStringtableEstUnXmlStrictAvecLesDeuxLangues(): void
    {
        $raw = self::read(self::MAIN . 'stringtable.xml');
        $xml = simplexml_load_string($raw);
        self::assertNotFalse($xml, 'stringtable.xml : XML strict');
        $ids = [];
        foreach ($xml->xpath('//Key') as $k) {
            $id = (string) $k['ID'];
            self::assertArrayNotHasKey($id, $ids, "Clé en double : {$id}");
            $ids[$id] = true;
            self::assertNotSame('', trim((string) $k->French), $id);
            self::assertNotSame('', trim((string) $k->English), $id);
        }
        // Toute clé STR_COMSPEC_FB_ lue par localize dans les réglages existe.
        $pre = self::read(self::MAIN . 'XEH_preInit.sqf');
        preg_match_all('/localize "(STR_COMSPEC_FB_AiReaction_[A-Za-z]+)"/', $pre, $m);
        foreach ($m[1] as $id) {
            self::assertArrayHasKey($id, $ids);
        }
    }

    public function testCalibrationLesIaReagissentJusqua35MetresEtDeJour(): void
    {
        // Avant 1.0.1 (plein soleil, dehors, de face) : à 10 m la vision n'atteignait pas le seuil d'aveuglement et la
        // force ne dépassait pas 0,40 : compétences à 66 % seulement, aucune action visible.
        [$visOld10, , $sOld10] = self::strength(10, 1.0, 0.0, true);
        self::assertLessThan(0.2, $visOld10);
        self::assertLessThan(0.45, $sOld10);
        [$visOld20, , ] = self::strength(20, 1.0, 0.0, true);
        self::assertSame(0.0, $visOld20, 'Au-delà de 19 m : aucune vision.');
        // Réglages par défaut 1.0.1 (portée x1,5, pupille >= 0,55) : aveuglement à 10 m, arrêt net (> 0,35) jusqu'à ~25 m.
        [$vis10, , $s10] = self::strength(10, 1.5, 0.55, false);
        self::assertGreaterThanOrEqual(0.2, $vis10, 'Aveuglée à 10 m de face en plein soleil.');
        self::assertGreaterThan(0.5, $s10);
        [, , $s25] = self::strength(25, 1.5, 0.55, false);
        self::assertGreaterThan(0.35, $s25, 'Désorientée à 25 m.');
        [, , $s40] = self::strength(40, 1.5, 0.55, false);
        self::assertGreaterThan(0.08, $s40, 'Encore touchée (dégradée) à 40 m.');
        [, , $s60] = self::strength(60, 1.5, 0.55, false);
        self::assertLessThan(0.2, $s60, 'Pas de réaction forte à 60 m.');
        // Durées : plafonds 10 s (aveugle) et 20 s (dégradée) pour un effet maximal.
        self::assertEqualsWithDelta(10.0, 2 + 8 * 1.0, 0.001);
        self::assertEqualsWithDelta(20.0, 5 + 15 * 1.0, 0.001);
        self::assertEqualsWithDelta(5.0, 5 + 15 * 0.0, 0.001);
        // Les valeurs par défaut documentées sont celles du code.
        $params = self::sqf('aiParams');
        self::assertStringContainsString('["comspec_fb_aiRadius", 1.5]', $params);
        self::assertStringContainsString('[0.55, 0.7] select (_mode == 2)', $params);
        self::assertStringContainsString('["comspec_fb_aiImmunity", 0.08]', $params);
        self::assertStringContainsString('["comspec_fb_aiMax", 20]', $params);
    }

    public function testVersionNoteDeVersionEtAucunBinaire(): void
    {
        $sc = self::read(self::MAIN . 'script_component.hpp');
        self::assertStringContainsString('#define VERSION_STR 1.0.1', $sc);
        self::assertStringContainsString('#define VERSION_AR 1,0,1', $sc);
        self::assertStringContainsString('patch = 1', self::read('mod/COMSPEC_Flashbang/Sources/.hemtt/project.toml'));
        // Premier bloc [h1] du changelog = note de version : 1.0.1, résumé joueur FR + EN.
        $log = self::read('mod/COMSPEC_Flashbang/workshop/STEAM_CHANGELOG.txt');
        self::assertStringStartsWith('[h1]COMSPEC Flashbang -BetterFlash- 1.0.1', $log);
        $blocks = preg_split('/^(?=\[h1\])/m', $log) ?: [];
        $first = '';
        foreach ($blocks as $b) {
            if (trim($b) !== '') {
                $first = $b;
                break;
            }
        }
        self::assertStringContainsString('[b]Résumé joueur[/b]', $first);
        self::assertStringContainsString('[b]Player summary[/b]', $first);
        self::assertStringContainsString('[h2]Français[/h2]', $first);
        self::assertStringContainsString('[h2]English[/h2]', $first);
        self::assertLessThan(8000, strlen($first), 'Note de version Steam : 8 000 octets au plus.');
        self::assertStringContainsString('1.0.1', self::read('mod/COMSPEC_Flashbang/README.md'));
        // Aucun binaire dans les sources du mod (build, signature, publication restent hors du dépôt).
        $root = self::path('mod/COMSPEC_Flashbang');
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        /** @var \SplFileInfo $file */
        foreach ($it as $file) {
            $p = substr(str_replace('\\', '/', $file->getPathname()), strlen(str_replace('\\', '/', $root)));
            if (str_contains($p, '/.hemttout/') || str_contains($p, '/@COMSPEC_Flashbang/')) {
                continue;
            }
            self::assertNotContains(strtolower($file->getExtension()), ['pbo', 'dll', 'bisign', 'bikey', 'biprivatekey', 'exe'], "Binaire dans le dépôt : {$p}");
            self::assertDoesNotMatchRegularExpression('~/(bin|obj|signing|publisher)/~', $p, "Dossier de build dans le dépôt : {$p}");
        }
    }
}
