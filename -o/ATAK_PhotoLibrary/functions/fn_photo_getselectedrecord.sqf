private _selectedId = missionNamespace getVariable ["Iceman_PhotoLibrary_selected", ""];
if (_selectedId == "") exitWith {[]};

private _records = call Iceman_fnc_photo_getRecords;
private _index = _records findIf {(_x param [0, ""]) == _selectedId};
if (_index < 0) exitWith {[]};

+(_records # _index)
