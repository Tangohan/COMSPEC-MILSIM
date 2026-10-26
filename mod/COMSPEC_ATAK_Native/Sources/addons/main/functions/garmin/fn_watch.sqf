/*
    Montre Garmin au poignet (Forerunner 570 noire), ouverte avec la touche « Montre » du jeu (O par défaut) quand le
    joueur porte la « Montre Garmin » (emplacement montre) ou l'a en objet divers : elle remplace la montre d'Arma.
    Sans montre Garmin, la touche garde son effet habituel.
    Touche Montre : appui court = allumer, puis écran suivant (après le dernier, la montre s'éteint) ; appui long
    (0,5 s) = éteindre. Touches CBA « Montre Garmin : écran précédent » et « éteindre » (sans touche par défaut).
    Écrans (AMOLED, comme la montre) :
      FACE     cadran : heure du jeu, date, Bluetooth (liaison au téléphone), batterie de la montre, Body Battery (arc
               bleu), objectif de pas (arc vert), fréquence cardiaque, pas, stress ;
      HR       fréquence cardiaque : valeur, zone (1 à 5), courbe des 12 dernières minutes, repos et maximum du jour ;
      ALT      altimètre-baromètre : altitude, pression, courbe des 30 dernières minutes, dénivelé positif et négatif du jour ;
      MAP      mini-carte nord en haut : ma position (GPS, mêmes erreurs que le téléphone), mon groupe, grille, échelle ;
      COMPASS  boussole : cap, point cardinal, couronne qui tourne ;
      PHONE    téléphone : liaison Bluetooth avec l'ATAK (téléphone allumé, Bluetooth allumé), batterie du téléphone,
               3 dernières notifications ;
      DAY      ma journée : pas, distance, stress, Body Battery, sommeil / repos.
    Notifications du téléphone (liaison active) : bandeau sur la montre ouverte pendant 5 s, quel que soit l'écran.
    La montre est un objet porté, comme le téléphone en miniature : elle n'apparaît que sur demande du joueur.
    Données de fn_garminTick (missionNamespace COMSPEC_ATAK_Gm). Textures : tools/gen_garmin_watch.py.
    Calque COMSPEC_ATAK_Watch (COMSPEC_RscTitleGarmin) ; état uiNamespace COMSPEC_ATAK_Watch.
    Réglages client : comspec_atak_native_watch_size (0 petite, 1 moyenne, 2 grande), comspec_atak_native_watch_side
    (0 gauche, 1 centre, 2 droite).
    Params : [action : "down" | "up" (touche Montre, renvoie true si la Garmin la prend) | "open" | "close" | "next" |
              "prev" | "tick" (PFH 0,1 s tant qu'elle est allumée)]
*/
params [["_act", "tick"]];
disableSerialization;
if (!hasInterface) exitWith { false };
private _pages = ["FACE", "HR", "ALT", "MAP", "COMPASS", "PHONE", "DAY"];
private _w = uiNamespace getVariable ["COMSPEC_ATAK_Watch", createHashMap];
if ((count _w) isEqualTo 0) then {
    _w = createHashMapFromArray [["open", false], ["page", 0], ["built", ""], ["down", -1], ["pfh", -1], ["seq", -1], ["banner", ["", 0]], ["slow", 0]];
    uiNamespace setVariable ["COMSPEC_ATAK_Watch", _w];
};
private _tex = { format ["\z\comspec_atak_native\addons\main\data\%1.paa", _this] };
private _has = {
    alive player && {lifeState player isNotEqualTo "INCAPACITATED"} && {!(player getVariable ["ACE_isUnconscious", false])}
    && {((assignedItems player) findIf { _x isKindOf ["COMSPEC_ATAK_GarminWatch", configFile >> "CfgWeapons"] }) >= 0
        || {"COMSPEC_ATAK_GarminWatchItem" in (items player)}}
};
private _close = {
    ("COMSPEC_ATAK_Watch" call BIS_fnc_rscLayer) cutText ["", "PLAIN"];
    if ((_w get "pfh") >= 0) then { [_w get "pfh"] call CBA_fnc_removePerFrameHandler; };
    _w set ["pfh", -1];
    _w set ["open", false];
    _w set ["built", ""];
};
private _open = {
    if !(call _has) exitWith {};
    ("COMSPEC_ATAK_Watch" call BIS_fnc_rscLayer) cutRsc ["COMSPEC_RscTitleGarmin", "PLAIN", 0, false];
    _w set ["open", true];
    _w set ["built", ""];
    _w set ["slow", 0];
    // Pas de bandeau pour les notifications reçues montre éteinte.
    _w set ["seq", (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["notifHistSeq", 0]];
    if ((_w get "pfh") < 0) then {
        _w set ["pfh", [{ ["tick"] call comspec_atak_native_fnc_watch; }, 0.1] call CBA_fnc_addPerFrameHandler];
    };
    ["tick"] call comspec_atak_native_fnc_watch;
};
private _turn = {
    params ["_step"];
    if !(_w get "open") exitWith { _w set ["page", 0]; call _open; };
    private _p = (_w get "page") + _step;
    if (_p >= (count _pages)) exitWith { call _close; };
    _w set ["page", _p max 0];
    _w set ["slow", 0];
    ["tick"] call comspec_atak_native_fnc_watch;
};

switch (_act) do {
    case "down": {
        if !(call _has) exitWith { false };
        if ((_w get "down") < 0) then { _w set ["down", diag_tickTime]; };
        true
    };
    case "up": {
        private _t0 = _w get "down";
        _w set ["down", -1];
        if (_t0 < 0) exitWith { false };
        if (!(call _has)) exitWith { call _close; false };
        if ((diag_tickTime - _t0) >= 0.5) then { call _close; } else { [1] call _turn; };
        true
    };
    case "open": { _w set ["page", 0]; call _open; true };
    case "close": { call _close; true };
    case "next": { [1] call _turn; true };
    case "prev": { if (_w get "open") then { [-1] call _turn; }; true };
    case "tick": {
        if !(_w get "open") exitWith { false };
        if !(call _has) exitWith { call _close; false };
        private _d = uiNamespace getVariable ["COMSPEC_ATAK_WatchDisplay", displayNull];
        if (isNull _d) exitWith { false };
        private _page = _pages select ((_w get "page") min ((count _pages) - 1));

        // --- Géométrie : montre carrée en pixels, écran rond de rayon 270/1024 de la texture ---
        private _ratio = pixelH / pixelW;
        private _size = safeZoneH * ([0.40, 0.50, 0.62] select ((missionNamespace getVariable ["comspec_atak_native_watch_size", 1]) max 0 min 2));
        private _ww = _size / _ratio;
        private _side = (missionNamespace getVariable ["comspec_atak_native_watch_side", 0]) max 0 min 2;
        private _x0 = [safeZoneX + safeZoneW * 0.02, safeZoneX + (safeZoneW - _ww) / 2, safeZoneX + safeZoneW * 0.98 - _ww] select _side;
        private _y0 = safeZoneY + safeZoneH - _size * 0.93;
        private _cx = _x0 + _ww / 2;
        private _cy = _y0 + _size / 2;
        private _ry = _size * 270 / 1024;
        private _rx = _ry / _ratio;
        private _P = { params ["_u", "_v", "_uw", "_vh"]; [_cx + _u * _rx, _cy + _v * _ry, _uw * _rx, _vh * _ry] };

        // --- Données ---
        private _gm = missionNamespace getVariable ["COMSPEC_ATAK_Gm", createHashMap];
        systemTime params ["_sy", "_smo", "_sd"];
        private _dk = format ["%1-%2-%3", _sy, [str _smo, "0" + str _smo] select (_smo < 10), [str _sd, "0" + str _sd] select (_sd < 10)];
        private _day = (_gm getOrDefault ["days", createHashMap]) getOrDefault [_dk, createHashMap];
        private _hr = round (_gm getOrDefault ["hr", 60]);
        private _bb = round (_gm getOrDefault ["bb", 75]);
        private _stress = round (_gm getOrDefault ["stress", 15]);
        private _steps = round (_day getOrDefault ["steps", 0]);
        private _goal = _gm getOrDefault ["goal", 10000];
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _phoneWhy = [] call comspec_atak_native_fnc_canUse;
        private _btOn = ((missionNamespace getVariable ["COMSPEC_ATAK_Bt", createHashMap]) getOrDefault ["on", false]);
        private _link = _phoneWhy isEqualTo "" && {_btOn};
        private _veh = vehicle player;
        private _heading = getDir _veh;
        private _alt = (getPosASL player) select 2;
        private _thou = {
            private _s = str (round _this);
            private _o = "";
            private _n = count _s;
            { _o = _o + _x; if (((_n - _forEachIndex - 1) mod 3) isEqualTo 0 && {_forEachIndex < (_n - 1)}) then { _o = _o + " "; }; } forEach (_s splitString "");
            _o
        };
        private _cWhite = [0.95, 0.96, 0.96, 1];
        private _cGrey = [0.55, 0.57, 0.58, 1];
        private _cDim = [0.22, 0.23, 0.24, 1];
        private _cBlue = [0.18, 0.58, 1, 1];
        private _cGreen = [0.24, 0.86, 0.52, 1];
        private _cRed = [1, 0.27, 0.33, 1];
        private _cOrange = [1, 0.6, 0.16, 1];
        private _zone = { params ["_v"]; private _p = _v / 190; [1, 2, 3, 4, 5] select (([0.6, 0.7, 0.8, 0.9] findIf { _p < _x }) call { [_this, 4] select (_this < 0) }) };
        private _zoneCol = [[0.55, 0.57, 0.58, 1], [0.18, 0.58, 1, 1], [0.24, 0.86, 0.52, 1], [1, 0.6, 0.16, 1], [1, 0.27, 0.33, 1]];

        // --- Construction de l'écran (au changement d'écran ou de taille) ---
        private _sig = str [_page, _size, _side, _ratio];
        private _n = _w getOrDefault ["ctrls", createHashMap];
        if ((_w get "built") isNotEqualTo _sig || {isNull (_n getOrDefault ["frame", controlNull])}) then {
            { ctrlDelete _x; } forEach (allControls _d);
            _n = createHashMap;
            _w set ["ctrls", _n];
            _w set ["built", _sig];
            private _mk = {
                params ["_key", "_cls", "_rect"];
                private _c = _d ctrlCreate [_cls, -1];
                _c ctrlSetPosition _rect;
                _c ctrlCommit 0;
                _c ctrlEnable false;
                _n set [_key, _c];
                _c
            };
            private _pic = {
                params ["_key", "_t", "_u", "_v", "_s", ["_col", [1, 1, 1, 1]]];
                private _c = [_key, "COMSPEC_RscPicture", [_u, _v, _s, _s] call _P] call _mk;
                _c ctrlSetText (_t call _tex);
                _c ctrlSetTextColor _col;
                _c
            };
            private _txt = {
                params ["_key", "_u", "_v", "_uw", "_vh", "_fh", ["_font", "COMSPEC_BarlowCBold"], ["_col", [0.95, 0.96, 0.96, 1]], ["_style", 2]];
                private _c = [_key, ["COMSPEC_RscText", "COMSPEC_RscTextRight", "COMSPEC_RscTextCenter"] select ([0, 1, 2] find _style), [_u, _v, _uw, _vh] call _P] call _mk;
                _c ctrlSetFont _font;
                _c ctrlSetFontHeight (_fh * _ry);
                _c ctrlSetTextColor _col;
                _c
            };
            private _rich = {
                params ["_key", "_u", "_v", "_uw", "_vh", "_fh"];
                private _c = [_key, "COMSPEC_RscStructuredText", [_u, _v, _uw, _vh] call _P] call _mk;
                _c ctrlSetFontHeight (_fh * _ry);
                _c
            };
            // Arc de points (jauge) : de l'angle _a0 à _a1 (degrés, 0 en haut, sens horaire), _k points.
            private _arc = {
                params ["_key", "_a0", "_a1", "_k", "_r", "_s"];
                for "_i" from 0 to (_k - 1) do {
                    private _a = _a0 + (_a1 - _a0) * _i / ((_k - 1) max 1);
                    [format ["%1%2", _key, _i], "gw_disc", (_r * sin _a) - _s / 2, (-_r * cos _a) - _s / 2, _s, _cDim] call _pic;
                };
            };
            // Fond noir de l'écran (disque), contenu, puis la montre par-dessus (l'écran de la texture est transparent).
            ["bg", "gw_disc", -1.02, -1.02, 2.04, [0.005, 0.005, 0.006, 1]] call _pic;
            switch (_page) do {
                case "FACE": {
                    ["bba", 214, 326, 15, 0.9, 0.075] call _arc;
                    ["sta", 34, 146, 15, 0.9, 0.075] call _arc;
                    ["bt", "gw_bt", -0.3, -0.84, 0.13] call _pic;
                    ["batI", "g_battery", -0.12, -0.84, 0.13, _cGrey] call _pic;
                    ["bat", 0.02, -0.84, 0.3, 0.13, 0.11, "COMSPEC_BarlowCBold", _cGrey, 0] call _txt;
                    ["date", -0.6, -0.66, 1.2, 0.16, 0.14, "COMSPEC_BarlowC", _cGrey] call _txt;
                    ["time", -0.9, -0.52, 1.8, 0.56, 0.52] call _txt;
                    {
                        _x params ["_k", "_t", "_u", "_col"];
                        [_k + "I", _t, _u + 0.11, 0.1, 0.14, _col] call _pic;
                        [_k, _u - 0.04, 0.25, 0.44, 0.18, 0.17] call _txt;
                    } forEach [["bbv", "gw_bolt", -0.66, _cBlue], ["hrv", "g_heart", -0.18, _cRed], ["stv", "g_steps", 0.3, _cGreen]];
                    ["foot", -0.6, 0.5, 1.2, 0.16, 0.12, "COMSPEC_BarlowC", _cGrey] call _txt;
                };
                case "HR": {
                    ["icon", "g_heart", -0.08, -0.84, 0.16, _cRed] call _pic;
                    ["title", -0.6, -0.68, 1.2, 0.14, 0.11, "COMSPEC_BarlowCBold", _cGrey] call _txt;
                    ["val", -0.7, -0.52, 1.1, 0.42, 0.4, "COMSPEC_BarlowCBold", _cWhite, 1] call _txt;
                    ["unit", 0.42, -0.28, 0.4, 0.16, 0.14, "COMSPEC_BarlowC", _cGrey, 0] call _txt;
                    ["zone", -0.7, -0.08, 1.4, 0.16, 0.13] call _txt;
                    for "_i" from 0 to 35 do { [format ["b%1", _i], "COMSPEC_RscText", [-0.72 + _i * 0.04, 0.1, 0.03, 0.4] call _P] call _mk; };
                    ["foot", -0.7, 0.56, 1.4, 0.16, 0.12, "COMSPEC_BarlowC", _cGrey] call _txt;
                };
                case "ALT": {
                    ["icon", "gw_alt", -0.08, -0.86, 0.16, _cGreen] call _pic;
                    ["title", -0.6, -0.7, 1.2, 0.14, 0.11, "COMSPEC_BarlowCBold", _cGrey] call _txt;
                    ["val", -0.75, -0.54, 1.15, 0.4, 0.38, "COMSPEC_BarlowCBold", _cWhite, 1] call _txt;
                    ["unit", 0.42, -0.32, 0.3, 0.16, 0.14, "COMSPEC_BarlowC", _cGrey, 0] call _txt;
                    ["baro", -0.7, -0.14, 1.4, 0.15, 0.12, "COMSPEC_BarlowC", _cGrey] call _txt;
                    for "_i" from 0 to 35 do { [format ["b%1", _i], "COMSPEC_RscText", [-0.72 + _i * 0.04, 0.04, 0.035, 0.42] call _P] call _mk; };
                    ["foot", -0.7, 0.52, 1.4, 0.16, 0.13] call _txt;
                };
                case "MAP": {
                    private _m = ["map", "COMSPEC_RscMapAtak", [-0.72, -0.72, 1.44, 1.44] call _P] call _mk;
                    _m ctrlMapAnimAdd [0, 0.035, getPosASL player];
                    ctrlMapAnimCommit _m;
                    _m ctrlAddEventHandler ["Draw", {
                        params ["_m"];
                        private _me = [] call comspec_atak_native_fnc_gpsPos;
                        {
                            if (_x isNotEqualTo player && {alive _x}) then {
                                _m drawIcon ["\z\comspec_atak_native\addons\main\data\gw_pin.paa", [0.25, 0.62, 1, 1], getPosASL _x, 14, 14, 0, "", 0];
                            };
                        } forEach (units group player);
                        _m drawIcon ["\z\comspec_atak_native\addons\main\data\gw_arrow.paa", [1, 0.42, 0.12, 1], _me, 22, 22, getDir (vehicle player), "", 0];
                    }];
                    ["north", -0.3, -0.92, 0.6, 0.14, 0.12, "COMSPEC_BarlowCBold", _cRed] call _txt;
                    ["grid", -0.6, 0.73, 1.2, 0.13, 0.11, "COMSPEC_BarlowCBold", _cWhite] call _txt;
                    ["scale", -0.4, 0.84, 0.8, 0.11, 0.09, "COMSPEC_BarlowC", _cGrey] call _txt;
                    ["nofix", -0.7, -0.08, 1.4, 0.16, 0.13, "COMSPEC_BarlowCBold", _cOrange] call _txt;
                };
                case "COMPASS": {
                    for "_i" from 0 to 11 do { [format ["l%1", _i], -0.1, -0.1, 0.2, 0.16, [0.11, 0.15] select ((_i mod 3) isEqualTo 0)] call _txt; };
                    for "_i" from 0 to 35 do {
                        if ((_i mod 3) isNotEqualTo 0) then { [format ["t%1", _i], "gw_disc", 0, 0, 0.03, _cDim] call _pic; };
                    };
                    ["idx", "gw_arrow", -0.06, -0.99, 0.12, _cRed] call _pic;
                    ["val", -0.6, -0.34, 1.2, 0.42, 0.4] call _txt;
                    ["card", -0.6, 0.08, 1.2, 0.18, 0.16, "COMSPEC_BarlowCBold", _cOrange] call _txt;
                    ["foot", -0.6, 0.3, 1.2, 0.14, 0.11, "COMSPEC_BarlowC", _cGrey] call _txt;
                };
                case "PHONE": {
                    ["icon", "gw_phone", -0.09, -0.86, 0.18] call _pic;
                    ["bt", "gw_bt", 0.1, -0.82, 0.11] call _pic;
                    ["state", -0.75, -0.64, 1.5, 0.16, 0.14] call _txt;
                    ["sub", -0.75, -0.48, 1.5, 0.14, 0.11, "COMSPEC_BarlowC", _cGrey] call _txt;
                    ["head", -0.75, -0.3, 1.5, 0.12, 0.1, "COMSPEC_BarlowCBold", _cGrey] call _txt;
                    for "_i" from 0 to 2 do {
                        [format ["sep%1", _i], "COMSPEC_RscText", [-0.7, -0.17 + _i * 0.27, 1.4, 0.008] call _P] call _mk;
                        [format ["n%1", _i], -0.72, -0.15 + _i * 0.27, 1.44, 0.26, 0.105] call _rich;
                    };
                };
                case "DAY": {
                    ["title", -0.6, -0.8, 1.2, 0.14, 0.12, "COMSPEC_BarlowCBold", _cGrey] call _txt;
                    {
                        _x params ["_k", "_t", "_col"];
                        private _v = -0.6 + _forEachIndex * 0.3;
                        [_k + "I", _t, -0.74, _v, 0.13, _col] call _pic;
                        [_k + "L", -0.56, _v - 0.01, 0.7, 0.15, 0.12, "COMSPEC_BarlowC", _cGrey, 0] call _txt;
                        [_k, -0.1, _v - 0.02, 0.84, 0.16, 0.15, "COMSPEC_BarlowCBold", _cWhite, 1] call _txt;
                        [_k + "B0", "COMSPEC_RscText", [-0.56, _v + 0.16, 1.3, 0.035] call _P] call _mk;
                        [_k + "B1", "COMSPEC_RscText", [-0.56, _v + 0.16, 0.01, 0.035] call _P] call _mk;
                    } forEach [["steps", "g_steps", _cGreen], ["dist", "g_walk", _cBlue], ["stress", "g_stress", _cOrange], ["bb", "gw_bolt", _cBlue]];
                    ["foot", -0.7, 0.62, 1.4, 0.14, 0.11, "COMSPEC_BarlowC", _cGrey] call _txt;
                };
            };
            // Bandeau des notifications du téléphone (caché sauf à l'arrivée).
            ["banBg", "COMSPEC_RscText", [-0.8, -0.62, 1.6, 0.5] call _P] call _mk;
            ["ban", -0.74, -0.58, 1.48, 0.42, 0.11] call _rich;
            ["frame", "COMSPEC_RscPicture", [_x0, _y0, _ww, _size]] call _mk;
            (_n get "frame") ctrlSetText ("gw_frame" call _tex);
            (_n get "frame") ctrlSetTextColor [1, 1, 1, 1];
            { (_n get _x) ctrlShow false; } forEach ["banBg", "ban"];
            (_n get "banBg") ctrlSetBackgroundColor [0.13, 0.14, 0.15, 0.97];
            _w set ["slow", 0];
        };
        private _g = { _n getOrDefault [_this, controlNull] };
        private _set = { params ["_k", "_t"]; (_k call _g) ctrlSetText ((_t) call comspec_atak_native_fnc_tr); };

        // --- Mises à jour rapides (0,1 s) : boussole, carte ---
        if (_page isEqualTo "COMPASS") then {
            private _cards = ["N", "3", "6", "E", "12", "15", "S", "21", "24", "O", "30", "33"];
            for "_i" from 0 to 11 do {
                private _a = _i * 30 - _heading;
                private _c = format ["l%1", _i] call _g;
                _c ctrlSetPosition ([0.8 * (sin _a) - 0.1, -0.8 * (cos _a) - 0.08, 0.2, 0.16] call _P);
                _c ctrlCommit 0;
                _c ctrlSetText ([_cards select _i, (_cards select _i) call comspec_atak_native_fnc_tr] select ((_i mod 3) isEqualTo 0));
                _c ctrlSetTextColor ([_cGrey, _cWhite] select ((_i mod 3) isEqualTo 0));
                if (_i isEqualTo 0) then { _c ctrlSetTextColor _cRed; };
            };
            for "_i" from 0 to 35 do {
                if ((_i mod 3) isNotEqualTo 0) then {
                    private _a = _i * 10 - _heading;
                    private _c = format ["t%1", _i] call _g;
                    _c ctrlSetPosition ([0.93 * (sin _a) - 0.015, -0.93 * (cos _a) - 0.015, 0.03, 0.03] call _P);
                    _c ctrlCommit 0;
                };
            };
            private _hd = (round _heading) mod 360;
            ("val" call _g) ctrlSetText format ["%1%2°", ["", "0", "00"] select (([100, 10] findIf { _hd >= _x }) call { [_this, 2] select (_this < 0) }), _hd];
            private _dirs = ["N", "NE", "E", "SE", "S", "SO", "O", "NO"];
            ["card", _dirs select ((round (_heading / 45)) mod 8)] call _set;
        };
        if (_page isEqualTo "MAP") then {
            private _m = "map" call _g;
            _m ctrlMapAnimAdd [0.1, 0.035, [] call comspec_atak_native_fnc_gpsPos];
            ctrlMapAnimCommit _m;
        };

        // --- Bandeau des notifications : nouvelle entrée de l'historique du téléphone, liaison active ---
        private _seq = _st getOrDefault ["notifHistSeq", 0];
        if (_seq isNotEqualTo (_w get "seq")) then {
            _w set ["seq", _seq];
            private _h = _st getOrDefault ["notifHistory", []];
            if (_link && {_h isNotEqualTo []}) then {
                private _last = _h select ((count _h) - 1);
                private _msg = (_last getOrDefault ["message", ""]) call comspec_atak_native_fnc_tr;
                if ((count _msg) > 90) then { _msg = (_msg select [0, 88]) + "…"; };
                _w set ["banner", [format ["<t font='COMSPEC_BarlowCBold' color='#2e94ff'>ATAK</t>  <t color='#8b9196'>%1</t><br/><t color='#f2f4f4'>%2</t>", _last getOrDefault ["time", ""], _msg], diag_tickTime + 5]];
            };
        };
        (_w get "banner") params ["_bt", "_bu"];
        private _showBan = diag_tickTime < _bu;
        if ((ctrlShown ("ban" call _g)) isNotEqualTo _showBan) then {
            { (_x call _g) ctrlShow _showBan; } forEach ["banBg", "ban"];
        };
        if (_showBan) then { ("ban" call _g) ctrlSetStructuredText parseText _bt; };

        // --- Mises à jour lentes (1 s) ---
        if (diag_tickTime < (_w get "slow")) exitWith { true };
        _w set ["slow", diag_tickTime + 1];
        switch (_page) do {
            case "FACE": {
                date params ["_y", "_mo", "_dd", "_h", "_mi"];
                private _yy = _y - ([0, 1] select (_mo < 3));
                private _wd = (_yy + floor (_yy / 4) - floor (_yy / 100) + floor (_yy / 400) + ([0, 3, 2, 5, 0, 3, 5, 1, 4, 6, 2, 4] select (_mo - 1)) + _dd) mod 7;
                ["date", format ["%1 %2", (["DIM.", "LUN.", "MAR.", "MER.", "JEU.", "VEN.", "SAM."] select _wd) call comspec_atak_native_fnc_tr, _dd]] call _set;
                ("time" call _g) ctrlSetText format ["%1:%2", _h, [str _mi, "0" + str _mi] select (_mi < 10)];
                ("bt" call _g) ctrlSetTextColor ([_cDim, _cBlue] select _link);
                ("bat" call _g) ctrlSetText format ["%1 %2", round (_gm getOrDefault ["wbat", 100]), "%"];
                ("bbv" call _g) ctrlSetText str _bb;
                ("hrv" call _g) ctrlSetText str _hr;
                ("stv" call _g) ctrlSetText (_steps call _thou);
                private _stressTxt = format [(["Stress %1 · repos", "Stress %1 · faible", "Stress %1 · moyen", "Stress %1 · élevé"] select ([0, 1, 2, 3] select (([25, 50, 75] findIf { _stress <= _x }) call { [_this, 3] select (_this < 0) }))), _stress];
                ("foot" call _g) ctrlSetText ((_stressTxt call comspec_atak_native_fnc_tr));
                for "_i" from 0 to 14 do {
                    (format ["bba%1", _i] call _g) ctrlSetTextColor ([_cDim, _cBlue] select (_i < round (_bb / 100 * 15)));
                    (format ["sta%1", _i] call _g) ctrlSetTextColor ([_cDim, _cGreen] select (_i < round (((_steps / (_goal max 1)) min 1) * 15)));
                };
            };
            case "HR": {
                ["title", "FRÉQUENCE CARDIAQUE"] call _set;
                ("val" call _g) ctrlSetText str _hr;
                ["unit", "bpm"] call _set;
                private _z = [_hr] call _zone;
                ["zone", format ["Zone %1 · %2", _z, ["Échauffement", "Échauffement", "Endurance", "Aérobie", "Seuil", "Maximum"] select _z]] call _set;
                ("zone" call _g) ctrlSetTextColor (_zoneCol select (_z - 1));
                private _l = _gm getOrDefault ["hrl", []];
                private _sl = _l select [((count _l) - 36) max 0];
                for "_i" from 0 to 35 do {
                    private _c = format ["b%1", _i] call _g;
                    private _j = _i - (36 - count _sl);
                    if (_j < 0) then { _c ctrlShow false; } else {
                        private _v = _sl select _j;
                        private _hh = 0.02 + 0.38 * (((_v - 40) / 150) max 0 min 1);
                        _c ctrlShow true;
                        _c ctrlSetPosition ([-0.72 + _i * 0.04, 0.5 - _hh, 0.03, _hh] call _P);
                        _c ctrlCommit 0;
                        _c ctrlSetBackgroundColor (_zoneCol select (([_v] call _zone) - 1));
                    };
                };
                private _rest = _day getOrDefault ["hr_rest", 999];
                ["foot", format ["Repos %1 · Max %2", [round _rest, "--"] select (_rest > 300), round (_day getOrDefault ["hr_max", _hr])]] call _set;
            };
            case "ALT": {
                ["title", "ALTIMÈTRE"] call _set;
                ("val" call _g) ctrlSetText (_alt call _thou);
                ("unit" call _g) ctrlSetText "m";
                // Baromètre : atmosphère standard, plus la météo (basse pression par temps couvert).
                private _hpa = 1013.25 * ((1 - 2.25577e-5 * _alt) ^ 5.25588) - 12 * overcast;
                ["baro", format ["Baromètre %1 hPa", _hpa call _thou]] call _set;
                private _l = _gm getOrDefault ["altl", []];
                private _sl = _l select [((count _l) - 36) max 0];
                private _lo = 1e9; private _hi = -1e9;
                { _lo = _lo min _x; _hi = _hi max _x; } forEach _sl;
                if (_hi - _lo < 20) then { _lo = _lo - 10; _hi = _lo + 30; };
                for "_i" from 0 to 35 do {
                    private _c = format ["b%1", _i] call _g;
                    private _j = _i - (36 - count _sl);
                    if (_j < 0) then { _c ctrlShow false; } else {
                        private _hh = 0.03 + 0.39 * ((((_sl select _j) - _lo) / (_hi - _lo)) max 0 min 1);
                        _c ctrlShow true;
                        _c ctrlSetPosition ([-0.72 + _i * 0.04, 0.46 - _hh, 0.035, _hh] call _P);
                        _c ctrlCommit 0;
                        _c ctrlSetBackgroundColor [0.24, 0.86, 0.52, 0.85];
                    };
                };
                ["foot", format ["D+ %1 m   D- %2 m", (_day getOrDefault ["up_m", 0]) call _thou, (_day getOrDefault ["down_m", 0]) call _thou]] call _set;
            };
            case "MAP": {
                private _fix = [] call comspec_atak_native_fnc_gpsFix;
                ("north" call _g) ctrlSetText "N";
                ["grid", format ["%1 · ±%2 m", mapGridPosition ([] call comspec_atak_native_fnc_gpsPos), round (_fix param [0, 0])]] call _set;
                ["scale", "1 carreau = 100 m"] call _set;
                private _ok = (_fix param [1, "FIX"]) in ["FIX", "NET"];
                ("nofix" call _g) ctrlShow !_ok;
                if (!_ok) then { ["nofix", "Recherche GPS…"] call _set; };
            };
            case "COMPASS": { ["foot", ["Boussole", "Boussole · véhicule"] select (_veh isNotEqualTo player)] call _set; };
            case "PHONE": {
                ("icon" call _g) ctrlSetTextColor ([_cGrey, _cGreen] select _link);
                ("bt" call _g) ctrlSetTextColor ([_cDim, _cBlue] select _btOn);
                ["state", ["Téléphone déconnecté", "ATAK connecté"] select _link] call _set;
                ("state" call _g) ctrlSetTextColor ([_cGrey, _cGreen] select _link);
                private _sub = switch (true) do {
                    case (_phoneWhy isEqualTo "item"): { "Pas de téléphone ATAK sur vous" };
                    case (_phoneWhy isEqualTo "battery"): { "Batterie du téléphone vide" };
                    case (_phoneWhy isEqualTo "broken"): { "Téléphone détruit" };
                    case (_phoneWhy isNotEqualTo ""): { "Téléphone éteint" };
                    case (!_btOn): { "Bluetooth du téléphone éteint" };
                    default { format ["Batterie du téléphone %1 %2", round (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]), "%"] };
                };
                ["sub", _sub] call _set;
                ["head", "NOTIFICATIONS"] call _set;
                private _h = (_st getOrDefault ["notifHistory", []]) select { _link };
                private _last = (_h select [((count _h) - 3) max 0]);
                reverse _last;
                for "_i" from 0 to 2 do {
                    private _c = format ["n%1", _i] call _g;
                    (format ["sep%1", _i] call _g) ctrlSetBackgroundColor [0.2, 0.21, 0.22, [0, 1] select (_i < count _last)];
                    if (_i < count _last) then {
                        private _e = _last select _i;
                        private _msg = (_e getOrDefault ["message", ""]) call comspec_atak_native_fnc_tr;
                        if ((count _msg) > 70) then { _msg = (_msg select [0, 68]) + "…"; };
                        _c ctrlSetStructuredText parseText format ["<t color='#8b9196' size='0.85'>%1</t>  <t color='#f2f4f4'>%2</t>", _e getOrDefault ["time", ""], _msg];
                    } else {
                        _c ctrlSetStructuredText parseText (if (_i isEqualTo 0) then { format ["<t align='center' color='#8b9196'>%1</t>", "Aucune notification" call comspec_atak_native_fnc_tr] } else { "" });
                    };
                };
            };
            case "DAY": {
                ["title", "MA JOURNÉE"] call _set;
                private _foot = _day getOrDefault ["foot_m", 0];
                private _rows = [
                    ["steps", "Pas", format ["%1 / %2", _steps call _thou, _goal call _thou], _steps / (_goal max 1), _cGreen],
                    ["dist", "Distance", format ["%1 km", (_foot / 1000) toFixed 1], _foot / 10000, _cBlue],
                    ["stress", "Stress", str _stress, _stress / 100, [_cGreen, _cOrange, _cRed] select (([33, 66] findIf { _stress <= _x }) call { [_this, 2] select (_this < 0) })],
                    ["bb", "Body Battery", str _bb, _bb / 100, _cBlue]
                ];
                {
                    _x params ["_k", "_lbl", "_val", "_p", "_col"];
                    [_k + "L", _lbl] call _set;
                    (_k call _g) ctrlSetText _val;
                    ((_k + "B0") call _g) ctrlSetBackgroundColor [0.16, 0.17, 0.18, 1];
                    private _b = (_k + "B1") call _g;
                    private _r = ctrlPosition ((_k + "B0") call _g);
                    _b ctrlSetPosition [_r select 0, _r select 1, ((_r select 2) * (_p max 0.01 min 1)), _r select 3];
                    _b ctrlCommit 0;
                    _b ctrlSetBackgroundColor _col;
                } forEach _rows;
                private _sl = round ((_day getOrDefault ["sleep_s", 0]) / 60);
                ["foot", format ["Sommeil / repos %1 h %2", floor (_sl / 60), [str (_sl mod 60), "0" + str (_sl mod 60)] select ((_sl mod 60) < 10)]] call _set;
            };
        };
        true
    };
    default { false };
};
