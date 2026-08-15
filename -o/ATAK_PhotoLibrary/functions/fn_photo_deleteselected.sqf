private _record = call Iceman_fnc_photo_getSelectedRecord;
if (_record isEqualTo []) exitWith {
    ["PHOTOS", "Select a picture first.", 3] call cTab_fnc_addNotification;
    false
};

private _id = _record param [0, ""];
private _confirmId = missionNamespace getVariable ["Iceman_PhotoLibrary_deleteConfirm", ""];
private _controls = call Iceman_fnc_photo_findControls;
private _button = _controls getOrDefault ["9443", controlNull];

if (_confirmId != _id) exitWith {
    missionNamespace setVariable ["Iceman_PhotoLibrary_deleteConfirm", _id];
    if (!isNull _button) then {_button ctrlSetText "Confirm"};
    ["PHOTOS", "Press Confirm to remove this picture.", 3] call cTab_fnc_addNotification;

    [{
        params ["_pendingId"];
        if ((missionNamespace getVariable ["Iceman_PhotoLibrary_deleteConfirm", ""]) == _pendingId) then {
            missionNamespace setVariable ["Iceman_PhotoLibrary_deleteConfirm", ""];
            private _controls = call Iceman_fnc_photo_findControls;
            private _button = _controls getOrDefault ["9443", controlNull];
            if (!isNull _button) then {_button ctrlSetText "Delete"};
        };
    }, [_id], 3] call CBA_fnc_waitAndExecute;
    false
};

private _records = call Iceman_fnc_photo_getRecords;
private _index = _records findIf {(_x param [0, ""]) == _id};
if (_index < 0) exitWith {false};

_records deleteAt _index;
[_records] call Iceman_fnc_photo_storeRecords;
missionNamespace setVariable ["Iceman_PhotoLibrary_deleteConfirm", ""];
missionNamespace setVariable ["Iceman_PhotoLibrary_selected", if (_records isEqualTo []) then {""} else {(_records # ((count _records) - 1)) param [0, ""]}];
missionNamespace setVariable ["Iceman_PhotoLibrary_expanded", false];

["PHOTOS", "Picture removed from your library.", 3] call cTab_fnc_addNotification;
call Iceman_fnc_photo_refresh;
true
