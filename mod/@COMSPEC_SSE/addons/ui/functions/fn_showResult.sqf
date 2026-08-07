/*
    Affiche le résultat d'exploitation (style terminal militaire).
    [_fogHashMap] call comspec_sse_fnc_showResult
*/
params [
    ["_fog", createHashMap, [createHashMap]]
];

if (!hasInterface) exitWith { false };

missionNamespace setVariable ["comspec_sse_lastResult", _fog];

if !(createDialog "COMSPEC_SSE_ResultDialog") exitWith {
    private _lines = _fog getOrDefault ["lines", []];
    hint ((_fog getOrDefault ["title", "SSE"]) + endl + (_lines joinString endl));
    true
};

private _display = findDisplay 93010;
if (isNull _display) exitWith { true };

private _type = _fog getOrDefault ["type", "UNKNOWN"];
private _uid = _fog getOrDefault ["uid", "?"];
private _q = _fog getOrDefault ["quality", 0];
private _ql = _fog getOrDefault ["qualityLabel", ""];
private _lines = _fog getOrDefault ["lines", []];

private _html = format [
    "<t color='#88ff88' size='0.9' font='PuristaMedium'>DEVICE ACQUISITION</t><br/>" +
    "<t color='#aaaaaa'>TYPE</t><br/><t color='#ccffcc'>%1</t><br/><br/>" +
    "<t color='#aaaaaa'>IDENTIFICATION</t><br/><t color='#ccffcc'>%2</t><br/><br/>" +
    "<t color='#aaaaaa'>EXTRACTION</t><br/><t color='#bbeebb' size='0.85'>%3</t><br/><br/>" +
    "<t color='#aaaaaa'>QUALITY</t><br/><t color='#88ff88'>%4 %% (%5)</t>",
    _type,
    _uid,
    _lines joinString "<br/>",
    _q,
    _ql
];

(_display displayCtrl 93011) ctrlSetText "COMSPEC SSE";
(_display displayCtrl 93012) ctrlSetStructuredText parseText _html;
true
