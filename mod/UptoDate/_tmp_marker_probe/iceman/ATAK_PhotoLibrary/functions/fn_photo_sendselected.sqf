private _record = call Iceman_fnc_photo_getSelectedRecord;
if (_record isEqualTo []) exitWith {
    ["PHOTOS", "Select a picture first.", 3] call cTab_fnc_addNotification;
    false
};

private _controls = call Iceman_fnc_photo_findControls;
private _recipient = _controls getOrDefault ["9431", controlNull];
if (isNull _recipient || {(lbCurSel _recipient) < 0}) exitWith {
    ["PHOTOS", "Select an ATAK recipient.", 3] call cTab_fnc_addNotification;
    false
};

private _uid = _recipient lbData (lbCurSel _recipient);
if (_uid == "") exitWith {
    ["PHOTOS", "Select an ATAK recipient.", 3] call cTab_fnc_addNotification;
    false
};

private _targetIndex = allPlayers findIf {getPlayerUID _x == _uid};
if (_targetIndex < 0) exitWith {
    ["PHOTOS", "That recipient is no longer online.", 4] call cTab_fnc_addNotification;
    call Iceman_fnc_photo_refresh;
    false
};

private _target = allPlayers # _targetIndex;
private _devices = missionNamespace getVariable ["ctab_core_leaderDevices", []];
if !([_target, _devices] call cTab_fnc_checkGear) exitWith {
    ["PHOTOS", "That player does not currently have an ATAK device.", 4] call cTab_fnc_addNotification;
    call Iceman_fnc_photo_refresh;
    false
};

private _payload = +_record;
_payload set [1, "received"];
_payload set [2, ""];
_payload set [14, profileName];

["Iceman_PhotoLibrary_receive", [_payload], _target] call CBA_fnc_targetEvent;
["PHOTOS", format ["Picture sent to %1.", name _target], 4] call cTab_fnc_addNotification;
playSound "cTab_mailSent";
true
