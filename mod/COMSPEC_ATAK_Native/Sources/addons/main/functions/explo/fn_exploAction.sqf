/*
    Actions de l'app Explosifs.
      "safety"            lève / remet la sécurité de la séquence            "delay", s : délai avant la première        "gap", s : intervalle
      "sel", clé          ajoute / retire une charge de la séquence          "selAll" / "selNone"
      "toggleArm", clé    arme / désarme une charge réseau                   "armAll" / "safeAll"
      "ask", clé / "askSeq"  ouvre la confirmation                           "askYes" / "askNo" : confirme ou annule
      "fire", clé         met à feu une charge (après confirmation)          "seq" : lance la séquence choisie (sécurité levée ; fn_breachAction l'appelle)
      "cancel"            annule ce qui n'a pas encore sauté                 "map", clé : montre la charge    "share", clé : ping à mon camp
      "can", entrée       -> [peut tirer, motif, précision]                  "changed" : liste à relire après un événement
      "live"              (interne, 1 s, page ouverte) mise à jour sur place, ou page redessinée si la structure a changé
      "tick"              (interne) détone les charges arrivées à échéance
    Aucun tir n'échappe aux règles d'ACE : clacker porté et à portée, droit vérifié par le serveur pour le déclencheur réseau.
*/
params [["_act", ""], ["_arg", ""]];
disableSerialization;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Explo", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Explo", _s];
// uiNamespace survit au changement de mission : une séquence (et ses identifiants de boucle) d'une partie
// précédente bloquait toute nouvelle séquence. La page passe aussi par ici avant de lire l'état.
if (isNil "COMSPEC_ATAK_ExploMissionInit") then {
    COMSPEC_ATAK_ExploMissionInit = true;
    _s set ["queue", []]; _s set ["pfh", -1]; _s set ["armed", false]; _s set ["sel", []]; _s set ["lastTick", -1];
    _s set ["livePfh", -1]; _s set ["liveAt", -1]; _s set ["confirm", createHashMap]; _s set ["sig", []]; _s set ["paths", createHashMap]; _s set ["rr", 0];
    uiNamespace setVariable ["COMSPEC_ATAK_ExploCache", [-1, []]];
};
private _sel = _s getOrDefault ["sel", []];
_s set ["sel", _sel];
private _th = [] call comspec_atak_native_fnc_theme;
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "EXPLO") then { ["EXPLO"] call comspec_atak_native_fnc_pageRender; }; }] call comspec_atak_native_fnc_nextFrame; };
private _find = { params ["_k"]; private _l = ([true] call comspec_atak_native_fnc_exploList) select { (_x get "key") isEqualTo _k }; [createHashMap, _l select 0] select ((count _l) > 0) };
private _isFireable = { params ["_c"]; (_c get "kind") in ["net", "atak", "command", "vanilla"] };
// Charge du jour par sa clé ; repli sur l'objet mémorisé si la liste l'a perdue.
private _resolve = {
    params ["_c"];
    private _f = [_c getOrDefault ["key", ""]] call _find;
    if ((count _f) > 0) exitWith { _f };
    createHashMap
};
// Boucle de séquence : relancée si absente ou muette depuis plus d'une seconde (identifiant périmé).
private _ensureLoop = {
    private _alive = ((_s getOrDefault ["pfh", -1]) >= 0) && {(diag_tickTime - (_s getOrDefault ["lastTick", -1])) < 1};
    if (_alive) exitWith {};
    if ((_s getOrDefault ["pfh", -1]) >= 0) then { [_s get "pfh"] call CBA_fnc_removePerFrameHandler; };
    _s set ["lastTick", diag_tickTime];
    _s set ["pfh", [{ ["tick"] call comspec_atak_native_fnc_exploAction; }, 0.1] call CBA_fnc_addPerFrameHandler];
};
// Liaison de la charge (bilan radio) : gardée 4 s ; "live" en rafraîchit une par seconde au plus.
private _pathOf = {
    params ["_c", ["_fresh", false]];
    private _k = _c get "key";
    private _paths = _s getOrDefault ["paths", createHashMap];
    private _hit = _paths getOrDefault [_k, []];
    if (!_fresh && {_hit isNotEqualTo []}) exitWith { _hit };
    private _r = ["path", [_c get "obj", player]] call comspec_atak_native_fnc_exploNet;
    _paths set [_k, _r];
    _s set ["paths", _paths];
    _r
};
// Mise à feu réelle de la charge : le réseau passe par le serveur, le clacker par l'API d'ACE avec sa vraie portée.
private _boom = {
    params ["_c"];
    private _e = _c getOrDefault ["obj", objNull];
    if (isNull _e || {!alive _e}) exitWith { false };
    (["can", _c] call comspec_atak_native_fnc_exploAction) params ["_can", "_why"];
    if (!_can) exitWith { ["WARNING", format ["%1 : %2", _c get "label", _why], 4, 30] call comspec_atak_native_fnc_notify; false };
    switch (_c get "kind") do {
        case "net": { ["cmd", ["fire", [_e]]] call comspec_atak_native_fnc_exploNet; };
        case "atak": { _e setVariable ["COMSPEC_atakFireOk", true, true]; [_c get "cid"] call comspec_overwatch_connect_fnc_detonateChargeById; };
        case "command": { [player, _c get "range", [_e, _c get "clackFuse"], _c get "det"] call ace_explosives_fnc_detonateExplosive; };
        case "vanilla": { _e setVariable ["COMSPEC_detonateFired", true, true]; triggerAmmo _e; };
    };
    true
};
private _safe = { if !(_s getOrDefault ["armed", false]) exitWith { ["WARNING", "Levez d'abord la sécurité de mise à feu", 3, 30] call comspec_atak_native_fnc_notify; true }; false };
private _closeConfirm = { _s set ["confirm", createHashMap]; };
private _askText = {
    params ["_list", "_extra"];
    private _dim = _th get "hexTextDim";
    private _lines = (_list select [0, 6]) apply {
        private _e = _x get "obj";
        private _d = round (player distance _e);
        private _near = { alive _x && {((side group _x) getFriend (side group player)) >= 0.6} } count (_e nearEntities ["CAManBase", 30]);
        format ["<t font='COMSPEC_BarlowBold'>%1</t> · <t color='%2'>%3 · %4 m · %5°</t>%6", _x get "label", _dim, _x get "grid", _d, round (player getDir _e),
            ["", format ["<br/><t color='%1' font='COMSPEC_BarlowBold'>Attention : %2 ami(s) à moins de 30 m, vous compris</t>", _th get "hexAlert", _near]] select (_near > 0)]
    };
    (_lines joinString "<br/>") + _extra
};
private _seqInfo = {
    private _nSel = { (_x get "key") in _sel } count ([] call comspec_atak_native_fnc_exploList);
    private _t = (_s getOrDefault ["delay", 5]) + ((_nSel - 1) max 0) * (_s getOrDefault ["gap", 1]);
    format ["<t size='0.85' color='%1'>%2 charge(s) cochée(s) · durée prévue %3 s</t>", _th get "hexTextDim", _nSel, _t toFixed 1]
};
private _setText = { params ["_var", "_txt"]; private _c = uiNamespace getVariable [_var, controlNull]; if (!isNull _c) then { _c ctrlSetStructuredText parseText (_txt call comspec_atak_native_fnc_tr); }; };
private _sigOf = {
    params ["_list"];
    [(_list apply { [_x get "key", _x get "kind"] }), (count (_s getOrDefault ["queue", []])) > 0, { _x isEqualTo "COMSPEC_ATAK_NetDetonator" } count (items player)]
};
switch (_act) do {
    case "init": {};
    case "safety": {
        _s set ["armed", !(_s getOrDefault ["armed", false])];
        ["exploSafety", _s get "armed"] call comspec_atak_native_fnc_formSet;
        ["live", true] call comspec_atak_native_fnc_exploAction;
    };
    case "sel": {
        if (_arg in _sel) then { _sel deleteAt (_sel find _arg); } else { _sel pushBack _arg; };
        ["live", true] call comspec_atak_native_fnc_exploAction;
    };
    case "selAll": {
        _s set ["sel", ((([] call comspec_atak_native_fnc_exploList) select { [_x] call _isFireable }) apply { _x get "key" })];
        ["live", true] call comspec_atak_native_fnc_exploAction;
    };
    case "selNone": { _s set ["sel", []]; ["live", true] call comspec_atak_native_fnc_exploAction; };
    case "delay": { _s set ["delay", _arg]; ["exploDelay", _arg] call comspec_atak_native_fnc_formSet; ["live", true] call comspec_atak_native_fnc_exploAction; };
    case "gap": { _s set ["gap", _arg]; ["exploGap", _arg] call comspec_atak_native_fnc_formSet; ["live", true] call comspec_atak_native_fnc_exploAction; };
    case "map": {
        private _c = [_arg] call _find;
        if ((count _c) isEqualTo 0) exitWith {};
        private _p = getPosASL (_c get "obj");
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.02] call comspec_atak_native_fnc_mapCenter; }, [_p select 0, _p select 1]] call comspec_atak_native_fnc_nextFrame;
    };
    case "share": {
        private _c = [_arg] call _find;
        if ((count _c) isEqualTo 0) exitWith {};
        (["phone"] call comspec_atak_native_fnc_exploNet) params ["_ok", "_why"];
        if (!_ok) exitWith { ["WARNING", _why, 3, 20] call comspec_atak_native_fnc_notify; };
        if (diag_tickTime < (_s getOrDefault ["shareAt", -1])) exitWith {};
        _s set ["shareAt", diag_tickTime + 5];
        private _pos = getPosASL (_c get "obj");
        private _to = allPlayers select { side group _x isEqualTo side group player };
        _to pushBackUnique player;
        ["comspec_atak_native_mapPing", [[_pos select 0, _pos select 1, 0], format ["CHARGE AMIE (%1)", name player], player], _to] call CBA_fnc_targetEvent;
        ["TACTICAL", format ["Charge signalée à votre camp · %1", _c get "grid"], 3, 30] call comspec_atak_native_fnc_notify;
    };
    case "toggleArm": {
        private _c = [_arg] call _find;
        if ((count _c) isEqualTo 0 || {(_c get "kind") isNotEqualTo "net"}) exitWith {};
        (["phone"] call comspec_atak_native_fnc_exploNet) params ["_ok", "_why"];
        if (!_ok) exitWith { ["WARNING", _why, 4, 30] call comspec_atak_native_fnc_notify; };
        ["cmd", [["arm", "safe"] select (_c get "armed"), [_c get "obj"]]] call comspec_atak_native_fnc_exploNet;
    };
    case "armAll";
    case "safeAll": {
        (["phone"] call comspec_atak_native_fnc_exploNet) params ["_ok", "_why"];
        if (!_ok) exitWith { ["WARNING", _why, 4, 30] call comspec_atak_native_fnc_notify; };
        private _l = ([true] call comspec_atak_native_fnc_exploList) select { (_x get "kind") isEqualTo "net" && {(_x get "armed") isEqualTo (_act isEqualTo "safeAll")} };
        if (_l isNotEqualTo []) then { ["cmd", [["arm", "safe"] select (_act isEqualTo "safeAll"), _l apply { _x get "obj" }]] call comspec_atak_native_fnc_exploNet; };
    };
    case "can": {
        private _c = _arg;
        private _kind = _c get "kind";
        if !([_c] call _isFireable) exitWith { [false, "", ""] };
        private _e = _c get "obj";
        switch (_kind) do {
            case "vanilla": {
                if (local _e) then { [true, "", "Télécommande du jeu"] } else { [false, "Charge hors de votre commande directe", ""] };
            };
            case "command": {
                private _range = _c get "range";
                private _det = _c get "det";
                private _name = getText (configFile >> "CfgWeapons" >> _det >> "displayName");
                private _d = player distance _e;
                if !(_det in ([player] call ace_explosives_fnc_getDetonators)) exitWith { [false, format ["Il faut %1 dans l'inventaire", [_name, "son clacker"] select (_name isEqualTo "")], ""] };
                if (_d > _range) exitWith { [false, format ["Hors de portée de %1 : %2 m sur %3 m", _name, round _d, round _range], ""] };
                [true, "", format ["%1 · portée %2 m", _name, round _range]]
            };
            default {
                // Réseau : téléphone, puis charge, puis armement.
                (["phone"] call comspec_atak_native_fnc_exploNet) params ["_pOk", "_pWhy"];
                if (!_pOk) exitWith { [false, _pWhy, ""] };
                ([_c] call _pathOf) params ["_ok", "", "_txt", "_mg", "_via"];
                if (!_ok) exitWith { [false, _txt, ""] };
                if (_kind isEqualTo "net" && {!(_c get "armed")}) exitWith { [false, "Désarmée : armez-la pour tirer", ""] };
                [true, "", format ["Liaison par %1 · marge %2 dB", _via, round _mg]]
            };
        };
    };
    case "linkText": {
        (["phone"] call comspec_atak_native_fnc_exploNet) params ["_ok", "_why"];
        format ["<t font='COMSPEC_BarlowBold' color='%1'>● %2</t><t size='0.85' color='%3'>   %4</t>", [_th get "hexAlert", _th get "hexOk"] select _ok, ["SANS LIAISON", "RÉSEAU DISPONIBLE"] select _ok, _th get "hexTextDim", _why]
    };
    case "seqText": {
        private _queue = _s getOrDefault ["queue", []];
        private _lines = _queue apply {
            _x params ["_at", "_c"];
            format ["<t color='%1' font='COMSPEC_BarlowBold'>T-%2 s</t>  %3", _th get "hexAlert", ((_at - diag_tickTime) max 0) toFixed 1, _c get "label"]
        };
        _lines joinString "<br/>"
    };
    case "seqInfo": { call _seqInfo };
    case "seqBtns": {
        private _nSel = { (_x get "key") in _sel } count ([] call comspec_atak_native_fnc_exploList);
        [format ["FEU (%1)", _nSel], (_s getOrDefault ["armed", false]) && {_nSel > 0}]
    };
    case "sigStore": { _s set ["sig", [[] call comspec_atak_native_fnc_exploList] call _sigOf]; };
    case "changed": {
        uiNamespace setVariable ["COMSPEC_ATAK_ExploCache", [-1, []]];
        _s set ["paths", createHashMap];
        if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "EXPLO") then {
            [{ ["live", true] call comspec_atak_native_fnc_exploAction; }] call comspec_atak_native_fnc_nextFrame;
        };
    };
    case "liveOn": {
        if ((_s getOrDefault ["livePfh", -1]) >= 0 && {(diag_tickTime - (_s getOrDefault ["liveAt", -1])) < 3}) exitWith {};
        if ((_s getOrDefault ["livePfh", -1]) >= 0) then { [_s get "livePfh"] call CBA_fnc_removePerFrameHandler; };
        _s set ["liveAt", diag_tickTime];
        _s set ["livePfh", [{ ["live"] call comspec_atak_native_fnc_exploAction; }, 1] call CBA_fnc_addPerFrameHandler];
    };
    case "live": {
        private _on = ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "EXPLO";
        if (!_on || {isNull ([] call comspec_atak_native_fnc_display)}) exitWith {
            if ((_s getOrDefault ["livePfh", -1]) >= 0) then { [_s get "livePfh"] call CBA_fnc_removePerFrameHandler; };
            _s set ["livePfh", -1];
            call _closeConfirm;
        };
        if (_arg isNotEqualTo true) then { _s set ["liveAt", diag_tickTime]; };
        private _conf = _s getOrDefault ["confirm", createHashMap];
        if ((count _conf) > 0) exitWith {
            private _left = (_conf get "until") - diag_tickTime;
            if (_left <= 0) exitWith { call _closeConfirm; call _render; };
            ["COMSPEC_ATAK_ExploConfirmT", format ["<t size='0.85' color='%1'>Annulé dans %2 s si vous ne confirmez pas</t>", _th get "hexTextDim", ceil _left]] call _setText;
        };
        private _list = [true] call comspec_atak_native_fnc_exploList;
        if (([_list] call _sigOf) isNotEqualTo (_s getOrDefault ["sig", []])) exitWith { call _render; };
        // Une liaison recalculée par seconde au plus : les autres gardent leur dernière valeur.
        private _net = _list select { (_x get "kind") in ["net", "atak"] };
        if (_net isNotEqualTo [] && {_arg isNotEqualTo true}) then {
            private _i = ((_s getOrDefault ["rr", 0]) + 1) mod (count _net);
            _s set ["rr", _i];
            [_net select _i, true] call _pathOf;
        };
        ["COMSPEC_ATAK_ExploLink", ["linkText"] call comspec_atak_native_fnc_exploAction] call _setText;
        if ((count (_s getOrDefault ["queue", []])) > 0) then { ["COMSPEC_ATAK_ExploSeqT", ["seqText"] call comspec_atak_native_fnc_exploAction] call _setText; };
        {
            ([_x, _forEachIndex + 1, _sel] call comspec_atak_native_fnc_exploRow) params ["_txt", "_btns"];
            [format ["COMSPEC_ATAK_ExploCard%1", _forEachIndex], _txt] call _setText;
            [format ["exploBtn%1", _forEachIndex], _btns apply { [_x select 0, _x select 3, _x select 2, _x select 4] }] call comspec_atak_native_fnc_formSet;
        } forEach _list;
        ["COMSPEC_ATAK_ExploSeqInfo", call _seqInfo] call _setText;
        private _sb = ["seqBtns"] call comspec_atak_native_fnc_exploAction;
        ["exploSeqRow", [["", true, ""], ["", true, ""], [_sb select 0, _sb select 1, ["", "red"] select (_sb select 1)]]] call comspec_atak_native_fnc_formSet;
    };
    case "ask": {
        private _c = [_arg] call _find;
        if ((count _c) isEqualTo 0) exitWith { ["WARNING", "Charge introuvable (déjà sautée ?)", 3, 20] call comspec_atak_native_fnc_notify; ["changed"] call comspec_atak_native_fnc_exploAction; };
        [_c, true] call _pathOf;
        (["can", _c] call comspec_atak_native_fnc_exploAction) params ["_can", "_why"];
        if (!_can) exitWith { ["WARNING", _why, 4, 30] call comspec_atak_native_fnc_notify; };
        _s set ["confirm", createHashMapFromArray [["what", "one"], ["key", _arg], ["until", diag_tickTime + 12], ["label", format ["METTRE À FEU : %1", _c get "label"]],
            ["text", [[_c], ""] call _askText], ["nonce", diag_tickTime]]];
        call _render;
    };
    case "askSeq": {
        if (call _safe) exitWith {};
        private _all = ([true] call comspec_atak_native_fnc_exploList) select { ((_x get "key") in _sel) && {[_x] call _isFireable} };
        if ((count _all) isEqualTo 0) exitWith { ["WARNING", "Cochez au moins une charge pour la séquence", 3, 30] call comspec_atak_native_fnc_notify; };
        private _ready = _all select { (["can", _x] call comspec_atak_native_fnc_exploAction) select 0 };
        if ((count _ready) isEqualTo 0) exitWith { ["WARNING", "Aucune charge cochée n'est prête", 4, 30] call comspec_atak_native_fnc_notify; };
        private _no = (count _all) - (count _ready);
        _s set ["confirm", createHashMapFromArray [["what", "seq"], ["key", ""], ["until", diag_tickTime + 12], ["label", format ["LANCER LA SÉQUENCE : %1 CHARGE(S)", count _ready]],
            ["text", [_ready, ["", format ["<br/><t color='%1'>%2 charge(s) non prête(s) : elles ne partiront pas</t>", _th get "hexWarn", _no]] select (_no > 0)] call _askText], ["nonce", diag_tickTime]]];
        call _render;
    };
    // FEU n'apparaît qu'après 0,7 s : un double appui sur le bouton d'origine ne peut pas confirmer.
    case "confirmShown": {
        private _n = (_s getOrDefault ["confirm", createHashMap]) getOrDefault ["nonce", -1];
        [{
            params ["_n"];
            private _cf = (uiNamespace getVariable ["COMSPEC_ATAK_Explo", createHashMap]) getOrDefault ["confirm", createHashMap];
            if ((_cf getOrDefault ["nonce", -2]) isEqualTo _n) then { ["exploFireRow", true] call comspec_atak_native_fnc_formSet; };
        }, [_n], 0.7] call comspec_atak_native_fnc_waitAndExec;
    };
    case "askNo": { call _closeConfirm; call _render; };
    case "askYes": {
        private _conf = _s getOrDefault ["confirm", createHashMap];
        if ((count _conf) isEqualTo 0) exitWith {};
        call _closeConfirm;
        if (diag_tickTime > (_conf get "until")) exitWith { call _render; };
        if ((_conf get "what") isEqualTo "seq") then {
            ["seq"] call comspec_atak_native_fnc_exploAction;
        } else {
            ["fire", _conf get "key"] call comspec_atak_native_fnc_exploAction;
        };
        call _render;
    };
    case "fire": {
        private _c = [_arg] call _find;
        if ((count _c) isEqualTo 0) exitWith { ["WARNING", "Charge introuvable (déjà sautée ?)", 3, 20] call comspec_atak_native_fnc_notify; call _render; };
        if ([_c] call _boom) then {
            if ((_c get "kind") isNotEqualTo "net") then { ["SUCCESS", format ["Mise à feu : %1", _c get "label"], 3, 40] call comspec_atak_native_fnc_notify; };
        };
        if (_arg in _sel) then { _sel deleteAt (_sel find _arg); };
        ["changed"] call comspec_atak_native_fnc_exploAction;
        [{ ["changed"] call comspec_atak_native_fnc_exploAction; }, [], 1.6] call comspec_atak_native_fnc_waitAndExec;
    };
    case "seq": {
        if (call _safe) exitWith {};
        private _list = ([true] call comspec_atak_native_fnc_exploList) select { ((_x get "key") in _sel) && {[_x] call _isFireable} && {(["can", _x] call comspec_atak_native_fnc_exploAction) select 0} };
        if ((count _list) isEqualTo 0) exitWith { ["WARNING", "Cochez au moins une charge prête pour la séquence", 3, 30] call comspec_atak_native_fnc_notify; };
        private _t0 = diag_tickTime + (_s getOrDefault ["delay", 5]);
        private _gap = _s getOrDefault ["gap", 1];
        private _queue = [];
        { _queue pushBack [_t0 + _forEachIndex * _gap, _x]; } forEach _list;
        _s set ["queue", _queue];
        ["WARNING", format ["Séquence lancée : %1 charge(s), première dans %2 s", count _queue, _s getOrDefault ["delay", 5]], 4, 60] call comspec_atak_native_fnc_notify;
        call _ensureLoop;
        call _render;
    };
    case "cancel": {
        private _n = count (_s getOrDefault ["queue", []]);
        _s set ["queue", []];
        _s set ["seqCancelled", true];
        ["INFO", format ["Séquence annulée : %1 charge(s) non mises à feu", _n], 3, 40] call comspec_atak_native_fnc_notify;
        call _render;
    };
    case "tick": {
        _s set ["lastTick", diag_tickTime];
        private _queue = _s getOrDefault ["queue", []];
        private _due = _queue select { diag_tickTime >= (_x select 0) };
        if ((count _due) > 0) then {
            // On retire d'abord, puis on tire chaque charge comme le bouton FEU unitaire, avec sa résolution fraîche.
            _s set ["queue", _queue select { diag_tickTime < (_x select 0) }];
            {
                private _c = [_x select 1] call _resolve;
                private _ok = if ((count _c) > 0) then { [_c] call _boom } else { false };
                if (!_ok && {(count _c) isEqualTo 0}) then { ["WARNING", format ["%1 : pas de mise à feu (charge introuvable ou déjà sautée)", (_x select 1) getOrDefault ["label", "Charge"]], 3, 30] call comspec_atak_native_fnc_notify; };
            } forEach _due;
            uiNamespace setVariable ["COMSPEC_ATAK_ExploCache", [-1, []]];
        };
        if ((count (_s getOrDefault ["queue", []])) isEqualTo 0) then {
            [_s getOrDefault ["pfh", -1]] call CBA_fnc_removePerFrameHandler;
            _s set ["pfh", -1];
            _s set ["lastTick", -1];
            _s set ["armed", false];
            _s set ["sel", []];
            // Une annulation a déjà son message : pas de « terminée » en plus.
            if (_s getOrDefault ["seqCancelled", false]) then {
                _s set ["seqCancelled", false];
            } else {
                ["SUCCESS", "Séquence terminée, sécurité remise", 3, 40] call comspec_atak_native_fnc_notify;
            };
            ["changed"] call comspec_atak_native_fnc_exploAction;
            [{ ["changed"] call comspec_atak_native_fnc_exploAction; }, [], 1.6] call comspec_atak_native_fnc_waitAndExec;
        };
    };
};
