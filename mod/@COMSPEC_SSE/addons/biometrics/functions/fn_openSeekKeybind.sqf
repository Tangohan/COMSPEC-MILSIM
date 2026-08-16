/*
    Raccourci / ouverture SEEK II via BII-10 si présent, sinon dialogue SEEK COMSPEC.
*/
if (!hasInterface) exitWith { false };

if (
    missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]
    && {!isNil "comspec_sse_fnc_biiIsPresent"}
    && {[] call comspec_sse_fnc_biiIsPresent}
    && {!isNil "comspec_sse_fnc_biiOpen"}
) exitWith {
    ["scan"] call comspec_sse_fnc_biiOpen
};

// Fallback sans BII : dialogue SEEK COMSPEC
private _target = cursorObject;
if (isNull _target) then { _target = cursorTarget; };
if (isNull _target || {!(_target isKindOf "CAManBase")}) then {
    _target = missionNamespace getVariable ["comspec_sse_seekTarget", objNull];
};
if (isNull _target) then {
    private _rec = if (!isNil "comspec_sse_fnc_uiGetRecord") then {
        [] call comspec_sse_fnc_uiGetRecord
    } else {
        objNull
    };
    if (!isNull _rec) then { _target = _rec; };
};
if (isNull _target) then { _target = player; };

[_target] call comspec_sse_fnc_openSeek
