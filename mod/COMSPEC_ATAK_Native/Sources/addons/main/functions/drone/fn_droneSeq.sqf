/*
    Suivi des cibles marquées (onglet CIBLES de l'app Drone) : le pilote choisit une ou plusieurs cibles marquées (LOCK)
    et leur ordre ; le drone les suit l'une après l'autre, la caméra pointée sur la cible en cours.
      ["toggle", clé]          ajoute la cible en fin de liste, ou l'en retire ;
      ["move", [clé, -1 | 1]]  la fait passer avant (-1) ou après (+1) dans l'ordre ;
      ["kind", "FOLLOW" | "ORBIT"]   FILER : le drone suit la cible (tâche escorte, 30 m derrière) ;
                               ORBITE : il tourne autour d'elle (tâche observer), centre recalé quand elle se déplace ;
      ["rad", m]               rayon de l'orbite ;
      ["dwell", s]             temps passé sur chaque cible avant la suivante (0 = au pilote, bouton CIBLE SUIVANTE) ;
      ["start"] / ["next"] / ["stop"(, "", silencieux)]   lance, passe à la suivante, arrête (stationnaire, sauf silencieux) ;
      ["tick"]                 chaque seconde (fn_droneAction "tick") : cible neutralisée ou perdue depuis 20 s → suivante,
                               temps écoulé → suivante, orbite recalée si la cible s'est éloignée de 40 m de son centre ;
      ["list"]                 → clés dans l'ordre (cibles libérées retirées) ;
      ["status"]               → [actif, index, clé en cours, secondes restantes (-1 = au pilote)].
    Après la dernière cible, le suivi repart de la première (en boucle) tant qu'il n'est pas arrêté.
    Un ordre de vol du pilote (boutons, carte, poste web) arrête le suivi (fn_droneAction, _send).
    État : missionNamespace COMSPEC_ATAK_DroneSeq (clés), COMSPEC_ATAK_DroneSeqRun ([actif, index, début, dernier envoi,
    centre envoyé, clé]) ; réglages dans l'état du téléphone : droneSeqKind, droneSeqRad, droneSeqDwell.
*/
params [["_act", "tick"], ["_arg", ""], ["_silent", false]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _locks = missionNamespace getVariable ["COMSPEC_ATAK_Locks", createHashMap];
private _seq = (missionNamespace getVariable ["COMSPEC_ATAK_DroneSeq", []]) select { _x in _locks };
missionNamespace setVariable ["COMSPEC_ATAK_DroneSeq", _seq];
private _run = missionNamespace getVariable ["COMSPEC_ATAK_DroneSeqRun", [false, 0, 0, 0, [], ""]];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "DRONE") then { ["DRONE"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _say = { params ["_lvl", "_txt"]; [_lvl, _txt, 4, 40] call comspec_atak_native_fnc_notify; };
private _log = { params ["_t", ["_c", "#4da3ff"]]; ["log", _t, _c] call comspec_atak_native_fnc_droneAction; };
private _numOf = { params ["_k"]; [(_locks getOrDefault [_k, createHashMapFromArray [["num", 0]]]) get "num", 3] call CBA_fnc_formatNumber };
// Prochaine cible encore à suivre (pas neutralisée) après l'index donné, en boucle ; -1 s'il n'y en a plus.
private _nextIdx = {
    params ["_from"];
    private _n = count _seq;
    private _r = -1;
    for "_j" from 1 to _n do {
        private _i = (_from + _j) mod _n;
        if (((_locks get (_seq select _i)) get "state") isNotEqualTo "DOWN") exitWith { _r = _i; };
    };
    _r
};
// Envoie au drone la cible n° _i de la liste : filature (cible accrochée et vivante) ou orbite sur sa position.
private _go = {
    params ["_i"];
    private _k = _seq select _i;
    private _e = _locks get _k;
    private _o = _e get "obj";
    private _live = (_e get "state") isEqualTo "TRACK" && {alive _o};
    private _p = [_e get "pos", getPosASL _o] select _live;
    private _follow = (_s getOrDefault ["droneSeqKind", "FOLLOW"]) isEqualTo "FOLLOW" && {_live};
    if (_follow) then { ["sendRaw", ["escort", [_o]]] call comspec_atak_native_fnc_droneAction; } else {
        ["sendRaw", ["observe", [_p, _s getOrDefault ["droneSeqRad", 150]]]] call comspec_atak_native_fnc_droneAction;
    };
    missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", format ["LOCK:%1", _k]];
    _run = [true, _i, time, time, _p, _k];
    missionNamespace setVariable ["COMSPEC_ATAK_DroneSeqRun", _run];
    [format ["Suivi des cibles : %1/%2, LOCK %3 (%4), %5", _i + 1, count _seq, [_k] call _numOf, _e get "name", ["orbite autour d'elle", "filature"] select _follow]] call _log;
};
private _stop = {
    params [["_quiet", false], ["_why", "arrêté"]];
    if !(_run select 0) exitWith {};
    _run = [false, 0, 0, 0, [], ""];
    missionNamespace setVariable ["COMSPEC_ATAK_DroneSeqRun", _run];
    if (!_quiet) then {
        ["sendRaw", ["hover"]] call comspec_atak_native_fnc_droneAction;
        [format ["Suivi des cibles %1 : stationnaire", _why]] call _log;
    } else { [format ["Suivi des cibles %1", _why]] call _log; };
    call _render;
};

switch (_act) do {
    case "list": { _seq };
    case "status": {
        _run params ["_on", "_i", "_t0", "", "", ["_k", ""]];
        private _dw = _s getOrDefault ["droneSeqDwell", 60];
        [_on, _i, _k, [-1, (round (_dw - (time - _t0))) max 0] select (_dw > 0)]
    };
    case "toggle": {
        if !(_arg in _locks) exitWith {};
        if (_arg in _seq) then {
            _seq deleteAt (_seq find _arg);
            if ((_run select 0) && {(_run param [5, ""]) isEqualTo _arg}) then { _run set [5, ""]; };
        } else { _seq pushBack _arg; };
        missionNamespace setVariable ["COMSPEC_ATAK_DroneSeq", _seq];
        call _render;
    };
    case "move": {
        _arg params [["_k", ""], ["_dir", 1]];
        private _i = _seq find _k;
        private _j = _i + _dir;
        if (_i < 0 || {_j < 0} || {_j >= count _seq}) exitWith {};
        _seq set [_i, _seq select _j];
        _seq set [_j, _k];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneSeq", _seq];
        if (_run select 0) then { _run set [1, _seq find (_run param [5, ""])]; };
        call _render;
    };
    case "kind";
    case "rad";
    case "dwell": {
        _s set [(createHashMapFromArray [["kind", "droneSeqKind"], ["rad", "droneSeqRad"], ["dwell", "droneSeqDwell"]]) get _act, _arg];
        // Suivi en cours : la cible en cours est renvoyée avec le nouveau réglage (le temps passé dessus repart).
        if ((_run select 0) && {_act isNotEqualTo "dwell"}) then {
            private _i = _seq find (_run param [5, ""]);
            if (_i >= 0) then { [_i] call _go; };
        };
        call _render;
    };
    case "start": {
        if (isNull (missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull])) exitWith {};
        if ((count _seq) isEqualTo 0) exitWith { ["WARNING", "Choisissez d'abord les cibles à suivre (bouton AJOUTER de chaque cible)"] call _say; };
        private _i = [-1] call _nextIdx;
        if (_i < 0) exitWith { ["WARNING", "Toutes les cibles choisies sont neutralisées"] call _say; };
        [_i] call _go;
        ["SUCCESS", format ["Suivi de %1 cible(s) lancé", count _seq]] call _say;
        call _render;
    };
    case "next": {
        if !(_run select 0) exitWith { ["start"] call comspec_atak_native_fnc_droneSeq; };
        private _i = [(_seq find (_run param [5, ""])) max ((_run select 1) - 1)] call _nextIdx;
        if (_i < 0) exitWith { [false, "terminé : plus de cible à suivre"] call _stop; };
        [_i] call _go;
        call _render;
    };
    case "stop": { [_silent, ["arrêté", "repris en main par le pilote"] select _silent] call _stop; };
    case "tick": {
        if !(_run select 0) exitWith {};
        if (isNull (missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull])) exitWith { [true, "arrêté : plus de drone"] call _stop; };
        if ((count _seq) isEqualTo 0) exitWith { [false, "terminé : liste vide"] call _stop; };
        _run params ["", "_i0", "_t0", "_tSend", "_c", ["_k", ""]];
        private _cur = _seq find _k;
        // Cible en cours libérée ou retirée de la liste : on passe à celle qui la suivait.
        if (_cur < 0) exitWith {
            private _n = [((_i0 min (count _seq)) - 1) max -1] call _nextIdx;
            if (_n < 0) exitWith { [false, "terminé : plus de cible à suivre"] call _stop; };
            [_n] call _go;
            call _render;
        };
        private _e = _locks get _k;
        private _st = _e get "state";
        private _dw = _s getOrDefault ["droneSeqDwell", 60];
        private _why = switch (true) do {
            case (_st isEqualTo "DOWN"): { "neutralisée" };
            case (_st isEqualTo "LOST" && {(time - (_e get "lostAt")) > 20}): { "perdue depuis 20 s" };
            case (_dw > 0 && {(time - _t0) > _dw}): { "" };
            default { "-" };
        };
        if (_why isNotEqualTo "-") exitWith {
            private _n = [_cur] call _nextIdx;
            if (_n < 0) exitWith { [false, "terminé : toutes les cibles sont neutralisées"] call _stop; };
            // Une seule cible encore à suivre : on reste dessus.
            if (_n isEqualTo _cur && {_why isEqualTo ""}) exitWith { _run set [2, time]; };
            if (_why isNotEqualTo "") then { [format ["LOCK %1 %2 : cible suivante", [_k] call _numOf, _why], "#f2ab33"] call _log; };
            [_n] call _go;
            call _render;
        };
        // Orbite autour d'une cible qui se déplace : centre recalé (au plus toutes les 8 s) dès qu'elle s'en éloigne de 40 m.
        private _o = _e get "obj";
        private _orbit = ((missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull]) getVariable ["COMSPEC_DroneMode", ""]) isEqualTo "OBSERVE";
        if (_orbit && {_st isEqualTo "TRACK"} && {alive _o} && {(time - _tSend) > 8} && {(_o distance2D _c) > 40}) then {
            ["sendRaw", ["observe", [getPosASL _o, _s getOrDefault ["droneSeqRad", 150]]]] call comspec_atak_native_fnc_droneAction;
            missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", format ["LOCK:%1", _k]];
            _run set [3, time];
            _run set [4, getPosASL _o];
        };
    };
};
