/*
    Définit les données numériques (téléphone / ordinateur).
    [_phone, [["owner","Karim"],["contacts",["ABU YASSIN"]]]] call comspec_sse_fnc_setDigitalData
*/
params [
    ["_entity", objNull, [objNull]],
    ["_pairs", [], [[]]],
    ["_public", true, [true]]
];

if (isNull _entity) exitWith { false };

private _devices = [_entity, "digitalDevices"] call comspec_sse_fnc_getSection;
if (isNil "_devices" || {!(_devices isEqualType [])}) then {
    _devices = [];
};

private _device = if (count _devices > 0) then {
    _devices select 0
} else {
    createHashMapFromArray [
        ["uid", ["SSE-DIG"] call comspec_sse_fnc_generateUID],
        ["deviceType", "PHONE"],
        ["contacts", []],
        ["sms", []],
        ["calls", []],
        ["photos", []],
        ["locations", []],
        ["notes", []],
        ["applications", []],
        ["deletedData", []]
    ]
};

{
    _x params ["_k", "_v"];
    _device set [toLower _k, _v];
} forEach _pairs;

if (count _devices == 0) then {
    _devices pushBack _device;
} else {
    _devices set [0, _device];
};

[_entity, "digitalDevices", _devices, _public] call comspec_sse_fnc_setSection;

private _status = [_entity, "sectionStatus"] call comspec_sse_fnc_getSection;
if (!isNil "_status" && {_status isEqualType createHashMap}) then {
    _status set ["digital", "complete"];
    [_entity, "sectionStatus", _status, _public] call comspec_sse_fnc_setSection;
};

true
