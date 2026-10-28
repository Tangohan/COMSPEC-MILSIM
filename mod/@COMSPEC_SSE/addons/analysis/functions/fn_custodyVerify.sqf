/*
    Vérifie l'intégrité du journal d'une pièce : chaque ligne doit porter
    l'empreinte de la précédente et de son propre contenu.
    [_pieceId] call comspec_sse_fnc_custodyVerify → [intègre, index de la première ligne altérée (-1)]
*/
params [["_id", "", [""]]];
private _p = (missionNamespace getVariable ["comspec_sse_custody", createHashMap]) getOrDefault [_id, createHashMap];
private _prev = "ORIGINE";
private _bad = -1;
{
    if ((hashValue ([_prev] + (_x select [0, 6]))) isNotEqualTo (_x select 6)) exitWith { _bad = _forEachIndex; };
    _prev = _x select 6;
} forEach (_p getOrDefault ["events", []]);
[_bad < 0, _bad]
