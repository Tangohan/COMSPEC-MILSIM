private _controls = call Iceman_fnc_photo_findControls;
if ((count _controls) == 0) exitWith {false};

private _records = call Iceman_fnc_photo_getRecords;
private _selectedId = missionNamespace getVariable ["Iceman_PhotoLibrary_selected", ""];
if (_records isEqualTo []) then {
    _selectedId = "";
} else {
    if ((_records findIf {(_x param [0, ""]) == _selectedId}) < 0) then {
        _selectedId = (_records # ((count _records) - 1)) param [0, ""];
    };
};
missionNamespace setVariable ["Iceman_PhotoLibrary_selected", _selectedId];

private _localCount = {(_x param [1, "received"]) == "local"} count _records;
private _receivedCount = (count _records) - _localCount;
private _status = _controls getOrDefault ["9401", controlNull];
if (!isNull _status) then {
    _status ctrlSetStructuredText parseText format [
        "<t align='center'><t color='#b8e8ef'>%1</t> photos | %2 local | %3 received</t>",
        count _records,
        _localCount,
        _receivedCount
    ];
};

private _list = _controls getOrDefault ["9410", controlNull];
if (!isNull _list) then {
    _list setVariable ["IcemanPhotoUpdating", true];
    lbClear _list;
    if !(_records isEqualTo []) then {
        for "_i" from ((count _records) - 1) to 0 step -1 do {
            private _record = _records # _i;
            private _id = _record param [0, ""];
            private _source = _record param [1, "received"];
            private _filePath = _record param [2, ""];
            private _author = _record param [4, "Unknown"];
            private _timeText = [_record param [6, []]] call Iceman_fnc_photo_formatTimestamp;
            private _row = _list lbAdd format ["%1  %2", _timeText, _author];
            _list lbSetData [_row, _id];
            _list lbSetTooltip [_row, format ["%1 | %2 | %3", _record param [3, "Picture"], _author, _record param [8, ""]]];
            _list lbSetPicture [_row, ["\ATAK_PhotoLibrary\data\photo_library_icon_ca.paa", _filePath] select (_source == "local" && {_filePath != ""})];
            if (_id == _selectedId) then {
                _list lbSetCurSel _row;
            };
        };
    };
    _list setVariable ["IcemanPhotoUpdating", false];
};

private _recipient = _controls getOrDefault ["9431", controlNull];
if (!isNull _recipient) then {
    private _oldData = if ((lbCurSel _recipient) >= 0) then {_recipient lbData (lbCurSel _recipient)} else {""};
    lbClear _recipient;
    private _first = _recipient lbAdd "Select ATAK user";
    _recipient lbSetData [_first, ""];

    private _playerSides = call cTab_fnc_getPlayerSides;
    private _devices = missionNamespace getVariable ["ctab_core_leaderDevices", []];
    private _players = allPlayers select {
        _x != player
        && {isPlayer _x}
        && {side group _x in _playerSides}
        && {[_x, _devices] call cTab_fnc_checkGear}
    };
    _players = [_players, [], {toLowerANSI name _x}, "ASCEND"] call BIS_fnc_sortBy;

    {
        private _uid = getPlayerUID _x;
        if (_uid != "") then {
            private _row = _recipient lbAdd name _x;
            _recipient lbSetData [_row, _uid];
            if (_uid == _oldData) then {
                _recipient lbSetCurSel _row;
            };
        };
    } forEach _players;

    if ((lbCurSel _recipient) < 0) then {
        _recipient lbSetCurSel 0;
    };
};

call Iceman_fnc_photo_showPreview;
call Iceman_fnc_photo_applyLayout;
true
