/*
    Capture de toute la carte (ou d'une zone) en tuiles d'images, pour en faire un fond de carte « Plans » :
      "sat"  : vue satellite, objectif vertical (orthophoto), comme Plans ou Google Earth vu de dessus ;
      "3d"   : vue 3D oblique (45°, nord en haut), bâtiments et arbres en relief comme le mode 3D de Plans ;
      "plan" : carte topographique d'Arma (carroyage, routes, courbes de niveau).
    Plusieurs hauteurs à la suite (une couche par hauteur, plus la caméra est basse plus l'image est détaillée).
    Chaque tuile part dans Documents\Arma 3\Screenshots sous un nom qui la décrit :
      cmap_<carte>_<mode>_h<hauteur>_s<pas en m>_w<largeur couverte en m>_o<origine x>-<origine y>_x<colonne>_y<ligne>.png
    (colonne 0 à l'ouest, ligne 0 au sud)
    puis mod/COMSPEC_ATAK_Native/tools/map_stitch.py assemble les tuiles (mosaïque et tuiles web z/x/y).
    Pendant la capture : vue lointaine, objets lointains et herbe au maximum, interface cachée ; tout est remis après.
    Le moteur limite la taille du dossier Screenshots (250 Mo par défaut) : relever maxScreenShotFolderSizeMB dans
    le profil Arma pour une grande carte. Lancer de jour, en solo ou dans l'éditeur, pour le plus beau rendu.
    Params :
      ["start", [mode ("sat" | "3d" | "plan"), hauteurs (m, ex. [300, 800]), centre ([] : carte entière),
                 rayon (m, avec un centre), recouvrement (0 à 0.5, défaut 0.15), attente par tuile (s, défaut 2)]]
      ["stop"]   arrête la capture en cours
      ["count", mêmes arguments que start] → [nombre de tuiles, pas en m] (estimation, sans rien capturer)
      ["zeus"]   formulaire du module Zeus (ZEN) « Capture de la carte »
    Exemple (console de débogage) : ["start", ["sat", [400, 1200], [], 0]] call comspec_atak_native_fnc_mapCapture;
*/
params [["_act", "start"], ["_arg", []]];
if (!hasInterface) exitWith { false };
// Emprise d'une image vue de la hauteur _h (mètres au sol, largeur × hauteur) : mesurée à l'écran au premier passage.
private _defaultFoot = {
    params ["_mode", "_h"];
    switch (_mode) do {
        case "plan": { [_h * 2, _h * 2 * safeZoneH / safeZoneW] };
        case "3d": { [_h * 1.4, _h * 1.4 * safeZoneH / safeZoneW] };
        default { [_h * 1.1, _h * 1.1 * safeZoneH / safeZoneW] };
    }
};
private _grid = {
    params ["_center", "_radius", "_step"];
    private _x0 = 0; private _y0 = 0; private _x1 = worldSize; private _y1 = worldSize;
    if (_center isNotEqualTo []) then {
        _x0 = ((_center select 0) - _radius) max 0; _y0 = ((_center select 1) - _radius) max 0;
        _x1 = ((_center select 0) + _radius) min worldSize; _y1 = ((_center select 1) + _radius) min worldSize;
    };
    private _nx = ceil ((_x1 - _x0) / _step) max 1;
    private _ny = ceil ((_y1 - _y0) / _step) max 1;
    [_x0, _y0, _nx, _ny]
};
switch (_act) do {
    case "stop": {
        missionNamespace setVariable ["COMSPEC_ATAK_MapCapStop", true];
        true
    };
    case "count": {
        _arg params [["_mode", "sat"], ["_heights", [400]], ["_center", []], ["_radius", 1000], ["_overlap", 0.15]];
        private _total = 0; private _steps = [];
        {
            ([_mode, _x] call _defaultFoot) params ["_fw", "_fh"];
            private _step = round ((_fw min _fh) * (1 - _overlap));
            ([_center, _radius, _step] call _grid) params ["", "", "_nx", "_ny"];
            _total = _total + _nx * _ny;
            _steps pushBack _step;
        } forEach _heights;
        [_total, _steps]
    };
    case "zeus": {
        if (isNil "zen_dialog_fnc_create") exitWith { false };
        ["Capture de la carte", [
            ["COMBO", "Rendu", [["sat", "3d", "plan"], ["Satellite (vue de dessus)", "3D oblique (45°)", "Plan topographique"], 0]],
            ["EDIT", "Hauteurs (m, séparées par des virgules)", "400, 1200"],
            ["COMBO", "Zone", [[0, 1], ["Autour de la position du module", "Carte entière"], 0]],
            ["SLIDER", "Rayon de la zone (m)", [200, 10000, 1500, 0]],
            ["SLIDER", "Attente par tuile (s)", [0.5, 6, 2, 1]]
        ], {
            params ["_values", "_pos"];
            _values params ["_mode", "_hs", "_zone", "_radius", "_wait"];
            private _heights = ((_hs splitString ", ;") apply { parseNumber _x }) select { _x >= 20 };
            if (_heights isEqualTo []) then { _heights = [400]; };
            ["start", [_mode, _heights, [[], _pos] select (_zone isEqualTo 0), _radius, 0.15, _wait]] call comspec_atak_native_fnc_mapCapture;
        }, {}, ASLToAGL _arg] call zen_dialog_fnc_create;
        true
    };
    case "start": {
        if (missionNamespace getVariable ["COMSPEC_ATAK_MapCapRunning", false]) exitWith {
            ["WARNING", "Capture de la carte déjà en cours (Échap pour l'arrêter)", 4, 20] call comspec_atak_native_fnc_notify;
            false
        };
        _arg params [["_mode", "sat"], ["_heights", [400]], ["_center", []], ["_radius", 1000], ["_overlap", 0.15], ["_wait", 2]];
        _mode = toLower _mode;
        if !(_mode in ["sat", "3d", "plan"]) then { _mode = "sat"; };
        if (_center isEqualType objNull) then { _center = getPosATL _center; };
        // Rien à l'écran pendant la capture : estimation annoncée avant, interface Zeus et téléphone fermés.
        (["count", [_mode, _heights, _center, _radius, _overlap]] call comspec_atak_native_fnc_mapCapture) params ["_total"];
        ["INFO", format ["Capture de la carte (%1) : environ %2 images, début dans 5 s. Échap pour arrêter.", _mode, _total], 5, 40] call comspec_atak_native_fnc_notify;
        if (!isNull (findDisplay 312)) then { (findDisplay 312) closeDisplay 2; };
        [] call comspec_atak_native_fnc_close;
        missionNamespace setVariable ["COMSPEC_ATAK_MapCapRunning", true];
        missionNamespace setVariable ["COMSPEC_ATAK_MapCapStop", false];
        [_mode, _heights, _center, _radius, (_overlap max 0) min 0.5, _wait max 0.3, _defaultFoot, _grid] spawn {
            params ["_mode", "_heights", "_center", "_radius", "_overlap", "_wait", "_defaultFoot", "_grid"];
            disableSerialization;
            uiSleep 5.5;
            private _keep = [viewDistance, getObjectViewDistance select 0, getTerrainGrid];
            setViewDistance 12000;
            setObjectViewDistance [12000, 400];
            setTerrainGrid 3.125;
            // Échap : arrêt propre (tuiles déjà prises gardées).
            private _eh = (findDisplay 46) displayAddEventHandler ["KeyDown", {
                params ["", "_key"];
                if (_key isEqualTo 1) exitWith { missionNamespace setVariable ["COMSPEC_ATAK_MapCapStop", true]; true };
                false
            }];
            private _cam = objNull;
            private _map = controlNull;
            private _mapDisp = displayNull;
            if (_mode isEqualTo "plan") then {
                // Carte plein écran sans rien autour (pas de légende, pas de curseur visible sur la photo).
                _mapDisp = (findDisplay 46) createDisplay "RscDisplayEmpty";
                _map = _mapDisp ctrlCreate ["RscMapControl", 4100];
                _map ctrlSetPosition [safeZoneX, safeZoneY, safeZoneW, safeZoneH];
                _map ctrlCommit 0;
                _map ctrlMapSetPosition [];
            } else {
                _cam = "camera" camCreate [worldSize / 2, worldSize / 2, 500];
                _cam cameraEffect ["Internal", "Back"];
                cameraEffectEnableHUD false;
                showCinemaBorder false;
            };
            ("COMSPEC_ATAK_MapCapUI" call BIS_fnc_rscLayer) cutText ["", "PLAIN"];
            private _shots = 0;
            private _t0 = diag_tickTime;
            {
                private _h = _x;
                if (missionNamespace getVariable ["COMSPEC_ATAK_MapCapStop", false]) then { break };
                // Emprise réelle mesurée au centre de la zone (bords de l'écran projetés au sol), sinon estimée.
                private _c = if (_center isEqualTo []) then { [worldSize / 2, worldSize / 2] } else { _center };
                ([_mode, _h] call _defaultFoot) params ["_fw", "_fh"];
                private _place = {
                    params ["_px", "_py"];
                    if (_mode isEqualTo "plan") then {
                        // Échelle de la carte : _fw mètres sur la largeur de l'écran.
                        private _scale = (_fw / worldSize) * (safeZoneH / safeZoneW) * 1.4;
                        _map ctrlMapAnimAdd [0, _scale, [_px, _py, 0]];
                        ctrlMapAnimCommit _map;
                    } else {
                        private _gz = getTerrainHeightASL [_px, _py];
                        if (_mode isEqualTo "3d") then {
                            // Objectif à 45° vers le nord : il regarde le centre de la tuile depuis le sud.
                            _cam setPosASL [_px, _py - _h, _gz + _h];
                            _cam setVectorDirAndUp [[0, 0.7071, -0.7071], [0, 0.7071, 0.7071]];
                        } else {
                            _cam setPosASL [_px, _py, _gz + _h];
                            _cam setVectorDirAndUp [[0, 0, -1], [0, 1, 0]];
                        };
                        _cam camSetFov 0.7;
                        _cam camCommit 0;
                    };
                };
                [_c select 0, _c select 1] call _place;
                uiSleep (_wait max 1);
                if (_mode isEqualTo "plan") then {
                    private _l = _map ctrlMapScreenToWorld [safeZoneX, safeZoneY + safeZoneH / 2];
                    private _r = _map ctrlMapScreenToWorld [safeZoneX + safeZoneW, safeZoneY + safeZoneH / 2];
                    private _t = _map ctrlMapScreenToWorld [safeZoneX + safeZoneW / 2, safeZoneY];
                    private _b = _map ctrlMapScreenToWorld [safeZoneX + safeZoneW / 2, safeZoneY + safeZoneH];
                    _fw = (_l distance2D _r) max 10; _fh = (_t distance2D _b) max 10;
                } else {
                    if (_mode isEqualTo "sat") then {
                        private _l = screenToWorld [safeZoneX, safeZoneY + safeZoneH / 2];
                        private _r = screenToWorld [safeZoneX + safeZoneW, safeZoneY + safeZoneH / 2];
                        private _t = screenToWorld [safeZoneX + safeZoneW / 2, safeZoneY];
                        private _b = screenToWorld [safeZoneX + safeZoneW / 2, safeZoneY + safeZoneH];
                        if ((_l distance2D _r) > 10 && {(_t distance2D _b) > 10}) then { _fw = _l distance2D _r; _fh = _t distance2D _b; };
                    };
                };
                private _step = round ((_fw min _fh) * (1 - _overlap)) max 10;
                ([_center, _radius, _step] call _grid) params ["_x0", "_y0", "_nx", "_ny"];
                private _n = _nx * _ny;
                ["INFO", "MAPCAP", format ["%1 h%2 : %3 x %4 tuiles, pas %5 m, emprise %6 x %7 m, origine %8 %9", _mode, _h, _nx, _ny, _step, round _fw, round _fh, round _x0, round _y0]] call comspec_atak_native_fnc_log;
                for "_iy" from 0 to (_ny - 1) do {
                    if (missionNamespace getVariable ["COMSPEC_ATAK_MapCapStop", false]) then { break };
                    for "_ix" from 0 to (_nx - 1) do {
                        if (missionNamespace getVariable ["COMSPEC_ATAK_MapCapStop", false]) then { break };
                        private _px = _x0 + (_ix + 0.5) * _step;
                        private _py = _y0 + (_iy + 0.5) * _step;
                        [_px, _py] call _place;
                        // Chargement des objets et du terrain détaillé avant la photo (plus long au premier passage).
                        uiSleep _wait;
                        screenshot format ["cmap_%1_%2_h%3_s%4_w%5_o%6-%7_x%8_y%9.png", worldName, _mode, round _h, _step, round _fw, round _x0, round _y0, _ix, _iy];
                        _shots = _shots + 1;
                        uiSleep 0.35;
                    };
                };
            } forEach _heights;
            if (!isNull _cam) then { _cam cameraEffect ["Terminate", "Back"]; camDestroy _cam; };
            if (!isNull _mapDisp) then { _mapDisp closeDisplay 1; };
            (findDisplay 46) displayRemoveEventHandler ["KeyDown", _eh];
            setViewDistance (_keep select 0);
            setObjectViewDistance (_keep select 1);
            setTerrainGrid (_keep select 2);
            missionNamespace setVariable ["COMSPEC_ATAK_MapCapRunning", false];
            ["SUCCESS", format ["Capture de la carte terminée : %1 image(s) en %2 min dans Documents\Arma 3\Screenshots (cmap_*). Assemblage : tools/map_stitch.py", _shots, round ((diag_tickTime - _t0) / 60)], 10, 40] call comspec_atak_native_fnc_notify;
        };
        true
    };
    default { false };
};
