/*
    Ouvre une app du tiroir ATAK Enhanced (BCE) par son nom de classe (ex. Athena, AtakStatus).

    IMPORTANT — ne pas appeler ChangeTool avec [nil, page, line] :
    sans contrôle, BCE traite page/line comme sous-menu (pas comme app),
    ce qui laisse _currentMenu indéfini dans ATAK_openMenu.
*/
params [["_page", "Athena", [""]]];

if (!hasInterface) exitWith { false };

private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _display) exitWith { false };

if (!isNil "BCE_fnc_ATAK_getAPPs") then {
    [true, true] call BCE_fnc_ATAK_getAPPs;
};

private _mode = ["cTab_Android_dlg", "mode"] call cTab_fnc_getSettings;
if (!(_mode isEqualTo "BFT")) then {
    ["cTab_Android_dlg", [["mode", "BFT"]], false, false] call cTab_fnc_setSettings;
};

private _ctrl = controlNull;
private _appsGroup = _display displayCtrl (17000 + 4660);
if (!isNull _appsGroup) then {
    {
        if ((ctrlClassName _x) isEqualTo _page) exitWith { _ctrl = _x; };
    } forEach (allControls _appsGroup);
};

if (!isNull _ctrl && {!isNil "BCE_fnc_ATAK_ChangeTool"}) exitWith {
    [_ctrl] call BCE_fnc_ATAK_ChangeTool;
    true
};

["cTab_Android_dlg", [["showMenu", [_page, true, ["", -1], createHashMap]]], true, true] call cTab_fnc_setSettings;
true
