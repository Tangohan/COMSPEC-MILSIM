/*
    Actions du rédacteur d'ordre (fn_pageOrderCompose).
      "open" / "cancel" : ouvre ou ferme le rédacteur     "set", clé, valeur : choix d'un segment
      "grid" : ma position dans la grille                 "send" : envoie l'ordre (Overwatch connect)
*/
params [["_action", ""], ["_key", ""], ["_value", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _d = uiNamespace getVariable ["COMSPEC_ATAK_OrderDraft", createHashMap];
private _keep = {
    // Garde la saisie avant de redessiner la page.
    private _form = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
    { if (_x in _form) then { _d set [_x, [_x] call comspec_atak_native_fnc_formValue]; }; } forEach ["target", "grid", "text", "sit", "mis", "exe", "sup", "cmd"];
    uiNamespace setVariable ["COMSPEC_ATAK_OrderDraft", _d];
};
private _redraw = { [{ ["TASK"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
switch (toLower _action) do {
    case "open": {
        if !([] call comspec_atak_native_fnc_bridge) exitWith { ["WARNING", "Ordres indisponibles : Overwatch connect absent", 3, 30] call comspec_atak_native_fnc_notify; };
        if !([] call comspec_overwatch_connect_fnc_canIssueOrder) exitWith { ["WARNING", "Seul le chef d'unité peut émettre un ordre", 3, 30] call comspec_atak_native_fnc_notify; };
        uiNamespace setVariable ["COMSPEC_ATAK_OrderHint", ""];
        _s set ["orderCompose", true];
        call _redraw;
    };
    case "cancel": { _s set ["orderCompose", false]; call _redraw; };
    case "set": { call _keep; _d set [_key, _value]; call _redraw; };
    case "grid": { call _keep; _d set ["grid", [player, 8] call comspec_atak_native_fnc_gridRef]; call _redraw; };
    case "send": {
        call _keep;
        private _kind = _d getOrDefault ["kind", "MOVE"];
        private _prio = _d getOrDefault ["prio", "ROUTINE"];
        private _tgt = (_d getOrDefault ["target", format ["group|%1|%1", groupId group player]]) splitString "|";
        private _tType = _tgt param [0, "group"];
        private _tLabel = _tgt param [2, _tgt param [1, ""]];
        private _grid = trim (_d getOrDefault ["grid", ""]);
        private _payload = if (_kind isEqualTo "FRAGO") then {
            private _parts = [];
            { _x params ["_k", "_lab"]; private _v = trim (_d getOrDefault [_k, ""]); if (_v isNotEqualTo "") then { _parts pushBack format ["%1: %2", _lab, _v]; }; } forEach [["sit", "Situation"], ["mis", "Mission"], ["exe", "Exécution"], ["sup", "Soutien"], ["cmd", "Commandement"]];
            _parts joinString " — "
        } else { trim (_d getOrDefault ["text", ""]) };
        if (_payload isEqualTo "") exitWith {
            uiNamespace setVariable ["COMSPEC_ATAK_OrderHint", ["Écrivez la consigne de l'ordre.", "Renseignez au moins une rubrique du FRAGO."] select (_kind isEqualTo "FRAGO")];
            call _redraw;
        };
        if (_grid isNotEqualTo "") then { _payload = format ["%1 — Grille: %2", _payload, _grid]; };
        private _target = if (_tType isEqualTo "all") then { "" } else { _tLabel };
        private _order = [_kind, _target, _payload, _prio, "", _tType] call comspec_overwatch_connect_fnc_issueOrder;
        if (_kind isEqualTo "FRAGO" && {!isNil "comspec_overwatch_connect_fnc_sendTacticalAlert"}) then {
            private _oid = if (_order isEqualType createHashMap) then { _order getOrDefault ["id", ""] } else { "" };
            ["FRAGO", [_payload, format ["ORDER_ID=%1|%2", _oid, _payload]] select (_oid isNotEqualTo ""), getPos player] call comspec_overwatch_connect_fnc_sendTacticalAlert;
        };
        ["INFO", format ["Ordre envoyé à %1", ["tout le monde", _tLabel] select (_tType isNotEqualTo "all")], 3, 30] call comspec_atak_native_fnc_notify;
        uiNamespace setVariable ["COMSPEC_ATAK_OrderDraft", createHashMapFromArray [["kind", _kind], ["prio", _prio], ["target", _d getOrDefault ["target", ""]]]];
        _s set ["orderCompose", false];
        call _redraw;
    };
};
true
