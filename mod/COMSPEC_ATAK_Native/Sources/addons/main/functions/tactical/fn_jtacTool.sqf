/*
    Outil carte « JTAC : IP → cible » (menu OUTILS de la carte) : clic sur l'IP puis sur la cible. Calcule ce que le
    JTAC lit dans son 9-LINE : cap magnétique et vrai de l'IP vers la cible (fn_magBearing), cap retour, distance en
    mètres et en milles nautiques, altitude de la cible (ft MSL), grilles, et l'ami le plus proche de la cible (ligne 8).
      ["click", pos]   1er clic : IP ; 2e clic : cible ; un clic de plus repart d'un nouvel IP
      ["calc"]         -> hashmap du calcul (vide sans IP et cible)
      ["draw", carte]  trait IP → cible fléché, IP, cible, cap et distance au milieu
      ["card", rect]   fiche du résultat (VERS LE 9-LINE, EFFACER)
      ["send"]         IP et cible repris dans l'app JTAC (onglet 9-LINE), comme s'ils avaient été pointés dans l'app
      ["clear"]        efface les points
    État : COMSPEC_ATAK_State jtacTool = [IP, cible] (positions ASL).
*/
params [["_act", ""], ["_arg", []]];
disableSerialization;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _pts = _s getOrDefault ["jtacTool", []];
private _redraw = { [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
private _card8 = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SW", "W", "NW"] select ((round (_deg / 45)) mod 8) };
private _ret = true;
switch (_act) do {
    case "click": {
        if ((count _arg) < 2) exitWith {};
        private _p = [_arg select 0, _arg select 1, getTerrainHeightASL _arg];
        _pts = [[_p], _pts + [_p]] select ((count _pts) isEqualTo 1);
        _s set ["jtacTool", _pts];
        playSound "ClickSoft";
        if ((count _pts) isEqualTo 2) then {
            private _c = ["calc"] call comspec_atak_native_fnc_jtacTool;
            ["TACTICAL", format ["IP → cible : %1° M · %2 NM · %3 ft MSL", [_c get "mag", 3] call CBA_fnc_formatNumber, _c get "nm", _c get "ft"], 5, 30] call comspec_atak_native_fnc_notify;
        };
        call _redraw;
    };
    case "calc": {
        _ret = createHashMap;
        if ((count _pts) < 2) exitWith {};
        _pts params ["_ip", "_tg"];
        ([_ip, _tg] call comspec_atak_native_fnc_magBearing) params ["_tru", "_mag"];
        private _d = _ip distance2D _tg;
        private _side = side group player;
        private _fr = (allUnits select { alive _x && {side group _x isEqualTo _side} && {!((vehicle _x) isKindOf "Air")} && {(_x distance2D _tg) < 3000} }) apply { [_x distance2D _tg, _x] };
        _fr sort true;
        private _l8 = if (_fr isEqualTo []) then { "aucun à 3 km" } else { (_fr select 0) params ["_fd", "_fu"]; format ["%1 %2 m", [_tg getDir _fu] call _card8, round _fd] };
        private _locs = nearestLocations [_ip, ["NameCityCapital", "NameCity", "NameVillage", "NameLocal", "Hill"], 2500];
        private _nm = "";
        { if (_nm isEqualTo "" && {(text _x) isNotEqualTo ""}) then { _nm = toUpper (text _x); }; } forEach _locs;
        _ret = createHashMapFromArray [
            ["ip", _ip], ["tgt", _tg], ["true", _tru], ["mag", _mag], ["back", (_mag + 180) mod 360],
            ["m", round _d], ["nm", (_d / 1852) toFixed 1], ["ft", round ((_tg select 2) * 3.28084)], ["alt", round (_tg select 2)],
            ["ipGrid", [_ip, 8] call comspec_atak_native_fnc_gridRef], ["tgtGrid", [_tg, 10] call comspec_atak_native_fnc_gridRef],
            ["ipName", ["IP " + _nm, format ["IP %1", [_ip, 6] call comspec_atak_native_fnc_gridRef]] select (_nm isEqualTo "")],
            ["l8", _l8], ["decl", missionNamespace getVariable ["comspec_atak_native_mag_decl", 0]]
        ];
    };
    case "draw": {
        private _map = _arg;
        if (isNull _map || {(count _pts) isEqualTo 0}) exitWith {};
        private _ts = uiNamespace getVariable ["COMSPEC_ATAK_MapTs", 1];
        private _cy = [0.30, 0.85, 1, 1];
        private _red = [0.95, 0.30, 0.25, 1];
        private _ip = _pts select 0;
        _map drawIcon ["\A3\ui_f\data\map\markers\military\start_CA.paa", _cy, _ip, 22, 22, 0, "IP", 2, 0.032 * _ts, (COMSPEC_ATAK_FontSet select 1), "right"];
        if ((count _pts) < 2) exitWith {};
        private _c = ["calc"] call comspec_atak_native_fnc_jtacTool;
        private _tg = _pts select 1;
        _map drawArrow [_ip, _tg, _cy];
        _map drawIcon ["\A3\ui_f\data\map\markers\military\destroy_CA.paa", _red, _tg, 22, 22, 0, "TGT", 2, 0.032 * _ts, (COMSPEC_ATAK_FontSet select 1), "right"];
        private _mid = (_ip vectorAdd _tg) vectorMultiply 0.5;
        _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)", _cy, _mid, 0, 0, 0, format ["%1° M · %2 NM", [_c get "mag", 3] call CBA_fnc_formatNumber, _c get "nm"], 2, 0.032 * _ts, (COMSPEC_ATAK_FontSet select 1), "center"];
    };
    case "card": {
        if ((count _pts) < 2) exitWith {};
        _arg params ["_px", "_py", "_pw", "_ph"];
        private _c = ["calc"] call comspec_atak_native_fnc_jtacTool;
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        private _fs = _l get "fontSmall";
        private _pad = (_l get "pad") * 2;
        private _bg = ["COMSPEC_RscMapPanel", [_px, _py, _pw, _ph], "", false] call comspec_atak_native_fnc_pageCtrl;
        _bg ctrlSetBackgroundColor [0.025, 0.030, 0.027, 0.94];
        private _edge = ["COMSPEC_RscText", [_px, _py, _pw, pixelH * 2], "", false] call comspec_atak_native_fnc_pageCtrl;
        _edge ctrlSetBackgroundColor [0.30, 0.85, 1, 0.8];
        private _bh = _fs * 1.4;
        private _t = ["COMSPEC_RscStructuredText", [_px + _pad / 2, _py + _pad / 3, _pw - _pad, _ph - _bh - _pad], "", false] call comspec_atak_native_fnc_pageCtrl;
        _t ctrlSetFontHeight (_fs * 0.95);
        private _f3 = { [_this, 3] call CBA_fnc_formatNumber };
        private _tr = { _this call comspec_atak_native_fnc_tr };
        _t ctrlSetStructuredText parseText format ["<t font='COMSPEC_BarlowBold' color='#4dd9ff'>%12</t>  <t size='0.85' color='#8a9a93'>%1 · %13 %2°</t><br/><t font='EtelkaMonospacePro' size='0.92'>2  %3° M (%14 %4°) · %15 %5° M<br/>3  %6 NM (%7 m)<br/>4  %8 ft MSL (%9 m)<br/>6  %10<br/>8  %11</t>",
            _c get "ipName", _c get "decl", (_c get "mag") call _f3, (_c get "true") call _f3, (_c get "back") call _f3, _c get "nm", _c get "m",
            _c get "ft", _c get "alt", _c get "tgtGrid", (_c get "l8") call _tr, "IP → CIBLE" call _tr, "déclinaison" call _tr, "vrai" call _tr, "retour" call _tr];
        private _btns = [
            ["VERS LE 9-LINE", { ["send"] call comspec_atak_native_fnc_jtacTool; }, true],
            ["EFFACER", { ["clear"] call comspec_atak_native_fnc_jtacTool; }, false]
        ];
        private _bw = (_pw - _pad * 1.25) / 2;
        {
            _x params ["_lbl", "_code", "_main"];
            private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select _main, [_px + _pad / 2 + _forEachIndex * (_bw + _pad / 4), _py + _ph - _bh - _pad / 3, _bw, _bh], _lbl, false] call comspec_atak_native_fnc_pageCtrl;
            _b ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
            _b ctrlAddEventHandler ["ButtonClick", _code];
        } forEach _btns;
    };
    case "send": {
        if ((count _pts) < 2) exitWith {};
        private _c = ["calc"] call comspec_atak_native_fnc_jtacTool;
        private _j = uiNamespace getVariable ["COMSPEC_ATAK_Jtac", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_Jtac", _j];
        _j set ["ip", _c get "ip"];
        _j set ["ipAuto", false];
        if ((trim (_j getOrDefault ["jIp", ""])) isEqualTo "") then { _j set ["jIp", _c get "ipName"]; };
        _j set ["target", _c get "tgt"];
        _j set ["source", format ["carte · %1", [dayTime, "HH:MM"] call BIS_fnc_timeToString]];
        _j set ["tab", "BRIEF"];
        ["SELECT"] call comspec_atak_native_fnc_mapToolSet;
        [{ ["JTAC"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
        ["SUCCESS", "IP et cible repris dans l'app JTAC (9-LINE)", 3, 20] call comspec_atak_native_fnc_notify;
    };
    case "clear": {
        _s set ["jtacTool", []];
        call _redraw;
    };
};
_ret
