/*
    comspec_fb_fnc_aiBurst : les IA LOCALES à cette machine dans la zone d'une détonation (appelé par fn_burst).
    Chaque machine traite les IA qui lui appartiennent : serveur, clients headless, ou le joueur dont l'IA fait partie
    du groupe. Une IA qui n'est pas locale est ignorée ici : sa machine reçoit le même message (fn_targets envoie aussi
    la détonation aux propriétaires des IA proches) et fait le travail ; aucun remoteExec, rien à autoriser.
    Coût borné : au plus « Unités IA traitées par détonation » (20 par défaut), les plus proches d'abord, par tranches de
    6 unités par image (fn_aiBatch) ; jamais de boucle d'attente.
    [_ctx, _posASL, _env, _force, _units, _centre, _joueurLocal] call comspec_fb_fnc_aiBurst
*/
params ["_ctx", "_pos", "_env", "_g", "_units", "_center", "_me"];
private _cfg = [] call comspec_fb_fnc_aiParams;
private _cands = _units select { local _x && {alive _x} && {!isPlayer _x} && {_x isNotEqualTo _me} };
if (_cands isEqualTo []) exitWith {};
private _keyed = [];
{ _keyed pushBack [_x distance _center, _forEachIndex] } forEach _cands;
_keyed sort true;
private _queue = (_keyed select [0, _cfg select 7]) apply { _cands select (_x select 1) };
if (missionNamespace getVariable ["comspec_fb_aiDebug", false] || {[true] call comspec_fb_fnc_logOn}) then {
    ["ia", format ["%1 : %2 IA locale(s) dans la zone, %3 traitée(s) (plafond %4), réaction %5, rayon x%6", _ctx get "id", count _cands, count _queue,
        _cfg select 7, ["discrète", "réaliste", "forte"] select (_cfg select 0), (_cfg select 1) toFixed 2]] call comspec_fb_fnc_log;
};
[_queue, _ctx, _pos, _env, _g, _center, _cfg] call comspec_fb_fnc_aiBatch;
