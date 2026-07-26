/*
    Marqueurs utilisateur cTab → Athena (SendMarker).
    cTabUserMarkerList = [ [transactionId, translatedData], … ]
    translatedData = [pos, iconPath, sizePath, dir, colorRGB, time, align]
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
if (!(["ctab_markers"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {};
if (isNil "cTabUserMarkerList") exitWith {};

private _list = cTabUserMarkerList;
if (!(_list isEqualType [])) then { _list = []; };

private _prev = missionNamespace getVariable ["COMSPEC_Athena_CtabMarkerSnap", createHashMap];
if (!(_prev isEqualType createHashMap)) then { _prev = createHashMap; };
private _next = createHashMap;

private _typeFromTexture = {
    params ["_tex"];
    private _t = toLower _tex;
    if ((_t find "o_inf") >= 0) exitWith { "o_inf" };
    if ((_t find "o_mech") >= 0) exitWith { "o_mech_inf" };
    if ((_t find "o_motor") >= 0) exitWith { "o_motor_inf" };
    if ((_t find "o_armor") >= 0) exitWith { "o_armor" };
    if ((_t find "o_air") >= 0) exitWith { "o_air" };
    if ((_t find "o_plane") >= 0) exitWith { "o_plane" };
    if ((_t find "b_hq") >= 0) exitWith { "b_hq" };
    if ((_t find "hospital") >= 0) exitWith { "loc_Hospital" };
    if ((_t find "warning") >= 0) exitWith { "mil_warning" };
    if ((_t find "circle") >= 0) exitWith { "mil_circle" };
    if ((_t find "join") >= 0) exitWith { "mil_join" };
    if ((_t find "end") >= 0) exitWith { "mil_end" };
    "mil_dot"
};

{
    if (!(_x isEqualType [])) then { continue };
    if ((count _x) < 2) then { continue };
    private _id = _x select 0;
    private _data = _x select 1;
    if (!(_data isEqualType []) || {(count _data) < 1}) then { continue };
    private _pos = _data select 0;
    if (!(_pos isEqualType []) || {(count _pos) < 2}) then { continue };

    private _tex = if ((count _data) > 1) then { str (_data select 1) } else { "" };
    private _type = [_tex] call _typeFromTexture;
    private _timeTxt = if ((count _data) > 5) then { str (_data select 5) } else { "" };
    private _color = "ColorRed";
    if ((_tex find "b_hq") >= 0 || {(_tex find "end_CA") >= 0}) then { _color = "ColorBLUFOR"; };
    if ((_tex find "join") >= 0 || {(_tex find "circle") >= 0} || {(_tex find "Hospital") >= 0} || {(_tex find "warning") >= 0}) then {
        _color = "ColorGreen";
    };

    private _armaName = format ["ctab_u_%1", _id];
    private _text = if (_timeTxt isEqualTo "") then { "Repère cTab" } else { format ["cTab %1", _timeTxt] };
    _text = (_text splitString """" joinString "'");
    private _sig = format ["%1|%2|%3|%4", _pos select 0, _pos select 1, _type, _text];
    _next set [_armaName, _sig];

    if ((_prev getOrDefault [_armaName, ""]) isEqualTo _sig) then { continue };

    private _json = format [
        "{""pos"":[%1,%2,0],""type"":""%3"",""text"":""%4"",""color"":""%5"",""dir"":0,""alpha"":1,""shape"":""ICON"",""size"":[1,1],""brush"":""Solid"",""polyline"":[],""source"":""ctab_user""}",
        _pos select 0,
        _pos select 1,
        _type,
        _text,
        _color
    ];
    "COMSPECExtension" callExtension ["SendMarker", [_armaName, _json, "1", "0"]];
    [format ["Marqueur cTab · %1", _text]] call comspec_overwatch_connect_fnc_appendModuleLog;
} forEach _list;

{
    if (!(_x in _next)) then {
        "COMSPECExtension" callExtension ["SendMarker", [_x, "{}", "1", "1"]];
    };
} forEach (keys _prev);

missionNamespace setVariable ["COMSPEC_Athena_CtabMarkerSnap", _next, false];
