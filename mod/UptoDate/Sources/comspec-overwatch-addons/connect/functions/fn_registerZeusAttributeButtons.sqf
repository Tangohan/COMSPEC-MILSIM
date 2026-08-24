/*
    Enregistre l’injection des boutons SSE / ATAK / OVERWATCH sur les panneaux Zeus.
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_ZeusAttrButtonsRegistered", false]) exitWith {};

private _hook = {
    params ["_display"];
    if (isNull _display) exitWith {};
    [{
        [_this] call comspec_overwatch_connect_fnc_zeusAttributesInject;
    }, _display, 0.05] call CBA_fnc_waitAndExecute;
};

{
    [_x, "onLoad", _hook] call CBA_fnc_addDisplayHandler;
} forEach [
    "RscDisplayAttributesMan",
    "RscDisplayAttributesVehicle",
    "RscDisplayAttributesVehicleEmpty",
    "RscDisplayAttributesGroup",
    "RscDisplayAttributes"
];

private _scan = {
    if (!hasInterface) exitWith {};
    if (isNull (findDisplay 312)) exitWith {};
    private _candidates = [findDisplay 315];
    _candidates append allDisplays;
    {
        if (isNull _x) then { continue };
        if (ctrlIDD _x == 312) then { continue };
        if (!isNull (_x displayCtrl 86101)) then { continue };
        private _ok = _x displayCtrl 1;
        if (isNull _ok) then { continue };
        private _py = (ctrlPosition _ok) select 1;
        if (_py < (safezoneY + 0.62 * safezoneH)) then { continue };
        [_x] call comspec_overwatch_connect_fnc_zeusAttributesInject;
    } forEach _candidates;
};
missionNamespace setVariable ["COMSPEC_ZeusAttrScan", _scan];

[{
    [] call (missionNamespace getVariable ["COMSPEC_ZeusAttrScan", {}]);
}, 0.4, []] call CBA_fnc_addPerFrameHandler;

missionNamespace setVariable ["COMSPEC_ZeusAttrButtonsRegistered", true];
["INFO", "Zeus", "Boutons SSE / ATAK / OVERWATCH du panneau Éditer enregistrés"] call comspec_overwatch_connect_fnc_log;
