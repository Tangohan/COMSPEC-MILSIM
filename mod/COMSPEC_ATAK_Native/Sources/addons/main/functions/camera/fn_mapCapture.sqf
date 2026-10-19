/*
    Capture de toute la carte (ou d'une zone) en tuiles d'images, pour en faire un fond de carte « Plans » :
      "sat"  : vue satellite, objectif vertical (orthophoto), comme Plans ou Google Earth vu de dessus ;
      "3d"   : vue 3D oblique (45°, nord en haut), bâtiments et arbres en relief comme le mode 3D de Plans ;
      "plan" : carte topographique d'Arma (carroyage, routes, courbes de niveau).
    Plusieurs hauteurs à la suite (une couche par hauteur, plus la hauteur est basse plus l'image est détaillée) :
    tools/map_stitch.py --pyramid en fait un seul fond à niveaux de détail (la hauteur la plus basse sert là où elle
    existe, la plus haute ailleurs), pour le site (calque « Photo COMSPEC ») et le fond PHOTO du téléphone.
    Trois hauteurs par défaut (300, 800 et 2000 m) : trois niveaux de zoom dans le fond assemblé.
    Chaque tuile part dans le dossier Screenshots du profil Arma (Documents\Arma 3\Screenshots pour le profil par
    défaut, Documents\Arma 3 - Other Profiles\<profil>\Screenshots pour un autre profil) sous un nom qui la décrit :
      cmap_<carte>_<mode>_h<hauteur>_s<pas en m>_w<largeur couverte en m>_o<origine x>-<origine y>_x<colonne>_y<ligne>.png
    (colonne 0 à l'ouest, ligne 0 au sud)
    Vue satellite : objectif étroit (téléobjectif) monté bien plus haut que la hauteur demandée, pour une image
    presque orthogonale (les tuiles voisines se raccordent même en relief). Le cadrage réel est mesuré à l'écran
    (worldToScreen) et noté dans le journal (RPT), avec les altitudes du terrain sous chaque image :
      CMAPL|carte|mode|hauteur demandée|taille carte|altitude caméra|fov|ax|ay|u0|v0|pas|largeur|hauteur|x0|y0|nx|ny|grille|portée
      CMAPS|fichier|x|y|altitude caméra|altitudes (grille × grille, du sud-ouest, lignes vers le nord)
    map_stitch.py s'en sert pour redresser chaque image sur le relief (orthophoto vraie).
    Suivi du relevé (profil du joueur, COMSPEC_ATAK_MapCapProg) : pour chaque carte, rendu et hauteur, le pas de la
    grille et les cases déjà prises. Un relevé repris saute ces cases (et les cases entièrement en mer) ; le formulaire
    Zeus affiche le pourcentage de la carte déjà photographié par hauteur.
    Pendant la capture : vue lointaine, objets lointains et herbe au maximum, interface cachée ; en option ciel
    dégagé à midi et unités / véhicules cachés (vue locale seulement) ; tout est remis après.
    Le moteur limite la taille du dossier Screenshots (250 Mo par défaut, une image pèse 3 à 5 Mo) : relever
    maxScreenShotFolderSizeMB dans Documents\Arma 3\Arma3.cfg pour une grande carte. Lancer en solo ou dans l'éditeur.
    Params :
      ["start", [mode ("sat" | "3d" | "plan"), hauteurs (m, ex. [300, 800]), centre ([] : carte entière),
                 rayon (m, avec un centre), recouvrement (0 à 0.5, défaut 0.15), attente par tuile (s, défaut 2),
                 ciel dégagé à midi (défaut vrai), cacher unités et véhicules (défaut vrai),
                 reprendre le relevé (défaut vrai), passer la mer (défaut vrai)]]
      ["stop"]   arrête la capture en cours
      ["count", mêmes arguments que start] → [nombre de tuiles à prendre, pas en m] (estimation, sans rien capturer)
      ["progress"] → texte du suivi du relevé de la carte en cours
      ["reset"]  oublie le suivi du relevé de la carte en cours
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
    // Vue satellite : grille calée sur des multiples du pas, pour que deux captures d'une même hauteur se raccordent.
    _x0 = (floor (_x0 / _step)) * _step; _y0 = (floor (_y0 / _step)) * _step;
    private _nx = ceil ((_x1 - _x0) / _step) max 1;
    private _ny = ceil ((_y1 - _y0) / _step) max 1;
    [_x0, _y0, _nx, _ny]
};
// Case entièrement en mer (5 × 5 points sur la case agrandie du recouvrement) : rien à photographier.
private _isSea = {
    params ["_px", "_py", "_span"];
    private _land = false;
    for "_j" from -2 to 2 do {
        for "_i" from -2 to 2 do {
            if (!_land && {!(surfaceIsWater [_px + _i * _span / 4, _py + _j * _span / 4])}) then { _land = true; };
        };
    };
    !_land
};
// Suivi du relevé, dans le profil du joueur : « carte|rendu|hauteur » -> [pas, cases prises, cases hors mer de la
// carte entière, date]. Une case = colonne × 10000 + ligne dans la grille calée sur les multiples du pas, la même pour
// toutes les captures d'une hauteur.
private _progAll = { createHashMapFromArray (profileNamespace getVariable ["COMSPEC_ATAK_MapCapProg", []]) };
private _progKey = { params ["_mode", "_h"]; format ["%1|%2|%3", toLower worldName, _mode, round _h] };
private _progPut = {
    params ["_key", "_rec"];
    private _all = call _progAll;
    _all set [_key, _rec];
    profileNamespace setVariable ["COMSPEC_ATAK_MapCapProg", (keys _all) apply { [_x, _all get _x] }];
};
private _landCount = {
    params ["_step"];
    ([[], 0, _step] call _grid) params ["_x0", "_y0", "_nx", "_ny"];
    private _n = 0;
    for "_iy" from 0 to (_ny - 1) do {
        for "_ix" from 0 to (_nx - 1) do {
            if !([_x0 + (_ix + 0.5) * _step, _y0 + (_iy + 0.5) * _step, _step * 1.2] call _isSea) then { _n = _n + 1; };
        };
    };
    _n max 1
};
private _shotDir = {
    format ["Documents\Arma 3\Screenshots (profil par défaut) ou Documents\Arma 3 - Other Profiles\%1\Screenshots", profileName]
};
private _progText = {
    private _all = call _progAll;
    private _names = createHashMapFromArray [["sat", "Satellite"], ["3d", "3D oblique"], ["plan", "Plan"]];
    private _rows = [];
    {
        (_x splitString "|") params ["_kw", "_km", "_kh"];
        if (_kw isEqualTo (toLower worldName)) then {
            _y params ["", "_ids", "_total", "_stamp"];
            _rows pushBack [_km, parseNumber _kh, format ["%1 %2 m : %3%4 (%5 / %6 cases hors mer), %7",
                _names getOrDefault [_km, _km], _kh, (floor (100 * (count _ids) / (_total max 1))) min 100, "%", count _ids, _total, _stamp]];
        };
    } forEach _all;
    if (_rows isEqualTo []) exitWith { format ["Aucun relevé de %1 pour l'instant.", worldName] };
    _rows sort true;
    (_rows apply { _x select 2 }) joinString endl
};
switch (_act) do {
    case "stop": {
        missionNamespace setVariable ["COMSPEC_ATAK_MapCapStop", true];
        true
    };
    case "count": {
        _arg params [["_mode", "sat"], ["_heights", [400]], ["_center", []], ["_radius", 1000], ["_overlap", 0.15], "", "", "",
            ["_resume", true], ["_skipSea", true]];
        if (_center isEqualType objNull) then { _center = getPosATL _center; };
        private _total = 0; private _steps = [];
        private _all = call _progAll;
        {
            ([_mode, _x] call _defaultFoot) params ["_fw", "_fh"];
            private _step = round ((_fw min _fh) * (1 - _overlap));
            private _rec = _all getOrDefault [[_mode, _x] call _progKey, []];
            if (_rec isNotEqualTo []) then { _step = _rec select 0; };
            private _done = createHashMapFromArray ((if (_resume && {_rec isNotEqualTo []}) then { _rec select 1 } else { [] }) apply { [_x, true] });
            ([_center, _radius, _step] call _grid) params ["_x0", "_y0", "_nx", "_ny"];
            private _gx0 = round (_x0 / _step); private _gy0 = round (_y0 / _step);
            for "_iy" from 0 to (_ny - 1) do {
                for "_ix" from 0 to (_nx - 1) do {
                    if (!((((_gx0 + _ix) * 10000) + _gy0 + _iy) in _done)
                        && {!_skipSea || {!([_x0 + (_ix + 0.5) * _step, _y0 + (_iy + 0.5) * _step, _step * 1.2] call _isSea)}}) then {
                        _total = _total + 1;
                    };
                };
            };
            _steps pushBack _step;
        } forEach _heights;
        [_total, _steps]
    };
    case "progress": { call _progText };
    case "reset": {
        private _all = call _progAll;
        { if ((_x find ((toLower worldName) + "|")) isEqualTo 0) then { _all deleteAt _x; }; } forEach (keys _all);
        profileNamespace setVariable ["COMSPEC_ATAK_MapCapProg", (keys _all) apply { [_x, _all get _x] }];
        saveProfileNamespace;
        true
    };
    case "zeus": {
        if (isNil "zen_dialog_fnc_create") exitWith { false };
        ["Capture de la carte", [
            ["COMBO", "Rendu", [["sat", "3d", "plan"], ["Satellite (vue de dessus)", "3D oblique (45°)", "Plan topographique"], 0]],
            ["EDIT", ["Hauteurs (m, séparées par des virgules)", "Une couche par hauteur, soit un niveau de zoom du fond : basse = détaillée (300 m), moyenne (800 m), haute = vue d'ensemble (2000 m)"], "300, 800, 2000"],
            ["COMBO", "Zone", [[0, 1], ["Autour de la position du module", "Carte entière"], 0]],
            ["SLIDER", "Rayon de la zone (m)", [200, 10000, 1500, 0]],
            ["SLIDER", "Attente par tuile (s)", [0.5, 6, 2, 1]],
            ["CHECKBOX", ["Ciel dégagé à midi", "Soleil haut, ni nuages, ni pluie, ni brume pendant la capture (vue locale, remis après)"], true],
            ["CHECKBOX", ["Cacher unités et véhicules", "Rien de mobile sur les images (vue locale, remis après)"], true],
            ["CHECKBOX", ["Reprendre le relevé", "Saute les cases déjà prises lors des captures précédentes de cette carte (suivi ci-dessous)"], true],
            ["CHECKBOX", ["Passer la mer", "Ne photographie pas les cases entièrement en mer"], true],
            ["CHECKBOX", ["Repartir de zéro", "Oublie le suivi de cette carte avant de commencer (les images déjà prises restent dans Screenshots)"], false],
            ["EDIT:MULTI", ["Suivi du relevé", "Part de la carte déjà photographiée, par rendu et hauteur (profil du joueur)"], call _progText]
        ], {
            params ["_values", "_pos"];
            _values params ["_mode", "_hs", "_zone", "_radius", "_wait", "_clear", "_hide", "_resume", "_skipSea", "_reset"];
            private _heights = ((_hs splitString ", ;") apply { parseNumber _x }) select { _x >= 20 };
            if (_heights isEqualTo []) then { _heights = [300, 800, 2000]; };
            if (_reset) then { ["reset"] call comspec_atak_native_fnc_mapCapture; };
            ["start", [_mode, _heights, [[], _pos] select (_zone isEqualTo 0), _radius, 0.15, _wait, _clear, _hide, _resume && !_reset, _skipSea]] call comspec_atak_native_fnc_mapCapture;
        }, {}, ASLToAGL _arg] call zen_dialog_fnc_create;
        true
    };
    case "start": {
        if (missionNamespace getVariable ["COMSPEC_ATAK_MapCapRunning", false]) exitWith {
            ["WARNING", "Capture de la carte déjà en cours (Échap pour l'arrêter)", 4, 20] call comspec_atak_native_fnc_notify;
            false
        };
        _arg params [["_mode", "sat"], ["_heights", [400]], ["_center", []], ["_radius", 1000], ["_overlap", 0.15], ["_wait", 2], ["_clear", true], ["_hide", true],
            ["_resume", true], ["_skipSea", true]];
        _mode = toLower _mode;
        if !(_mode in ["sat", "3d", "plan"]) then { _mode = "sat"; };
        if (_center isEqualType objNull) then { _center = getPosATL _center; };
        // Rien à l'écran pendant la capture : estimation annoncée avant, interface Zeus et téléphone fermés.
        (["count", [_mode, _heights, _center, _radius, _overlap, 0, true, true, _resume, _skipSea]] call comspec_atak_native_fnc_mapCapture) params ["_total"];
        if (_total isEqualTo 0) exitWith {
            ["SUCCESS", "Rien à relever : toutes les cases de cette zone sont déjà prises (ou en mer). Décocher « Reprendre le relevé » pour les refaire.", 8, 40] call comspec_atak_native_fnc_notify;
            false
        };
        ["INFO", format ["Capture de la carte (%1) : environ %2 images (%3 Go, %4 min), début dans 5 s. Échap pour arrêter.", _mode, _total, (round (_total * 0.04)) / 10, ceil (_total * (_wait + 0.6) / 60)], 6, 40] call comspec_atak_native_fnc_notify;
        ["INFO", "MAPCAP", format ["Images dans %1", call _shotDir]] call comspec_atak_native_fnc_log;
        if (!isNull (findDisplay 312)) then { (findDisplay 312) closeDisplay 2; };
        [] call comspec_atak_native_fnc_close;
        missionNamespace setVariable ["COMSPEC_ATAK_MapCapRunning", true];
        missionNamespace setVariable ["COMSPEC_ATAK_MapCapStop", false];
        [_mode, _heights, _center, _radius, (_overlap max 0) min 0.5, _wait max 0.3, _clear, _hide, _defaultFoot, _grid,
            _resume, _skipSea, _isSea, _progAll, _progKey, _progPut, _landCount, _shotDir] spawn {
            params ["_mode", "_heights", "_center", "_radius", "_overlap", "_wait", "_clear", "_hide", "_defaultFoot", "_grid",
                "_resume", "_skipSea", "_isSea", "_progAll", "_progKey", "_progPut", "_landCount", "_shotDir"];
            disableSerialization;
            uiSleep 5.5;
            private _keep = [viewDistance, getObjectViewDistance select 0, getTerrainGrid];
            setViewDistance 12000;
            setObjectViewDistance [12000, 400];
            setTerrainGrid 3.125;
            // Ciel dégagé à midi (vue locale ; en solo ou dans l'éditeur l'heure est remise à la fin).
            private _weather = [date, overcast, fog, rain];
            // _weather select 0 : la date [année, mois, jour, heure, minute] (passer _weather entier donnait un tableau
            // là où setDate attend un nombre : « Type Tableau, Nombre attendu »).
            private _noon = {
                private _d = _this;
                setDate [_d select 0, _d select 1, _d select 2, 12, 0];
                0 setOvercast 0; 0 setRain 0; 0 setFog 0;
            };
            if (_clear) then { (_weather select 0) call _noon; forceWeatherChange; };
            // Unités, morts et véhicules cachés (localement), le joueur compris.
            private _hidden = [];
            if (_hide) then {
                private _objs = allUnits + allDead + (vehicles select { _x isKindOf "AllVehicles" });
                {
                    if (!isObjectHidden _x) then { _x hideObject true; _hidden pushBack _x; };
                } forEach (_objs arrayIntersect _objs);
            };
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
                // Échap ferme cet écran sans passer par l'écran de jeu : même arrêt propre ici.
                _mapDisp displayAddEventHandler ["KeyDown", {
                    params ["", "_key"];
                    if (_key isEqualTo 1) exitWith { missionNamespace setVariable ["COMSPEC_ATAK_MapCapStop", true]; true };
                    false
                }];
            } else {
                _cam = "camera" camCreate [worldSize / 2, worldSize / 2, 500];
                _cam cameraEffect ["Internal", "Back"];
                cameraEffectEnableHUD false;
                showCinemaBorder false;
            };
            ("COMSPEC_ATAK_MapCapUI" call BIS_fnc_rscLayer) cutText ["", "PLAIN"];
            private _shots = 0;
            private _t0 = diag_tickTime;
            private _summary = [];
            private _p2 = { if (_this < 10) then { "0" + str _this } else { str _this } };
            // Grille des altitudes notées sous chaque image satellite (13 × 13 points sur 1,2 pas).
            private _gn = 13;
            {
                private _h = _x;
                if (missionNamespace getVariable ["COMSPEC_ATAK_MapCapStop", false]) then { break };
                private _c = if (_center isEqualTo []) then { [worldSize / 2, worldSize / 2] } else { _center };
                ([_mode, _h] call _defaultFoot) params ["_fw", "_fh"];
                // Satellite : téléobjectif (fov 0,12 au lieu de 0,7) monté environ 6 fois plus haut, même emprise au sol.
                // Plafond 9000 m (vue lointaine de 12 km) : au-delà, l'objectif s'ouvre un peu.
                private _alt = _h;
                private _fov = 0.7;
                if (_mode isEqualTo "sat") then {
                    _alt = (_h * (tan 20.05) / (tan 3.44)) min 9000;
                    _fov = 2 * (rad (atan ((tan 20.05) * _h / _alt)));
                };
                // Plan : échelle fixée une fois (_fw par défaut) ; l'emprise est ensuite mesurée à cette échelle. La recalculer
                // avec l'emprise mesurée changeait le zoom des tuiles sans changer le pas (tuiles décalées).
                private _planScale = (_fw / worldSize) * (safeZoneH / safeZoneW) * 1.4;
                private _place = {
                    params ["_px", "_py"];
                    if (_mode isEqualTo "plan") then {
                        _map ctrlMapAnimAdd [0, _planScale, [_px, _py, 0]];
                        ctrlMapAnimCommit _map;
                    } else {
                        private _gz = getTerrainHeightASL [_px, _py];
                        if (_mode isEqualTo "3d") then {
                            // Objectif à 45° vers le nord : il regarde le centre de la tuile depuis le sud.
                            _cam setPosASL [_px, _py - _h, _gz + _h];
                            _cam setVectorDirAndUp [[0, 0.7071, -0.7071], [0, 0.7071, 0.7071]];
                        } else {
                            _cam setPosASL [_px, _py, _gz + _alt];
                            _cam setVectorDirAndUp [[0, 0, -1], [0, 1, 0]];
                        };
                        _cam camSetFov _fov;
                        _cam camCommit 0;
                    };
                };
                [_c select 0, _c select 1] call _place;
                if (!isNull _cam) then { preloadCamera (getPosATL _cam); };
                uiSleep (_wait max 1);
                // Cadrage satellite mesuré : position à l'écran (0 à 1) de points du sol autour du centre.
                private _calib = [];
                if (_mode isEqualTo "plan") then {
                    private _l = _map ctrlMapScreenToWorld [safeZoneX, safeZoneY + safeZoneH / 2];
                    private _r = _map ctrlMapScreenToWorld [safeZoneX + safeZoneW, safeZoneY + safeZoneH / 2];
                    private _t = _map ctrlMapScreenToWorld [safeZoneX + safeZoneW / 2, safeZoneY];
                    private _b = _map ctrlMapScreenToWorld [safeZoneX + safeZoneW / 2, safeZoneY + safeZoneH];
                    _fw = (_l distance2D _r) max 10; _fh = (_t distance2D _b) max 10;
                } else {
                    if (_mode isEqualTo "sat") then {
                        private _gz = getTerrainHeightASL _c;
                        private _dz = _alt;
                        private _d = _fw * 0.2;
                        private _uv = {
                            private _s = worldToScreen (ASLToAGL _this);
                            if ((count _s) < 2) exitWith { [] };
                            [((_s select 0) - safeZoneX) / safeZoneW, ((_s select 1) - safeZoneY) / safeZoneH]
                        };
                        private _s0 = [_c select 0, _c select 1, _gz] call _uv;
                        private _se = [(_c select 0) + _d, _c select 1, _gz] call _uv;
                        private _sn = [_c select 0, (_c select 1) + _d, _gz] call _uv;
                        if ((count _s0) isEqualTo 2 && {(count _se) isEqualTo 2} && {(count _sn) isEqualTo 2}) then {
                            private _ax = ((_se select 0) - (_s0 select 0)) * _dz / _d;
                            private _ay = ((_s0 select 1) - (_sn select 1)) * _dz / _d;
                            if (_ax > 0.01 && {_ay > 0.01}) then {
                                _calib = [_ax, _ay, _s0 select 0, _s0 select 1];
                                _fw = _dz / _ax; _fh = _dz / _ay;
                            };
                        };
                    };
                };
                // Pas arrondi à 5 m : deux captures d'une même hauteur (deux villes) gardent le même pas malgré le bruit de
                // la mesure, map_stitch.py les réunit en une seule couche.
                private _step = ((round ((_fw min _fh) * (1 - _overlap) / 5)) * 5) max 10;
                // Suivi : un relevé repris garde la grille déjà commencée (la mesure varie de quelques mètres d'une
                // séance à l'autre) et saute les cases déjà prises.
                private _key = [_mode, _h] call _progKey;
                private _rec = (call _progAll) getOrDefault [_key, []];
                if (_rec isNotEqualTo [] && {(_rec select 0) isNotEqualTo _step}) then {
                    if (_resume) then { _step = _rec select 0; } else { _rec = []; };
                };
                private _done = createHashMapFromArray ((if (_rec isEqualTo []) then { [] } else { _rec select 1 }) apply { [_x, true] });
                private _land = if (_rec isNotEqualTo [] && {(_rec select 2) > 0}) then { _rec select 2 } else { [_step] call _landCount };
                private _save = {
                    systemTime params ["_yy", "_mo", "_dd", "_hh", "_mi"];
                    [_key, [_step, keys _done, _land, format ["%1-%2-%3 %4:%5", _yy, _mo call _p2, _dd call _p2, _hh call _p2, _mi call _p2]]] call _progPut;
                };
                ([_center, _radius, _step] call _grid) params ["_x0", "_y0", "_nx", "_ny"];
                private _gx0 = round (_x0 / _step); private _gy0 = round (_y0 / _step);
                ["INFO", "MAPCAP", format ["%1 h%2 : %3 x %4 tuiles, pas %5 m, emprise %6 x %7 m, origine %8 %9, caméra %10 m fov %11", _mode, _h, _nx, _ny, _step, round _fw, round _fh, round _x0, round _y0, round _alt, _fov]] call comspec_atak_native_fnc_log;
                if (_calib isNotEqualTo []) then {
                    diag_log text format ["CMAPL|%1|%2|%3|%4|%5|%6|%7|%8|%9|%10|%11|%12|%13|%14|%15|%16|%17|%18|%19",
                        worldName, _mode, round _h, worldSize, _alt, _fov, _calib select 0, _calib select 1, _calib select 2, _calib select 3,
                        _step, _fw, _fh, _x0, _y0, _nx, _ny, _gn, _step * 1.2];
                };
                for "_iy" from 0 to (_ny - 1) do {
                    if (missionNamespace getVariable ["COMSPEC_ATAK_MapCapStop", false]) then { break };
                    // Lignes en aller-retour : la caméra ne saute jamais d'un bord de la carte à l'autre.
                    private _cols = [];
                    for "_ix" from 0 to (_nx - 1) do { _cols pushBack _ix; };
                    if ((_iy mod 2) isEqualTo 1) then { reverse _cols; };
                    {
                        private _ix = _x;
                        if (missionNamespace getVariable ["COMSPEC_ATAK_MapCapStop", false]) then { break };
                        private _px = _x0 + (_ix + 0.5) * _step;
                        private _py = _y0 + (_iy + 0.5) * _step;
                        private _id = (_gx0 + _ix) * 10000 + _gy0 + _iy;
                        if (_resume && {_id in _done}) then { continue };
                        if (_skipSea && {[_px, _py, _step * 1.2] call _isSea}) then { continue };
                        [_px, _py] call _place;
                        // Chargement des objets et du terrain détaillé avant la photo (plus long au premier passage).
                        if (!isNull _cam) then { preloadCamera (getPosATL _cam); };
                        uiSleep _wait;
                        private _file = format ["cmap_%1_%2_h%3_s%4_w%5_o%6-%7_x%8_y%9.png", worldName, _mode, round _h, _step, round _fw, round _x0, round _y0, _ix, _iy];
                        screenshot _file;
                        diag_log text format ["CMAPF|%1", _file];
                        _done set [_id, true];
                        if (_calib isNotEqualTo []) then {
                            private _span = _step * 1.2;
                            private _hs = [];
                            for "_j" from 0 to (_gn - 1) do {
                                for "_i" from 0 to (_gn - 1) do {
                                    _hs pushBack ((round ((getTerrainHeightASL [_px + (_i / (_gn - 1) - 0.5) * _span, _py + (_j / (_gn - 1) - 0.5) * _span]) * 10)) / 10);
                                };
                            };
                            diag_log text format ["CMAPS|%1|%2|%3|%4|%5", _file, _px, _py, (getPosASL _cam) select 2, _hs joinString ","];
                        };
                        _shots = _shots + 1;
                        if ((_shots mod 25) isEqualTo 0) then { call _save; };
                        // Soleil tenu à midi (l'heure avance pendant une longue capture).
                        if (_clear && {(_shots mod 40) isEqualTo 0}) then { (_weather select 0) call _noon; };
                        uiSleep 0.35;
                    } forEach _cols;
                };
                call _save;
                _summary pushBack format ["%1 m %2%3", round _h, (floor (100 * (count _done) / _land)) min 100, "%"];
            } forEach _heights;
            saveProfileNamespace;
            if (!isNull _cam) then { _cam cameraEffect ["Terminate", "Back"]; camDestroy _cam; };
            if (!isNull _mapDisp) then { _mapDisp closeDisplay 1; };
            (findDisplay 46) displayRemoveEventHandler ["KeyDown", _eh];
            setViewDistance (_keep select 0);
            setObjectViewDistance (_keep select 1);
            setTerrainGrid (_keep select 2);
            { if (!isNull _x) then { _x hideObject false; }; } forEach _hidden;
            if (_clear) then {
                _weather params ["_date", "_oc", "_fog", "_rain"];
                setDate _date;
                0 setOvercast _oc; 0 setFog _fog; 0 setRain _rain;
                forceWeatherChange;
            };
            missionNamespace setVariable ["COMSPEC_ATAK_MapCapRunning", false];
            ["SUCCESS", format ["Capture de la carte terminée : %1 image(s) en %2 min dans %3 (cmap_*). Assemblage : tools/map_stitch.py", _shots, round ((diag_tickTime - _t0) / 60), call _shotDir], 12, 40] call comspec_atak_native_fnc_notify;
            ["INFO", format ["Relevé de %1 (%2), part de la carte prise : %3", worldName, _mode, _summary joinString ", "], 12, 40] call comspec_atak_native_fnc_notify;
        };
        true
    };
    default { false };
};
