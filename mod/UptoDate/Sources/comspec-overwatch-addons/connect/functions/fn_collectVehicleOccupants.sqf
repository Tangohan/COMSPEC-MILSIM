/*
    Personnes à bord d’un véhicule (pilote, tireur, passagers).
    Retour : tableau de HashMap { name, seat, role, player }.
*/
params [["_vehicle", objNull, [objNull]]];
if (isNull _vehicle) exitWith { [] };

private _fnc_bad = {
    params ["_s"];
    if (!(_s isEqualType "")) then { _s = str _s };
    _s = trim _s;
    if (_s isEqualTo "") exitWith { true };
    private _low = toLower _s;
    if ((_low find "error:") == 0) exitWith { true };
    _low in [
        "se déplacer", "se deplacer", "attendre", "suivre", "regarder autour",
        "monter", "descendre", "get in", "get out", "unknown", "inconnu", "error",
        "grpnull"
    ]
};

private _out = [];
{
    if (!(_x isEqualType objNull) || {isNull _x} || {!alive _x}) then { continue };
    if ((count _out) >= 24) then { break };

    private _u = _x;
    private _seat = "cargo";
    private _assigned = assignedVehicleRole _u;
    if ((count _assigned) > 0 && {(_assigned select 0) isEqualType ""}) then {
        private _r = toLower (_assigned select 0);
        if (_r in ["driver", "pilot"]) then { _seat = "driver"; };
        if (_r in ["gunner", "turret"]) then { _seat = "gunner"; };
        if (_r isEqualTo "commander") then { _seat = "commander"; };
        if (_r isEqualTo "cargo") then { _seat = "cargo"; };
    };
    if (_u isEqualTo driver _vehicle) then { _seat = "driver"; };
    if (_u isEqualTo gunner _vehicle) then { _seat = "gunner"; };
    if (!isNull (commander _vehicle) && {_u isEqualTo commander _vehicle} && {_seat isEqualTo "cargo"}) then {
        _seat = "commander";
    };

    private _name = "";
    if (isPlayer _u) then {
        if (_u isEqualTo player) then {
            _name = [] call comspec_overwatch_connect_fnc_getCallsign;
        } else {
            private _cs = _u getVariable ["COMSPEC_Callsign", ""];
            if (!(_cs isEqualType "")) then { _cs = str _cs };
            _name = trim _cs;
        };
        if (_name isEqualTo "") then { _name = name _u; };
    } else {
        if (!isNil "comspec_overwatch_connect_fnc_allyTrackCallsign") then {
            _name = [_u] call comspec_overwatch_connect_fnc_allyTrackCallsign;
        };
        if ([_name] call _fnc_bad || {(toLower _name) in ["unité alliée", "unite alliee"]}) then {
            _name = "";
        };
        if (_name isEqualTo "") then {
            private _gid = "";
            private _grp = group _u;
            if (!isNull _grp) then { _gid = trim (groupId _grp); };
            private _roleNm = [_u] call comspec_overwatch_connect_fnc_getUnitRole;
            if (!(_roleNm isEqualType "")) then { _roleNm = str _roleNm; };
            _roleNm = trim _roleNm;
            if (!([_roleNm] call _fnc_bad) && {_roleNm isNotEqualTo ""} && {!((toLower _roleNm) in ["operator", "operateur", "unité alliée", "unite alliee"])}) then {
                _name = if (!([_gid] call _fnc_bad) && {_gid isNotEqualTo ""}) then {
                    format ["%1 — %2", _gid, _roleNm]
                } else {
                    _roleNm
                };
            } else {
                if (!([_gid] call _fnc_bad) && {_gid isNotEqualTo ""}) then {
                    _name = format ["%1 (%2)", _gid, (count _out) + 1];
                } else {
                    _name = format ["Passager %1", (count _out) + 1];
                };
            };
        };
    };
    if (!(_name isEqualType "")) then { _name = str _name; };
    _name = (_name splitString """" joinString "");

    private _role = [_u] call comspec_overwatch_connect_fnc_getUnitRole;
    if (!(_role isEqualType "")) then { _role = str _role; };
    _role = trim _role;
    if ([_role] call _fnc_bad) then { _role = ""; };
    _role = (_role splitString """" joinString "");

    private _row = createHashMap;
    _row set ["name", _name];
    _row set ["seat", _seat];
    _row set ["role", _role];
    _row set ["player", isPlayer _u];
    _out pushBack _row;
} forEach (crew _vehicle);

_out
