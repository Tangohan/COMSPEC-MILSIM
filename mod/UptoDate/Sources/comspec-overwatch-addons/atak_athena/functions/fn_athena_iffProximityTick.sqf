/*
    Alerte IFF de proximité : contact non identifié dans le rayon.
    Appelé depuis le tick téléphone. Rayon 0 = désactivé.
*/
if (!hasInterface) exitWith {};
if (!alive player) exitWith {};

private _radius = missionNamespace getVariable ["COMSPEC_AtakIffProximityM", 200];
if (!(_radius isEqualType 0)) then { _radius = 200; };
if (_radius <= 0) exitWith {};

private _inside = missionNamespace getVariable ["COMSPEC_AtakIffProxInside", createHashMap];
if (!(_inside isEqualType createHashMap)) then { _inside = createHashMap; };
private _cool = missionNamespace getVariable ["COMSPEC_AtakIffProxCool", createHashMap];
if (!(_cool isEqualType createHashMap)) then { _cool = createHashMap; };

private _exitR = _radius * 1.15;
private _now = diag_tickTime;
private _playerSide = side player;
private _grp = group player;

{
    private _u = _x;
    if (isNull _u) then { continue };
    if (_u isEqualTo player) then { continue };
    if (!alive _u) then { continue };
    if (!(_u isKindOf "CAManBase")) then { continue };
    if (isPlayer _u && {side _u isEqualTo _playerSide}) then { continue };
    if ((group _u) isEqualTo _grp) then { continue };
    if ([_playerSide, side _u] call BIS_fnc_sideIsFriendly) then { continue };

    private _key = netId _u;
    if (_key isEqualTo "") then { _key = str _u; };

    private _dist = player distance2D _u;
    private _was = _inside getOrDefault [_key, false];
    private _nowInside = false;
    private _alert = false;
    if (_dist <= _radius) then {
        _nowInside = true;
        _alert = !_was;
    } else {
        if (_was && {_dist <= _exitR}) then {
            _nowInside = true;
        };
    };

    if (_nowInside) then {
        _inside set [_key, true];
    } else {
        _inside deleteAt _key;
    };

    if (!_alert) then { continue };
    private _last = _cool getOrDefault [_key, -999];
    if ((_now - _last) < 45) then { continue };
    _cool set [_key, _now];
    [_dist] call comspec_overwatch_atak_athena_fnc_athena_iffProximityAlert;
} forEach allUnits;

missionNamespace setVariable ["COMSPEC_AtakIffProxInside", _inside, false];
missionNamespace setVariable ["COMSPEC_AtakIffProxCool", _cool, false];
