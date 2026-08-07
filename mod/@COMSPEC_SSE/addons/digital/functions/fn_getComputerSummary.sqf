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
createHashMapFromArray [
    ["ok", true],
    ["uid", _d getOrDefault ["uid", "?"]],
    ["hostname", _d getOrDefault ["hostname", "?"]],
    ["owner", _d getOrDefault ["owner", ""]],
    ["users", count (_d getOrDefault ["users", []])],
    ["files", count (_d getOrDefault ["files", []])],
    ["browser", count (_d getOrDefault ["browser", []])],
    ["mail", count (_d getOrDefault ["mail", []])],
    ["usb", count (_d getOrDefault ["usbHistory", []])],
    ["credentials", count (_d getOrDefault ["credentials", []])],
    ["encrypted", count (_d getOrDefault ["encryptedFiles", []])]
]
