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

createHashMapFromArray [
    ["ok", true],
    ["uid", _d getOrDefault ["uid", "?"]],
    ["deviceType", _d getOrDefault ["deviceType", "DEVICE"]],
    ["model", _d getOrDefault ["model", ""]],
    ["owner", _d getOrDefault ["owner", ""]],
    ["imei", _d getOrDefault ["imei", ""]],
    ["sim", _d getOrDefault ["sim", ""]],
    ["contacts", count (_d getOrDefault ["contacts", []])],
    ["messages", count (_d getOrDefault ["sms", []])],
    ["calls", count (_d getOrDefault ["calls", []])],
    ["images", count (_d getOrDefault ["photos", []])],
    ["locations", count (_d getOrDefault ["locations", []])],
    ["deleted", count (_d getOrDefault ["deletedData", []])]
]
