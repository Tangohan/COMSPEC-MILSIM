/*
    Vibration du téléphone à l'arrivée d'un message : la coque tremble un instant (porté ou en main)
    et Overwatch joue son son de vibration (anti-spam intégré). Réglage profil COMSPEC_ATAK_Vibrate.
*/
if !(profileNamespace getVariable ["COMSPEC_ATAK_Vibrate", true]) exitWith { false };
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { false };
if (!isNil "comspec_overwatch_connect_fnc_playAtakVibrate") then { [0.7] call comspec_overwatch_connect_fnc_playAtakVibrate; };
private _c = _d displayCtrl 88509;
if ((uiNamespace getVariable ["COMSPEC_ATAK_Vibrating", -1]) >= 0) exitWith { true };
private _base = ctrlPosition _c;
private _h = [{
    params ["_args", "_handle"];
    _args params ["_c", "_base", "_end"];
    if (isNull _c || {diag_tickTime > _end}) exitWith {
        if (!isNull _c) then { _c ctrlSetPosition _base; _c ctrlCommit 0; };
        uiNamespace setVariable ["COMSPEC_ATAK_Vibrating", -1];
        [_handle] call CBA_fnc_removePerFrameHandler;
    };
    private _a = (random 2 - 1) * pixelW * 3;
    private _b = (random 2 - 1) * pixelH * 2;
    _c ctrlSetPosition [(_base select 0) + _a, (_base select 1) + _b, _base select 2, _base select 3];
    _c ctrlCommit 0;
}, 0.02, [_c, _base, diag_tickTime + 0.6]] call CBA_fnc_addPerFrameHandler;
uiNamespace setVariable ["COMSPEC_ATAK_Vibrating", _h];
true
