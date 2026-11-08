/*
    GPS de poignet : appareil autonome à écran LCD monochrome, sans téléphone ATAK. Se porte dans l'emplacement GPS
    (COMSPEC_ATAK_WristGps) ou dans le sac, la veste, l'uniforme (COMSPEC_ATAK_WristGpsItem).
    Tout l'écran est fait de contrôles créés ici : aucune image. Touche d'ouverture : Ctrl+Maj+G ; vue agrandie cliquable :
    Ctrl+Alt+G (Échap pour revenir au poignet). Touches CBA pour chaque bouton, sans valeur par défaut.
    Vue agrandie : Tab PAGE, Entrée ENTER, flèches DOWN et UP, G GOTO, M MARK.
    Boutons :
      PAGE   page suivante     UP / DOWN   ligne précédente ou suivante d'une liste
      MARK   enregistre ma position, sur n'importe quelle page
      GOTO   page Points ou Routes : navigue vers la ligne choisie ; ailleurs : ouvre la page Points
      ENTER  page Boussole : arrête la navigation ; page Points : efface la ligne (deux appuis) ; page Routes : comme GOTO
    Pages : POSITION (MGRS, lat/long, altitude), BOUSSOLE (cap, relèvement, distance, vitesse, temps restant vers le point
    actif), POINTS (enregistrés par carte dans le profil, 99 au plus), ROUTES (celles de l'app Routes, lues seulement quand le
    téléphone est allumé et utilisable ; une route choisie est copiée dans l'appareil et le suit ensuite sans téléphone,
    étape suivante à moins de 30 m).
    Position : réelle, avec une précision affichée qui baisse sous un toit (rayon vers le haut, 1 fois par seconde, appareil
    allumé seulement). Elle ne passe pas par fn_gpsPos : celle-ci suit l'état du téléphone. Rien ne tourne appareil éteint.
    Params : [action, argument]. Actions : "has" "toggle" "close" "big" "page" "enter" "up" "down" "goto" "mark" "tick".
    Retour des touches : true si l'appareil est porté.
*/
params [["_act", "tick"], ["_arg", []]];
disableSerialization;
if (!hasInterface) exitWith { false };
private _w = uiNamespace getVariable ["COMSPEC_ATAK_WristGps", createHashMap];
if ((count _w) isEqualTo 0) then {
    _w = createHashMapFromArray [["open", false], ["big", false], ["page", 0], ["sel", 0], ["pfh", -1], ["ctrls", createHashMap], ["msg", ["", 0]], ["del", -1]];
    uiNamespace setVariable ["COMSPEC_ATAK_WristGps", _w];
};
private _m = missionNamespace getVariable ["COMSPEC_ATAK_WristGpsM", createHashMap];
if ((count _m) isEqualTo 0) then {
    // Cible active : [nom, position, étapes restantes de la route copiée [[position, nom]...]]
    _m = createHashMapFromArray [["target", []]];
    missionNamespace setVariable ["COMSPEC_ATAK_WristGpsM", _m];
};
private _tr = { _this call comspec_atak_native_fnc_tr };
private _pages = ["POSITION", "BOUSSOLE", "POINTS", "ROUTES"];
private _has = {
    alive player && {lifeState player isNotEqualTo "INCAPACITATED"} && {!(player getVariable ["ACE_isUnconscious", false])}
    && {((assignedItems player) findIf { _x isKindOf ["COMSPEC_ATAK_WristGps", configFile >> "CfgWeapons"] }) >= 0
        || {((items player) findIf { _x isKindOf ["COMSPEC_ATAK_WristGpsItem", configFile >> "CfgWeapons"] }) >= 0}}
};
private _ptsKey = format ["COMSPEC_ATAK_WristGps_%1", toLower worldName];
private _points = {
    private _p = profileNamespace getVariable [_ptsKey, []];
    if !(_p isEqualType []) exitWith { [] };
    _p select { _x isEqualTypeParams ["", []] && {(count (_x select 1)) >= 2} }
};
private _routes = {
    // Tables des routes préparées par fn_routeAction (relues du profil au premier appel).
    ["get", ""] call comspec_atak_native_fnc_routeAction;
    (values (missionNamespace getVariable ["COMSPEC_ATAK_RteMine", createHashMap])) + (values (missionNamespace getVariable ["COMSPEC_ATAK_RteRx", createHashMap]))
};
private _linked = { ([] call comspec_atak_native_fnc_canUse) isEqualTo "" };
private _say = { _w set ["msg", [_this, diag_tickTime + 3]]; };
private _rowsOf = {
    switch (_pages select (_w get "page")) do {
        case "POINTS": { count (call _points) };
        case "ROUTES": { [0, count (call _routes)] select (call _linked) };
        default { 0 };
    }
};
private _close = {
    if ((_w get "pfh") >= 0) then { [_w get "pfh"] call CBA_fnc_removePerFrameHandler; };
    _w set ["pfh", -1];
    _w set ["open", false];
    _w set ["big", false];
    _w set ["ctrls", createHashMap];
    private _dlg = uiNamespace getVariable ["COMSPEC_ATAK_WristGpsDlg", displayNull];
    uiNamespace setVariable ["COMSPEC_ATAK_WristGpsDlg", displayNull];
    if (!isNull _dlg) then { _dlg closeDisplay 1; };
    ("COMSPEC_ATAK_WristGps" call BIS_fnc_rscLayer) cutText ["", "PLAIN"];
};
// Boîtier noir, sangle, écran, boutons. Vue agrandie : boutons cliquables ; au poignet : simples libellés (un calque de titre ne reçoit aucun clic).
private _build = {
    private _big = _w get "big";
    private _d = uiNamespace getVariable [["COMSPEC_ATAK_WristGpsDisplay", "COMSPEC_ATAK_WristGpsDlg"] select _big, displayNull];
    if (isNull _d) exitWith { false };
    private _ratio = pixelH / pixelW;
    private _h = [0.3 * safeZoneH, 0.62 * safeZoneH] select _big;
    private _w0 = _h * 1.42 / _ratio;
    private _x0 = [safeZoneX + safeZoneW - _w0 - 0.02 * safeZoneW, safeZoneX + (safeZoneW - _w0) / 2] select _big;
    private _y0 = [safeZoneY + safeZoneH - _h - 0.14 * safeZoneH, safeZoneY + (safeZoneH - _h) / 2] select _big;
    private _box = {
        params ["_cls", "_r", "_col"];
        private _c = _d ctrlCreate [_cls, -1];
        _c ctrlSetPosition _r;
        _c ctrlSetBackgroundColor _col;
        _c ctrlCommit 0;
        _c
    };
    private _cs = createHashMap;
    // Sangle
    ["COMSPEC_RscText", [_x0 + _w0 * 0.3, _y0 - _h * 0.13, _w0 * 0.4, _h * 1.26], [0.09, 0.09, 0.08, 1]] call _box;
    // Boîtier
    ["COMSPEC_RscText", [_x0, _y0, _w0, _h], [0.05, 0.05, 0.05, 1]] call _box;
    ["COMSPEC_RscText", [_x0 + pixelW * 2, _y0 + pixelH * 2, _w0 - pixelW * 4, _h - pixelH * 4], [0.11, 0.11, 0.11, 1]] call _box;
    // Écran
    private _sx = _x0 + _w0 * 0.05;
    private _sy = _y0 + _h * 0.05;
    private _sw = _w0 * 0.9;
    private _sh = _h * 0.72;
    _cs set ["lcd", ["COMSPEC_RscText", [_sx, _sy, _sw, _sh], [0.63, 0.67, 0.56, 1]] call _box];
    private _rows = 8;
    private _rh = _sh / _rows;
    private _fh = _rh * 0.82;
    private _texts = [];
    for "_i" from 0 to (_rows - 1) do {
        private _t = _d ctrlCreate ["COMSPEC_RscText", -1];
        _t ctrlSetPosition [_sx + _sw * 0.015, _sy + _i * _rh, _sw * 0.97, _rh];
        _t ctrlSetFont "EtelkaMonospacePro";
        _t ctrlSetFontHeight _fh;
        _t ctrlSetTextColor [0.05, 0.07, 0.04, 1];
        _t ctrlSetBackgroundColor [0, 0, 0, 0];
        _t ctrlCommit 0;
        _texts pushBack _t;
    };
    _cs set ["rows", _texts];
    // Boutons sous l'écran
    private _btns = [["PAGE", "page"], ["ENTER", "enter"], ["GOTO", "goto"], ["MARK", "mark"], ["DOWN", "down"], ["UP", "up"]];
    private _bw = _w0 * 0.9 / (count _btns);
    private _by = _y0 + _h * 0.8;
    private _bh = _h * 0.15;
    {
        _x params ["_lbl", "_action"];
        private _r = [_sx + _forEachIndex * _bw + _bw * 0.04, _by, _bw * 0.92, _bh];
        private _b = _d ctrlCreate [["COMSPEC_RscTextCenter", "COMSPEC_RscButton"] select _big, -1];
        _b ctrlSetPosition _r;
        _b ctrlSetText _lbl; // i18n:brut (inscription du boîtier)
        _b ctrlSetFontHeight (_bh * 0.5);
        _b ctrlSetBackgroundColor [0.17, 0.17, 0.17, 1];
        _b ctrlSetTextColor [0.82, 0.82, 0.78, 1];
        _b ctrlCommit 0;
        if (_big) then {
            _b ctrlAddEventHandler ["ButtonClick", compile format ["[{ [%1] call comspec_atak_native_fnc_wristGps; }] call comspec_atak_native_fnc_nextFrame;", str _action]];
        };
    } forEach _btns;
    _w set ["ctrls", _cs];
    true
};
private _surface = {
    private _dlg = uiNamespace getVariable ["COMSPEC_ATAK_WristGpsDlg", displayNull];
    uiNamespace setVariable ["COMSPEC_ATAK_WristGpsDlg", displayNull];
    if (!isNull _dlg) then { _dlg closeDisplay 1; };
    ("COMSPEC_ATAK_WristGps" call BIS_fnc_rscLayer) cutText ["", "PLAIN"];
    uiNamespace setVariable ["COMSPEC_ATAK_WristGpsDisplay", displayNull];
    if (_w get "big") then {
        private _parent = findDisplay 46;
        if (isNull _parent) exitWith { _w set ["big", false]; };
        private _d = _parent createDisplay "RscDisplayEmpty";
        uiNamespace setVariable ["COMSPEC_ATAK_WristGpsDlg", _d];
        _d displayAddEventHandler ["Unload", {
            uiNamespace setVariable ["COMSPEC_ATAK_WristGpsDlg", displayNull];
            private _ww = uiNamespace getVariable ["COMSPEC_ATAK_WristGps", createHashMap];
            if (_ww getOrDefault ["open", false] && {_ww getOrDefault ["big", false]}) then {
                [{ ["big"] call comspec_atak_native_fnc_wristGps; }] call comspec_atak_native_fnc_nextFrame;
            };
        }];
        _d displayAddEventHandler ["KeyDown", {
            params ["", "_key"];
            private _action = switch (_key) do {
                case 0x0F: { "page" };
                case 0x1C: { "enter" };
                case 0xD0: { "down" };
                case 0xC8: { "up" };
                case 0x22: { "goto" };
                case 0x32: { "mark" };
                default { "" };
            };
            if (_action isEqualTo "") exitWith { false };
            [{ [_this] call comspec_atak_native_fnc_wristGps; }, _action] call comspec_atak_native_fnc_nextFrame;
            true
        }];
    };
    if !(_w get "big") then {
        ("COMSPEC_ATAK_WristGps" call BIS_fnc_rscLayer) cutRsc ["COMSPEC_RscTitleWristGps", "PLAIN", 0, false];
    };
};
private _open = {
    call _surface;
    _w set ["open", true];
    _w set ["ctrls", createHashMap];
    if ((_w get "pfh") < 0) then {
        _w set ["pfh", [{ ["tick"] call comspec_atak_native_fnc_wristGps; }, 1] call CBA_fnc_addPerFrameHandler];
    };
    ["tick"] call comspec_atak_native_fnc_wristGps;
};
private _setTarget = {
    params ["_name", "_pos", ["_next", []]];
    _m set ["target", [_name, _pos, _next]];
};
private _gotoSel = {
    private _page = _pages select (_w get "page");
    if (_page isEqualTo "POINTS") exitWith {
        private _p = (call _points) param [_w get "sel", []];
        if (_p isEqualTo []) exitWith { ("AUCUN POINT" call _tr) call _say; false };
        [_p select 0, _p select 1] call _setTarget;
        _w set ["page", 1];
        true
    };
    if (_page isEqualTo "ROUTES") exitWith {
        if !(call _linked) exitWith { ("ATAK NON RELIÉ" call _tr) call _say; false };
        private _r = (call _routes) param [_w get "sel", createHashMap];
        private _steps = (_r getOrDefault ["steps", []]) apply { [_x param [0, []], _x param [2, ""]] };
        _steps = _steps select { (count (_x select 0)) >= 2 };
        if (_steps isEqualTo []) exitWith { ("ROUTE VIDE" call _tr) call _say; false };
        (_steps deleteAt 0) params ["_pos", "_name"];
        [_name, _pos, _steps] call _setTarget;
        _w set ["page", 1];
        true
    };
    _w set ["page", 2];
    _w set ["sel", 0];
    true
};
if (_act isEqualTo "has") exitWith { call _has };
if (_act isNotEqualTo "tick") then {
    // Une action arme ou désarme l'effacement d'une ligne : seul un second ENTER le confirme.
    if (_act isNotEqualTo "enter") then { _w set ["del", -1]; };
};
switch (_act) do {
    case "toggle": {
        if !(call _has) exitWith { false };
        if (_w get "open") then { call _close; } else { _w set ["big", false]; call _open; };
        true
    };
    case "close": { call _close; true };
    case "big": {
        if !(call _has) exitWith { false };
        _w set ["big", !(_w get "big")];
        call _open;
        true
    };
    case "page": {
        if !(call _has) exitWith { false };
        _w set ["page", ((_w get "page") + 1) mod (count _pages)];
        _w set ["sel", 0];
        ["tick"] call comspec_atak_native_fnc_wristGps;
        true
    };
    case "up";
    case "down": {
        if !(call _has) exitWith { false };
        private _n = call _rowsOf;
        if (_n > 0) then { _w set ["sel", ((_w get "sel") + ([-1, 1] select (_act isEqualTo "down")) + _n) mod _n]; };
        ["tick"] call comspec_atak_native_fnc_wristGps;
        true
    };
    case "mark": {
        if !(call _has) exitWith { false };
        private _p = call _points;
        if ((count _p) >= 99) exitWith { ("MÉMOIRE PLEINE" call _tr) call _say; ["tick"] call comspec_atak_native_fnc_wristGps; true };
        private _seq = (profileNamespace getVariable [_ptsKey + "_n", 0]) + 1;
        profileNamespace setVariable [_ptsKey + "_n", _seq];
        private _name = format ["PT %1", [_seq, 3] call CBA_fnc_formatNumber];
        _p pushBack [_name, getPosASL (vehicle player)];
        profileNamespace setVariable [_ptsKey, _p];
        [] call comspec_atak_native_fnc_saveProfile;
        (format [("MARQUÉ : %1") call _tr, _name]) call _say;
        ["tick"] call comspec_atak_native_fnc_wristGps;
        true
    };
    case "goto": {
        if !(call _has) exitWith { false };
        call _gotoSel;
        ["tick"] call comspec_atak_native_fnc_wristGps;
        true
    };
    case "enter": {
        if !(call _has) exitWith { false };
        switch (_pages select (_w get "page")) do {
            case "BOUSSOLE": {
                if ((_m get "target") isNotEqualTo []) then { _m set ["target", []]; ("NAVIGATION ARRÊTÉE" call _tr) call _say; };
            };
            case "POINTS": {
                private _p = call _points;
                private _i = _w get "sel";
                if (_i >= (count _p)) exitWith {};
                if ((_w get "del") isNotEqualTo _i) exitWith { _w set ["del", _i]; ("ENTER : EFFACER ?" call _tr) call _say; };
                _p deleteAt _i;
                profileNamespace setVariable [_ptsKey, _p];
                [] call comspec_atak_native_fnc_saveProfile;
                _w set ["del", -1];
                _w set ["sel", (_i min ((count _p) - 1)) max 0];
            };
            case "ROUTES": { call _gotoSel; };
        };
        ["tick"] call comspec_atak_native_fnc_wristGps;
        true
    };
    case "tick": {
        if !(_w get "open") exitWith {};
        if !(call _has) exitWith { call _close; };
        // Écran construit ici : le calque de titre peut n'exister qu'à l'image suivante de cutRsc.
        if ((((_w get "ctrls") getOrDefault ["rows", []]) findIf { isNull _x }) >= 0 || {((_w get "ctrls") getOrDefault ["rows", []]) isEqualTo []}) then { call _build; };
        private _cs = _w get "ctrls";
        private _rowsC = _cs getOrDefault ["rows", []];
        if (_rowsC isEqualTo []) exitWith {};
        private _veh = vehicle player;
        private _me = getPosASL _veh;
        // Rétroéclairage la nuit.
        private _lcdCol = [[0.63, 0.67, 0.56, 1], [0.27, 0.46, 0.3, 1]] select (sunOrMoon < 0.5 && {((getLightingAt player) param [1, 0]) < 40});
        (_cs get "lcd") ctrlSetBackgroundColor _lcdCol;
        // Étape suivante d'une route copiée.
        private _tg = _m get "target";
        _tg params [["_tName", ""], ["_tPos", []], ["_tNext", []]];
        if (_tPos isNotEqualTo [] && {_tNext isNotEqualTo []} && {(_me distance2D _tPos) < 30}) then {
            (_tNext deleteAt 0) params ["_nPos", "_nName"];
            _tName = _nName;
            _tPos = _nPos;
            [_tName, _tPos, _tNext] call _setTarget;
            (format [("ÉTAPE : %1") call _tr, _tName]) call _say;
        };
        // Ciel couvert : précision dégradée, jamais de décalage de position.
        private _roof = (lineIntersectsSurfaces [_me vectorAdd [0, 0, 1.7], _me vectorAdd [0, 0, 40], player, _veh, true, 1]) isNotEqualTo [];
        private _fix = ["±4 m", "±18 m"] select _roof;
        private _sats = [["%1 SAT", 10], ["%1 SAT", 4]] select _roof;
        private _head = (getDirVisual _veh + 360) mod 360;
        private _cardinal = ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_head / 45)) mod 8);
        private _spd = abs (speed _veh);
        private _fmtDist = { params ["_d"]; if (_d < 1000) exitWith { format ["%1 m", round _d] }; format ["%1 km", (_d / 1000) toFixed 2] };
        private _lines = [];
        private _title = _pages select (_w get "page");
        private _sel = _w get "sel";
        // Fenêtre de 6 lignes autour de la ligne choisie.
        private _listLines = {
            params ["_labels"];
            private _first = ((_sel - 2) max 0) min (((count _labels) - 5) max 0);
            private _out = [];
            for "_i" from _first to ((_first + 4) min ((count _labels) - 1)) do {
                _out pushBack format ["%1 %2", [" ", ">"] select (_i isEqualTo _sel), _labels select _i];
            };
            _out
        };
        switch (_title) do {
            case "POSITION": {
                private _zone = uiNamespace getVariable ["COMSPEC_ATAK_MgrsZone", ["", ""]];
                if ((_zone select 0) isNotEqualTo worldName) then {
                    private _z = "";
                    if (!isNil "ace_common_fnc_getMGRSdata") then {
                        private _r = [] call ace_common_fnc_getMGRSdata;
                        if (_r isEqualType [] && {(count _r) >= 2} && {(_r select 0) isEqualType ""} && {(_r select 1) isEqualType ""}) then { _z = format ["%1 %2", _r select 0, _r select 1]; };
                    };
                    if (_z isEqualTo "") then {
                        private _cfg = configFile >> "CfgWorlds" >> worldName;
                        private _lat0 = ((-(getNumber (_cfg >> "latitude"))) max -80) min 83.9;
                        private _lon0 = getNumber (_cfg >> "longitude");
                        _z = format ["%1%2", ((floor ((_lon0 + 180) / 6)) mod 60) + 1, "CDEFGHJKLMNPQRSTUVWX" select [floor ((_lat0 + 80) / 8), 1]];
                    };
                    _zone = [worldName, _z];
                    uiNamespace setVariable ["COMSPEC_ATAK_MgrsZone", _zone];
                };
                // Lat/long : centre de la carte à la latitude et longitude de CfgWorlds (latitude comptée au sud).
                private _cfg = configFile >> "CfgWorlds" >> worldName;
                private _lat = (-(getNumber (_cfg >> "latitude"))) + ((_me select 1) - worldSize / 2) / 110574;
                private _lon = (getNumber (_cfg >> "longitude")) + ((_me select 0) - worldSize / 2) / (111320 * ((cos _lat) max 0.1));
                private _dm = {
                    params ["_v", "_pos", "_neg"];
                    private _a = abs _v;
                    private _deg = floor _a;
                    format ["%1 %2° %3'", [_neg, _pos] select (_v >= 0), _deg, ((_a - _deg) * 60) toFixed 3]
                };
                _lines = [
                    "MGRS",
                    format ["%1 %2", _zone select 1, [_me, 8] call comspec_atak_native_fnc_gridRef],
                    "LAT/LONG",
                    [_lat, "N", "S"] call _dm,
                    [_lon, "E", "O"] call _dm,
                    format [("ALT %1 m  %2") call _tr, round (_me select 2), _fix],
                    format [_sats select 0, _sats select 1]
                ];
            };
            case "BOUSSOLE": {
                // Bande de cap : 17 cases de 5°, repères tous les 15°, points cardinaux, relèvement de la cible.
                private _brg = if (_tPos isEqualTo []) then { -1 } else { (_me getDir _tPos) };
                private _tape = "";
                private _mark = "";
                for "_i" from -8 to 8 do {
                    private _deg = ((round ((_head + _i * 5) / 5)) * 5 + 360) mod 360;
                    _tape = _tape + (switch (true) do {
                        case (_deg isEqualTo 0): { "N" };
                        case (_deg isEqualTo 90): { "E" };
                        case (_deg isEqualTo 180): { "S" };
                        case (_deg isEqualTo 270): { "O" };
                        case ((_deg mod 15) isEqualTo 0): { "|" };
                        default { "." };
                    });
                    private _off = if (_brg < 0) then { 999 } else { abs ((((_deg - _brg) + 540) mod 360) - 180) };
                    _mark = _mark + (["_", "v"] select (_off <= 2.5));
                };
                private _centre = "________^________";
                if (_tPos isEqualTo []) then {
                    _lines = [_mark, _tape, _centre, format [("CAP %1° %2") call _tr, round _head, _cardinal], format [("VIT %1 km/h") call _tr, round _spd], "", ("AUCUN POINT ACTIF" call _tr)];
                } else {
                    private _dist = _me distance2D _tPos;
                    private _eta = if (_spd > 1) then { private _s = round (_dist / (_spd / 3.6)); format ["%1:%2", floor (_s / 60), [str (_s mod 60), "0" + str (_s mod 60)] select ((_s mod 60) < 10)] } else { "--:--" };
                    _lines = [
                        _mark, _tape, _centre,
                        format [("VERS %1 %2") call _tr, _tName, ["", format ["(+%1)", count _tNext]] select (_tNext isNotEqualTo [])],
                        format [("CAP %1° REL %2°") call _tr, round _head, round _brg],
                        format [("DIST %1") call _tr, [_dist] call _fmtDist],
                        format [("VIT %1 km/h  %2") call _tr, round _spd, _eta]
                    ];
                };
            };
            case "POINTS": {
                private _p = call _points;
                _sel = (_sel max 0) min (((count _p) - 1) max 0);
                _w set ["sel", _sel];
                _lines = if (_p isEqualTo []) then { [("AUCUN POINT" call _tr), ("MARK : ENREGISTRER" call _tr)] } else {
                    ([_p apply { format ["%1 %2", _x select 0, [(_me distance2D (_x select 1))] call _fmtDist] }] call _listLines)
                };
                _lines pushBack ("GOTO : NAVIGUER" call _tr);
            };
            case "ROUTES": {
                if !(call _linked) then {
                    _lines = [("ATAK NON RELIÉ" call _tr), ("TÉLÉPHONE ÉTEINT OU ABSENT" call _tr)];
                    if (_tNext isNotEqualTo []) then { _lines pushBack format [("ROUTE EN COURS : %1 ÉTAPE(S)") call _tr, (count _tNext) + 1]; };
                } else {
                    private _rts = call _routes;
                    _sel = (_sel max 0) min (((count _rts) - 1) max 0);
                    _w set ["sel", _sel];
                    _lines = if (_rts isEqualTo []) then { [("AUCUNE ROUTE" call _tr), ("APP ROUTES DU TÉLÉPHONE" call _tr)] } else {
                        ([_rts apply { format ["%1 (%2)", (_x getOrDefault ["name", "?"]) select [0, 18], count (_x getOrDefault ["steps", []])] }] call _listLines)
                    };
                    _lines pushBack ("GOTO : SUIVRE" call _tr);
                };
            };
        };
        (_w get "msg") params ["_mTxt", "_mEnd"];
        if (diag_tickTime < _mEnd) then { _lines = (_lines select [0, 6]); while { (count _lines) < 6 } do { _lines pushBack ""; }; _lines pushBack _mTxt; };
        private _top = _rowsC select 0;
        _top ctrlSetText format ["%1  %2/%3  %4", _title call _tr, (_w get "page") + 1, count _pages, [dayTime, "HH:MM"] call BIS_fnc_timeToString]; // i18n:brut (titre déjà traduit)
        _top ctrlSetBackgroundColor [0.05, 0.07, 0.04, 1];
        _top ctrlSetTextColor _lcdCol;
        {
            if (_forEachIndex > 0) then { _x ctrlSetText (_lines param [_forEachIndex - 1, ""]); }; // i18n:brut (lignes déjà traduites)
        } forEach _rowsC;
    };
    default { false };
};
