/*
    Exécute les entrées de la file serveur arrivées à échéance.
    Returns: nombre d’actions exécutées
*/
if (!isServer) exitWith { 0 };

private _queue = missionNamespace getVariable ["COMSPEC_ServerQueue", createHashMap];
if (!(_queue isEqualType createHashMap)) exitWith { 0 };

private _now = diag_tickTime;
private _done = 0;
private _keep = createHashMap;

{
    private _key = _x;
    private _entry = _queue getOrDefault [_key, []];
    if (!(_entry isEqualType []) || {(count _entry) < 2}) then { continue };
    private _due = _entry param [0, 0];
    private _code = _entry param [1, {}];
    private _args = _entry param [2, []];
    if (_due > _now) then {
        _keep set [_key, _entry];
    } else {
        if (_code isEqualType {}) then {
            _args call _code;
            _done = _done + 1;
        };
    };
} forEach (keys _queue);

missionNamespace setVariable ["COMSPEC_ServerQueue", _keep, false];
_done
