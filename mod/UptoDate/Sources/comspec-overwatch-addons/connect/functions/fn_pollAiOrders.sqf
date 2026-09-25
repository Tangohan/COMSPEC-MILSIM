/*
    Interroge Athena pour les déplacements demandés aux IA alliées (carte ATAK).
    Lignes : id \t type \t target_ref \t status \t pos_x \t pos_y \t label
    Priorité serveur (hub) : un seul poll pour toute la mission.
*/
if (!isServer && {!hasInterface}) exitWith { false };
if (
    hasInterface
    && {!isServer}
    && {missionNamespace getVariable ["COMSPEC_ServerHubOwnsMissionPoll", false]}
) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
if (
    !(missionNamespace getVariable ["COMSPEC_AthenaReady", false])
    && {!(missionNamespace getVariable ["COMSPEC_ServerUplinkOk", false])}
) exitWith { false };

private _txGate = createHashMap;
_txGate set ["can_transmit", true];
if (hasInterface) then {
    _txGate = [true] call comspec_overwatch_connect_fnc_canTransmit;
};
if !(_txGate getOrDefault ["can_transmit", true]) exitWith { false };

private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
if (_mapId isEqualTo "" || {_mapId isEqualTo "0"}) then { _mapId = "1"; };

private _sessionStart = missionNamespace getVariable ["COMSPEC_OrdersSessionStartedAt", ""];
if (!(_sessionStart isEqualType "") || {_sessionStart isEqualTo ""}) then {
    private _st = systemTimeUTC;
    private _p = {
        params ["_n"];
        _n = floor _n;
        if (_n < 10) then { "0" + str _n } else { str _n };
    };
    _sessionStart = format ["%1-%2-%3T%4:%5:%6Z",
        _st select 0,
        [_st select 1] call _p,
        [_st select 2] call _p,
        [_st select 3] call _p,
        [_st select 4] call _p,
        [_st select 5] call _p
    ];
    missionNamespace setVariable ["COMSPEC_OrdersSessionStartedAt", _sessionStart, false];
};

private _raw = ["COMSPECExtension" callExtension ["GetAiOrders", [_mapId, _sessionStart]]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { false };
if ((_raw select [0, 3]) != "OK|") exitWith { false };

private _body = _raw select [3];
if (_body isEqualTo "") exitWith { false };
private _lines = _body splitString (toString [10]);
private _tab = toString [9];
private _applied = missionNamespace getVariable ["COMSPEC_AiOrdersApplied", []];
if (!(_applied isEqualType [])) then { _applied = []; };
private _n = 0;

{
    private _line = _x;
    if (_line isEqualTo "") then { continue };
    private _cols = _line splitString _tab;
    if ((count _cols) < 6) then { continue };

    private _unblank = {
        params ["_s"];
        _s = trim _s;
        if (_s isEqualTo "-") then { "" } else { _s };
    };

    private _oid = [_cols select 0] call _unblank;
    if (_oid isEqualTo "" || {_oid in _applied}) then { continue };

    private _ref = [_cols select 2] call _unblank;
    private _px = parseNumber ([_cols select 4] call _unblank);
    private _py = parseNumber ([_cols select 5] call _unblank);
    private _label = if ((count _cols) > 6) then { [_cols select 6] call _unblank } else { "Déplacement" };
    if (_label isEqualTo "") then { _label = "Déplacement"; };
    if ((abs _px) < 0.5 && {(abs _py) < 0.5}) then { continue };

    private _unit = [_ref] call comspec_overwatch_connect_fnc_findAllyTrackUnit;
    if (isNull _unit) then { continue };

    if ([_px, _py, _oid, _label, _unit] call comspec_overwatch_connect_fnc_applyAiMoveOrder) then {
        _applied pushBack _oid;
        _n = _n + 1;
        private _by = "SERVER";
        if (hasInterface) then {
            _by = [] call comspec_overwatch_connect_fnc_getCallsign;
            if (_by isEqualTo "") then { _by = name player; };
        };
        ["COMSPECExtension" callExtension ["UpdateOrderStatus", [_oid, "ACK", _by, _mapId, "Deplacement IA"]]] call comspec_overwatch_connect_fnc_extResult;
        [
            {
                params ["_id", "_by", "_map"];
                ["COMSPECExtension" callExtension ["UpdateOrderStatus", [_id, "EXEC", _by, _map, "En route"]]] call comspec_overwatch_connect_fnc_extResult;
            },
            [_oid, _by, _mapId],
            0.45
        ] call CBA_fnc_waitAndExecute;
    };
} forEach _lines;

if ((count _applied) > 80) then { _applied deleteRange [0, (count _applied) - 80]; };
missionNamespace setVariable ["COMSPEC_AiOrdersApplied", _applied, false];

_n > 0
