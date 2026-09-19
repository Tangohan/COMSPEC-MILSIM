/*
    Retire une photo de la bibliothèque IceMan, puis le fichier local.
    Params: [_record, _refresh]
*/
params [
    ["_record", [], [[]]],
    ["_refresh", true, [true]]
];

if (!(_record isEqualType []) || {_record isEqualTo []}) exitWith { false };

private _id = _record param [0, ""];
private _path = _record param [2, ""];
private _fileName = _record param [3, ""];
if (_path isEqualTo "" && {_fileName isNotEqualTo ""}) then { _path = _fileName; };

if (_id isNotEqualTo "" && {!isNil "Iceman_fnc_photo_getRecords"} && {!isNil "Iceman_fnc_photo_storeRecords"}) then {
    private _records = [] call Iceman_fnc_photo_getRecords;
    if (_records isEqualType []) then {
        _records = _records select { (_x param [0, ""]) isNotEqualTo _id };
        [_records] call Iceman_fnc_photo_storeRecords;
        private _sel = missionNamespace getVariable ["Iceman_PhotoLibrary_selected", ""];
        if (_sel isEqualTo _id) then {
            private _next = if (_records isEqualTo []) then { "" } else { (_records select ((count _records) - 1)) param [0, ""] };
            missionNamespace setVariable ["Iceman_PhotoLibrary_selected", _next];
        };
        missionNamespace setVariable ["Iceman_PhotoLibrary_expanded", false];
        if (_refresh && {!isNil "Iceman_fnc_photo_refresh"}) then {
            [] call Iceman_fnc_photo_refresh;
        };
    };
};

if (_path isNotEqualTo "") then {
    [{
        params ["_p"];
        if (!(_p isEqualType "") || {_p isEqualTo ""}) exitWith {};
        if (!isNil "comspec_overwatch_connect_fnc_extResult") then {
            ["COMSPECExtension" callExtension ["DeleteLocalFile", [_p]]] call comspec_overwatch_connect_fnc_extResult;
        };
        deleteFile _p;
    }, [_path], 8] call CBA_fnc_waitAndExecute;
};

true
