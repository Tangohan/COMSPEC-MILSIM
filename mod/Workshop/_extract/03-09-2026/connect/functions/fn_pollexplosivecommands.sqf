/*
    Interroge Athena pour les déclenchements demandés par le poste de commandement.
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };
if (!isClass (configFile >> "CfgPatches" >> "ace_explosives")) exitWith { false };

private _txGate = [true] call comspec_overwatch_connect_fnc_canTransmit;
if !(_txGate getOrDefault ["can_transmit", true]) exitWith { false };

private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
if (_mapId isEqualTo "" || {_mapId isEqualTo "0"}) then { _mapId = "1"; };

private _raw = ["COMSPECExtension" callExtension ["GetExplosiveCommands", [_mapId]]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { false };
if ((_raw select [0, 3]) != "OK|") exitWith { false };

private _body = _raw select [3];
if (_body isEqualTo "") exitWith { false };
private _lines = _body splitString (toString [10]);
private _tab = toString [9];
private _n = 0;

{
    private _line = _x;
    if (_line isEqualTo "") then { continue };
    private _cols = _line splitString _tab;
    if ((count _cols) < 1) then { continue };
    private _cid = trim (_cols select 0);
    if (_cid isEqualTo "" || {_cid isEqualTo "-"}) then { continue };
    if ([_cid] call comspec_overwatch_connect_fnc_detonateChargeById) then {
        _n = _n + 1;
    };
} forEach _lines;

_n > 0
