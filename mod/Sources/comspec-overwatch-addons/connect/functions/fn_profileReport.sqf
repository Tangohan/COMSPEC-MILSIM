/*
    Formate COMSPEC_ProfileStats (alimenté par fn_profileWrap) en lignes lisibles, triées par temps
    total décroissant — pour affichage dans le panneau de debug (fn_showDebugInfo.sqf).

    Retourne : [] si le profiler n'a jamais tourné (réglage désactivé ou aucune mesure encore prise) —
    jamais une ligne "0" inventée.
*/
private _stats = missionNamespace getVariable ["COMSPEC_ProfileStats", createHashMap];
private _keys = keys _stats;
if (count _keys == 0) exitWith { [] };

private _rows = _keys apply {
    private _entry = _stats get _x;
    _entry params ["_count", "_total", "_max"];
    private _avgMs = if (_count > 0) then { round ((_total / _count) * 1000) } else { 0 };
    [_x, _count, round (_total * 1000), _avgMs, round (_max * 1000)]
};
_rows sort [false, 2]; // décroissant par temps total (ms, index 2)

private _lines = ["[Debug] --- Profiler (nom : appels / total ms / moy ms / max ms) ---"];
{
    _x params ["_name", "_count", "_totalMs", "_avgMs", "_maxMs"];
    _lines pushBack format ["[Debug] %1 : %2 appels / %3 ms / %4 ms moy / %5 ms max", _name, _count, _totalMs, _avgMs, _maxMs];
} forEach _rows;

_lines
