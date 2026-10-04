/*
    Ouvre le terminal.
    _interactive = true  : en main (souris, clavier), mini ou plein écran selon le réglage.
    _interactive = false : porté (HUD), téléphone mini visible pendant que l'on joue.
*/
params [["_interactive", true]];
if (!hasInterface) exitWith { false };
disableSerialization;
if (isNil { uiNamespace getVariable "COMSPEC_ATAK_State" }) then { [] call comspec_atak_native_fnc_stateInit; };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
_s set ["suppressHudRestore", true];
[] call comspec_atak_native_fnc_close;
_s set ["suppressHudRestore", false];
// Menu d'actions d'Arma masqué tant que le téléphone est affiché (COMSPEC_ATAK_ActionBlock, XEH_postInitClient).
// Réinstallé à chaque ouverture : une mission ou un autre mod a pu le remplacer.
inGameUISetEventHandler ["PrevAction", "[false] call (missionNamespace getVariable ['COMSPEC_ATAK_ActionBlock', { false }])"];
inGameUISetEventHandler ["NextAction", "[false] call (missionNamespace getVariable ['COMSPEC_ATAK_ActionBlock', { false }])"];
inGameUISetEventHandler ["Action", "[true] call (missionNamespace getVariable ['COMSPEC_ATAK_ActionBlock', { false }])"];
if (_interactive) exitWith {
    private _parent = findDisplay 46;
    if (isNull _parent) then { _parent = findDisplay 12; };
    if (isNull _parent) exitWith { ["ERROR", "UI", "No parent display"] call comspec_atak_native_fnc_log; false };
    private _disp = _parent createDisplay "COMSPEC_RscDisplayATAK";
    // Téléphone en main : les raccourcis CBA ne passent plus. Principal, orientation et position relus ici
    // (porter, prendre en main et zoom : fn_displayLoad).
    if (!isNull _disp) then {
        _disp displayAddEventHandler ["KeyDown", {
            params ["", "_key", "_shift", "_ctrl", "_alt"];
            private _hit = {
                params ["_action", "_default"];
                private _kb = ["COMSPEC ATAK", _action] call CBA_fnc_getKeybind;
                private _bind = if (isNil "_kb") then { _default } else { _kb select 5 };
                _bind params [["_k", -1], ["_mods", [false, false, false]]];
                _k > 0 && {_k isEqualTo _key} && {_mods isEqualTo [_shift, _ctrl, _alt]}
            };
            if (["PhoneMain", [0x16, [false, false, true]]] call _hit) exitWith { [{ [] call (missionNamespace getVariable ["COMSPEC_ATAK_KeyMain", {}]); }] call CBA_fnc_execNextFrame; true };
            if (["PhoneOrient", [0x16, [false, true, true]]] call _hit) exitWith { [{ [] call comspec_atak_native_fnc_orientationToggle; }] call CBA_fnc_execNextFrame; true };
            if (["PhonePosition", [0x16, [true, false, true]]] call _hit) exitWith { [{ [] call (missionNamespace getVariable ["COMSPEC_ATAK_KeyPosition", {}]); }] call CBA_fnc_execNextFrame; true };
            false
        }];
    };
    !isNull _disp
};
("COMSPEC_ATAK_Hud" call BIS_fnc_rscLayer) cutRsc ["COMSPEC_RscTitleATAK", "PLAIN", 0, false];
true
