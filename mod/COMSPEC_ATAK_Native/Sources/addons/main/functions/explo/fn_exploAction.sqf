/*
    Actions de l'app Explosifs.
      "safety"            lève / remet la sécurité de mise à feu
      "sel", clé          ajoute / retire une charge de la séquence      "selAll" / "selNone"
      "delay", s          délai avant la première charge                 "gap", s : intervalle entre charges
      "fire", clé         met à feu une charge (sécurité levée)          "seq" : lance la séquence choisie
      "cancel"            annule ce qui n'a pas encore sauté             "map", clé : montre la charge sur la carte
      "tick"              (interne) détone les charges arrivées à échéance
*/
params [["_act", ""], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Explo", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Explo", _s];
private _sel = _s getOrDefault ["sel", []];
_s set ["sel", _sel];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "EXPLO") then { ["EXPLO"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _find = { params ["_k"]; private _l = ([true] call comspec_atak_native_fnc_exploList) select { (_x get "key") isEqualTo _k }; [createHashMap, _l select 0] select ((count _l) > 0) };
// Détonation d'une charge : par Overwatch quand elle a un identifiant Athena (le poste est prévenu), sinon par ACE, sinon vanilla.
private _boom = {
    params ["_c"];
    private _e = _c getOrDefault ["obj", objNull];
    if (isNull _e || {!alive _e}) exitWith { false };
    private _cid = _c getOrDefault ["cid", ""];
    if (_cid isNotEqualTo "" && {!isNil "comspec_overwatch_connect_fnc_detonateChargeById"}) exitWith { [_cid] call comspec_overwatch_connect_fnc_detonateChargeById };
    _e setVariable ["COMSPEC_atakFireOk", true, true];
    if (!isNil "ace_explosives_fnc_detonateExplosive") exitWith { [player, -1, [_e, 0], "#scripted"] call ace_explosives_fnc_detonateExplosive; true };
    if (local _e) then { _e setDamage 1; } else { [_e, 1] remoteExecCall ["setDamage", _e]; };
    true
};
private _safe = { if !(_s getOrDefault ["armed", false]) exitWith { ["WARNING", "Levez d'abord la sécurité de mise à feu", 3, 30] call comspec_atak_native_fnc_notify; true }; false };
switch (_act) do {
    case "safety": { _s set ["armed", !(_s getOrDefault ["armed", false])]; call _render; };
    case "sel": { if (_arg in _sel) then { _sel deleteAt (_sel find _arg); } else { _sel pushBack _arg; }; call _render; };
    case "selAll": { _s set ["sel", (([] call comspec_atak_native_fnc_exploList) select { (_x get "kind") isNotEqualTo "timer" }) apply { _x get "key" }]; call _render; };
    case "selNone": { _s set ["sel", []]; call _render; };
    case "delay": { _s set ["delay", _arg]; call _render; };
    case "gap": { _s set ["gap", _arg]; call _render; };
    case "map": {
        private _c = [_arg] call _find;
        if ((count _c) isEqualTo 0) exitWith {};
        private _p = getPosASL (_c get "obj");
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.02] call comspec_atak_native_fnc_mapCenter; }, [_p select 0, _p select 1]] call CBA_fnc_execNextFrame;
    };
    case "fire": {
        if (call _safe) exitWith {};
        private _c = [_arg] call _find;
        if ((count _c) isEqualTo 0) exitWith { ["WARNING", "Charge introuvable (déjà sautée ?)", 3, 20] call comspec_atak_native_fnc_notify; call _render; };
        if ([_c] call _boom) then { ["SUCCESS", format ["Mise à feu : %1", _c get "label"], 3, 40] call comspec_atak_native_fnc_notify; };
        uiNamespace setVariable ["COMSPEC_ATAK_ExploCache", [-1, []]];
        [{ ["redraw"] call comspec_atak_native_fnc_exploAction; }, [], 1.6] call CBA_fnc_waitAndExecute;
    };
    case "redraw": { call _render; };
    case "seq": {
        if (call _safe) exitWith {};
        private _list = ([true] call comspec_atak_native_fnc_exploList) select { ((_x get "key") in _sel) && {(_x get "kind") isNotEqualTo "timer"} };
        if ((count _list) isEqualTo 0) exitWith { ["WARNING", "Cochez au moins une charge pour la séquence", 3, 30] call comspec_atak_native_fnc_notify; };
        private _t0 = diag_tickTime + (_s getOrDefault ["delay", 5]);
        private _gap = _s getOrDefault ["gap", 1];
        private _queue = [];
        { _queue pushBack [_t0 + _forEachIndex * _gap, _x]; } forEach _list;
        _s set ["queue", _queue];
        _s set ["seqStart", diag_tickTime];
        _s set ["seqEnd", _t0 + ((count _queue) - 1) * _gap];
        ["WARNING", format ["Séquence lancée : %1 charge(s), première dans %2 s", count _queue, _s getOrDefault ["delay", 5]], 4, 60] call comspec_atak_native_fnc_notify;
        if ((_s getOrDefault ["pfh", -1]) < 0) then {
            _s set ["pfh", [{ ["tick"] call comspec_atak_native_fnc_exploAction; }, 0.1] call CBA_fnc_addPerFrameHandler];
        };
        call _render;
    };
    case "cancel": {
        private _n = count (_s getOrDefault ["queue", []]);
        _s set ["queue", []];
        ["INFO", format ["Séquence annulée : %1 charge(s) non mises à feu", _n], 3, 40] call comspec_atak_native_fnc_notify;
        call _render;
    };
    case "tick": {
        private _queue = _s getOrDefault ["queue", []];
        private _due = _queue select { diag_tickTime >= (_x select 0) };
        if ((count _due) > 0) then {
            { [_x select 1] call _boom; } forEach _due;
            _s set ["queue", _queue - _due];
            uiNamespace setVariable ["COMSPEC_ATAK_ExploCache", [-1, []]];
        };
        // Compte à rebours de la page : deux fois par seconde.
        if (diag_tickTime >= (_s getOrDefault ["nextDraw", 0])) then { _s set ["nextDraw", diag_tickTime + 0.5]; call _render; };
        if ((count (_s getOrDefault ["queue", []])) isEqualTo 0) then {
            [_s getOrDefault ["pfh", -1]] call CBA_fnc_removePerFrameHandler;
            _s set ["pfh", -1];
            _s set ["armed", false];
            _s set ["sel", []];
            ["SUCCESS", "Séquence terminée, sécurité remise", 3, 40] call comspec_atak_native_fnc_notify;
            [{ ["redraw"] call comspec_atak_native_fnc_exploAction; }, [], 1.6] call CBA_fnc_waitAndExecute;
        };
    };
};
true
