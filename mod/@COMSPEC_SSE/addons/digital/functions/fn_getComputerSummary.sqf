params [
    ["_target", objNull, [objNull]]
];

[_target] call comspec_sse_fnc_ensureGenerated;
private _devices = [_target, "digitalDevices"] call comspec_sse_fnc_getSection;
if (isNil "_devices" || {!(_devices isEqualType [])}) exitWith {
    createHashMapFromArray [["ok", false]]
};

private _pcs = _devices select {
    private _t = toUpper (_x getOrDefault ["deviceType", ""]);
    _t in ["LAPTOP", "COMPUTER", "PC"]
};
if (count _pcs == 0) exitWith { createHashMapFromArray [["ok", false], ["reason", "Aucun ordinateur"]] };

private _d = _pcs select 0;
private _users = _d getOrDefault ["users", []];
private _files = _d getOrDefault ["files", []];
private _browser = _d getOrDefault ["browser", []];
private _mail = _d getOrDefault ["mail", []];
private _usb = _d getOrDefault ["usbHistory", []];
private _creds = _d getOrDefault ["credentials", []];
private _enc = _d getOrDefault ["encryptedFiles", []];

createHashMapFromArray [
    ["ok", true],
    ["uid", _d getOrDefault ["uid", "?"]],
    ["hostname", _d getOrDefault ["hostname", "?"]],
    ["owner", _d getOrDefault ["owner", ""]],
    ["users", count _users],
    ["files", count _files],
    ["browser", count _browser],
    ["mail", count _mail],
    ["usb", count _usb],
    ["credentials", count _creds],
    ["encrypted", count _enc]
]
