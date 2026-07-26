/*
    Profiler basique façon Arma3BasicScriptProfiler : exécute _code avec _args et mesure le temps
    écoulé (diag_tickTime), accumulé par nom dans COMSPEC_ProfileStats. Désactivé par défaut
    (réglage "comspec_overwatch_profile_enabled") — coût nul quand désactivé (pas de diag_tickTime,
    pas d'écriture HashMap), donc sans risque à laisser en place en permanence dans le code appelant.

    Utilisation typique (autour d'un PerFrameHandler ou d'une boucle chaude) :
        [{ [player] call comspec_overwatch_connect_fnc_updatePosition }, [], "updatePosition"] call comspec_overwatch_connect_fnc_profileWrap;

    Params : [_code, _args, _name]
    Retourne : le résultat de l'appel _args call _code (inchangé, avec ou sans profiling).
*/
params [["_code", {}, [{}]], ["_args", [], [[]]], ["_name", "unknown", [""]]];

if !(missionNamespace getVariable ["comspec_overwatch_profile_enabled", false]) exitWith {
    _args call _code
};

private _t0 = diag_tickTime;
private _result = _args call _code;
private _dt = diag_tickTime - _t0;

private _stats = missionNamespace getVariable ["COMSPEC_ProfileStats", createHashMap];
private _entry = _stats getOrDefault [_name, [0, 0, 0]];
_entry params ["_count", "_total", "_max"];
_stats set [_name, [_count + 1, _total + _dt, _max max _dt]];
missionNamespace setVariable ["COMSPEC_ProfileStats", _stats];

_result
