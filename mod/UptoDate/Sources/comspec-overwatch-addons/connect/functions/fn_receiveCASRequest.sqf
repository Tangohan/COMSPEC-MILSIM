/*
    Process CAS data from DLL (raw JSON string). Store first CAS id for buttons.
*/
private _json = missionNamespace getVariable ["COMSPEC_CAS_Raw", ""];
if (_json isEqualTo "") exitWith {};

private _q = toString [34]; // "

// Minimal parse: find "id": and first number, then "status":"...", "line1":"..." etc.
private _id = "";
private _status = "";
private _assigned = "";
private _lines = ["-","-","-","-","-","-","-","-","-"];

// Extract id (first "id":N or "id": "N")
private _idx = _json find (_q + "id" + _q);
if (_idx >= 0) then {
    private _rest = _json select [_idx + 4, 24];
    private _num = _rest call BIS_fnc_parseNumber;
    if (!isNil "_num" && {_num > 0}) then { _id = str (round _num); };
};
// Extract status
private _sIdx = _json find (_q + "status" + _q);
if (_sIdx >= 0) then {
    private _qi = _json find [_q, _sIdx + 8];
    if (_qi >= 0) then {
        private _q2 = _json find [_q, _qi + 1];
        if (_q2 > _qi) then { _status = _json select [_qi + 1, _q2 - _qi - 1]; };
    };
};
// assigned_aircraft / assignedAircraft
private _aIdx = _json find (_q + "assigned");
if (_aIdx >= 0) then {
    private _qi = _json find [_q, _aIdx + 12];
    if (_qi >= 0) then {
        private _q2 = _json find [_q, _qi + 1];
        if (_q2 > _qi) then { _assigned = _json select [_qi + 1, _q2 - _qi - 1]; };
    };
};
// line1..line9
for "_i" from 1 to 9 do {
    private _key = _q + "line" + str _i + _q;
    private _lIdx = _json find _key;
    if (_lIdx >= 0) then {
        private _qi = _json find [_q, _lIdx + count _key + 1];
        if (_qi >= 0) then {
            private _q2 = _json find [_q, _qi + 1];
            if (_q2 > _qi) then {
                private _val = _json select [_qi + 1, _q2 - _qi - 1];
                _lines set [_i - 1, _val];
            };
        };
    };
};

missionNamespace setVariable ["COMSPEC_CurrentCASId", _id];
missionNamespace setVariable ["COMSPEC_CurrentCASStatus", _status];
missionNamespace setVariable ["COMSPEC_CurrentCASAssigned", _assigned];
missionNamespace setVariable ["COMSPEC_CurrentCASLines", _lines];
