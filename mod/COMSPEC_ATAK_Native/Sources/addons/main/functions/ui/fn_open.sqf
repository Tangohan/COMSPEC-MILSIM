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
if (_interactive) exitWith {
    private _parent = findDisplay 46;
    if (isNull _parent) then { _parent = findDisplay 12; };
    if (isNull _parent) exitWith { ["ERROR", "UI", "No parent display"] call comspec_atak_native_fnc_log; false };
    !isNull (_parent createDisplay "COMSPEC_RscDisplayATAK")
};
("COMSPEC_ATAK_Hud" call BIS_fnc_rscLayer) cutRsc ["COMSPEC_RscTitleATAK", "PLAIN", 0, false];
true
