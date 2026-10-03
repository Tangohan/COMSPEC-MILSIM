/* Actions de l'app Alertes. Params : [action, argument] */
params ["_action", ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "ALERTS") then { ["ALERTS"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
uiNamespace setVariable ["COMSPEC_ATAK_AlertsRerender", _render];
private _bridge = [] call comspec_atak_native_fnc_bridge && {!isNil "comspec_overwatch_connect_fnc_sendTacticalAlert"};
private _me = [player, true] call comspec_atak_native_fnc_unitCallsign;
private _grid = [getPosASL player, 8] call comspec_atak_native_fnc_gridRef;
private _saveDraft = {
    private _d = uiNamespace getVariable ["COMSPEC_ATAK_SaluteDraft", createHashMap];
    { private _c = [_x] call comspec_atak_native_fnc_formValue; if (_c isEqualType "") then { _d set [_x, _c]; }; } forEach ["sS", "sA", "sL", "sU", "sT", "sE"];
    uiNamespace setVariable ["COMSPEC_ATAK_SaluteDraft", _d];
};
switch (_action) do {
    case "panic": {
        if (diag_tickTime >= (_s getOrDefault ["panicArmedUntil", -1])) exitWith {
            _s set ["panicArmedUntil", diag_tickTime + 5];
            call _saveDraft;
            call _render;
            [{ call (uiNamespace getVariable ["COMSPEC_ATAK_AlertsRerender", {}]); }, [], 5.1] call CBA_fnc_waitAndExecute;
        };
        _s set ["panicArmedUntil", -1];
        // Téléphones alliés du camp : alerte immédiate, même sans Athena.
        ["comspec_atak_native_panic", [_me, getPosASL player, _grid], allPlayers select { side group _x isEqualTo side group player && {_x isNotEqualTo player} }] call CBA_fnc_targetEvent;
        if (_bridge) then { ["PANIC", format ["PANIQUE — %1 en détresse", _me], getPos player] call comspec_overwatch_connect_fnc_sendTacticalAlert; };
        _s set ["alertsHint", format ["PANIQUE envoyée à %1 · %2", ["le camp (Athena hors ligne)", "le camp et au poste"] select _bridge, _grid]];
        ["WARNING", "PANIQUE envoyée", 5, 50] call comspec_atak_native_fnc_notify;
        call _saveDraft;
        call _render;
    };
    case "quick": {
        private _label = switch (_arg) do { case "TIC": { "Contact" }; case "TIC_CLEAR": { "Fin de contact" }; default { "Appareil abattu" }; };
        // Téléphones du camp par le réseau simulé (marche sans Athena), puis le poste web si Overwatch est là.
        [{ ["comspec_atak_native_alert", _this] call CBA_fnc_globalEvent; }, [_arg, _me, getPosASL player, _grid, "", str side group player], _label, 1] call comspec_atak_native_fnc_netSend;
        if (_bridge) then { [_arg, format ["%1 — %2", _label, _me], getPos player] call comspec_overwatch_connect_fnc_sendTacticalAlert; };
        [_arg, "Moi", getPosASL player, _grid, ""] call comspec_atak_native_fnc_alertsLog;
        _s set ["alertsHint", format ["%1 envoyé %2 · %3 · %4", _label, ["au camp", "au camp et au poste"] select _bridge, _grid, [daytime, "HH:MM"] call BIS_fnc_timeToString]];
        call _saveDraft;
        call _render;
    };
    case "salute": {
        call _saveDraft;
        private _d = uiNamespace getVariable ["COMSPEC_ATAK_SaluteDraft", createHashMap];
        private _parts = [];
        { private _val = trim (_d getOrDefault ["s" + _x, ""]); if (_val isNotEqualTo "") then { _parts pushBack format ["%1=%2", _x, _val]; }; } forEach ["S", "A", "L", "U", "T", "E"];
        if ((count _parts) < 2) exitWith { ["WARNING", "SALUTE : remplissez au moins la taille ou l'activité", 4, 30] call comspec_atak_native_fnc_notify; };
        // Lieu : la grille saisie sert de position si elle se lit, sinon la mienne.
        private _pos = getPos player;
        private _lg = (_d getOrDefault ["sL", ""]) splitString " ";
        if ((count _lg) > 0) then { private _p = [_lg joinString ""] call BIS_fnc_gridToPos; if ((_p param [0, []]) isEqualType [] && {(count (_p select 0)) >= 2}) then { _pos = _p select 0; }; };
        if (_bridge) then { ["SALUTE", _parts joinString "|", _pos] call comspec_overwatch_connect_fnc_sendTacticalAlert; };
        private _txt = ((_parts apply { _x splitString "=" }) apply { format ["%1 %2", _x select 0, (_x select [1, 9]) joinString "="] }) joinString " · ";
        private _sg = [_pos, 8] call comspec_atak_native_fnc_gridRef;
        [{ ["comspec_atak_native_alert", _this] call CBA_fnc_globalEvent; }, ["SALUTE", _me, ATLToASL [_pos select 0, _pos select 1, 0], _sg, _txt, str side group player], "SALUTE", 1] call comspec_atak_native_fnc_netSend;
        ["SALUTE", "Moi", ATLToASL [_pos select 0, _pos select 1, 0], _sg, _txt] call comspec_atak_native_fnc_alertsLog;
        _s set ["alertsHint", format ["SALUTE envoyé %1 · %2", ["au camp", "au camp et au poste"] select _bridge, [daytime, "HH:MM"] call BIS_fnc_timeToString]];
        uiNamespace setVariable ["COMSPEC_ATAK_SaluteDraft", createHashMap];
        call _render;
    };
    case "map": {
        private _e = (missionNamespace getVariable ["COMSPEC_ATAK_AlertLog", []]) param [_arg, []];
        if ((count _e) < 3) exitWith {};
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.05] call comspec_atak_native_fnc_mapCenter; }, [(_e select 2) select 0, (_e select 2) select 1]] call CBA_fnc_execNextFrame;
    };
    case "clearLog": { missionNamespace setVariable ["COMSPEC_ATAK_AlertLog", []]; call _render; };
    case "saluteClear": { uiNamespace setVariable ["COMSPEC_ATAK_SaluteDraft", createHashMap]; _s set ["alertsHint", ""]; call _render; };
};
true
