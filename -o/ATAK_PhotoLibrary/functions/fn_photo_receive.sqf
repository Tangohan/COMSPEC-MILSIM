params [["_record", []]];

if (!hasInterface || {!(_record isEqualType [])} || {count _record < 14}) exitWith {false};

private _receiver = missionNamespace getVariable ["cTab_player", player];
if (isNull _receiver) then {_receiver = player};
private _devices = missionNamespace getVariable ["ctab_core_leaderDevices", []];
if !([_receiver, _devices] call cTab_fnc_checkGear) exitWith {false};

private _id = _record param [0, ""];
if (_id == "") exitWith {false};

private _records = call Iceman_fnc_photo_getRecords;
if ((_records findIf {(_x param [0, ""]) == _id}) >= 0) exitWith {
    ["PHOTOS", "That picture is already in your library.", 3] call cTab_fnc_addNotification;
    false
};

_record set [1, "received"];
_record set [2, ""];
_records pushBack _record;
[_records] call Iceman_fnc_photo_storeRecords;

missionNamespace setVariable ["Iceman_PhotoLibrary_selected", _id];
missionNamespace setVariable ["Iceman_PhotoLibrary_previewMode", "live"];

private _author = _record param [4, "Unknown"];
private _sharedBy = _record param [14, _author];
["PHOTOS", format ["Picture received from %1.", _sharedBy], 7] call cTab_fnc_addNotification;
playSound "cTab_phoneVibrate";
call Iceman_fnc_photo_refresh;
true
