/*
    Rend un objet extractible au câble DL-7 (serveur).

    [_obj, _settings] call comspec_sse_fnc_dl7Apply
      _settings : HashMap ou paires — content (sse | manual | both), files (texte),
      name, theme (auto | …), banner, password, hint, attempts, onFail, crack,
      crackTime, duration, port, alarm, oneShot (voir comspec_atak_native_fnc_dataSetup)
*/
params [["_obj", objNull, [objNull]], ["_cfg", [], [[], createHashMap]]];
if (isNull _obj) exitWith { false };
if (!isServer) exitWith { [_obj, _cfg] remoteExecCall ["comspec_sse_fnc_dl7Apply", 2]; true };
if (_cfg isEqualType []) then { _cfg = createHashMapFromArray (_cfg select { _x isEqualType [] && {(count _x) == 2} }); };

private _content = toLower (_cfg getOrDefault ["content", "sse"]);
private _files = [];
private _name = _cfg getOrDefault ["name", ""];
private _theme = toLower (_cfg getOrDefault ["theme", "auto"]);
if (_content in ["sse", "both"]) then {
    ([_obj] call comspec_sse_fnc_dl7FilesFromSse) params ["_f", "_n", "_t"];
    _files append _f;
    if (_name isEqualTo "") then { _name = _n; };
    if (_theme isEqualTo "auto") then { _theme = _t; };
};
if (_content in ["manual", "both"]) then {
    _files append ([_cfg getOrDefault ["files", ""]] call comspec_atak_native_fnc_dataParseFiles);
};
if (_theme isEqualTo "auto") then { _theme = "windows"; };

private _out = [["files", _files], ["theme", _theme]];
if (_name isNotEqualTo "") then { _out pushBack ["name", _name]; };
{
    if (_x in _cfg) then { _out pushBack [_x, _cfg get _x]; };
} forEach ["kind", "accent", "wallpaper", "banner", "password", "hint", "attempts", "onFail", "crack", "crackTime", "duration", "port", "alarm", "oneShot"];
// Eden / Zeus ne fournissent pas « kind » : déduit du premier support SSE (même table que dl7Auto), sinon dataSetup
// affichait tout support généré comme « ordinateur ».
if (!("kind" in _cfg) && {_content in ["sse", "both"]}) then {
    private _devs = [_obj, "digitalDevices"] call comspec_sse_fnc_getSection;
    if (!isNil "_devs" && {_devs isEqualType []}) then {
        private _d0 = (_devs select { _x isEqualType createHashMap }) param [0, createHashMap];
        private _type = toUpper (_d0 getOrDefault ["deviceType", ""]);
        if (_type isNotEqualTo "") then {
            _out pushBack ["kind", switch (true) do {
                case (_type in ["SMARTPHONE", "PHONE"]): { "telephone" };
                case (_type in ["LAPTOP"]): { "portable" };
                case (_type in ["COMPUTER", "PC"]): { "ordinateur" };
                case (_type in ["TABLET"]): { "tablette" };
                case (_type in ["USB", "SDCARD", "MEDIA", "HARDDRIVE"]): { "usb" };
                case (_type in ["RADIO"]): { "radio" };
                default { "autre" };
            }];
        };
    };
};
_obj setVariable ["COMSPEC_SSE_Dl7Ready", true, true];
[_obj, _out] call comspec_atak_native_fnc_dataSetup
