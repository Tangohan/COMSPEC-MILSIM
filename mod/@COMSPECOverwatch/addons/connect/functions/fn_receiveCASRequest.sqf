/*
    Process CAS data from DLL (raw JSON string). Store first CAS id for buttons.
*/
private _json = missionNamespace getVariable ["COMSPEC_CAS_Raw", ""];
if (_json isEqualTo "") exitWith {};

// Minimal parse: find "id": and first number, then "status":"...", "line1":"..." etc.
private _id = "";
private _status = "";
private _assigned = "";
private _lines = ["—","—","—","—","—","—","—","—","—"];

// Extract id (first "id":N or "id": "N")
private _idx = _json find "\"id\"";
if (_idx >= 0) then {
    private _rest = _json select [_idx + 4, 24];
    private _num = _rest call BIS_fnc_parseNumber;
    if (!isNil "_num" && {_num > 0}) then { _id = str (round _num); };
};
// Extract status
private _sIdx = _json find "\"status\"";
if (_sIdx >= 0) then {
    private _q = _json find "\"", _sIdx + 8;
    if (_q >= 0) then {
        private _q2 = _json find "\"", _q + 1;
        if (_q2 > _q) then { _status = _json select [_q + 1, _q2 - _q - 1]; };
    };
};
// assigned_aircraft / assignedAircraft
private _aIdx = _json find "\"assigned";
if (_aIdx >= 0) then {
    private _q = _json find "\"", _aIdx + 12;
    if (_q >= 0) then {
        private _q2 = _json find "\"", _q + 1;
        if (_q2 > _q) then { _assigned = _json select [_q + 1, _q2 - _q - 1]; };
    };
};
// line1..line9
for "_i" from 1 to 9 do {
    private _key = "\"line" + str _i + "\"";
    private _lIdx = _json find _key;
    if (_lIdx >= 0) then {
        private _q = _json find "\"", _lIdx + count _key + 1;
        if (_q >= 0) then {
            private _q2 = _json find "\"", _q + 1;
            if (_q2 > _q) then {
                private _val = _json select [_q + 1, _q2 - _q - 1];
                _lines set [_i - 1, _val];
            };
        };
    };
};

missionNamespace setVariable ["COMSPEC_CurrentCASId", _id];
missionNamespace setVariable ["COMSPEC_CurrentCASStatus", _status];
missionNamespace setVariable ["COMSPEC_CurrentCASAssigned", _assigned];
missionNamespace setVariable ["COMSPEC_CurrentCASLines", _lines];
