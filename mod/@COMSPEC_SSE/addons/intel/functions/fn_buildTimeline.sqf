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
                    [_x getOrDefault ["time", format ["T+%1", _forEachIndex]], _x getOrDefault ["text", "SMS"], "SMS", "phone"] call _push;
                } else {
                    if (_x isEqualType "") then {
                        [format ["SMS-%1", _forEachIndex], _x, "SMS", "phone"] call _push;
                    };
                };
            } forEach _sms;
            private _calls = _x getOrDefault ["calls", []];
            {
                if (_x isEqualType createHashMap) then {
                    [_x getOrDefault ["time", "?"], format ["Appel %1", _x getOrDefault ["number", ""]], "CALL", "phone"] call _push;
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
            [_x getOrDefault ["when", "?"], _x getOrDefault ["text", ""], "RADIO", "radio"] call _push;
        };
    } forEach (_radio getOrDefault ["trafficLog", []]);
};

private _hist = [_entity] call comspec_sse_fnc_getActionHistory;
{
    if (_x isEqualType createHashMap) then {
        [str (_x getOrDefault ["at", 0]), format ["%1 — %2", _x getOrDefault ["action", ""], _x getOrDefault ["detail", ""]], "OBS", "operator"] call _push;
    };
} forEach _hist;

_events
