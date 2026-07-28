/*
    Ouvre le terminal SSE — enregistrement d’une personne.
    Args optionnels: [targetUnit]
*/
params [["_target", objNull, [objNull]]];

if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if (!isNull (uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull])) exitWith {};

if (isNull _target) then {
    private _cursor = cursorObject;
    if (!isNull _cursor && { _cursor isKindOf "CAManBase" } && { _cursor != player } && { alive _cursor }) then {
        _target = _cursor;
    };
};

uiNamespace setVariable ["COMSPEC_SsePerson_Target", _target];
uiNamespace setVariable ["COMSPEC_SsePerson_BioPending", false];
uiNamespace setVariable ["COMSPEC_SsePerson_PhotoPending", false];

private _parent = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _parent) then {
    _parent = findDisplay 46;
};

private _ok = false;
private _disp = displayNull;
if (!isNull _parent) then {
    _disp = _parent createDisplay "COMSPEC_SsePerson_Dialog";
    _ok = !isNull _disp;
} else {
    _ok = createDialog "COMSPEC_SsePerson_Dialog";
    _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
};

if (!_ok || {isNull _disp}) exitWith {
    ["Impossible d’ouvrir le terminal de renseignement.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
};

uiNamespace setVariable ["COMSPEC_SsePerson_Display", _disp];
