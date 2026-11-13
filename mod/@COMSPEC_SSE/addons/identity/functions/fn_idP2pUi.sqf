/*
    Fenêtre des papiers entre joueurs (dialogs/idP2p.hpp). État dans uiNamespace comspec_sse_idP2p (jamais diffusé).
    [_op, _arg] call comspec_sse_fnc_idP2pUi
      open [mode, données]   mode : request | present | wait | received. Données (HashMap) : peer (l'autre personne),
                             end (fin du délai, horloge locale), canRefuse, serial, docs [[docId, libellé…]…], how
                             (show | give), pre (classe cochée d'avance), result (refused | timeout), reason
      load display · unload [display, code] · render · tick (délai affiché, 4 fois par seconde, fenêtre ouverte seulement)
      toggle i (case d'un papier) · dbl i (double clic : ouvrir) · btn 0 | 1 | 2 · status [clé ou texte, ton] · done
    Échap sur une demande = refus (si la mission le permet). Aucune alerte Arma : tout se lit dans la fenêtre.
*/
params [["_op", "render", [""]], ["_a", []]];
if (!hasInterface) exitWith { false };
disableSerialization;
private _L = { localize ("STR_COMSPEC_SSE_P2P_" + _this) };
private _esc = { private _t = _this; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _col = createHashMapFromArray [["ok", "#7fd08a"], ["warn", "#f2c94c"], ["bad", "#e5675a"], ["muted", "#9fb0a8"], ["text", "#e6ede6"]];

if (_op isEqualTo "open") exitWith {
    _a params [["_mode", "present", [""]], ["_extra", createHashMap, [createHashMap]]];
    private _new = createHashMapFromArray [["mode", _mode], ["peer", objNull], ["status", ["", "muted"]], ["options", _mode isEqualTo "present"], ["checked", []], ["init", false], ["done", false], ["canRefuse", true], ["end", -1]];
    _new merge [_extra, true];
    uiNamespace setVariable ["comspec_sse_idP2p", _new];
    if (!isNull (uiNamespace getVariable ["comspec_sse_idP2pDisplay", displayNull])) exitWith { ["render"] call comspec_sse_fnc_idP2pUi; true };
    if (!isNil "comspec_atak_native_fnc_display" && {!isNull ([] call comspec_atak_native_fnc_display)}) then { [] call comspec_atak_native_fnc_close; };
    [{ closeDialog 0; createDialog "COMSPEC_SSE_IdP2p"; }] call CBA_fnc_execNextFrame;
    true
};
private _st = uiNamespace getVariable ["comspec_sse_idP2p", createHashMap];
if (_op isEqualTo "unload") exitWith {
    _a params [["_disp", displayNull], ["_code", 0]];
    [uiNamespace getVariable ["comspec_sse_idP2pPfh", -1]] call CBA_fnc_removePerFrameHandler;
    uiNamespace setVariable ["comspec_sse_idP2pDisplay", displayNull];
    // Échap (ou la croix) sur une demande à laquelle je n'ai pas répondu : refus, sans motif.
    if ((_st getOrDefault ["mode", ""]) isEqualTo "request" && {_code isEqualTo 2} && {!(_st getOrDefault ["done", false])} && {_st getOrDefault ["canRefuse", true]}) then {
        ["answer", [_st getOrDefault ["peer", objNull], "refuse", [], "show", ""]] call comspec_sse_fnc_idP2pClient;
    };
    _st set ["mode", ""];
    true
};
if (_op isEqualTo "load") then {
    uiNamespace setVariable ["comspec_sse_idP2pDisplay", _a];
    uiNamespace setVariable ["comspec_sse_idP2pPfh", [{ ["tick"] call comspec_sse_fnc_idP2pUi; }, 0.25, []] call CBA_fnc_addPerFrameHandler];
};
private _d = uiNamespace getVariable ["comspec_sse_idP2pDisplay", displayNull];
if (isNull _d || {(count _st) isEqualTo 0}) exitWith { false };
private _c = { _d displayCtrl _this };
private _mode = _st getOrDefault ["mode", "present"];
private _peer = _st getOrDefault ["peer", objNull];
private _who = [name _peer, ("Someone" call _L)] select (isNull _peer);
private _picking = _mode in ["request", "present"];
private _setStatus = {
    (_st getOrDefault ["status", ["", "muted"]]) params [["_t", ""], ["_tone", "muted"]];
    (95204 call _c) ctrlSetStructuredText parseText format ["<t color='%1'>%2</t>", _col getOrDefault [_tone, _col get "muted"], _t call _esc];
};
private _left = { ((_st getOrDefault ["end", -1]) - time) max 0 };
private _setTimer = {
    private _t = if ((_st getOrDefault ["end", -1]) < 0 || {(_st getOrDefault ["result", ""]) isNotEqualTo ""}) then { "" } else { format ["%1 s", ceil (call _left)] };
    (95202 call _c) ctrlSetText _t;
};
private _mark = {
    params ["_i", "_on"];
    (95213 call _c) lbSetPicture [_i, ["\a3\ui_f\data\gui\rsccommon\rsccheckbox\checkbox_unchecked_ca.paa", "\a3\ui_f\data\gui\rsccommon\rsccheckbox\checkbox_checked_ca.paa"] select _on];
};

switch (_op) do {
    case "status": {
        _a params [["_t", ""], ["_tone", "muted"]];
        _st set ["status", [_t, _tone]];
        _st set ["busyAt", -10];
        call _setStatus;
    };
    case "tick": {
        call _setTimer;
        // Délai écoulé sans réponse : la fenêtre de la demande se ferme (le contrôleur lit « pas de réponse »).
        if (_mode isEqualTo "request" && {(_st getOrDefault ["end", -1]) >= 0} && {(call _left) <= 0}) then {
            _st set ["done", true];
            closeDialog 0;
        };
    };
    case "done": { _st set ["done", true]; closeDialog 0; };
    case "toggle": {
        if (!_picking || {!(_a isEqualType 0)} || {_a < 0}) exitWith {};
        private _lb = 95213 call _c;
        private _cls = _lb lbData _a;
        private _chk = _st get "checked";
        if (_cls in _chk) then { _chk deleteAt (_chk find _cls); } else { _chk pushBack _cls; };
        [_a, _cls in _chk] call _mark;
        // Sélection retirée : un second clic sur la même ligne redonne l'évènement (décocher).
        _lb lbSetCurSel -1;
        (95230 call _c) ctrlEnable (_chk isNotEqualTo []);
    };
    case "dbl": { if (_mode isEqualTo "received") then { ["btn", 0] call comspec_sse_fnc_idP2pUi; }; };
    case "btn": {
        private _how = ["show", "give"] select ((lbCurSel (95220 call _c)) isEqualTo 1);
        switch (true) do {
            case (_a isEqualTo 2): { _st set ["options", !(_st getOrDefault ["options", false])]; ["render"] call comspec_sse_fnc_idP2pUi; };
            case (_a isEqualTo 1): {
                if (_mode isEqualTo "request" && {_st getOrDefault ["canRefuse", true]}) exitWith {
                    private _rc = 95221 call _c;
                    private _reason = if ((lbCurSel _rc) < 0) then { "" } else { _rc lbData (lbCurSel _rc) };
                    _st set ["done", true];
                    ["answer", [_peer, "refuse", [], "show", _reason]] call comspec_sse_fnc_idP2pClient;
                    closeDialog 0;
                };
                closeDialog 2;
            };
            // Envoi en cours (3 s au plus : une réponse perdue ne bloque pas la fenêtre).
            case ((time - (_st getOrDefault ["busyAt", -10])) < 3): {};
            case (_mode isEqualTo "received"): {
                private _lb = 95213 call _c;
                private _id = _lb lbData ((lbCurSel _lb) max 0);
                if (_id isEqualTo "") exitWith {};
                _st set ["busyAt", time];
                _st set ["status", [("S_Opening" call _L), "muted"]];
                call _setStatus;
                ["view", [_id]] call comspec_sse_fnc_idP2pClient;
            };
            case (_picking): {
                private _chk = +(_st get "checked");
                if (_chk isEqualTo []) exitWith { ["status", [("S_PickPaper" call _L), "warn"]] call comspec_sse_fnc_idP2pUi; };
                if (_mode isEqualTo "present") then {
                    private _tc = 95211 call _c;
                    _peer = objectFromNetId ([_tc lbData (lbCurSel _tc), ""] select ((lbCurSel _tc) < 0));
                    _st set ["peer", _peer];
                };
                if (isNull _peer) exitWith { ["status", [("S_PickPerson" call _L), "warn"]] call comspec_sse_fnc_idP2pUi; };
                _st set ["status", [("S_Sending" call _L), "muted"]];
                call _setStatus;
                _st set ["busyAt", time];
                if (_mode isEqualTo "request") then {
                    ["answer", [_peer, "accept", _chk, _how, ""]] call comspec_sse_fnc_idP2pClient;
                } else {
                    ["send", ["p2pPresent", [player, _peer, _chk, _how]]] call comspec_sse_fnc_idClient;
                };
            };
        };
    };
    default {
        // --- load / render -------------------------------------------------------------------------------------
        private _max = ["distance", missionNamespace getVariable ["comspec_sse_id_p2pDistance", 3]] call comspec_sse_fnc_idP2pRules;
        private _result = _st getOrDefault ["result", ""];
        private _docs = _st getOrDefault ["docs", []];
        private _mine = if (_picking) then { ([player, 0] call comspec_sse_fnc_idDocsOf) apply { _x select 0 } } else { [] };
        if (_picking && {!(_st getOrDefault ["init", false])}) then {
            _st set ["init", true];
            private _pre = _st getOrDefault ["pre", ""];
            _st set ["checked", if (_pre in _mine) then { [_pre] } else { [[], +_mine] select ((count _mine) isEqualTo 1) }];
        };
        private _chk = _st get "checked";
        _st set ["checked", _chk select { _x in _mine }];
        _chk = _st get "checked";

        (95201 call _c) ctrlSetText (((["T_Present", "T_Request", "T_Wait", "T_Received"] select ((["request", "wait", "received"] find _mode) + 1)) call _L));
        private _body = switch (_mode) do {
            case "request": {
                format ["<t color='%1'>%2</t><br/><t color='%3'>%4</t>", _col get "text", (format [("B_Request" call _L), _who]) call _esc, _col get "muted",
                    (((["B_RequestHow", "B_RequestNoRefuse", "B_NoPapers"] select ([[0, 1] select !(_st getOrDefault ["canRefuse", true]), 2] select (_mine isEqualTo []))) call _L)) call _esc]
            };
            case "wait": {
                switch (_result) do {
                    case "refused": {
                        private _why = _st getOrDefault ["reason", ""];
                        format ["<t color='%1'>%2</t><br/><t color='%3'>%4</t>", _col get "bad", (("R_Refused" call _L)) call _esc, _col get "muted",
                            ([format [("R_Reason" call _L), (("Reason_" + _why) call _L)], format [("R_RefusedBy" call _L), _who]] select (_why isEqualTo "")) call _esc]
                    };
                    case "denied": { format ["<t color='%1'>%2</t>", _col get "warn", (_st getOrDefault ["text", ""]) call _esc] };
                    case "timeout": { format ["<t color='%1'>%2</t><br/><t color='%3'>%4</t>", _col get "warn", (("R_Timeout" call _L)) call _esc, _col get "muted", (format [("R_TimeoutBy" call _L), _who]) call _esc] };
                    default { format ["<t color='%1'>%2</t><br/><t color='%3'>%4</t>", _col get "text", (format [("B_Wait" call _L), _who]) call _esc, _col get "muted", (("B_WaitHow" call _L)) call _esc] };
                }
            };
            case "received": {
                format ["<t color='%1'>%2</t><br/><t color='%3'>%4</t>", _col get "text",
                    (format [((["B_Received", "B_Given"] select ((_st getOrDefault ["how", "show"]) isEqualTo "give")) call _L), _who, count _docs]) call _esc, _col get "muted", (("B_ReceivedHow" call _L)) call _esc]
            };
            default { format ["<t color='%1'>%2</t>", _col get "muted", (format [((["B_Present", "B_NoPapers"] select (_mine isEqualTo [])) call _L), _max]) call _esc] };
        };
        (95203 call _c) ctrlSetStructuredText parseText _body;

        // Liste : mes papiers à cocher, ou les papiers qu'on me tend.
        private _lb = 95213 call _c;
        lbClear _lb;
        if (_picking) then {
            {
                private _i = _lb lbAdd getText (configFile >> "CfgWeapons" >> _x >> "displayName");
                _lb lbSetData [_i, _x];
                [_i, _x in _chk] call _mark;
            } forEach _mine;
        } else {
            {
                private _i = _lb lbAdd (_x param [1, ""]);
                _lb lbSetData [_i, _x param [0, ""]];
                _lb lbSetPicture [_i, "\z\comspec_sse\addons\identity\data\icon_card_ca.paa"];
            } forEach _docs;
            if (_docs isNotEqualTo []) then { _lb lbSetCurSel 0; };
        };
        _lb ctrlShow (_mode isNotEqualTo "wait");

        // Destinataire (présentation d'initiative) : personnes à portée et en vue, la personne visée d'abord.
        private _tc = 95211 call _c;
        if (_mode isEqualTo "present") then {
            lbClear _tc;
            private _near = ["near"] call comspec_sse_fnc_idP2pClient;
            {
                private _i = _tc lbAdd format ["%1 · %2 m", name _x, (round ((player distance _x) * 10)) / 10];
                _tc lbSetData [_i, netId _x];
            } forEach _near;
            if (_near isEqualTo []) then { _tc lbAdd (("NoOneNear" call _L)); };
            _tc lbSetCurSel ((_near find _peer) max 0);
        };
        { (_x call _c) ctrlShow (_mode isEqualTo "present"); } forEach [95210, 95211];

        // Options : montrer ou remettre en main, motif d'un refus. Remplies une fois (le choix est gardé).
        private _mc = 95220 call _c;
        if ((lbSize _mc) isEqualTo 0) then {
            { _mc lbAdd ((_x call _L)); } forEach ["Mode_Show", "Mode_Give"];
            _mc lbSetCurSel 0;
            private _rc = 95221 call _c;
            { private _i = _rc lbAdd (((["Reason_none", "Reason_" + _x] select (_x isNotEqualTo "")) call _L)); _rc lbSetData [_i, _x]; } forEach ["", "no_papers", "not_now", "no_authority", "superior"];
            _rc lbSetCurSel 0;
        };
        private _opt = _picking && {_st getOrDefault ["options", false]};
        _mc ctrlShow _opt;
        (95221 call _c) ctrlShow (_opt && {_mode isEqualTo "request"} && {_st getOrDefault ["canRefuse", true]});

        // Boutons.
        private _b0 = 95230 call _c;
        private _b1 = 95231 call _c;
        _b0 ctrlSetText (((["BtnPresent", "BtnAccept", "BtnOpen", "BtnOpen"] select ((["request", "received", "wait"] find _mode) + 1)) call _L));
        _b0 ctrlShow (_mode isNotEqualTo "wait");
        _b0 ctrlEnable ([_docs isNotEqualTo [], _chk isNotEqualTo []] select _picking);
        (95232 call _c) ctrlShow (_mode isEqualTo "request");
        _b1 ctrlSetText (((["BtnClose", "BtnRefuse", "BtnLater"] select ([0, [2, 1] select (_st getOrDefault ["canRefuse", true])] select (_mode isEqualTo "request"))) call _L));

        // Sans liste (wait) : le bas de la fenêtre remonte. Pas de grille en SQF : tout est déduit du fond (20 x 16 cases).
        // Positions d'origine gardées sur la fenêtre (l'état, lui, est refait à chaque changement d'usage).
        private _base = _d getVariable ["comspec_sse_p2pBase", createHashMap];
        if ((count _base) isEqualTo 0) then {
            { _base set [_x, ctrlPosition (_x call _c)]; } forEach [95240, 95204, 95230, 95231, 95232];
            _d setVariable ["comspec_sse_p2pBase", _base];
        };
        private _gh = ((_base get 95240) select 3) / 16;
        private _up = [0, 7.6 * _gh] select (_mode isEqualTo "wait");
        {
            (_base get _x) params ["_px", "_py", "_pw", "_ph"];
            private _k = _x call _c;
            _k ctrlSetPosition ([[_px, _py - _up, _pw, _ph], [_px, _py, _pw, _ph - _up]] select (_x isEqualTo 95240));
            _k ctrlCommit 0;
        } forEach [95240, 95204, 95230, 95231, 95232];
        call _setStatus;
        call _setTimer;
    };
};
true
