/*
    Règles communautaires de détection des marqueurs (libellé, symbole, rayon).
    Lignes : id \t label \t match_mode \t match_value \t radius \t confirm
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };

private _raw = ["COMSPECExtension" callExtension ["GetMarkerDetectionRules", []]] call comspec_overwatch_connect_fnc_extResult;
if (_raw isEqualTo "" || {(_raw select [0, 3]) != "OK|"}) exitWith { false };

private _payload = _raw select [3, count _raw - 3];
private _last = missionNamespace getVariable ["COMSPEC_MarkerDetectionRulesRaw", ""];
if (_payload isEqualTo _last) exitWith { true };
missionNamespace setVariable ["COMSPEC_MarkerDetectionRulesRaw", _payload, false];

private _rules = [];
{
    private _line = trim _x;
    if (_line isEqualTo "") then { continue };
    private _parts = _line splitString toString [9];
    if ((count _parts) < 5) then { continue };
    private _mode = toLower (_parts select 2);
    private _value = _parts select 3;
    if (_value isEqualTo "") then { continue };
    private _radius = parseNumber (_parts select 4);
    if (_radius < 1) then { _radius = 20; };
    private _confirm = if ((count _parts) > 5) then { (_parts select 5) isEqualTo "1" } else { true };
    private _label = _parts select 1;
    _rules pushBack [_mode, _value, _radius, _confirm, _label];
} forEach (_payload splitString toString [10]);

missionNamespace setVariable ["COMSPEC_MarkerDetectionRules", _rules, false];
true
