params [
    ["_prefix", "SSE", [""]],
    ["_recordId", "", [""]]
];

private _t = round (diag_tickTime * 1000);
private _r = floor random 99999;
format ["%1-%2-%3-%4", _prefix, _recordId, _t, _r]
