/*
    App Guerre électronique, onglet GONIO : analyseur de spectre (30 MHz à 2,4 GHz, barres avec seuil) et boussole
    du dernier relèvement. Params : [[x, y, w, h] dans la zone de contenu]
    Le spectre montre les émissions réelles autour de moi (3 km) : téléphones (chacun sur sa fréquence, tirée de son
    identité), brouilleurs (large bande). Au-dessus du seuil, la barre passe en rouge. Rafraîchi toutes les 250 ms.
*/
params ["_rect"];
disableSerialization;
_rect params ["_x0", "_y0", "_w", "_h"];
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
private _ratio = pixelH / pixelW;
private _mk = { params ["_c", "_p", ["_t", ""]]; [_c, _p, _t] call comspec_atak_native_fnc_pageCtrl };
private _dir = "\z\comspec_atak_native\addons\main\data\";
private _bg = ["COMSPEC_RscPanel", _rect] call _mk;
_bg ctrlSetBackgroundColor [0.02, 0.03, 0.025, 0.98];
private _sw = _w * 0.6;
private _head = ["COMSPEC_RscStructuredText", [_x0 + _pad, _y0 + _pad / 2, _sw - _pad, _fs * 1.4]] call _mk;
_head ctrlSetStructuredText parseText "<t size='0.75' color='#8a9a93'>SPECTRE · 30 MHz à 2,4 GHz</t>";
private _gy = _y0 + _fs * 1.8;
private _gh = _h - _fs * 1.8 - _pad * 1.5;
private _n = 48;
private _bwid = (_sw - _pad * 2) / _n;
private _bars = [];
for "_i" from 0 to (_n - 1) do {
    private _c = ["COMSPEC_RscText", [_x0 + _pad + _i * _bwid, _gy + _gh, _bwid * 0.72, 0]] call _mk;
    _bars pushBack _c;
};
// Seuil : tirets orange à 60 %.
private _thY = _gy + _gh * 0.4;
for "_i" from 0 to 23 do {
    private _c = ["COMSPEC_RscText", [_x0 + _pad + _i * (_sw - _pad * 2) / 24, _thY, (_sw - _pad * 2) / 48, pixelH * 2]] call _mk;
    _c ctrlSetBackgroundColor [0.95, 0.67, 0.2, 0.9];
};
private _thl = ["COMSPEC_RscText", [_x0 + _sw - _pad - _fs * 3 / _ratio * 0.5, _thY - _fs * 1.1, _fs * 3 / _ratio * 0.5, _fs]] call _mk;
_thl ctrlSetText "SEUIL"; _thl ctrlSetFontHeight (_fs * 0.75); _thl ctrlSetTextColor [0.95, 0.67, 0.2, 1];
// Boussole du relèvement
private _cx = _x0 + _sw + (_w - _sw) / 2;
private _ch = (_h * 0.58) min ((_w - _sw) * 0.8 * _ratio);
private _cwid = _ch / _ratio;
private _cy = _y0 + _fs * 1.6;
private _lab = ["COMSPEC_RscStructuredText", [_x0 + _sw, _y0 + _pad / 2, _w - _sw, _fs * 1.4]] call _mk;
_lab ctrlSetStructuredText parseText "<t size='0.75' color='#8a9a93' align='center'>GONIOMÉTRIE</t>";
private _ring = ["COMSPEC_RscIcon", [_cx - _cwid / 2, _cy, _cwid, _ch], _dir + "compass_ring.paa"] call _mk;
_ring ctrlSetTextColor [0.75, 0.8, 0.77, 0.8];
private _needle = ["COMSPEC_RscIcon", [_cx - _cwid / 2, _cy, _cwid, _ch], _dir + "compass_needle.paa"] call _mk;
_needle ctrlSetTextColor [0.9, 0.28, 0.23, 1];
private _brg = ["COMSPEC_RscStructuredText", [_x0 + _sw, _cy + _ch + _pad / 2, _w - _sw, _h - (_cy - _y0) - _ch - _pad]] call _mk;
private _alarm = ["COMSPEC_RscStructuredText", [_x0 + _w * 0.3, _y0 + _pad / 2, _w * 0.7 - _pad, _fs * 1.4]] call _mk;
uiNamespace setVariable ["COMSPEC_ATAK_Spec", [_bars, [_gy, _gh], _needle, _brg, _alarm, [], 0]];
if ((uiNamespace getVariable ["COMSPEC_ATAK_SpecPfh", -1]) >= 0) exitWith {};
private _pfh = [{
    params ["", "_pfh"];
    private _e = uiNamespace getVariable ["COMSPEC_ATAK_Spec", []];
    if ((count _e) < 7 || {isNull ((_e select 0) param [0, controlNull])}) exitWith {
        [_pfh] call CBA_fnc_removePerFrameHandler;
        uiNamespace setVariable ["COMSPEC_ATAK_SpecPfh", -1];
    };
    _e params ["_bars", "_geo", "_needle", "_brgC", "_alarm", "_smooth", "_tick"];
    _geo params ["_gy", "_gh"];
    private _n = count _bars;
    private _now = [time, serverTime] select isMultiplayer;
    private _me = getPosATL player;
    private _lvl = [];
    for "_i" from 1 to _n do { _lvl pushBack (0.12 + random 0.2); };
    // Téléphones à moins de 3 km : un pic sur leur fréquence, intermittent au repos, permanent s'ils balayent.
    {
        if (_x isNotEqualTo player && {alive _x} && {(_x distance2D _me) < 3000} && {[_x] call comspec_atak_native_fnc_hasDevice}) then {
            private _loud = (_x getVariable ["COMSPEC_ATAK_EwScanUntil", -1]) > _now;
            if (_loud || {(random 1) < 0.6}) then {
                private _k = (([_x, "norm"] call comspec_atak_native_fnc_phoneIdent) select 1);
                private _bin = 0; { _bin = (_bin * 7 + _x) mod 997; } forEach (toArray _k);
                _bin = _bin mod _n;
                private _a = (1 - ((_x distance2D _me) / 3000)) * ([0.75, 1] select _loud) + 0.15;
                _lvl set [_bin, (_lvl select _bin) max _a];
                if (_bin > 0) then { _lvl set [_bin - 1, (_lvl select (_bin - 1)) max (_a * 0.6)]; };
            };
        };
    } forEach allPlayers;
    // Brouilleurs : large bande saturée.
    private _jam = false;
    {
        _x params [["_pos", [0, 0, 0]], ["_rad", 500], "", "", ["_until", 1e9]];
        if (_pos isEqualType objNull) then { _pos = getPosATL _pos; };
        private _d = _me distance2D _pos;
        if (_until >= _now && {_d < (_rad + 2000)}) then {
            _jam = true;
            private _a = 0.55 + 0.45 * (1 - ((_d / (_rad + 2000)) min 1));
            private _c = round (_n * 0.62);
            for "_i" from (_c - 4) to (_c + 4) do { _lvl set [_i, (_lvl select _i) max (_a - 0.04 * abs (_i - _c) + random 0.08)]; };
        };
    } forEach (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]);
    if ((count _smooth) isNotEqualTo _n) then { _smooth = +_lvl; };
    {
        private _v = ((_smooth select _forEachIndex) * 0.45 + (_lvl select _forEachIndex) * 0.55) min 1;
        _smooth set [_forEachIndex, _v];
        private _p = ctrlPosition _x;
        _x ctrlSetPosition [_p select 0, _gy + _gh * (1 - _v), _p select 2, _gh * _v];
        _x ctrlSetBackgroundColor ([[0.36, 0.78, 0.42, 0.95], [0.9, 0.28, 0.23, 0.95]] select (_v > 0.6));
        _x ctrlCommit 0.2;
    } forEach _bars;
    _e set [5, _smooth];
    _alarm ctrlSetStructuredText parseText (["", "<t size='0.8' color='#e5483a' align='right' font='RobotoCondensedBold'>BROUILLAGE DÉTECTÉ</t>"] select _jam);
    // Dernier relèvement
    private _b = (missionNamespace getVariable ["COMSPEC_ATAK_EwBearings", []]) param [((count (missionNamespace getVariable ["COMSPEC_ATAK_EwBearings", []])) - 1) max 0, []];
    if ((count _b) >= 5) then {
        _b params ["", "_deg", "_b0", "_b1", "_kind"];
        _needle ctrlSetAngle [_deg, 0.5, 0.5];
        _needle ctrlShow true;
        private _mhz = 30 + 2370 * ((round (_deg * 7.3)) mod 100) / 100;
        private _d3 = str ((round _deg) mod 360);
        while { (count _d3) < 3 } do { _d3 = "0" + _d3; };
        _brgC ctrlSetStructuredText parseText format ["<t align='center' size='2' font='RobotoCondensedBold' color='#e5483a'>%1°</t><br/><t align='center' size='0.75' color='#c9d4cf'>%2 · %3</t>",
            _d3, ["Émetteur hostile", "Brouilleur"] select (_kind isEqualTo "BROUILLEUR"),
            [format ["%1 MHz", round _mhz], format ["%1 GHz", (_mhz / 1000) toFixed 2]] select (_mhz >= 1000)];
    } else {
        _needle ctrlShow false;
        _brgC ctrlSetStructuredText parseText "<t align='center' size='0.8' color='#8a9a93'>Aucun relèvement<br/>BALAYER pour chercher</t>";
    };
}, 0.25] call CBA_fnc_addPerFrameHandler;
uiNamespace setVariable ["COMSPEC_ATAK_SpecPfh", _pfh];
