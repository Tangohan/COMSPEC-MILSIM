/*
    Autotest COMSPEC Flashbang (lancé par tools\ingame_test.ps1, Stratis, solo) : chaque cas lance une vraie grenade
    (lancer vanilla par forceWeaponFire, lancer avancé ACE par ses propres fonctions, ou lancer vanilla avec les XEH
    « fired » du joueur vidés pour éprouver le filet ProjectileCreated), puis lit le journal de diagnostic du mod
    (comspec_fb_capture) et écrit AUTOTEST|OK|... ou AUTOTEST|KO|... dans le RPT.
    Dès que l'engin est posé (0,25 s avant la 1re détonation), le joueur est placé à 3 m face à lui et une IA à 5 m.
    Fin : AUTOTEST|FIN (le script PowerShell ferme alors le jeu).
*/
if (!hasInterface) exitWith {};
[] spawn {
    waitUntil { time > 3 && {!isNil "comspec_fb_events"} && {!isNull player} };
    sleep 2;
    diag_log text "AUTOTEST|DEBUT";
    missionNamespace setVariable ["comspec_fb_debug", true];
    private _home = getPosATL player;
    // Sans arme en main : le lancer vanilla part tout de suite (pas d'animation de rangement d'arme).
    removeAllWeapons player;
    player addWeapon "Throw";
    player addWeapon "Put";
    private _grp = createGroup [west, true];
    private _ai = _grp createUnit ["B_Soldier_F", _home vectorAdd [40, 0, 0], [], 0, "CAN_COLLIDE"];
    _ai disableAI "PATH";
    _ai allowDamage false;
    player allowDamage false;

    private _ace = !isNil "ace_advanced_throwing_fnc_prepare";
    // Témoin brut : événement « Fired » du moteur, indépendant de CBA et du mod ; heure du lancer réel.
    AT_tFired = -1;
    player addEventHandler ["Fired", {
        params ["_unit", "_weapon", "_muzzle", "", "_ammo", "_magazine", "_projectile"];
        if (_weapon == "Throw") then { AT_tFired = CBA_missionTime };
        diag_log text format ["AUTOTEST|RAW|Fired moteur : arme %1 muzzle %2 munition %3 magasin %4 projectile %5", _weapon, _muzzle, _ammo, _magazine, _projectile];
    }];
    diag_log text format ["AUTOTEST|INFO|ACE lancer avancé chargé=%1 activé=%2 XEH fired sur le joueur=%3", _ace,
        missionNamespace getVariable ["ace_advanced_throwing_enabled", "nil"], count (player getVariable ["cba_xeh_fired", []])];

    // [nom, magasin, nombre de détonations, mèche attendue (s), chemin "vanilla" | "ace" | "secours", drill]
    private _cases = [
        ["a1_vanilla_x1", "COMSPEC_FB_Flash_1", 1, 1.5, "vanilla", false],
        ["a3_vanilla_x3", "COMSPEC_FB_Flash_3", 3, 1.5, "vanilla", false],
        ["a9_vanilla_x9", "COMSPEC_FB_Flash_9", 9, 1.5, "vanilla", false],
        ["b1_ace_x1", "COMSPEC_FB_Flash_1", 1, 1.5, "ace", false],
        ["b3_ace_x3", "COMSPEC_FB_Flash_3", 3, 1.5, "ace", false],
        ["b9_ace_x9", "COMSPEC_FB_Flash_9", 9, 1.5, "ace", false],
        ["c3_vanilla_x3_d3", "COMSPEC_FB_Flash_3_D3", 3, 3, "vanilla", false],
        ["c8_vanilla_x2_d8", "COMSPEC_FB_Flash_2_D8", 2, 8, "vanilla", false],
        ["c8_ace_x1_d8", "COMSPEC_FB_Flash_1_D8", 1, 8, "ace", false],
        ["d_vanilla_drill", "COMSPEC_FB_Drill", 1, 1.5, "vanilla", true],
        ["d_ace_drill_d3", "COMSPEC_FB_Drill_D3", 1, 3, "ace", true],
        ["e_secours_x3", "COMSPEC_FB_Flash_3", 3, 1.5, "secours", false]
    ];
    private _tSec = { parseNumber ((_this splitString "=") select ((count (_this splitString "=")) - 1)) };
    {
        _x params ["_name", "_mag", "_n", "_fuse", "_path", "_drill"];
        if (_path == "ace" && {!_ace}) then {
            diag_log text format ["AUTOTEST|SKIP|%1|ACE absent", _name];
            continue;
        };
        // État propre : effets du joueur remis à zéro, anciens engins retirés, une seule grenade en inventaire.
        [] call comspec_fb_fnc_playerReset;
        { deleteVehicle _x } forEach (allMissionObjects "COMSPEC_FB_Device_Base");
        player setPosATL _home;
        player setDir 0;
        _ai setPosATL (_home vectorAdd [40, 0, 0]);
        { player removeMagazines _x } forEach ((magazines player) arrayIntersect (magazines player));
        player addMagazine _mag;
        sleep 1.5;
        private _muzzle = (getArray (configFile >> "CfgWeapons" >> "Throw" >> "muzzles")) select {
            _mag in getArray (configFile >> "CfgWeapons" >> "Throw" >> _x >> "magazines")
        } param [0, ""];

        comspec_fb_capture = [];
        AT_tFired = -1;
        private _xehSave = player getVariable ["cba_xeh_fired", []];
        private _t0 = CBA_missionTime;
        if (_path == "ace") then {
            [player] call ace_advanced_throwing_fnc_prepare;
            sleep 0.5;
            _t0 = CBA_missionTime;
            [player] call ace_advanced_throwing_fnc_throw;
        } else {
            // « secours » : gestionnaires « fired » des XEH vidés sur le joueur (cas d'une unité sans XEH).
            if (_path == "secours") then { player setVariable ["cba_xeh_fired", []] };
            player forceWeaponFire [_muzzle, _muzzle];
            private _lim = time + 8;
            waitUntil { AT_tFired > 0 || {time > _lim} };
            if (AT_tFired > 0) then { _t0 = AT_tFired };
        };
        // Engin posé : joueur à 3 m face à lui, IA à 5 m face à lui.
        private _dev = objNull;
        private _limit = time + _fuse + 4;
        waitUntil {
            _dev = (allMissionObjects "COMSPEC_FB_Device_Base") param [0, objNull];
            !isNull _dev || {time > _limit}
        };
        if (!isNull _dev) then {
            private _p = getPosATL _dev;
            player setPosATL (_p vectorAdd [0, -3, 0]);
            player setDir 0;
            _ai setPosATL (_p vectorAdd [5, 0, 0]);
            _ai setDir 270;
        };
        sleep (_n * 0.65 + 2.5);
        player setVariable ["cba_xeh_fired", _xehSave];
        private _lines = +comspec_fb_capture;
        comspec_fb_capture = nil;

        private _firedLines = _lines select { "] fired |" in _x };
        private _bursts = _lines select { "] burst |" in _x && {"tonation " in _x} };
        private _first = if (_bursts isEqualTo []) then { -1 } else { ((_bursts select 0) call _tSec) - _t0 };
        private _aiHit = { "] unité |" in _x && {" IA :" in _x} && {"effets appliqu" in _x} } count _lines;
        private _meHit = { "] unité |" in _x && {"JOUEUR" in _x} && {"effets appliqu" in _x} } count _lines;
        private _fx = { "] fx |" in _x } count _lines;
        private _errs = [];
        if (_firedLines isEqualTo []) then { _errs pushBack "aucune ligne fired (grenade non prise en charge)" };
        private _want = ["vanilla", "ACE lancer avancé", "secours ProjectileCreated"] select (["vanilla", "ace", "secours"] find _path);
        if (_firedLines isNotEqualTo [] && {!(_want in (_firedLines select 0))}) then { _errs pushBack format ["chemin attendu « %1 » absent", _want] };
        if (isNull _dev) then { _errs pushBack "aucun engin posé" };
        if (count _bursts != _n) then { _errs pushBack format ["%1 détonations au lieu de %2", count _bursts, _n] };
        private _tol = [0.25, 0.4] select (_fuse > 2);
        if (_first >= 0 && {abs (_first - _fuse) > _tol}) then { _errs pushBack format ["1re détonation à %1 s au lieu de %2 s", _first toFixed 2, _fuse] };
        if (_fx != _n) then { _errs pushBack format ["%1 lignes fx au lieu de %2", _fx, _n] };
        if (_drill) then {
            if (_aiHit + _meHit > 0) then { _errs pushBack "DRILL : effets appliqués à tort" };
        } else {
            if (_aiHit == 0) then { _errs pushBack "IA à 5 m non touchée" };
            if (_meHit == 0) then { _errs pushBack "joueur à 3 m non touché" };
        };
        diag_log text format ["AUTOTEST|%1|%2|chemin=%3 magasin=%4 détonations=%5/%6 1re=%7 s (attendu %8) IA touchée=%9 joueur touché=%10%11",
            ["KO", "OK"] select (_errs isEqualTo []), _name, _path, _mag, count _bursts, _n, _first toFixed 2, _fuse,
            _aiHit > 0, _meHit > 0, [" | " + (_errs joinString " ; "), ""] select (_errs isEqualTo [])];
        sleep 1;
    } forEach _cases;

    // Test intégré (console) : rapport complet.
    [] call comspec_fb_fnc_playerReset;
    private _ret = [3] call comspec_fb_fnc_debugTest;
    diag_log text format ["AUTOTEST|INFO|debugTest : %1", _ret];
    sleep 8;
    diag_log text format ["AUTOTEST|%1|debugTest_x3|rapport %2", ["KO", "OK"] select (isNil "comspec_fb_capture"), ["en cours", "écrit"] select (isNil "comspec_fb_capture")];
    [] call comspec_fb_fnc_playerReset;
    diag_log text "AUTOTEST|FIN";
};
