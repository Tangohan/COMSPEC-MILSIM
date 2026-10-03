/*
    Goniométrie : relit les émetteurs estimés par Athena (recoupement des relèvements des relais, /api/atak/sigint/zones)
    via la commande DLL Overwatch GetSigintZones. Résultat : uiNamespace COMSPEC_ATAK_Sigint =
    [[indicatif, "ellipse"|"azimuth", [x, y], rayon, nb relevés, relèvement, heure]...]
*/
if !([] call comspec_atak_native_fnc_bridge) exitWith { false };
if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith { false };
private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
private _raw = "COMSPECExtension" callExtension ["GetSigintZones", [_mapId]];
if (!isNil "comspec_overwatch_connect_fnc_extResult") then { _raw = [_raw] call comspec_overwatch_connect_fnc_extResult; };
if !(_raw isEqualType "") exitWith { false };
if ((_raw select [0, 3]) isNotEqualTo "OK|") exitWith { false };
private _out = [];
{
    private _c = _x splitString (toString [9]);
    if ((count _c) >= 5) then {
        _out pushBack [_c select 0, toLower (_c select 1), [parseNumber (_c select 2), parseNumber (_c select 3)], (parseNumber (_c select 4)) max 80, parseNumber (_c param [5, "0"]), parseNumber (_c param [6, "0"]), _c param [7, ""]];
    };
} forEach ((_raw select [3]) splitString (toString [10]));
uiNamespace setVariable ["COMSPEC_ATAK_Sigint", _out];
true
