/*
    Chronologie à partir SMS / appels / docs / observations.
    [_entity] call comspec_sse_fnc_buildTimeline
*/
params [
    ["_entity", objNull, [objNull]]
];

private _events = [];
private _push = {
    params ["_when", "_text", "_kind", "_source"];
    _events pushBack (createHashMapFromArray [
        ["when", _when],
        ["text", _text],
        ["kind", _kind],
        ["source", _source]
    ]);
};

private _devices = [_entity, "digitalDevices"] call comspec_sse_fnc_getSection;
if (!isNil "_devices" && {_devices isEqualType []}) then {
    {
        if (_x isEqualType createHashMap) then {
            private _sms = _x getOrDefault ["sms", []];
            {
                if (_x isEqualType createHashMap) then {
                    private _when = _x getOrDefault ["time", ""];
                    if (_when isEqualTo "") then { _when = format ["T+%1", _forEachIndex]; };
                    private _txt = _x getOrDefault ["text", "SMS"];
                    [_when, _txt, "SMS", "phone"] call _push;
                } else {
                    if (_x isEqualType "") then {
                        [format ["SMS-%1", _forEachIndex], _x, "SMS", "phone"] call _push;
                    };
                };
            } forEach _sms;
            private _calls = _x getOrDefault ["calls", []];
            {
                if (_x isEqualType createHashMap) then {
                    private _when = _x getOrDefault ["time", "?"];
                    private _num = _x getOrDefault ["number", ""];
                    [_when, format ["Appel %1", _num], "CALL", "phone"] call _push;
                };
            } forEach _calls;
        };
    } forEach _devices;
};

private _docs = [_entity, "documents"] call comspec_sse_fnc_getSection;
if (!isNil "_docs" && {_docs isEqualType []}) then {
    {
        if (_x isEqualType createHashMap) then {
            ["DOC", _x getOrDefault ["title", "Document"], "DOCUMENT", "paper"] call _push;
        };
    } forEach _docs;
};

private _radio = [_entity, "radio"] call comspec_sse_fnc_getSection;
if (!isNil "_radio" && {_radio isEqualType createHashMap}) then {
    {
        if (_x isEqualType createHashMap) then {
            private _when = _x getOrDefault ["when", "?"];
            private _txt = _x getOrDefault ["text", ""];
            [_when, _txt, "RADIO", "radio"] call _push;
        };
    } forEach (_radio getOrDefault ["trafficLog", []]);
};

private _hist = [_entity] call comspec_sse_fnc_getActionHistory;
{
    if (_x isEqualType createHashMap) then {
        private _at = str (_x getOrDefault ["at", 0]);
        private _act = _x getOrDefault ["action", ""];
        private _det = _x getOrDefault ["detail", ""];
        [_at, format ["%1 — %2", _act, _det], "OBS", "operator"] call _push;
    };
} forEach _hist;

_events
