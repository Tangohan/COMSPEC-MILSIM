/*
    Messagerie : canal TOC (Athena) + messages directs entre joueurs.
    Les messages directs reprennent le principe de l'app message de BCE (Aaren, APL-SA) :
    un événement CBA ciblé sur le destinataire, sans serveur ni Athena, donc utilisable hors ligne.

    Interface (même famille que Reco et Galerie : fond nuit, en-têtes sombres, filets, couleur d'accent du joueur) :
      - LISTE des conversations (clé chatView = "LIST") : canaux Athena puis messages directs, avec icône ou photo,
        dernier message, heure et pastille de non-lus ; + CANAL crée un canal (avec Athena, en main).
      - CONVERSATION (chatView = "CONV") : en-tête (retour, icône, nom, sous-titre ; aide, tri, vider, supprimer),
        fil en bulles regroupées par auteur, séparateurs de jour, repère des non-lus, puis l'aperçu des commandes
        et la barre de saisie (? , champ, envoi).
      - En main et en paysage : les deux côte à côte. Porté (sans souris) : la conversation seule, sans saisie.
    Comportement inchangé : canaux, commandes /, raccourcis @grille, SMS et leurs accusés, VIDER, tri, création et
    suppression de canal. Les apps qui ouvrent une conversation posent chatPeer et chatView = "CONV".
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _rowH = _font * 1.6;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _peer = _s getOrDefault ["chatPeer", "ATHENA"];
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
private _dir = "\z\comspec_atak_native\addons\main\data\";
private _d = [] call comspec_atak_native_fnc_display;

private _bridge = [] call comspec_atak_native_fnc_bridge;
private _interactive = _l get "interactive";
private _split = _interactive && {_l get "landscape"};
private _view = if (_interactive) then { _s getOrDefault ["chatView", "LIST"] } else { "CONV" };
if !(_view in ["LIST", "CONV"]) then { _view = "LIST"; };

// Teintes (celles de Reco) et accent choisi dans Réglages > Personnalisation.
private _cBg = [0.026, 0.031, 0.028, 1];
private _cHdr = [0.012, 0.015, 0.013, 1];
private _cTile = [0.065, 0.078, 0.071, 1];
private _cField = [0.014, 0.017, 0.015, 1];
private _cLine = [0.16, 0.20, 0.17, 1];
private _cText = [0.92, 0.95, 0.93, 1];
private _cDim = [0.55, 0.61, 0.58, 1];
private _cRed = [0.90, 0.28, 0.23, 1];
private _cBlue = [0.10, 0.42, 0.82, 1];
([] call comspec_atak_native_fnc_accent) params ["_acc", "_accHex"];
private _accDark = [(_acc select 0) * 0.30, (_acc select 1) * 0.30, (_acc select 2) * 0.30, 1];

// Canaux web : COMSPEC Link ne les relit qu'à la création ou suppression, on les rafraîchit ici (toutes les 20 s).
if (_bridge && {!isNil "comspec_overwatch_connect_fnc_pollChatChannels"} && {diag_tickTime - (_s getOrDefault ["chatChannelsPoll", -100]) > 20}) then {
    _s set ["chatChannelsPoll", diag_tickTime];
    [] spawn {
        private _before = +(missionNamespace getVariable ["COMSPEC_Comms_Channels", []]);
        [] call comspec_overwatch_connect_fnc_pollChatChannels;
        if ((missionNamespace getVariable ["COMSPEC_Comms_Channels", []]) isNotEqualTo _before) then {
            [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "CHAT") then { ["CHAT"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
        };
    };
};
// Canaux système (non supprimables), comme dans COMSPEC Link.
private _system = ["groupe", "commandement", "general", "jtac", "air", "squad", "global", "hq", "c2", "command", "group", "alertes"];
// Canaux Athena (fil COMSPEC Link quand il est chargé), puis messages directs.
private _channelRows = []; // [clé, libellé, personnalisé]
if (_bridge) then {
    {
        _x params [["_k", ""], ["_lbl", ""], ["_kind", "custom"]];
        if (_k isNotEqualTo "" && {_k isNotEqualTo "alertes"}) then { _channelRows pushBack [_k, [_lbl, _k] select (_lbl isEqualTo ""), _kind isEqualTo "custom" && {!(_k in _system)}]; };
    } forEach (missionNamespace getVariable ["COMSPEC_Comms_Channels", []]);
    if ((count _channelRows) isEqualTo 0) then { _channelRows = [["general", "Général", false], ["commandement", "Commandement", false], ["groupe", "Groupe", false]]; };
    _channelRows pushBack ["alertes", "Alertes TOC", false];
};
private _channels = if (_bridge) then { _channelRows apply { ["CH:" + (_x select 0), _x select 1] } } else { [["ATHENA", "TOC — canal Athena"]] };
private _peers = (allPlayers select { _x isNotEqualTo player && {side group _x isEqualTo side group player} }) apply { name _x };
{ if ((_x getOrDefault ["peer", ""]) isNotEqualTo "") then { _peers pushBackUnique (_x get "peer"); }; } forEach (_data getOrDefault ["p2p", []]);
_peers sort true;
if !((_channels findIf { (_x select 0) isEqualTo _peer }) >= 0 || {_peer in _peers}) then { _peer = (_channels select 0) select 0; };
private _isChannel = _peer isEqualTo "ATHENA" || {(_peer select [0, 3]) isEqualTo "CH:"};
_s set ["chatChannel", [_peer select [3], ""] select (_peer isEqualTo "ATHENA")];
_s set ["chatPeer", _peer];
private _manage = _bridge && {_interactive};
private _curKey = _peer select [3];
// Non-lus par canal (affichage de la liste) : nombre de messages vus à la dernière ouverture.
private _seenMap = _s getOrDefault ["chatSeen", createHashMap];
_s set ["chatSeen", _seenMap];
private _custom = _isChannel && {(_channelRows findIf { (_x select 0) isEqualTo _curKey && {_x select 2} }) >= 0};

// ---------------------------------------------------------------- Outils de dessin
private _esc = {
    params ["_t"];
    if !(_t isEqualType "") then { _t = str _t; };
    _t = [_t, "&", "&amp;"] call CBA_fnc_replace;
    _t = [_t, "<", "&lt;"] call CBA_fnc_replace;
    [_t, ">", "&gt;"] call CBA_fnc_replace
};
private _mk = { params ["_c", "_p", ["_t", ""]]; [_c, _p, _t] call comspec_atak_native_fnc_pageCtrl };
// Contrôle dans un groupe défilant (supprimé avec lui).
private _gmk = {
    params ["_grp", "_c", "_p", ["_t", ""]];
    private _o = _d ctrlCreate [_c, -1, _grp];
    _o ctrlSetPosition _p;
    if (_t isNotEqualTo "") then { _o ctrlSetText ((_t) call comspec_atak_native_fnc_tr); };
    _o ctrlCommit 0;
    _o
};
private _box = { params ["_rect", "_rgba", ["_grp", controlNull]]; private _c = if (isNull _grp) then { ["COMSPEC_RscText", _rect] call _mk } else { [_grp, "COMSPEC_RscText", _rect] call _gmk }; _c ctrlSetBackgroundColor _rgba; _c };
private _lbl = {
    params ["_cls", "_rect", "_t", ["_fh", _fs], ["_col", _cText], ["_bold", false], ["_grp", controlNull]];
    private _c = if (isNull _grp) then { [_cls, _rect, _t] call _mk } else { [_grp, _cls, _rect, _t] call _gmk };
    _c ctrlSetFontHeight ((_fh) max COMSPEC_ATAK_FontMin);
    _c ctrlSetTextColor _col;
    if (_bold) then { _c ctrlSetFont (COMSPEC_ATAK_FontSet select 1); };
    _c
};
private _hit = {
    params ["_rect", "_code", ["_tip", ""], ["_grp", controlNull]];
    private _b = if (isNull _grp) then { ["COMSPEC_RscButtonInvisible", _rect] call _mk } else { [_grp, "COMSPEC_RscButtonInvisible", _rect] call _gmk };
    if (_code isEqualType "") then { _code = compile _code; };
    _b ctrlAddEventHandler ["ButtonClick", _code];
    if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
    _b
};
// Icône carrée centrée dans un rectangle (largeur corrigée du ratio de l'écran).
private _icon = {
    params ["_rect", "_path", ["_col", _cText], ["_scale", 0.6], ["_grp", controlNull]];
    _rect params ["_x0", "_y0", "_w0", "_h0"];
    private _ih = (_h0 * _scale) min (_w0 * _ratio * _scale);
    private _pos = [_x0 + (_w0 - _ih / _ratio) / 2, _y0 + (_h0 - _ih) / 2, _ih / _ratio, _ih];
    private _c = if (isNull _grp) then { ["COMSPEC_RscPicture", _pos, _path] call _mk } else { [_grp, "COMSPEC_RscPicture", _pos, _path] call _gmk };
    _c ctrlSetTextColor _col;
    _c
};
// Bouton icône de l'en-tête : fond discret (ou couleur d'état), icône, infobulle.
private _iconBtn = {
    params ["_rect", "_path", "_code", "_tip", ["_col", _cText], ["_bg", [1, 1, 1, 0.05]], ["_enabled", true]];
    [_rect, _bg] call _box;
    [_rect, _path, [[_col select 0, _col select 1, _col select 2, 0.35], _col] select _enabled, 0.58] call _icon;
    private _b = [_rect, _code, _tip] call _hit;
    _b ctrlEnable _enabled;
    _b
};
// Couleur stable par auteur (nom dans les bulles, liseré).
private _palette = [[0.36, 0.78, 0.42, 1], [0.30, 0.62, 0.98, 1], [0.98, 0.58, 0.20, 1], [0.68, 0.45, 0.95, 1], [0.85, 0.75, 0.50, 1], [0.35, 0.80, 0.80, 1], [0.95, 0.45, 0.60, 1]];
private _authorCol = {
    params ["_n"];
    private _sum = 0;
    { _sum = _sum + _x; } forEach (toArray (toLower _n));
    _palette select (_sum mod (count _palette))
};
private _hex = {
    params ["_c"];
    private _h = "0123456789abcdef";
    "#" + (((_c select [0, 3]) apply { private _v = round ((_x max 0 min 1) * 255); (_h select [floor (_v / 16), 1]) + (_h select [_v mod 16, 1]) }) joinString "")
};
private _unitCache = createHashMap;
private _unitFor = {
    params ["_n"];
    private _ln = toLower _n;
    if (_ln in _unitCache) exitWith { _unitCache get _ln };
    private _i = allPlayers findIf { (toLower name _x) isEqualTo _ln || {(toLower ([_x, true] call comspec_atak_native_fnc_unitCallsign)) isEqualTo _ln} };
    private _u = [objNull, allPlayers select (_i max 0)] select (_i >= 0);
    _unitCache set [_ln, _u];
    _u
};
// Initiales d'un nom ou indicatif (« HAWK 6 » → H6, « Tango-2 » → T2).
private _initials = {
    params ["_n"];
    private _w = (_n splitString " -_.") select { _x isNotEqualTo "" };
    toUpper ([_n select [0, 2], ((_w select 0) select [0, 1]) + ((_w select 1) select [0, 1])] select ((count _w) > 1))
};
// Vignette d'une conversation ou d'un auteur : photo de l'opérateur si connue, sinon initiales (personne)
// ou icône (canal, TOC) sur une tuile teintée.
private _avatar = {
    params ["_rect", "_iconPath", "_tint", ["_unit", objNull], ["_grp", controlNull], ["_ini", ""]];
    private _photo = if (isNull _unit) then { "" } else { [_unit] call comspec_atak_native_fnc_avatarPath };
    if (_photo isNotEqualTo "") exitWith {
        if (isNull _grp) then { ["COMSPEC_RscSlide", _rect, _photo] call _mk } else { [_grp, "COMSPEC_RscSlide", _rect, _photo] call _gmk };
    };
    [_rect, [(_tint select 0) * 0.28, (_tint select 1) * 0.28, (_tint select 2) * 0.28, 1], _grp] call _box;
    if (_ini isNotEqualTo "") exitWith {
        private _c = ["COMSPEC_RscTextCenter", _rect, "", (_rect select 3) * 0.42, _tint, true, _grp] call _lbl;
        _c ctrlSetText _ini; // i18n:brut
        _c
    };
    [_rect, _iconPath, _tint, 0.62, _grp] call _icon
};
private _rr = "[{ ['CHAT'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;";
private _open = { params ["_key"]; compile format ["private _s = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]; _s set ['chatPeer', %1]; _s set ['chatView', 'CONV']; _s set ['chatDelArm', '']; _s set ['chatWiki', false]; %2", str _key, _rr] };
private _today = [] call comspec_atak_native_fnc_p2pDate;

// Rectangles : liste à gauche et conversation à droite en paysage ; une seule vue sinon.
private _listW = [_bw, (_bw * 0.38) max (_font * 9 / _ratio) min (_bw * 0.5)] select _split;
private _convX = [0, _listW + pixelW] select _split;
private _convW = _bw - _convX;
[[0, 0, _bw, _bh], _cBg] call _box;

// ================================================================ LISTE DES CONVERSATIONS
private _drawList = {
    params ["_lx", "_lw"];
    // Bandeau d'état : où partent les messages, et + CANAL.
    private _sh = _font * 1.7;
    [[_lx, 0, _lw, _sh], _cHdr] call _box;
    [[_lx, _sh - pixelH, _lw, pixelH], _cLine] call _box;
    private _ready = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
    private _net = (([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 0]) > 0;
    private _state = switch (true) do {
        case (!_bridge): { ["TOC local · SMS directs", "#f2ab33"] };
        case (!_ready): { ["Athena non connecté", "#f2ab33"] };
        case (!_net): { ["Hors réseau · envoi différé", "#f2ab33"] };
        default { ["Athena connecté", _accHex] };
    };
    private _nbW = [0, (_font * 4.2 / _ratio) min (_lw * 0.36)] select _manage;
    private _st = ["COMSPEC_RscStructuredText", [_lx + _pad * 0.6, _sh * 0.18, _lw - _nbW - _pad * 1.6, _sh * 0.8]] call _mk;
    _st ctrlSetFontHeight (_fs * 0.95);
    _st ctrlSetStructuredText parseText ((format ["<t color='%2'>●</t> <t color='#c9d4cf'>%1</t>", _state select 0, _state select 1]) call comspec_atak_native_fnc_tr);
    if (_manage) then {
        private _r = [_lx + _lw - _nbW - _pad * 0.5, _sh * 0.16, _nbW, _sh * 0.68];
        private _on = _s getOrDefault ["chatNewOpen", false];
        [_r, [[1, 1, 1, 0.06], _accDark] select _on] call _box;
        [[_r select 0, (_r select 1) + (_r select 3) - pixelH * 2, _r select 2, pixelH * 2], _acc] call _box;
        ["COMSPEC_RscTextCenter", _r, "+ CANAL", _fs * 0.85, _cText, true] call _lbl;
        [_r, "private _s = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]; _s set ['chatNewOpen', !(_s getOrDefault ['chatNewOpen', false])]; " + _rr, "Créer un canal sur Athena"] call _hit;
    };
    private _y0 = _sh;
    // Ligne de création : nom du canal + CRÉER / ANNULER.
    if (_manage && {_s getOrDefault ["chatNewOpen", false]}) then {
        private _ch = _rowH + _pad;
        [[_lx, _y0, _lw, _ch], _cTile] call _box;
        private _cw = (_lw * 0.22) max (_font * 3 / _ratio);
        private _ew = _lw - 2 * _cw - _pad * 2;
        [[_lx + _pad * 0.5, _y0 + _pad / 2, _ew, _rowH], _cField] call _box;
        [[_lx + _pad * 0.5, _y0 + _pad / 2 + _rowH - pixelH, _ew, pixelH], _acc] call _box;
        private _ne = ["COMSPEC_RscEdit", [_lx + _pad * 0.5, _y0 + _pad / 2, _ew, _rowH], uiNamespace getVariable ["COMSPEC_ATAK_ChatNewDraft", ""]] call _mk;
        _ne ctrlSetBackgroundColor [0, 0, 0, 0];
        _ne ctrlSetFontHeight _font;
        _ne ctrlSetTooltip (("Nom du nouveau canal") call comspec_atak_native_fnc_tr);
        uiNamespace setVariable ["COMSPEC_ATAK_ChatNewEdit", _ne];
        private _ok = ["COMSPEC_RscButtonPrimary", [_lx + _lw - 2 * _cw - _pad, _y0 + _pad / 2, _cw, _rowH], "CRÉER"] call _mk;
        _ok ctrlSetFontHeight _fs;
        _ok ctrlAddEventHandler ["ButtonClick", {
            private _label = trim ctrlText (uiNamespace getVariable ["COMSPEC_ATAK_ChatNewEdit", controlNull]);
            if (_label isEqualTo "") exitWith { ["WARNING", "Donnez un nom au canal", 3, 20] call comspec_atak_native_fnc_notify; };
            [_label] spawn {
                params ["_label"];
                if ([_label] call comspec_overwatch_connect_fnc_createChatChannel) then {
                    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
                    _s set ["chatNewOpen", false];
                    uiNamespace setVariable ["COMSPEC_ATAK_ChatNewDraft", ""];
                    _s set ["chatPeer", "CH:" + (missionNamespace getVariable ["COMSPEC_Comms_Channel", "general"])];
                    _s set ["chatView", "CONV"];
                };
                [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
            };
        }];
        private _no = ["COMSPEC_RscButton", [_lx + _lw - _cw - _pad * 0.5, _y0 + _pad / 2, _cw, _rowH], "ANNULER"] call _mk;
        _no ctrlSetFontHeight _fs;
        _no ctrlAddEventHandler ["ButtonClick", { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["chatNewOpen", false]; [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }];
        ctrlSetFocus _ne;
        _y0 = _y0 + _ch;
    };
    private _grp = ["COMSPEC_RscControlsGroup", [_lx, _y0, _lw, _bh - _y0]] call _mk;
    private _gw = _lw - 0.012;
    private _rh = _font * 2.75;
    private _avH = _rh * 0.68;
    private _avW = _avH / _ratio;
    private _seen = _seenMap;
    private _y = 0;
    private _section = {
        params ["_t", ["_right", ""]];
        ["COMSPEC_RscText", [_pad, _y + _fs * 0.35, _gw * 0.7, _fs * 1.3], toUpper _t, _fs * 0.8, _cDim, true, _grp] call _lbl;
        if (_right isNotEqualTo "") then { ["COMSPEC_RscTextRight", [_gw * 0.3, _y + _fs * 0.35, _gw * 0.7 - _pad, _fs * 1.3], _right, _fs * 0.8, _cDim, false, _grp] call _lbl; };
        _y = _y + _fs * 1.75;
    };
    // Une ligne : vignette, nom + heure, dernier message + pastille.
    private _row = {
        params ["_key", "_name", "_iconPath", "_tint", "_unit", "_last", "_time", "_unread", ["_sub", ""], ["_ini", ""]];
        private _cur = _split && {_key isEqualTo _peer};
        if (_cur) then {
            [[0, _y, _gw, _rh], _cTile, _grp] call _box;
            [[0, _y, pixelW * 3, _rh], _acc, _grp] call _box;
        };
        [[_pad, _y + (_rh - _avH) / 2, _avW, _avH], _iconPath, _tint, _unit, _grp, _ini] call _avatar;
        private _tx = _pad * 1.6 + _avW;
        private _tw = _gw - _tx - _pad;
        private _timeW = _tw * 0.3;
        ["COMSPEC_RscText", [_tx, _y + _rh * 0.12, _tw - _timeW, _rh * 0.42], _name, _font * 0.92, _cText, true, _grp] call _lbl;
        ["COMSPEC_RscTextRight", [_tx + _tw - _timeW, _y + _rh * 0.14, _timeW, _rh * 0.4], _time, _fs * 0.82, [_cDim, _acc] select (_unread > 0), _unread > 0, _grp] call _lbl;
        private _bdW = [0, ((_fs * 1.25) / _ratio) max ((_fs * 0.55 * (count str _unread) + _fs * 0.6) / _ratio)] select (_unread > 0);
        private _prevTxt = [_last, _sub] select (_last isEqualTo "");
        ["COMSPEC_RscText", [_tx, _y + _rh * 0.52, _tw - _bdW - _pad * 0.4, _rh * 0.38], _prevTxt select [0, 90], _fs * 0.9, [_cDim, [0.78, 0.84, 0.81, 1]] select (_unread > 0), false, _grp] call _lbl;
        if (_unread > 0) then {
            private _b = ["COMSPEC_RscTextCenter", [_tx + _tw - _bdW, _y + _rh * 0.53, _bdW, _fs * 1.15], str (_unread min 99), _fs * 0.78, [0.03, 0.05, 0.04, 1], true, _grp] call _lbl;
            _b ctrlSetBackgroundColor _acc;
        };
        [[_tx, _y + _rh - pixelH, _gw - _tx, pixelH], [1, 1, 1, 0.05], _grp] call _box;
        [[0, _y, _gw, _rh], [_key] call _open, _name, _grp] call _hit;
        _y = _y + _rh;
    };
    // Canaux
    [["Canaux", "TOC"] select !_bridge, ["", "Athena"] select _bridge] call _section;
    {
        _x params ["_key", "_label"];
        private _ck = [_key select [3], ""] select (_key isEqualTo "ATHENA");
        private _msgs = [_ck] call comspec_atak_native_fnc_messagesAll;
        private _n = count _msgs;
        if !(_key in _seen) then { _seen set [_key, _n]; };
        private _unread = (_n - (_seen get _key)) max 0;
        private _lastTxt = "";
        private _lastTime = "";
        if (_n > 0) then {
            private _m = _msgs select (_n - 1);
            _lastTime = _m get "time";
            _lastTxt = format ["%1 : %2", [_m get "author", ("Vous") call comspec_atak_native_fnc_tr] select (_m get "mine"), _m get "body"];
        };
        private _alert = _ck isEqualTo "alertes";
        [_key, (_label) call comspec_atak_native_fnc_tr, _dir + (["ui_hash.paa", "app_alerts.paa"] select _alert), [_acc, [0.95, 0.55, 0.20, 1]] select _alert, objNull,
            _lastTxt, _lastTime, _unread, ["Aucun message", "Alertes et notifications du TOC"] select _alert] call _row;
    } forEach _channels;
    // Messages directs
    _y = _y + _fs * 0.4;
    ["Messages directs", "SMS"] call _section;
    if ((count _peers) isEqualTo 0) then {
        private _t = ["COMSPEC_RscStructuredText", [_pad, _y, _gw - 2 * _pad, _font * 2.4], "", _fs, _cText, false, _grp] call _lbl;
        _t ctrlSetStructuredText parseText (("<t color='#8a9a93' size='0.9'>Aucun autre joueur de votre camp en ligne. Les SMS passent de téléphone à téléphone, sans Athena.</t>") call comspec_atak_native_fnc_tr);
        _y = _y + _font * 2.4;
    };
    {
        private _name = _x;
        private _conv = (_data getOrDefault ["p2p", []]) select { (_x getOrDefault ["peer", ""]) isEqualTo _name };
        private _n = { (_x getOrDefault ["dir", ""]) isEqualTo "in" && {!(_x getOrDefault ["read", false])} } count _conv;
        private _u = [_name] call _unitFor;
        private _lastTxt = "";
        private _lastTime = "";
        if ((count _conv) > 0) then {
            private _m = _conv select ((count _conv) - 1);
            ([_m getOrDefault ["body", ""], ""] call comspec_atak_native_fnc_chatParse) params ["", "", "_ptext"];
            _lastTime = _m getOrDefault ["time", ""];
            _lastTxt = [_ptext, format ["%1 : %2", ("Vous") call comspec_atak_native_fnc_tr, _ptext]] select ((_m getOrDefault ["dir", ""]) isEqualTo "out");
        };
        [_name, _name, _dir + "app_group.paa", [_name] call _authorCol, _u, _lastTxt, _lastTime, _n,
            (["Hors ligne", "Écrire un SMS"] select !(isNull _u)) call comspec_atak_native_fnc_tr, [_name] call _initials] call _row;
    } forEach _peers;
    _y = _y + _pad;
    // Fond cliquable de fin de liste (rien) : la hauteur fixe le défilement.
    [[0, _y, _gw, pixelH], [0, 0, 0, 0], _grp] call _box;
};

// ================================================================ CONVERSATION
private _drawConv = {
    params ["_cx", "_cw", "_back"];
    private _wiki = _s getOrDefault ["chatWiki", false];
    // --- En-tête de la conversation
    private _hh = _font * 2.5;
    [[_cx, 0, _cw, _hh], _cHdr] call _box;
    private _u = if (_isChannel) then { objNull } else { [_peer] call _unitFor };
    private _alert = _isChannel && {_curKey isEqualTo "alertes"};
    private _tint = switch (true) do { case (!_isChannel): { [_peer] call _authorCol }; case (_alert): { [0.95, 0.55, 0.20, 1] }; default { _acc } };
    [[_cx, _hh - pixelH * 2, _cw, pixelH * 2], [_tint select 0, _tint select 1, _tint select 2, 0.7]] call _box;
    private _hx = _cx + _pad * 0.5;
    if (_back) then {
        private _bw0 = _hh * 0.8 / _ratio;
        [[_hx, _hh * 0.1, _bw0, _hh * 0.8], _dir + "ui_back.paa", "(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['chatView', 'LIST']; " + _rr, "Toutes les conversations", _cText, [0, 0, 0, 0]] call _iconBtn;
        _hx = _hx + _bw0;
    };
    private _avH = _hh * 0.7;
    private _avW = _avH / _ratio;
    [[_hx + _pad * 0.3, (_hh - _avH) / 2, _avW, _avH], _dir + (switch (true) do { case (!_isChannel): { "app_group.paa" }; case (_alert): { "app_alerts.paa" }; case (_peer isEqualTo "ATHENA"): { "app_athena.paa" }; default { "ui_hash.paa" } }), _tint, _u, controlNull, ["", [_peer] call _initials] select !_isChannel] call _avatar;
    _hx = _hx + _avW + _pad * 0.9;
    // Boutons à droite : aide, tri, vider (en main), supprimer (canal créé, avec Athena).
    private _btns = [];
    if (_interactive) then {
        _btns pushBack [_dir + "ui_help.paa", "private _s = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]; _s set ['chatWiki', !(_s getOrDefault ['chatWiki', false])]; " + _rr,
            ["Aide du tchat (commandes /)", "Fermer l'aide"] select _wiki, [_cText, [0.03, 0.05, 0.04, 1]] select _wiki, [[1, 1, 1, 0.05], _acc] select _wiki, true];
        private _desc = _s getOrDefault ["chatSortDesc", false];
        _btns pushBack [_dir + (["ui_sort_up.paa", "ui_sort_down.paa"] select _desc), "private _s = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]; _s set ['chatSortDesc', !(_s getOrDefault ['chatSortDesc', false])]; " + _rr,
            ["Plus anciens en haut (cliquer : plus récents en haut)", "Plus récents en haut (cliquer : plus anciens en haut)"] select _desc, _cText, [1, 1, 1, 0.05], true];
        // VIDER : masque les messages affichés de cette conversation sur ce téléphone (rien n'est supprimé sur Athena).
        _btns pushBack [_dir + "ui_erase.paa", "private _s = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]; private _cleared = uiNamespace getVariable ['COMSPEC_ATAK_ChatCleared', createHashMap]; private _peer = _s getOrDefault ['chatPeer', 'ATHENA']; private _keys = _cleared getOrDefault [_peer, []]; _keys append (uiNamespace getVariable ['COMSPEC_ATAK_ChatShownKeys', []]); _cleared set [_peer, _keys select [((count _keys) - 300) max 0]]; uiNamespace setVariable ['COMSPEC_ATAK_ChatCleared', _cleared]; " + _rr,
            "Vider l'affichage de cette conversation (sur ce téléphone seulement)", _cText, [1, 1, 1, 0.05], true];
        if (_manage && {_isChannel} && {_peer isNotEqualTo "ATHENA"}) then {
            private _armed = (_s getOrDefault ["chatDelArm", ""]) isEqualTo _curKey;
            _btns pushBack [_dir + "ui_trash.paa", "private _s = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]; private _key = (_s getOrDefault ['chatPeer', '']) select [3]; if ((_s getOrDefault ['chatDelArm', '']) isNotEqualTo _key) exitWith { _s set ['chatDelArm', _key]; " + _rr + " }; _s set ['chatDelArm', '']; [_key] spawn { params ['_key']; if ([_key] call comspec_overwatch_connect_fnc_deleteChatChannel) then { private _s = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]; _s set ['chatPeer', 'CH:general']; _s set ['chatView', 'LIST']; }; [{ ['CHAT'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };",
                [["Seuls les canaux créés depuis le web ou le terminal se suppriment", "Supprimer ce canal pour tout le monde"] select _custom, "Cliquer encore pour supprimer ce canal pour tout le monde"] select _armed,
                [_cRed, [1, 1, 1, 1]] select _armed, [[1, 1, 1, 0.05], _cRed] select _armed, _custom];
        };
    };
    private _ib = _hh * 0.66;
    private _ibW = _ib / _ratio;
    private _bx = _cx + _cw - _pad * 0.5;
    {
        _x params ["_ic", "_code", "_tip", "_col", "_bg", "_en"];
        _bx = _bx - _ibW;
        [[_bx, (_hh - _ib) / 2, _ibW, _ib], _ic, _code, _tip, _col, _bg, _en] call _iconBtn;
        _bx = _bx - _pad * 0.35;
    } forEach (+_btns);
    // Titre et sous-titre.
    private _title = if (_isChannel) then { ((_channels select { (_x select 0) isEqualTo _peer }) param [0, ["", _peer]]) select 1 } else { _peer };
    private _sub = switch (true) do {
        case (!_isChannel): { format ["SMS · %1", if (isNull _u) then { "hors ligne" } else { ([_u] call comspec_atak_native_fnc_phoneIdent) select 0 }] };
        case (_alert): { "Alertes et notifications du TOC" };
        case (_peer isEqualTo "ATHENA"): { "Canal TOC · Athena" };
        default { ["Canal Athena", "Canal Athena · créé sur le web ou au terminal"] select _custom };
    };
    private _tt = ["COMSPEC_RscStructuredText", [_hx, _hh * 0.12, (_bx - _hx) max (_cw * 0.2), _hh * 0.85]] call _mk;
    _tt ctrlSetFontHeight _font;
    _tt ctrlSetStructuredText parseText (((format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.72' color='#8a9a93'>%2</t>", [(_title) call comspec_atak_native_fnc_tr] call _esc, [(_sub) call comspec_atak_native_fnc_tr] call _esc])) call comspec_atak_native_fnc_tr);
    private _topY = _hh;
    // Suppression armée : bandeau de confirmation.
    if (_manage && {(_s getOrDefault ["chatDelArm", ""]) isEqualTo _curKey} && {_isChannel}) then {
        private _bn = ["COMSPEC_RscStructuredText", [_cx, _topY, _cw, _fs * 1.6]] call _mk;
        _bn ctrlSetBackgroundColor [0.36, 0.09, 0.07, 1];
        _bn ctrlSetFontHeight _fs;
        _bn ctrlSetStructuredText parseText ((format ["<t align='center'>Supprimer « %1 » pour tout le monde ? Cliquez encore sur la corbeille.</t>", [(_title) call comspec_atak_native_fnc_tr] call _esc]) call comspec_atak_native_fnc_tr);
        _topY = _topY + _fs * 1.6;
    };

    // --- Messages de la conversation
    private _items = []; // [moi, auteur, heure, date, texte, puces, clé, état, non lu, urgent, texte brut]
    if (_isChannel) then {
        private _msgs = [_s getOrDefault ["chatChannel", ""]] call comspec_atak_native_fnc_messagesAll;
        {
            private _mine = _x get "mine";
            private _urgent = ((_x get "tags") findIf { _x in ["URGENT", "FLASH", "PRIORITAIRE", "IMMEDIATE"] }) >= 0;
            private _tags = ((_x get "tags") apply { format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t>", ["#f2ab33", "#e5483a"] select (_x in ["URGENT", "FLASH", "PRIORITAIRE", "IMMEDIATE"]), [_x] call _esc] }) joinString " ";
            private _status = switch (_x get "status") do { case "FAILED": { "<t color='#e5483a'>Non transmis</t>" }; case "SENT": { "<t color='#8a9a93'>Envoi…</t>" }; case "PENDING": { "<t color='#f2ab33'>En attente de réseau</t>" }; default { "" }; };
            private _stamp = _x getOrDefault ["stamp", ""];
            private _date = [_today, (_stamp splitString " ") select 0] select ((_stamp find "-") > 0);
            _items pushBack [_mine, ["TOC", _x get "author"] select ((_x get "author") isNotEqualTo ""), _x get "time", _date, [_x get "body"] call _esc, _tags,
                toLower format ["%1|%2|%3", _x get "author", _x get "time", _x get "body"], _status, false, _urgent, _x get "body"];
        } forEach (_msgs select [((count _msgs) - 60) max 0]);
        _s set ["seenAthena", count ([] call comspec_atak_native_fnc_messagesAll)];
        _seenMap set [_peer, count _msgs];
    } else {
        {
            if ((_x getOrDefault ["peer", ""]) isEqualTo _peer) then {
                private _out = (_x getOrDefault ["dir", ""]) isEqualTo "out";
                ([_x getOrDefault ["body", ""], ""] call comspec_atak_native_fnc_chatParse) params ["", "_ptags", "_ptext"];
                private _hot = ["URGENT", "FLASH", "IMPORTANT", "CONTACT", "TIC", "MEDEVAC"];
                private _chips = (_ptags apply { format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t>", ["#f2ab33", "#e5483a"] select (_x in _hot), [_x] call _esc] }) joinString " ";
                // État d'un SMS envoyé : en attente de réseau, envoi, distribué, lu, non transmis (60 s sans accusé).
                private _st = _x getOrDefault ["status", ""];
                if (_st isEqualTo "SENDING" && {time - (_x getOrDefault ["sentAt", time]) > 60}) then { _st = "FAILED"; };
                private _status = if (_out) then { switch (_st) do {
                    case "READ": { format ["<t color='#7fd0ff'>Lu %1</t>", _x getOrDefault ["statusTime", ""]] };
                    case "DELIVERED": { "<t color='#c9d4cf'>Distribué</t>" };
                    case "QUEUED": { "<t color='#f2ab33'>Non transmis · en attente de réseau</t>" };
                    case "FAILED": { "<t color='#e5483a'>Non transmis</t>" };
                    case "SENDING": { "<t color='#8a9a93'>Envoi…</t>" };
                    default { "" };
                } } else { "" };
                private _unread = !_out && {!(_x getOrDefault ["read", false])};
                _items pushBack [_out, _peer, _x getOrDefault ["time", "--:--"], _x getOrDefault ["date", _today], [_ptext] call _esc, _chips,
                    toLower format ["%1|%2|%3", _x getOrDefault ["dir", ""], _x getOrDefault ["time", ""], _x getOrDefault ["body", ""]], _status, _unread,
                    (_ptags findIf { _x in _hot }) >= 0, _ptext];
                // Conversation ouverte : les SMS reçus passent à « lu », l'expéditeur en est informé.
                if (_unread) then {
                    _x set ["read", true];
                    private _snd = _x getOrDefault ["sender", objNull];
                    if ((_x getOrDefault ["id", ""]) isNotEqualTo "" && {!isNull _snd} && {_interactive}) then {
                        ["comspec_atak_native_p2pAck", [_x get "id", "READ", [dayTime, "HH:MM"] call BIS_fnc_timeToString], _snd] call CBA_fnc_targetEvent;
                    };
                };
            };
        } forEach (_data getOrDefault ["p2p", []]);
    };
    // Tri par date et heure (ordre d'arrivée à égalité), puis sens choisi.
    private _keyed = [];
    { _keyed pushBack [format ["%1 %2", _x select 3, _x select 2], _forEachIndex, _x]; } forEach _items;
    _keyed sort true;
    _items = _keyed apply { _x select 2 };
    private _desc = _s getOrDefault ["chatSortDesc", false];
    if (_desc) then { reverse _items; };
    // Messages vidés de l'affichage
    private _hidden = (uiNamespace getVariable ["COMSPEC_ATAK_ChatCleared", createHashMap]) getOrDefault [_peer, []];
    _items = _items select { !((_x select 6) in _hidden) };
    uiNamespace setVariable ["COMSPEC_ATAK_ChatShownKeys", _items apply { _x select 6 }];

    // --- Zones : fil, aperçu des commandes, barre de saisie.
    private _barH = _rowH + _pad * 1.2;
    private _prevH = _fs * 1.5;
    private _threadY = _topY;
    private _threadH = _bh - _threadY - ([0, _barH + _prevH] select _interactive);
    if (_wiki) then { _items = []; };
    private _thread = ["COMSPEC_RscControlsGroup", [_cx, _threadY, _cw, _threadH]] call _mk;
    private _tw = _cw - 0.012 - 2 * _pad;
    private _y = _pad * 0.6;
    if (_wiki) then {
        // Mini wiki du tchat
        private _t = [_thread, "COMSPEC_RscStructuredText", [_pad, _y, _tw, _threadH]] call _gmk;
        private _h = { params ["_x"]; format ["<t color='%2' font='COMSPEC_BarlowBold'>%1</t>", _x, _accHex] };
        private _c = { params ["_k", "_v"]; format ["<t font='EtelkaMonospacePro' color='#f2ab33'>%1</t>  %2", _k, _v] };
        _t ctrlSetStructuredText parseText ((([
            ["AIDE DU TCHAT"] call _h,
            "<t color='#8a9a93'>Tapez une ou plusieurs commandes au début du message, puis le texte. L'aperçu au-dessus de la saisie montre ce qui partira.</t>",
            "", ["Priorité"] call _h,
            ["/routine  /r", "message courant (par défaut)"] call _c,
            ["/prioritaire  /p", "à traiter rapidement"] call _c,
            ["/urgent  /u", "tout de suite, s'affiche en rouge"] call _c,
            "", ["Type de compte rendu"] call _h,
            ["/contact  /c", "contact ennemi"] call _c,
            ["/tic", "troupes au contact"] call _c,
            ["/sitrep", "point de situation"] call _c,
            ["/salute", "taille, activité, lieu, unité, heure, équipement"] call _c,
            ["/lace", "munitions, eau, blessés, équipement"] call _c,
            ["/medevac", "demande d'évacuation"] call _c,
            ["/intel", "renseignement"] call _c,
            ["/ordre", "ordre ou consigne"] call _c,
            ["/log", "logistique"] call _c,
            "", ["Raccourcis dans le texte"] call _h,
            ["@grille", "ma grille (8 chiffres)"] call _c,
            ["@heure", "l'heure du jeu"] call _c,
            ["@cap", "mon cap"] call _c,
            ["@alt", "mon altitude"] call _c,
            "", ["Exemples"] call _h,
            "<t font='EtelkaMonospacePro'>/u /contact 2 BMP en approche @grille</t>",
            "<t font='EtelkaMonospacePro'>/sitrep RAS sur la position, en attente</t>",
            "", "<t color='#8a9a93'>/aide rouvre cette page. Le bouton ? aussi.</t>"
        ] joinString "<br/>")) call comspec_atak_native_fnc_tr);
        _t ctrlSetPosition [_pad, _y, _tw, (ctrlTextHeight _t) max _threadH];
        _t ctrlCommit 0;
    };
    if ((count _items) isEqualTo 0 && {!_wiki}) then {
        private _eh = _font * 2.2;
        [[_cx + (_cw - _eh * 1.6 / _ratio) / 2, _threadY + _threadH * 0.28, _eh * 1.6 / _ratio, _eh * 1.6], [_dir + "app_chat.paa", _dir + "app_group.paa"] select !_isChannel, [_cDim select 0, _cDim select 1, _cDim select 2, 0.45], 0.9] call _icon;
        private _t = ["COMSPEC_RscStructuredText", [_cx + _pad, _threadY + _threadH * 0.28 + _eh * 1.8, _cw - 2 * _pad, _font * 3]] call _mk;
        _t ctrlSetFontHeight _fs;
        _t ctrlSetStructuredText parseText (((["<t color='#8a9a93' align='center'>Aucun message. Écrivez ci-dessous pour démarrer la conversation.</t>", "<t color='#8a9a93' align='center'>Aucun message du TOC pour l'instant.</t>"] select _isChannel)) call comspec_atak_native_fnc_tr);
    };
    // Bulles : les miennes à droite ; les autres à gauche avec la photo de l'opérateur (TOC : logo Athena),
    // une seule fois par série du même auteur. SMS : bulles bleues pour les miennes.
    private _avH = _font * 1.8;
    private _avW = _avH / _ratio;
    private _sms = !_isChannel;
    private _bodyFh = _font * 0.88;
    private _meas = [_thread, "COMSPEC_RscText", [0, 0, _tw, _bodyFh]] call _gmk;
    _meas ctrlSetTextColor [0, 0, 0, 0];
    _meas ctrlSetFontHeight ((_bodyFh) max COMSPEC_ATAK_FontMin);
    private _dayLabel = {
        params ["_dt"];
        if (_dt isEqualTo _today) exitWith { "Aujourd'hui" };
        private _p = _dt splitString "-";
        if ((count _p) < 3) exitWith { _dt };
        format ["%1/%2/%3", _p select 2, _p select 1, _p select 0]
    };
    private _lastDay = "";
    private _lastAuthor = "";
    private _unreadShown = false;
    {
        _x params ["_mine", "_author", "_time", "_date", "_body", "_chips", "", "_status", "_unread", "_urgent", "_raw"];
        // Séparateur de jour : pastille centrée.
        if (_date isNotEqualTo _lastDay) then {
            _lastDay = _date;
            _lastAuthor = "";
            _y = _y + _font * 0.2;
            private _sep = [_thread, "COMSPEC_RscTextCenter", [0, _y, _tw, _fs * 1.25], ([_date] call _dayLabel)] call _gmk;
            _sep ctrlSetFontHeight ((_fs * 0.82) max COMSPEC_ATAK_FontMin);
            _sep ctrlSetTextColor [0.70, 0.76, 0.73, 1];
            private _sw = ((ctrlTextWidth _sep) + _pad * 1.4) min _tw;
            _sep ctrlSetPosition [_pad + (_tw - _sw) / 2, _y, _sw, _fs * 1.25];
            _sep ctrlSetBackgroundColor [1, 1, 1, 0.06];
            _sep ctrlCommit 0;
            _y = _y + _fs * 1.25 + _font * 0.35;
        };
        // Repère « non lus » avant le premier message pas encore lu.
        if (_unread && {!_unreadShown}) then {
            _unreadShown = true;
            [[_pad, _y + _fs * 0.6, _tw, pixelH], [_acc select 0, _acc select 1, _acc select 2, 0.5], _thread] call _box;
            private _nl = [_thread, "COMSPEC_RscTextCenter", [0, _y, _tw, _fs * 1.2], "NON LUS"] call _gmk;
            _nl ctrlSetFontHeight ((_fs * 0.75) max COMSPEC_ATAK_FontMin);
            _nl ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
            _nl ctrlSetTextColor _acc;
            private _nw = (ctrlTextWidth _nl) + _pad;
            _nl ctrlSetPosition [_pad + (_tw - _nw) / 2, _y, _nw, _fs * 1.2];
            _nl ctrlSetBackgroundColor _cBg;
            _nl ctrlCommit 0;
            _y = _y + _fs * 1.4;
            _lastAuthor = "";
        };
        private _who = format ["%1|%2", _mine, _author];
        private _first = _who isNotEqualTo _lastAuthor;
        _lastAuthor = _who;
        if (_first && {!(_forEachIndex isEqualTo 0)}) then { _y = _y + _font * 0.3; };
        private _aCol = [_author] call _authorCol;
        private _showAv = !_mine && {!_sms};
        private _indent = [0, _avW + _pad * 0.6] select _showAv;
        private _maxW = (_tw - _indent) * ([0.82, 0.80] select _mine);
        // Largeur ajustée au texte (court : bulle étroite), plafonnée.
        _meas ctrlSetText ((_raw) call comspec_atak_native_fnc_tr);
        private _need = (ctrlTextWidth _meas) * 1.08;
        _meas ctrlSetText ((format ["%1  %2", _time, (_status regexReplace ["<[^>]*>", ""])]) call comspec_atak_native_fnc_tr);
        _need = _need max ((ctrlTextWidth _meas) * 0.8);
        if (_showAv && {_first}) then { _meas ctrlSetText ((_author) call comspec_atak_native_fnc_tr); _need = _need max ((ctrlTextWidth _meas) * 0.9); };
        if (_chips isNotEqualTo "") then { _need = _need max (_maxW * 0.45); };
        private _bubbleW = ((_need + _pad * 1.6) max (_tw * 0.22)) min _maxW;
        private _bx = [_pad + _indent, _pad + _tw - _bubbleW] select _mine;
        if (_showAv && {_first}) then {
            private _u = [_author] call _unitFor;
            private _toc = (toUpper _author) in ["TOC", "ATHENA"];
            [[_pad, _y, _avW, _avH], _dir + "app_athena.paa", [_aCol, _acc] select _toc, _u, _thread, ["", [_author] call _initials] select !_toc] call _avatar;
        };
        private _bg = switch (true) do {
            case (_sms && _mine): { _cBlue };
            case (_mine): { _accDark };
            case (_urgent): { [0.20, 0.07, 0.06, 1] };
            default { [0.085, 0.102, 0.093, 1] };
        };
        private _b = [_thread, "COMSPEC_RscStructuredText", [_bx, _y, _bubbleW, _font]] call _gmk;
        _b ctrlSetBackgroundColor _bg;
        _b ctrlSetFontHeight ((_bodyFh) max COMSPEC_ATAK_FontMin);
        private _top = if (_showAv && {_first}) then {
            format ["<t size='0.82' font='COMSPEC_BarlowBold' color='%1'>%2</t>%3<br/>", [_aCol] call _hex, [_author] call _esc, ["", " " + _chips] select (_chips isNotEqualTo "")]
        } else { ["", _chips + "<br/>"] select (_chips isNotEqualTo "") };
        private _foot = format ["<t align='right' size='0.72' color='%3'>%1%2</t>", _time, ["", "  " + _status] select (_status isNotEqualTo ""), ["#8a9a93", "#d6e4ef"] select (_sms && _mine)];
        _b ctrlSetStructuredText parseText ((format ["%1%2<br/>%3", _top, _body, _foot]) call comspec_atak_native_fnc_tr);
        private _h = (ctrlTextHeight _b) + _font * 0.15;
        _b ctrlSetPosition [_bx, _y, _bubbleW, _h];
        _b ctrlCommit 0;
        // Liseré : accent (moi), couleur de l'auteur, rouge si urgent.
        private _edge = switch (true) do { case (_urgent): { _cRed }; case (_mine): { [_acc, [0.45, 0.70, 1, 1]] select _sms }; default { _aCol } };
        [[[_bx, _bx + _bubbleW - pixelW * 2] select _mine, _y, pixelW * 2, _h], _edge, _thread] call _box;
        _y = _y + (_h max ([0, _avH] select (_showAv && {_first}))) + _font * 0.18;
    } forEach _items;
    _y = _y + _pad;
    [[0, _y, _tw, pixelH], [0, 0, 0, 0], _thread] call _box;
    _thread ctrlSetScrollValues [[[1, 0] select _desc, 0] select _wiki, -1];
    if !(_interactive) exitWith {};

    // --- Aperçu des commandes : type détecté, puces et texte final ; suggestions pendant qu'on tape une commande.
    private _by = _bh - _barH;
    private _prev = ["COMSPEC_RscStructuredText", [_cx + _pad, _by - _prevH, _cw - 2 * _pad, _prevH]] call _mk;
    _prev ctrlSetFontHeight _fs;
    uiNamespace setVariable ["COMSPEC_ATAK_ChatPreview", _prev];
    uiNamespace setVariable ["COMSPEC_ATAK_ChatPreviewCode", {
        params ["_raw"];
        private _p = uiNamespace getVariable ["COMSPEC_ATAK_ChatPreview", controlNull];
        if (isNull _p) exitWith {};
        private _txt = trim _raw;
        if (_txt isEqualTo "" || {!((_txt select [0, 1]) isEqualTo "/") && {(_txt find "@") < 0}}) exitWith {
            _p ctrlSetStructuredText parseText (("<t size='0.8' color='#5b6b63'>Astuce : /urgent, /contact, /sitrep… et @grille. Tapez /aide.</t>") call comspec_atak_native_fnc_tr);
        };
        private _words = _txt splitString " ";
        private _last = _words select ((count _words) - 1);
        private _typing = ((_last select [0, 1]) isEqualTo "/") && {!(_txt select [(count _txt) - 1] isEqualTo " ")} && {(_words findIf { (_x select [0, 1]) isNotEqualTo "/" }) < 0};
        if (_typing) exitWith {
            private _all = ["routine", "prioritaire", "urgent", "contact", "tic", "sitrep", "salute", "lace", "medevac", "intel", "ordre", "log", "aide"];
            private _m = _all select { (_x select [0, (count _last) - 1]) isEqualTo (toLower (_last select [1])) };
            _p ctrlSetStructuredText parseText ((format ["<t size='0.8' color='#8a9a93'>Commandes : </t><t size='0.8' font='EtelkaMonospacePro' color='#f2ab33'>%1</t>", (_m apply { "/" + _x }) joinString "  "]) call comspec_atak_native_fnc_tr);
        };
        ([_txt] call comspec_atak_native_fnc_chatCommand) params ["", "", "_text", "_unknown", "_tags"];
        private _chips = (_tags apply { format ["<t font='COMSPEC_BarlowBold' color='%1'>[%2]</t>", ["#f2ab33", "#e5483a"] select (_x in ["URGENT", "CONTACT", "TIC", "MEDEVAC"]), _x] }) joinString " ";
        private _bad = ["", format ["  <t color='#e5483a'>inconnu : %1</t>", _unknown joinString " "]] select ((count _unknown) > 0);
        private _safe = [[_text, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace;
        _p ctrlSetStructuredText parseText ((format ["<t size='0.8' color='#8a9a93'>Aperçu : </t><t size='0.8'>%1 %2</t>%3", _chips, _safe, _bad]) call comspec_atak_native_fnc_tr);
    }];
    [uiNamespace getVariable ["COMSPEC_ATAK_ChatDraft", ""]] call (uiNamespace getVariable "COMSPEC_ATAK_ChatPreviewCode");

    // --- Barre de saisie : champ (filet d'accent) et bouton d'envoi.
    [[_cx, _by, _cw, _barH], _cHdr] call _box;
    [[_cx, _by, _cw, pixelH], _cLine] call _box;
    private _ey = _by + (_barH - _rowH) / 2;
    private _sendW = _rowH * 1.25 / _ratio;
    private _ex = _cx + _pad * 0.6;
    private _ew = _cw - _sendW - _pad * 1.8;
    [[_ex, _ey, _ew, _rowH], _cField] call _box;
    [[_ex, _ey + _rowH - pixelH * 2, _ew, pixelH * 2], [_acc select 0, _acc select 1, _acc select 2, 0.75]] call _box;
    private _edit = ["COMSPEC_RscEdit", [_ex + _pad * 0.3, _ey, _ew - _pad * 0.3, _rowH], uiNamespace getVariable ["COMSPEC_ATAK_ChatDraft", ""]] call _mk;
    _edit ctrlSetBackgroundColor [0, 0, 0, 0];
    _edit ctrlSetFontHeight _font;
    _edit ctrlSetTooltip ((["Message au canal (Entrée pour envoyer, /aide pour les commandes)", "SMS (Entrée pour envoyer, /aide pour les commandes)"] select _sms) call comspec_atak_native_fnc_tr);
    _edit ctrlAddEventHandler ["KeyUp", { params ["_c"]; [ctrlText _c] call (uiNamespace getVariable ["COMSPEC_ATAK_ChatPreviewCode", {}]); }];
    uiNamespace setVariable ["COMSPEC_ATAK_ChatEdit", _edit];
    private _sr = [_cx + _cw - _sendW - _pad * 0.6, _ey, _sendW, _rowH];
    [_sr, [_acc, _cBlue] select _sms] call _box;
    [_sr, _dir + "ui_send.paa", [0.03, 0.05, 0.04, 1], 0.62] call _icon;
    private _send = [_sr, { [] call (uiNamespace getVariable ["COMSPEC_ATAK_ChatSendCode", {}]); }, "Envoyer"] call _hit;
    private _doSend = {
        private _peer = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["chatPeer", "ATHENA"];
        private _edit = uiNamespace getVariable ["COMSPEC_ATAK_ChatEdit", controlNull];
        if (isNull _edit) exitWith {};
        if (_peer isEqualTo "ATHENA" || {(_peer select [0, 3]) isEqualTo "CH:"}) then {
            [] call comspec_atak_native_fnc_chatSend;
        } else {
            if ([_peer, ctrlText _edit] call comspec_atak_native_fnc_p2pSend) then {
                _edit ctrlSetText (("") call comspec_atak_native_fnc_tr);
                uiNamespace setVariable ["COMSPEC_ATAK_ChatDraft", ""];
                [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
            };
        };
    };
    uiNamespace setVariable ["COMSPEC_ATAK_ChatSendCode", _doSend];
    _edit ctrlAddEventHandler ["KeyDown", {
        params ["", "_key"];
        if (_key in [28, 156]) exitWith { [] call (uiNamespace getVariable ["COMSPEC_ATAK_ChatSendCode", {}]); true };
        false
    }];
    if !(_s getOrDefault ["chatNewOpen", false]) then { ctrlSetFocus _edit; };
};

// ---------------------------------------------------------------- Rendu
if (_split) exitWith {
    [0, _listW] call _drawList;
    [[_listW, 0, pixelW, _bh], _cLine] call _box;
    [_convX, _convW, false] call _drawConv;
    true
};
if (_view isEqualTo "LIST") exitWith {
    [0, _bw] call _drawList;
    true
};
[0, _bw, _interactive] call _drawConv;
true
