/*
    comspec_fb_fnc_debugTest : test intégré, à lancer depuis la console de débogage (n'importe quelle mission, Eden compris).

        [3] call comspec_fb_fnc_debugTest;          grenade Flash ×3 posée à 4 m devant le joueur
        [5, "D8"] call comspec_fb_fnc_debugTest;    Flash ×5 à mèche 8 s
        [1, "", true] call comspec_fb_fnc_debugTest; grenade DRILL

    La grenade est créée comme un vrai lancer (projectile de la munition du mod, comme le lancer avancé d'ACE), puis
    passe par la chaîne réelle : fn_fired (mèche) -> fn_fuseEnd -> serveur (fn_serverDetonate) -> fn_timeline ->
    fn_burst (lumière, son, effets). Le journal de diagnostic est activé le temps du test sur ce poste ; à la fin, le
    rapport (réglages, XEH, chaque étape, intensité calculée pour le joueur, effets appliqués ou raison du refus) est écrit
    dans le RPT et copié dans le presse-papiers (copyToClipboard). Rien ne s'affiche à l'écran.
    Retour : texte court pour la console (quand le rapport sera prêt).
    [_n, _delai, _drill] call comspec_fb_fnc_debugTest
      _n     : 1 à 9 détonations ; _delai : "" (mèche du réglage), "D3" ou "D8" ; _drill : true = grenade DRILL
*/
params [["_n", 3, [0]], ["_delay", "", [""]], ["_drill", false, [false]]];
if (!hasInterface) exitWith { "comspec_fb_fnc_debugTest : à lancer sur un poste avec interface (joueur)" };
if (!isNil "comspec_fb_capture") exitWith { "comspec_fb_fnc_debugTest : un test est déjà en cours" };
_n = ((round _n) max 1) min 9;
if !(_delay in ["", "D3", "D8"]) then { _delay = "" };
private _sfx = ["", "_" + _delay] select (_delay != "");
private _mag = if (_drill) then { "COMSPEC_FB_Drill" + _sfx } else { format ["COMSPEC_FB_Flash_%1%2", _n, _sfx] };
private _ammo = getText (configFile >> "CfgMagazines" >> _mag >> "ammo");
private _muzzle = if (_drill) then { "COMSPEC_FB_Muzzle_Drill" + _sfx } else { format ["COMSPEC_FB_Muzzle_%1%2", _n, _sfx] };
private _unit = call CBA_fnc_currentUnit;

comspec_fb_capture = [];
private _head = [];
_head pushBack format ["[COMSPEC FB] TEST %1 (%2), lancé par %3, mission %4, serveur=%5 multijoueur=%6, mod sur le serveur=%7 (version %8)",
    _mag, _ammo, name _unit, missionName, isServer, isMultiplayer, call comspec_fb_fnc_serverHasMod,
    missionNamespace getVariable ["comspec_fb_serverVersion", "inconnue"]];
_head pushBack format ["[COMSPEC FB] config : magasin %1, munition %2 (simulation %3, explosionTime %4, timeToLive %5, mèche config %6), engin %7 %8, muzzle %9 dans Throw : %10",
    isClass (configFile >> "CfgMagazines" >> _mag), isClass (configFile >> "CfgAmmo" >> _ammo),
    getText (configFile >> "CfgAmmo" >> _ammo >> "simulation"), getNumber (configFile >> "CfgAmmo" >> _ammo >> "explosionTime"),
    getNumber (configFile >> "CfgAmmo" >> _ammo >> "timeToLive"), getNumber (configFile >> "CfgAmmo" >> _ammo >> "comspec_fb_fuse"),
    getText (configFile >> "CfgAmmo" >> _ammo >> "comspec_fb_device"),
    isClass (configFile >> "CfgVehicles" >> getText (configFile >> "CfgAmmo" >> _ammo >> "comspec_fb_device")),
    _muzzle, _muzzle in getArray (configFile >> "CfgWeapons" >> "Throw" >> "muzzles")];
// Le gestionnaire « fired » du mod est-il posé sur l'unité (XEH CBA) ? Sinon un vrai lancer ne ferait rien.
private _xeh = _unit getVariable ["cba_xeh_fired", []];
_head pushBack format ["[COMSPEC FB] XEH : %1 gestionnaire(s) « fired » sur %2 (%3), dont celui du mod : %4",
    count _xeh, typeOf _unit, _unit, ({ "comspec_fb_fnc_fired" in str _x } count _xeh) > 0];
_head pushBack format ["[COMSPEC FB] réglages : préréglage %1, intensité %2, mèche %3 s, intervalle %4 s, irrégularité %5 s, IA affectée %6, effet sur son camp %7, éblouissement %8, acouphène %9, son DRILL %10 (volume %11)",
    missionNamespace getVariable ["comspec_fb_preset", "nil"], missionNamespace getVariable ["comspec_fb_intensity", "nil"],
    missionNamespace getVariable ["comspec_fb_fuse", "nil"], missionNamespace getVariable ["comspec_fb_interval", "nil"],
    missionNamespace getVariable ["comspec_fb_jitter", "nil"], missionNamespace getVariable ["comspec_fb_aiAffected", "nil"],
    missionNamespace getVariable ["comspec_fb_friendly", "nil"], missionNamespace getVariable ["comspec_fb_flash", "nil"],
    missionNamespace getVariable ["comspec_fb_tinnitus", "nil"], missionNamespace getVariable ["comspec_fb_drillSound", "nil"],
    missionNamespace getVariable ["comspec_fb_drillVolume", "nil"]];
{ diag_log text _x } forEach _head;

// Grenade posée à 4 m devant le joueur, au sol, comme si elle venait d'être lancée.
private _dir = getDir _unit;
private _p = (getPosATL _unit) vectorAdd [4 * sin _dir, 4 * cos _dir, 0];
private _posASL = AGLToASL [_p select 0, _p select 1, 0];
_posASL set [2, (_posASL select 2) max ((getPosASL _unit) select 2)];
private _proj = createVehicle [_ammo, ASLToAGL (_posASL vectorAdd [0, 0, 0.3]), [], 0, "CAN_COLLIDE"];
_proj setPosASL (_posASL vectorAdd [0, 0, 0.3]);
comspec_fb_capture pushBack format ["[COMSPEC FB] test | projectile créé : %1 (%2) à 4 m", [typeOf _proj, "ÉCHEC"] select (isNull _proj), _proj];
[_unit, "Throw", _muzzle, _muzzle, _ammo, _mag, _proj, _unit] call comspec_fb_fnc_fired;

// Rapport : après la dernière détonation possible (mèche + 9 intervalles) et la fin des effets immédiats.
private _fixed = getNumber (configFile >> "CfgAmmo" >> _ammo >> "comspec_fb_fuse");
private _fuse = [(missionNamespace getVariable ["comspec_fb_fuse", 1.5]) min 4, _fixed + 0.3] select (_fixed > 0);
private _wait = _fuse + _n * ((missionNamespace getVariable ["comspec_fb_interval", 0.5]) + 0.3) + 2;
[{
    params ["_head"];
    private _lines = _head + comspec_fb_capture;
    comspec_fb_capture = nil;
    // Les étapes sont déjà dans le RPT (fn_log) : seule la conclusion y est ajoutée ; le presse-papiers reçoit tout.
    private _result = [
        "[COMSPEC FB] RÉSULTAT : chaîne complète exécutée sur ce poste (lumière, son et effets : lignes fx et unité)",
        "[COMSPEC FB] RÉSULTAT : aucune détonation exécutée sur ce poste (voir la dernière étape ci-dessus ; en multijoueur, voir aussi la ligne « [COMSPEC FB] SERVEUR »)"
    ] select (({ "] burst |" in _x } count _lines) == 0);
    _lines pushBack _result;
    diag_log text _result;
    copyToClipboard (_lines joinString endl);
}, [_head], _wait] call CBA_fnc_waitAndExecute;
format ["Test %1 lancé : rapport dans le RPT et le presse-papiers dans %2 s", _mag, ceil _wait]
