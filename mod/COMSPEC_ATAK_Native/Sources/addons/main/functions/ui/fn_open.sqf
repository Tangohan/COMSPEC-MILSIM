if (!hasInterface) exitWith { false };
disableSerialization;
private _existing=findDisplay 88500; if (!isNull _existing) exitWith { _existing closeDisplay 2; true };
if (isNil {uiNamespace getVariable "COMSPEC_ATAK_State"}) then { [] call comspec_atak_native_fnc_stateInit; };
private _parent=findDisplay 46; if (isNull _parent) then { _parent=findDisplay 12; };
if (isNull _parent) exitWith { ["ERROR","UI","No parent display"] call comspec_atak_native_fnc_log; false };
private _display=_parent createDisplay "COMSPEC_RscDisplayATAK";
!isNull _display
