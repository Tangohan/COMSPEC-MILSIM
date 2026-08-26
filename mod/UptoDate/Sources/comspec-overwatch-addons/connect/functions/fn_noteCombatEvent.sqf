/*
    Enfile un événement de combat (agrégé) pour le prochain envoi de position.
    Kinds : fire | hit | missile
    Détail (hashmap) : x, y, n, out (attempt|lock|miss|shot), missile (bool)
*/
params [
    ["_kind", "", [""]],
    ["_detail", createHashMap, [createHashMap]]
];

if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
if (isNull player || {!alive player}) exitWith { false };
if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith { false };
if !([player] call comspec_overwatch_connect_fnc_hasTerminal) exitWith { false };

private _kindNorm = toLower _kind;
if !(_kindNorm in ["fire", "hit", "missile"]) exitWith { false };

private _now = diag_tickTime;
private _pos = getPosWorld player;
private _xPos = _detail getOrDefault ["x", _pos select 0];
private _yPos = _detail getOrDefault ["y", _pos select 1];
if (!(_xPos isEqualType 0)) then { _xPos = _pos select 0; };
if (!(_yPos isEqualType 0)) then { _yPos = _pos select 1; };

private _fnc_push = {
    params ["_t", "_n", "_x", "_y", "_out", "_exch"];
    private _q = missionNamespace getVariable ["COMSPEC_CombatQueue", []];
    if (!(_q isEqualType [])) then { _q = []; };
    if ((count _q) >= 6) then { _q deleteAt 0; };
    private _hm = createHashMapFromArray [["t", _t], ["x", _x], ["y", _y]];
    if (_n > 0) then { _hm set ["n", _n]; };
    if (_out isNotEqualTo "") then { _hm set ["out", _out]; };
    if (_exch) then { _hm set ["exch", true]; };
    _q pushBack _hm;
    missionNamespace setVariable ["COMSPEC_CombatQueue", _q, false];
};

switch (_kindNorm) do {
    case "fire": {
        if (_detail getOrDefault ["missile", false]) then {
            private _last = missionNamespace getVariable ["COMSPEC_CombatMissileShotAt", -1e9];
            if ((_now - _last) < 8) exitWith { false };
            missionNamespace setVariable ["COMSPEC_CombatMissileShotAt", _now, false];
            missionNamespace setVariable ["COMSPEC_CombatLastFireAt", _now, false];
            ["missile", 1, _xPos, _yPos, "shot", false] call _fnc_push;
        } else {
            private _shots = (missionNamespace getVariable ["COMSPEC_CombatFireShots", 0]) + 1;
            missionNamespace setVariable ["COMSPEC_CombatFireShots", _shots, false];
            missionNamespace setVariable ["COMSPEC_CombatFirePos", [_xPos, _yPos], false];
            missionNamespace setVariable ["COMSPEC_CombatLastFireAt", _now, false];
            if (missionNamespace getVariable ["COMSPEC_CombatFirePending", false]) exitWith { true };
            missionNamespace setVariable ["COMSPEC_CombatFirePending", true, false];
            [{
                if (isNull player) exitWith {};
                private _n = missionNamespace getVariable ["COMSPEC_CombatFireShots", 0];
                missionNamespace setVariable ["COMSPEC_CombatFireShots", 0, false];
                missionNamespace setVariable ["COMSPEC_CombatFirePending", false, false];
                if (_n < 1) exitWith {};
                private _xy = missionNamespace getVariable ["COMSPEC_CombatFirePos", getPosWorld player];
                private _hitAt = missionNamespace getVariable ["COMSPEC_CombatLastHitAt", -1e9];
                private _exch = (diag_tickTime - _hitAt) < 8;
                private _t = if (_exch) then { "exchange" } else { "fire" };
                private _q = missionNamespace getVariable ["COMSPEC_CombatQueue", []];
                if (!(_q isEqualType [])) then { _q = []; };
                if ((count _q) >= 6) then { _q deleteAt 0; };
                private _hm = createHashMapFromArray [
                    ["t", _t],
                    ["n", _n],
                    ["x", _xy select 0],
                    ["y", _xy select 1]
                ];
                if (_exch) then { _hm set ["exch", true]; };
                _q pushBack _hm;
                missionNamespace setVariable ["COMSPEC_CombatQueue", _q, false];
            }, [], 2.6] call CBA_fnc_waitAndExecute;
        };
    };
    case "hit": {
        missionNamespace setVariable ["COMSPEC_CombatLastHitAt", _now, false];
        missionNamespace setVariable ["COMSPEC_CombatMissileWatchAt", -1e9, false];
        private _hits = (missionNamespace getVariable ["COMSPEC_CombatHitShots", 0]) + 1;
        missionNamespace setVariable ["COMSPEC_CombatHitShots", _hits, false];
        missionNamespace setVariable ["COMSPEC_CombatHitPos", [_xPos, _yPos], false];
        if (missionNamespace getVariable ["COMSPEC_CombatHitPending", false]) exitWith { true };
        missionNamespace setVariable ["COMSPEC_CombatHitPending", true, false];
        [{
            if (isNull player) exitWith {};
            private _n = missionNamespace getVariable ["COMSPEC_CombatHitShots", 0];
            missionNamespace setVariable ["COMSPEC_CombatHitShots", 0, false];
            missionNamespace setVariable ["COMSPEC_CombatHitPending", false, false];
            if (_n < 1) exitWith {};
            private _xy = missionNamespace getVariable ["COMSPEC_CombatHitPos", getPosWorld player];
            private _q = missionNamespace getVariable ["COMSPEC_CombatQueue", []];
            if (!(_q isEqualType [])) then { _q = []; };
            if ((count _q) >= 6) then { _q deleteAt 0; };
            _q pushBack (createHashMapFromArray [
                ["t", "hit"],
                ["n", _n],
                ["x", _xy select 0],
                ["y", _xy select 1]
            ]);
            missionNamespace setVariable ["COMSPEC_CombatQueue", _q, false];
        }, [], 1.2] call CBA_fnc_waitAndExecute;
    };
    case "missile": {
        private _out = toLower (_detail getOrDefault ["out", "attempt"]);
        if (_out isEqualTo "") then { _out = "attempt"; };
        if (_out in ["shot", "launch", "fired"]) then { _out = "shot"; };
        if (_out in ["lock", "locked"]) then { _out = "lock"; };
        if (_out in ["miss", "missed", "evade"]) then { _out = "miss"; };
        private _key = "COMSPEC_CombatMissileAt_" + _out;
        private _last = missionNamespace getVariable [_key, -1e9];
        private _cool = switch (_out) do {
            case "lock": { 12 };
            case "shot": { 8 };
            default { 8 };
        };
        if ((_now - _last) < _cool) exitWith { false };
        missionNamespace setVariable [_key, _now, false];
        if (_out isEqualTo "shot") then {
            missionNamespace setVariable ["COMSPEC_CombatLastFireAt", _now, false];
        };
        ["missile", 1, _xPos, _yPos, _out, false] call _fnc_push;
    };
};

true
