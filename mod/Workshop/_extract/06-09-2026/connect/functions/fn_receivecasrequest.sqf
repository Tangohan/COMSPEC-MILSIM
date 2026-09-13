/*

    Process CAS data from DLL (raw JSON string). Store first CAS id for buttons.

    N’ouvre le viewer 9-lignes que s’il y a une vraie demande (id valide).

    Une liste vide [] ou un JSON sans id ne doit JAMAIS ouvrir le dialogue.

*/

private _json = missionNamespace getVariable ["COMSPEC_CAS_Raw", ""];

if (_json isEqualTo "") exitWith {};



private _trimmed = trim _json;

if (

    _trimmed isEqualTo ""

    || {_trimmed isEqualTo "[]"}

    || {_trimmed isEqualTo "null"}

    || {_trimmed isEqualTo "{}"}

) exitWith {

    missionNamespace setVariable ["COMSPEC_CurrentCASId", ""];

    missionNamespace setVariable ["COMSPEC_CurrentCASStatus", ""];

    missionNamespace setVariable ["COMSPEC_CurrentCASAssigned", ""];

    missionNamespace setVariable ["COMSPEC_CurrentCASLines", ["-","-","-","-","-","-","-","-","-"]];

};



private _q = toString [34]; // "



// Minimal parse: find "id": and first number, then "status":"...", "line1":"..." etc.

private _id = "";

private _status = "";

private _assigned = "";

private _lines = ["-","-","-","-","-","-","-","-","-"];



// Extract id (first "id":N or "id": "N") — éviter les faux positifs type "missionId"

private _idKey = _q + "id" + _q + ":";

private _idx = _json find _idKey;

if (_idx < 0) then {

    _idKey = _q + "id" + _q + " :";

    _idx = _json find _idKey;

};

if (_idx >= 0) then {

    private _rest = _json select [_idx + (count _idKey), 24];

    _rest = trim _rest;

    // id numérique ou "N"

    if ((_rest select [0, 1]) isEqualTo _q) then {

        private _q2 = _rest find [_q, 1];

        if (_q2 > 1) then {

            private _idStr = _rest select [1, _q2 - 1];

            private _num = _idStr call BIS_fnc_parseNumber;

            if (!isNil "_num" && {_num > 0}) then { _id = str (round _num); };

        };

    } else {

        private _num = _rest call BIS_fnc_parseNumber;

        if (!isNil "_num" && {_num > 0}) then { _id = str (round _num); };

    };

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



// Pas d’id = pas de demande CAS réelle (liste vide, parse raté, bruit API)

if (_id isEqualTo "") exitWith {};



private _disp = uiNamespace getVariable ["COMSPEC_CAS_Display", displayNull];

private _lastOpenedId = missionNamespace getVariable ["COMSPEC_LastCASOpenedId", ""];



// Même demande déjà vue : rafraîchir si ouvert, ne pas rouvrir de force

if (_id isEqualTo _lastOpenedId) exitWith {

    if (!isNull _disp && {!isNil "comspec_overwatch_connect_fnc_updateCASState"}) then {

        [] call comspec_overwatch_connect_fnc_updateCASState;

    };

};



// Nouvelle demande CAS → ouvrir le viewer 9-lignes (pilote / destinataire)

missionNamespace setVariable ["COMSPEC_LastCASOpenedId", _id, false];

if (hasInterface && {!isNil "comspec_overwatch_connect_fnc_casDialogShow"}) then {

    [] call comspec_overwatch_connect_fnc_casDialogShow;

};


