/*
    Vérifie si le joueur (ATAK allié) entre dans le rayon d’un téléphone suivi.
    Appelé ~toutes les 1,5 s. Rayon 0 = désactivé.
*/
if (!hasInterface) exitWith {};
if (!alive player) exitWith {};

private _radius = missionNamespace getVariable ["COMSPEC_AtakPhoneProximityM", 200];
if (!(_radius isEqualType 0)) then { _radius = 200; };
if (_radius <= 0) exitWith {};

private _inside = missionNamespace getVariable ["COMSPEC_AtakPhoneProxInside", createHashMap];
if (!(_inside isEqualType createHashMap)) then { _inside = createHashMap; };

private _exitR = _radius * 1.15;

private _scan = +allUnits;
_scan append allDeadMen;

{
    private _u = _x;
    if (isNull _u) then { continue };
    if (_u isEqualTo player) then { continue };
    private _tracked = if (!isNil "comspec_overwatch_connect_fnc_isObjectFlag") then {
        [_u, "COMSPEC_PhoneTrack"] call comspec_overwatch_connect_fnc_isObjectFlag
    } else {
        private _v = _u getVariable ["COMSPEC_PhoneTrack", false];
        if (_v isEqualType true) then { _v } else { false }
    };
    if (!_tracked) then { continue };

    private _key = _u getVariable ["COMSPEC_PhoneTrackId", ""];
    if (!(_key isEqualType "")) then { _key = str _key; };
    _key = trim _key;
    if (_key isEqualTo "") then { _key = netId _u; };
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

    private _label = "Téléphone suivi";
    if (!isNil "comspec_overwatch_connect_fnc_phoneTrackCallsign") then {
        _label = [_u] call comspec_overwatch_connect_fnc_phoneTrackCallsign;
    } else {
        private _custom = _u getVariable ["COMSPEC_PhoneCallsign", ""];
        if (_custom isEqualType "" && {_custom isNotEqualTo ""}) then {
            _label = _custom;
        };
    };
    [_label, _dist] call comspec_overwatch_atak_athena_fnc_athena_phoneProximityAlert;
} forEach _scan;

missionNamespace setVariable ["COMSPEC_AtakPhoneProxInside", _inside, false];
