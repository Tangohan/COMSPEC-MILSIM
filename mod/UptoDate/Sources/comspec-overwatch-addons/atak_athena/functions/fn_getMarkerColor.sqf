/*
  COMSPEC override of BCE_fnc_getMarkerColor.

  Upstream bug (Compat_cTab fn_updateInterface display path):
  - reads BCE_Marker_Color_Array before the cache is built
  - bad/nil index → empty color → lbSetPictureColor Type Any, Array expected

  Keep [] for unknown scoped-out colors (dialog filter), but never return
  nil / non-array / non-numeric RGBA when the lookup key is empty.
*/
params [["_colorFind", ""]];

if (!(_colorFind isEqualType "")) then { _colorFind = ""; };

// Display-path nil/oob index ends up here as "" — give a safe RGBA (dialog always passes configName).
if (_colorFind isEqualTo "") exitWith { [1, 0, 0, 1] };

private _cache = uiNamespace getVariable ["BCE_Marker_Color", createHashMap];

if (!(_cache isEqualType createHashMap) || {count _cache == 0}) then {
    private _cfg = "getnumber (_x >> 'scope') == 2" configClasses (configFile >> "CfgMarkerColors");

    private _markerColors = _cfg apply {
        private _color = (getArray (_x >> "color")) apply {
            if (_x isEqualType "") then {
                private _n = call compile _x;
                if (_n isEqualType 0) then { _n } else { 0 }
            } else {
                if (_x isEqualType 0) then { _x } else { 0 }
            };
        };
        if ((count _color) < 3) then { _color = [1, 0, 0, 1]; };
        if ((count _color) < 4) then { _color pushBack 1; };
        [configName _x, _color]
    };

    _cache = createHashMapFromArray _markerColors;
    uiNamespace setVariable ["BCE_Marker_Color_Array", _markerColors apply { _x select 0 }];
    uiNamespace setVariable ["BCE_Marker_Color", _cache];
};

private _color = _cache getOrDefault [_colorFind, []];
if (!(_color isEqualType [])) exitWith { [] };
if ((count _color) == 0) exitWith { [] };

// Sanitize cached entries (mods with broken CfgMarkerColors)
private _out = [];
{
    if (_x isEqualType 0) then { _out pushBack _x } else { _out pushBack 0 };
} forEach _color;
if ((count _out) < 3) exitWith { [] };
if ((count _out) < 4) then { _out pushBack 1; };
_out
