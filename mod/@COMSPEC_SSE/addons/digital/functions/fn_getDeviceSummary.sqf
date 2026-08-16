params [
    ["_target", objNull, [objNull]]
];

[_target] call comspec_sse_fnc_ensureGenerated;
private _devices = [_target, "digitalDevices"] call comspec_sse_fnc_getSection;
if (isNil "_devices" || {!(_devices isEqualType [])} || {count _devices == 0}) exitWith {
    createHashMapFromArray [["ok", false], ["reason", "Aucun support numérique"]]
};

private _phones = _devices select {
    private _t = toUpper (_x getOrDefault ["deviceType", ""]);
    !(_t in ["LAPTOP", "COMPUTER", "PC"])
};
private _d = if (count _phones > 0) then { _phones select 0 } else { _devices select 0 };

private _contacts = _d getOrDefault ["contacts", []];
private _sms = _d getOrDefault ["sms", []];
private _calls = _d getOrDefault ["calls", []];
private _photos = _d getOrDefault ["photos", []];
private _locs = _d getOrDefault ["locations", []];
private _deleted = _d getOrDefault ["deletedData", []];

createHashMapFromArray [
    ["ok", true],
    ["uid", _d getOrDefault ["uid", "?"]],
    ["deviceType", _d getOrDefault ["deviceType", "DEVICE"]],
    ["model", _d getOrDefault ["model", ""]],
    ["owner", _d getOrDefault ["owner", ""]],
    ["imei", _d getOrDefault ["imei", ""]],
    ["sim", _d getOrDefault ["sim", ""]],
    ["contacts", count _contacts],
    ["messages", count _sms],
    ["calls", count _calls],
    ["images", count _photos],
    ["locations", count _locs],
    ["deleted", count _deleted]
]
