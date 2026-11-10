/*
    Terminal « Parler à… » (client) : dialog COMSPEC_SSE_IarpTerminal (dialogs\iarp.hpp).
      open [ia | objNull, onglet]   ouvre sur une IA vivante à 5 m (à pied) ; objNull + "log" : journal seul (Zeus)
      onLoad / onUnload             brouillon gardé par IA (Échap ferme sans rien perdre)
      key [contrôle, touche, maj, ctrl, alt] -> vrai si traitée : Entrée envoie (Maj+Entrée : nouvelle ligne),
                                    flèches haut / bas : messages déjà envoyés
      keyUp                         compteur de caractères, indicateur d'écriture (une fois par 2,5 s au plus)
      prefix [me | do | try | todo] commande RP en tête du message     send   envoie
      tab [talk | log] · refresh · refreshLog · copy (journal dans le presse-papiers) · tick (distance, 2 fois par s)
    Commandes : /me (action, à la 3e personne), /do (décor, fait constaté), /try (tentative : tirage du serveur, même
    résultat pour tous), /todo (parole et geste). Sans commande : on parle à l'IA, qui répond (fn_iarpServer).
*/
params [["_op", ""], ["_a", []]];
if (!hasInterface) exitWith { false };
disableSerialization;
private _d = uiNamespace getVariable ["comspec_sse_iarpDisplay", displayNull];
private _drafts = uiNamespace getVariable ["comspec_sse_iarpDrafts", createHashMap];
uiNamespace setVariable ["comspec_sse_iarpDrafts", _drafts];
private _sent = uiNamespace getVariable ["comspec_sse_iarpSent", []];
uiNamespace setVariable ["comspec_sse_iarpSent", _sent];
private _targetObj = { objectFromNetId (uiNamespace getVariable ["comspec_sse_iarpTarget", ""]) };
private _maxLen = round (missionNamespace getVariable ["comspec_sse_iarp_maxLen", 600]);
private _esc = {
    private _s = [_this, "&", "&amp;"] call CBA_fnc_replace;
    _s = [_s, "<", "&lt;"] call CBA_fnc_replace;
    [_s, ">", "&gt;"] call CBA_fnc_replace
};
private _hex = {
    params ["_c"];
    private _h = "0123456789ABCDEF";
    "#" + (((_c select [0, 3]) apply { private _v = round (((_x max 0) min 1) * 255); (_h select [floor (_v / 16), 1]) + (_h select [_v mod 16, 1]) }) joinString "")
};
private _rgbOf = {
    params ["_kind"];
    private _c = switch (_kind) do {
        case "me": { missionNamespace getVariable ["comspec_sse_iarp_colMe", [0.8, 0.62, 1]] };
        case "do": { missionNamespace getVariable ["comspec_sse_iarp_colDo", [0.55, 0.8, 1]] };
        case "todo": { missionNamespace getVariable ["comspec_sse_iarp_colTodo", [1, 0.75, 0.35]] };
        case "try": { missionNamespace getVariable ["comspec_sse_iarp_colTry", [0.5, 0.95, 0.55]] };
        case "tryOk": { [0.5, 0.95, 0.55] };
        case "tryKo": { [1, 0.45, 0.4] };
        case "ai": { missionNamespace getVariable ["comspec_sse_iarp_colAi", [0.62, 0.9, 1]] };
        case "sys": { [0.62, 0.68, 0.64] };
        default { missionNamespace getVariable ["comspec_sse_iarp_colSay", [0.97, 0.98, 0.95]] };
    };
    [_c param [0, 1], _c param [1, 1], _c param [2, 1], 1]
};
private _colOf = { [_this call _rgbOf] call _hex };
// Commande RP en tête du texte : ["me", "lève les mains"] ; sans commande : ["", texte].
private _split = {
    params ["_t"];
    private _l = toLower _t;
    private _r = ["", _t];
    {
        private _p = "/" + _x + " ";
        if ((_l select [0, count _p]) isEqualTo _p) exitWith { _r = [_x, trim (_t select [count _p])]; };
    } forEach ["todo", "try", "me", "do"];
    _r
};

switch (_op) do {
    case "open": {
        _a params [["_t", objNull, [objNull]], ["_tab", "talk", [""]]];
        if !(missionNamespace getVariable ["comspec_sse_iarp_enabled", true]) exitWith { false };
        if (_tab isNotEqualTo "log") then {
            if (isNull _t || {!alive _t} || {isPlayer _t} || {!(_t isKindOf "CAManBase")}) exitWith { _t = objNull; };
            if ((player distance _t) > 5 || {!isNull objectParent player}) exitWith { ["notice", ["STR_COMSPEC_IARP_N_TooFar"]] call comspec_sse_fnc_iarpClient; _t = objNull; };
        };
        if (_tab isNotEqualTo "log" && {isNull _t}) exitWith { false };
        if (!isNull _d) then { closeDialog 0; };
        uiNamespace setVariable ["comspec_sse_iarpTarget", [netId _t, ""] select (isNull _t)];
        uiNamespace setVariable ["comspec_sse_iarpTab", _tab];
        createDialog "COMSPEC_SSE_IarpTerminal";
        true
    };
    case "onLoad": {
        _a params [["_disp", displayNull]];
        uiNamespace setVariable ["comspec_sse_iarpDisplay", _disp];
        _d = _disp;
        private _t = call _targetObj;
        // Nom de l'IA : celui de ses papiers SSE, jamais le nom d'unité Arma (fn_iarpName).
        private _name = if (isNull _t) then { localize "STR_COMSPEC_IARP_LogOnly" } else { ["shown", _t] call comspec_sse_fnc_iarpName };
        (_d displayCtrl 94601) ctrlSetText format [localize "STR_COMSPEC_IARP_TitleTalk", toUpper _name];
        { (_d displayCtrl (_x select 0)) ctrlSetTextColor ([_x select 1] call _rgbOf); } forEach [[94621, "me"], [94622, "do"], [94623, "try"], [94624, "todo"]];
        private _e = _d displayCtrl 94620;
        _e ctrlSetText (_drafts getOrDefault [uiNamespace getVariable ["comspec_sse_iarpTarget", ""], ""]);
        uiNamespace setVariable ["comspec_sse_iarpRecall", -1];
        uiNamespace setVariable ["comspec_sse_iarpTypingAt", -10];
        ["keyUp"] call comspec_sse_fnc_iarpUi;
        ["refresh"] call comspec_sse_fnc_iarpUi;
        ["tab", [uiNamespace getVariable ["comspec_sse_iarpTab", "talk"]]] call comspec_sse_fnc_iarpUi;
        ["tick"] call comspec_sse_fnc_iarpUi;
        private _h = [{ ["tick"] call comspec_sse_fnc_iarpUi; }, 0.5] call CBA_fnc_addPerFrameHandler;
        uiNamespace setVariable ["comspec_sse_iarpTick", _h];
        ctrlSetFocus _e;
    };
    case "onUnload": {
        private _net = uiNamespace getVariable ["comspec_sse_iarpTarget", ""];
        if (!isNull _d && {_net isNotEqualTo ""}) then { _drafts set [_net, ctrlText (_d displayCtrl 94620)]; };
        [uiNamespace getVariable ["comspec_sse_iarpTick", -1]] call CBA_fnc_removePerFrameHandler;
        if ((uiNamespace getVariable ["comspec_sse_iarpTypingAt", -10]) > (time - 8)) then { ["send", ["typing", [player, false]]] call comspec_sse_fnc_iarpClient; };
        uiNamespace setVariable ["comspec_sse_iarpTypingAt", -10];
        uiNamespace setVariable ["comspec_sse_iarpDisplay", displayNull];
    };
    case "tick": {
        if (isNull _d) exitWith {};
        private _t = call _targetObj;
        if (isNull _t) exitWith { (_d displayCtrl 94602) ctrlSetText ""; };
        private _dist = player distance _t;
        private _ok = alive _t && {_dist <= 6} && {isNull objectParent player};
        (_d displayCtrl 94602) ctrlSetText format [localize (["STR_COMSPEC_IARP_StatusFar", "STR_COMSPEC_IARP_StatusNear"] select _ok), round _dist];
        (_d displayCtrl 94625) ctrlEnable _ok;
    };
    case "tab": {
        _a params [["_tab", "talk"]];
        if (isNull _d) exitWith {};
        private _log = _tab isEqualTo "log" || {isNull (call _targetObj)};
        uiNamespace setVariable ["comspec_sse_iarpTab", ["talk", "log"] select _log];
        { (_d displayCtrl _x) ctrlShow !_log; } forEach [94610, 94620, 94621, 94622, 94623, 94624, 94625, 94626, 94627];
        { (_d displayCtrl _x) ctrlShow _log; } forEach [94630, 94632, 94633];
        (_d displayCtrl 94603) ctrlEnable !(isNull (call _targetObj));
        if (_log) then { ["send", ["log", [player]]] call comspec_sse_fnc_iarpClient; ["refreshLog"] call comspec_sse_fnc_iarpUi; } else { ctrlSetFocus (_d displayCtrl 94620); };
    };
    case "refresh": {
        if (isNull _d) exitWith {};
        private _l = (uiNamespace getVariable ["comspec_sse_iarpHist", createHashMap]) getOrDefault [uiNamespace getVariable ["comspec_sse_iarpTarget", ""], []];
        private _rows = _l apply {
            _x params ["_kind", "_who", "_text", "_hm"];
            private _body = switch (_kind) do {
                case "me": { format ["* %1 %2", _who, _text call _esc] };
                case "do": { format ["(%1)", _text call _esc] };
                case "todo": { format ["%1 : %2 *", _who, _text call _esc] };
                case "tryOk"; case "tryKo": { _text call _esc };
                case "sys": { _text call _esc };
                default { format ["<t font='RobotoCondensedBold'>%1</t> : %2", _who call _esc, _text call _esc] };
            };
            format ["<t color='#7F8A84' size='0.8'>%1</t>  <t color='%2'>%3</t>", _hm, [_kind] call _colOf, _body]
        };
        if (_rows isEqualTo []) then { _rows = [format ["<t color='#9EAFA6'>%1</t>", localize "STR_COMSPEC_IARP_EmptyHistory"]]; };
        private _g = _d displayCtrl 94610;
        private _st = _d displayCtrl 94611;
        _st ctrlSetStructuredText parseText (_rows joinString "<br/>");
        private _w = (ctrlPosition _g) select 2;
        _st ctrlSetPosition [0, 0, _w - 0.01, (ctrlTextHeight _st) max ((ctrlPosition _g) select 3)];
        _st ctrlCommit 0;
        _g ctrlSetScrollValues [1, -1];
    };
    case "refreshLog": {
        if (isNull _d) exitWith {};
        private _rows = uiNamespace getVariable ["comspec_sse_iarpLogRows", []];
        private _out = (_rows select [((count _rows) - 200) max 0]) apply {
            _x params ["", "_gt", "_rt", "_dir", "_rp", "_steam", "_ai", "_net", "_grid", "_dist", "_cmd", "_text", "_intent", "_res"];
            private _who = format ["%1 %2%3 · %4 (%5) · %6 · %7 m", _dir, _rp call _esc, ["", format [" (%1)", _steam call _esc]] select (_steam isNotEqualTo ""),
                [_ai, localize "STR_COMSPEC_IARP_Anonymous"] select (_ai isEqualTo ""), _net, _grid, _dist];
            private _col = [["say"] call _colOf, ["ai"] call _colOf] select ((_dir select [0, 2]) isEqualTo "IA");
            format ["<t color='#7F8A84' size='0.8'>%1 · %2</t>  <t color='#E6EDE6'>%3</t> <t color='#9EAFA6'>%4</t><br/><t color='%5'>%6</t> <t color='#7F8A84' size='0.85'>[%7 · %8]</t>",
                _gt, _rt, _who, ["", "/" + _cmd] select (_cmd isNotEqualTo ""), _col, _text call _esc, _intent, _res call _esc]
        };
        if (_out isEqualTo []) then { _out = [format ["<t color='#9EAFA6'>%1</t>", localize "STR_COMSPEC_IARP_EmptyLog"]]; };
        private _g = _d displayCtrl 94630;
        private _st = _d displayCtrl 94631;
        _st ctrlSetStructuredText parseText (_out joinString "<br/>");
        _st ctrlSetPosition [0, 0, ((ctrlPosition _g) select 2) - 0.01, (ctrlTextHeight _st) max ((ctrlPosition _g) select 3)];
        _st ctrlCommit 0;
        _g ctrlSetScrollValues [1, -1];
    };
    case "copy": {
        private _rows = uiNamespace getVariable ["comspec_sse_iarpLogRows", []];
        private _nl = toString [13, 10];
        private _txt = (_rows apply {
            _x params ["_id", "_gt", "_rt", "_dir", "_rp", "_steam", "_ai", "_net", "_grid", "_dist", "_cmd", "_text", "_intent", "_res"];
            format ["#%1 | %2 | %3 | %4 | %5 (%6) | %7 (%8) | %9 | %10 m | %11 | %12 | %13 | %14", _id, _gt, _rt, _dir, _rp, _steam, _ai, _net, _grid, _dist, _cmd, _text, _intent, _res]
        }) joinString _nl;
        copyToClipboard _txt;
        ["notice", ["STR_COMSPEC_IARP_N_Copied", count _rows]] call comspec_sse_fnc_iarpClient;
    };
    case "prefix": {
        _a params [["_cmd", "me"]];
        if (isNull _d) exitWith {};
        private _e = _d displayCtrl 94620;
        ([ctrlText _e] call _split) params ["_old", "_body"];
        _e ctrlSetText ([format ["/%1 %2", _cmd, _body], _body] select (_old isEqualTo _cmd));
        ctrlSetFocus _e;
        ["keyUp"] call comspec_sse_fnc_iarpUi;
    };
    case "keyUp": {
        if (isNull _d) exitWith {};
        private _e = _d displayCtrl 94620;
        private _txt = ctrlText _e;
        private _n = count (toArray _txt);
        if (_n > _maxLen) then { _e ctrlSetText (toString ((toArray _txt) select [0, _maxLen])); _n = _maxLen; };
        (_d displayCtrl 94626) ctrlSetText format ["%1 / %2", _n, _maxLen];
        if (_n > 0 && {missionNamespace getVariable ["comspec_sse_iarp_typing", true]} && {(time - (uiNamespace getVariable ["comspec_sse_iarpTypingAt", -10])) > 2.5}) then {
            uiNamespace setVariable ["comspec_sse_iarpTypingAt", time];
            ["send", ["typing", [player, true]]] call comspec_sse_fnc_iarpClient;
        };
    };
    case "key": {
        _a params ["", ["_dik", 0], ["_shift", false], ["_ctrlKey", false], ["_alt", false]];
        if (isNull _d) exitWith { false };
        private _e = _d displayCtrl 94620;
        if (_dik in [28, 156] && {!_shift}) exitWith { ["send"] call comspec_sse_fnc_iarpUi; true };
        if (_dik in [200, 208] && {_sent isNotEqualTo []}) exitWith {
            private _i = uiNamespace getVariable ["comspec_sse_iarpRecall", -1];
            private _cur = ctrlText _e;
            // Rappel seulement sur une saisie vide ou un message déjà rappelé : sinon la flèche déplace le curseur.
            if (_cur isNotEqualTo "" && {_i < 0 || {_cur isNotEqualTo (_sent param [_i, ""])}}) exitWith { false };
            if (_i < 0) then { _i = count _sent; };
            _i = if (_dik isEqualTo 200) then { (_i - 1) max 0 } else { _i + 1 };
            if (_i >= (count _sent)) then { uiNamespace setVariable ["comspec_sse_iarpRecall", -1]; _e ctrlSetText ""; } else {
                uiNamespace setVariable ["comspec_sse_iarpRecall", _i];
                _e ctrlSetText (_sent select _i);
            };
            ["keyUp"] call comspec_sse_fnc_iarpUi;
            true
        };
        false
    };
    case "send": {
        if (isNull _d) exitWith {};
        private _t = call _targetObj;
        private _e = _d displayCtrl 94620;
        private _raw = trim (ctrlText _e);
        if (_raw isEqualTo "" || {isNull _t}) exitWith {};
        if !(ctrlEnabled (_d displayCtrl 94625)) exitWith { ["notice", ["STR_COMSPEC_IARP_N_TooFar"]] call comspec_sse_fnc_iarpClient; };
        ([_raw] call _split) params ["_cmd", "_body"];
        if (_body isEqualTo "") exitWith {};
        _sent pushBack _raw;
        if ((count _sent) > 30) then { _sent deleteAt 0; };
        uiNamespace setVariable ["comspec_sse_iarpRecall", -1];
        _e ctrlSetText "";
        _drafts deleteAt (netId _t);
        uiNamespace setVariable ["comspec_sse_iarpTypingAt", -10];
        // Ma ligne tout de suite dans l'historique (le résultat d'un /try arrive du serveur).
        if (_cmd isNotEqualTo "try") then {
            private _hist = uiNamespace getVariable ["comspec_sse_iarpHist", createHashMap];
            private _l = _hist getOrDefault [netId _t, []];
            _l pushBack [["say", _cmd] select (_cmd isNotEqualTo ""), localize "STR_COMSPEC_IARP_Me", _body, [daytime, "HH:MM"] call BIS_fnc_timeToString];
            if ((count _l) > 80) then { _l deleteAt 0; };
            _hist set [netId _t, _l];
            uiNamespace setVariable ["comspec_sse_iarpHist", _hist];
        };
        ["send", ["say", [player, _t, _body, _cmd, profileNameSteam]]] call comspec_sse_fnc_iarpClient;
        ["keyUp"] call comspec_sse_fnc_iarpUi;
        ["refresh"] call comspec_sse_fnc_iarpUi;
        ctrlSetFocus _e;
    };
};
