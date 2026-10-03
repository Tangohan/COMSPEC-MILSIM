/*
    Messagerie : canal TOC (Athena) + messages directs entre joueurs.
    Les messages directs reprennent le principe de l'app message de BCE (Aaren, APL-SA) :
    un événement CBA ciblé sur le destinataire, sans serveur ni Athena, donc utilisable hors ligne.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _font = _l get "font";
private _rowH = _font * 1.6;
private _gw = _bw - 0.012;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _peer = _s getOrDefault ["chatPeer", "ATHENA"];

private _bridge = [] call comspec_atak_native_fnc_bridge;
private _interactiveTop = _l get "interactive";
// Canaux web : Overwatch connect ne les relit qu'à la création ou suppression, on les rafraîchit ici (toutes les 20 s).
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
// Canaux système (non supprimables), comme dans Overwatch connect.
private _system = ["groupe", "commandement", "general", "jtac", "air", "squad", "global", "hq", "c2", "command", "group", "alertes"];
// Canaux Athena (fil Overwatch connect quand il est chargé), puis messages directs.
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

// Destinataire (+ créer / supprimer un canal en main, avec Athena)
private _manage = _bridge && {_interactiveTop};
private _mbw = [0, _gw * 0.14] select _interactiveTop;
// Boutons d'en-tête en main : VIDER (toujours), + CANAL et SUPPRIMER (avec Athena).
private _nBtn = [0, [1, 3] select _manage] select _interactiveTop;
private _combo = ["COMSPEC_RscCombo", [_pad, _pad, _gw - 2 * _pad - _nBtn * (_mbw + _pad / 2), _rowH]] call comspec_atak_native_fnc_pageCtrl;
_combo ctrlSetFontHeight _font;
private _peers = (allPlayers select { _x isNotEqualTo player && {side group _x isEqualTo side group player} }) apply { name _x };
{ if ((_x getOrDefault ["peer", ""]) isNotEqualTo "") then { _peers pushBackUnique (_x get "peer"); }; } forEach (_data getOrDefault ["p2p", []]);
_peers sort true;
private _sel = -1;
{
    _x params ["_key", "_label"];
    private _i = _combo lbAdd _label;
    _combo lbSetData [_i, _key];
    _combo lbSetPicture [_i, "\z\comspec_atak_native\addons\main\data\app_chat.paa"];
    if (_key isEqualTo _peer) then { _sel = _i; };
} forEach _channels;
{
    private _name = _x;
    private _n = { (_x getOrDefault ["peer", ""]) isEqualTo _name && {(_x getOrDefault ["dir", ""]) isEqualTo "in"} && {!(_x getOrDefault ["read", false])} } count (_data getOrDefault ["p2p", []]);
    // Messages directs (SMS entre téléphones, sans Athena) : libellés « SMS · » pour les distinguer des canaux.
    private _k = _combo lbAdd ([format ["SMS · %1", _name], format ["SMS · %1 (%2 non lus)", _name, _n]] select (_n > 0));
    _combo lbSetPicture [_k, "\z\comspec_atak_native\addons\main\data\app_group.paa"];
    _combo lbSetData [_k, _x];
    if (_x isEqualTo _peer) then { _sel = _k; };
} forEach _peers;
if (_sel < 0) then { _sel = 0; _peer = (_channels select 0) select 0; };
private _isChannel = _peer isEqualTo "ATHENA" || {(_peer select [0, 3]) isEqualTo "CH:"};
_s set ["chatChannel", [_peer select [3], ""] select (_peer isEqualTo "ATHENA")];
_s set ["chatPeer", _peer];
_combo lbSetCurSel _sel;
_combo ctrlAddEventHandler ["LBSelChanged", {
    params ["_c", "_index"];
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    _s set ["chatPeer", _c lbData _index];
    _s set ["chatDelArm", ""];
    [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];
private _topY = _pad * 2 + _rowH;
// VIDER : masque les messages affichés de cette conversation sur ce téléphone (rien n'est supprimé sur Athena).
if (_interactiveTop) then {
    private _bClr = ["COMSPEC_RscButton", [_gw - _pad - _nBtn * (_mbw + _pad / 2) + _pad / 2, _pad, _mbw, _rowH], "VIDER"] call comspec_atak_native_fnc_pageCtrl;
    _bClr ctrlSetFontHeight (_l get "fontSmall");
    _bClr ctrlSetTooltip "Vider l'affichage de cette conversation (sur ce téléphone seulement)";
    _bClr ctrlAddEventHandler ["ButtonClick", {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _cleared = uiNamespace getVariable ["COMSPEC_ATAK_ChatCleared", createHashMap];
        private _peer = _s getOrDefault ["chatPeer", "ATHENA"];
        private _keys = _cleared getOrDefault [_peer, []];
        _keys append (uiNamespace getVariable ["COMSPEC_ATAK_ChatShownKeys", []]);
        _cleared set [_peer, _keys select [((count _keys) - 300) max 0]];
        uiNamespace setVariable ["COMSPEC_ATAK_ChatCleared", _cleared];
        [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    }];
};
if (_manage) then {
    private _curKey = _peer select [3];
    private _custom = (_channelRows findIf { (_x select 0) isEqualTo _curKey && {_x select 2} }) >= 0;
    private _x0 = _gw - _pad - 2 * _mbw - _pad / 2;
    private _bNew = ["COMSPEC_RscButton", [_x0, _pad, _mbw, _rowH], "+ CANAL"] call comspec_atak_native_fnc_pageCtrl;
    _bNew ctrlSetFontHeight (_l get "fontSmall");
    _bNew ctrlSetTooltip "Créer un canal sur Athena";
    _bNew ctrlAddEventHandler ["ButtonClick", { private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _s set ["chatNewOpen", !(_s getOrDefault ["chatNewOpen", false])]; [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }];
    private _armed = (_s getOrDefault ["chatDelArm", ""]) isEqualTo _curKey;
    private _bDel = ["COMSPEC_RscButton", [_x0 + _mbw + _pad / 2, _pad, _mbw, _rowH], ["SUPPRIMER", "CONFIRMER ?"] select _armed] call comspec_atak_native_fnc_pageCtrl;
    _bDel ctrlSetFontHeight (_l get "fontSmall");
    _bDel ctrlEnable _custom;
    _bDel ctrlSetTooltip (["Seuls les canaux créés depuis le web ou le terminal se suppriment", "Supprimer ce canal pour tout le monde"] select _custom);
    if (_armed) then { _bDel ctrlSetBackgroundColor [0.70, 0.18, 0.14, 1]; };
    _bDel ctrlAddEventHandler ["ButtonClick", {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _key = (_s getOrDefault ["chatPeer", ""]) select [3];
        if ((_s getOrDefault ["chatDelArm", ""]) isNotEqualTo _key) exitWith {
            _s set ["chatDelArm", _key];
            [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        };
        _s set ["chatDelArm", ""];
        [_key] spawn {
            params ["_key"];
            if ([_key] call comspec_overwatch_connect_fnc_deleteChatChannel) then {
                (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["chatPeer", "CH:general"];
            };
            [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        };
    }];
    // Ligne de création : nom du canal + CRÉER / ANNULER.
    if (_s getOrDefault ["chatNewOpen", false]) then {
        private _cw = _gw * 0.18;
        private _ne = ["COMSPEC_RscEdit", [_pad, _topY, _gw - 4 * _pad - 2 * _cw, _rowH], uiNamespace getVariable ["COMSPEC_ATAK_ChatNewDraft", ""]] call comspec_atak_native_fnc_pageCtrl;
        _ne ctrlSetFontHeight _font;
        _ne ctrlSetTooltip "Nom du nouveau canal";
        uiNamespace setVariable ["COMSPEC_ATAK_ChatNewEdit", _ne];
        private _ok = ["COMSPEC_RscButtonPrimary", [_gw - 2 * _pad - 2 * _cw, _topY, _cw, _rowH], "CRÉER"] call comspec_atak_native_fnc_pageCtrl;
        _ok ctrlSetFontHeight (_l get "fontSmall");
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
                };
                [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
            };
        }];
        private _no = ["COMSPEC_RscButton", [_gw - _pad - _cw, _topY, _cw, _rowH], "ANNULER"] call comspec_atak_native_fnc_pageCtrl;
        _no ctrlSetFontHeight (_l get "fontSmall");
        _no ctrlAddEventHandler ["ButtonClick", { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["chatNewOpen", false]; [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }];
        ctrlSetFocus _ne;
        _topY = _topY + _rowH + _pad;
    };
};

// Fil de discussion : une bulle par message, à droite pour mes messages, puces pour les préfixes Athena.
private _esc = {
    params ["_t"];
    if !(_t isEqualType "") then { _t = str _t; };
    _t = [_t, "&", "&amp;"] call CBA_fnc_replace;
    _t = [_t, "<", "&lt;"] call CBA_fnc_replace;
    [_t, ">", "&gt;"] call CBA_fnc_replace
};
private _items = []; // [moi, en-tête, texte, clé, auteur]
if (_isChannel) then {
    private _msgs = [_s getOrDefault ["chatChannel", ""]] call comspec_atak_native_fnc_messagesAll;
    {
        private _mine = _x get "mine";
        private _tags = (_x get "tags") apply { format ["<t font='RobotoCondensedBold' color='%1'>%2</t>", ["#f2ab33", "#e5483a"] select (_x in ["URGENT", "FLASH", "PRIORITAIRE", "IMMEDIATE"]), [_x] call _esc] };
        private _status = switch (_x get "status") do { case "FAILED": { " <t color='#e5483a'>non envoyé</t>" }; case "SENT": { " <t color='#8a9a93'>envoi…</t>" }; case "PENDING": { " <t color='#f2ab33'>en attente de réseau…</t>" }; default { "" }; };
        _items pushBack [_mine,
            format ["<t font='RobotoCondensedBold' color='%1'>%2</t> <t color='#8a9a93'>%3</t> %4%5", ["#5cc76b", "#9be3a5"] select _mine, [["Moi", _x get "author"] select !_mine] call _esc, [_x get "time"] call _esc, _tags joinString " ", _status],
            [_x get "body"] call _esc,
            toLower format ["%1|%2|%3", _x get "author", _x get "time", _x get "body"],
            ["", _x get "author"] select !_mine];
    } forEach (_msgs select [((count _msgs) - 40) max 0]);
    _s set ["seenAthena", count ([] call comspec_atak_native_fnc_messagesAll)];
} else {
    {
        if ((_x getOrDefault ["peer", ""]) isEqualTo _peer) then {
            private _out = (_x getOrDefault ["dir", ""]) isEqualTo "out";
            ([_x getOrDefault ["body", ""], ""] call comspec_atak_native_fnc_chatParse) params ["", "_ptags", "_ptext"];
            private _chips = (_ptags apply { format ["<t font='RobotoCondensedBold' color='%1'>%2</t>", ["#f2ab33", "#e5483a"] select (_x in ["URGENT", "FLASH", "IMPORTANT", "CONTACT", "TIC", "MEDEVAC"]), [_x] call _esc] }) joinString " ";
            _items pushBack [_out,
                format ["<t font='RobotoCondensedBold' color='%1'>%2</t> <t color='#8a9a93'>%3</t> %4", ["#5cc76b", "#9be3a5"] select _out, [[_peer] call _esc, "Moi"] select _out, [_x getOrDefault ["time", "--:--"]] call _esc, _chips],
                [_ptext] call _esc,
                toLower format ["%1|%2|%3", _x getOrDefault ["dir", ""], _x getOrDefault ["time", ""], _x getOrDefault ["body", ""]],
                ["", _peer] select !_out];
            _x set ["read", true];
        };
    } forEach (_data getOrDefault ["p2p", []]);
};
// Messages vidés de l'affichage
private _hidden = (uiNamespace getVariable ["COMSPEC_ATAK_ChatCleared", createHashMap]) getOrDefault [_peer, []];
_items = _items select { !((_x select 3) in _hidden) };
uiNamespace setVariable ["COMSPEC_ATAK_ChatShownKeys", _items apply { _x select 3 }];
private _interactive = _l get "interactive";
private _threadY = _topY;
private _prevH = (_l get "fontSmall") * 1.5;
private _threadH = _bh - _threadY - _pad - ([0, _rowH + _pad + _prevH] select _interactive);
private _wiki = _s getOrDefault ["chatWiki", false];
if (_wiki) then { _items = []; };
private _thread = ["COMSPEC_RscControlsGroup", [_pad, _threadY, _gw - 2 * _pad, _threadH]] call comspec_atak_native_fnc_pageCtrl;
private _d = [] call comspec_atak_native_fnc_display;
private _tw = _gw - 2 * _pad - 0.012;
private _y = 0;
if (_wiki) then {
    // Mini wiki du tchat
    private _t = _d ctrlCreate ["COMSPEC_RscStructuredText", -1, _thread];
    _t ctrlSetPosition [0, 0, _tw, _threadH];
    _t ctrlCommit 0;
    private _h = { params ["_x"]; format ["<t color='#5cc76b' font='RobotoCondensedBold'>%1</t>", _x] };
    private _c = { params ["_k", "_v"]; format ["<t font='EtelkaMonospacePro' color='#f2ab33'>%1</t>  %2", _k, _v] };
    _t ctrlSetStructuredText parseText ([
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
    ] joinString "<br/>");
    _t ctrlSetPosition [0, 0, _tw, (ctrlTextHeight _t) max _threadH];
    _t ctrlCommit 0;
};
if ((count _items) isEqualTo 0 && {!_wiki}) then {
    private _t = _d ctrlCreate ["COMSPEC_RscStructuredText", -1, _thread];
    _t ctrlSetPosition [0, 0, _tw, _threadH];
    _t ctrlCommit 0;
    _t ctrlSetStructuredText parseText (["<t color='#8a9a93' align='center'>Aucun message. Écrivez ci-dessous pour démarrer la conversation.</t>", "<t color='#8a9a93' align='center'>Aucun message du TOC pour l'instant.</t>"] select _isChannel);
};
// Photo de l'opérateur à côté de chaque bulle (photo Athena, sinon pictogramme ; TOC : logo Athena).
private _ratio = pixelH / pixelW;
private _avH = _font * 1.9;
private _avW = _avH / _ratio;
private _bubbleW = (_tw - _avW - _pad) * 0.84;
private _dir = "\z\comspec_atak_native\addons\main\data\";
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
{
    _x params ["_mine", "_head", "_body", "", "_author"];
    private _u = if (_mine) then { player } else { [_author] call _unitFor };
    private _photo = if (isNull _u) then { "" } else { [_u] call comspec_atak_native_fnc_avatarPath };
    private _ax = [0, _tw - _avW] select _mine;
    private _av = if (_photo isNotEqualTo "") then {
        _d ctrlCreate ["COMSPEC_RscSlide", -1, _thread]
    } else {
        private _c = _d ctrlCreate ["COMSPEC_RscIcon", -1, _thread];
        _c ctrlSetTextColor ([[0.55, 0.62, 0.58, 1], [0.36, 0.78, 0.42, 1]] select _mine);
        _c
    };
    _av ctrlSetText ([[_dir + "app_athena.paa", _dir + "app_group.paa"] select !(isNull _u), _photo] select (_photo isNotEqualTo ""));
    _av ctrlSetPosition [_ax, _y, _avW, _avH];
    _av ctrlCommit 0;
    private _b = _d ctrlCreate [["COMSPEC_RscBubbleIn", "COMSPEC_RscBubbleOut"] select _mine, -1, _thread];
    private _bx = [_avW + _pad / 2, _tw - _avW - _pad / 2 - _bubbleW] select _mine;
    _b ctrlSetPosition [_bx, _y, _bubbleW, _rowH];
    _b ctrlCommit 0;
    _b ctrlSetStructuredText parseText format ["<t size='0.8'>%1</t><br/>%2", _head, _body];
    private _h = (ctrlTextHeight _b) + _font * 0.25;
    _b ctrlSetPosition [_bx, _y, _bubbleW, _h];
    _b ctrlCommit 0;
    _y = _y + (_h max _avH) + _font * 0.35;
} forEach _items;
_thread ctrlSetScrollValues [[1, 0] select _wiki, -1];
if !(_interactive) exitWith { true };

// Saisie
private _sendW = _gw * 0.26;
private _helpW = _rowH / _ratio;
private _bHelp = ["COMSPEC_RscButton", [_pad, _bh - _rowH - _pad, _helpW, _rowH], ["?", "×"] select _wiki] call comspec_atak_native_fnc_pageCtrl;
_bHelp ctrlSetTooltip (["Aide du tchat (commandes /)", "Fermer l'aide"] select _wiki);
if (_wiki) then { _bHelp ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _bHelp ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
_bHelp ctrlAddEventHandler ["ButtonClick", { private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _s set ["chatWiki", !(_s getOrDefault ["chatWiki", false])]; [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }];
private _edit = ["COMSPEC_RscEdit", [_pad * 1.5 + _helpW, _bh - _rowH - _pad, _gw - 3.5 * _pad - _sendW - _helpW, _rowH], uiNamespace getVariable ["COMSPEC_ATAK_ChatDraft", ""]] call comspec_atak_native_fnc_pageCtrl;
// Aperçu : type détecté, puces et texte final ; suggestions pendant qu'on tape une commande.
private _prev = ["COMSPEC_RscStructuredText", [_pad, _bh - _rowH - _pad * 1.5 - _prevH, _gw - 2 * _pad, _prevH]] call comspec_atak_native_fnc_pageCtrl;
uiNamespace setVariable ["COMSPEC_ATAK_ChatPreview", _prev];
uiNamespace setVariable ["COMSPEC_ATAK_ChatPreviewCode", {
    params ["_raw"];
    private _p = uiNamespace getVariable ["COMSPEC_ATAK_ChatPreview", controlNull];
    if (isNull _p) exitWith {};
    private _txt = trim _raw;
    if (_txt isEqualTo "" || {!((_txt select [0, 1]) isEqualTo "/") && {(_txt find "@") < 0}}) exitWith {
        _p ctrlSetStructuredText parseText "<t size='0.8' color='#5b6b63'>Astuce : /urgent, /contact, /sitrep… et @grille. Tapez /aide.</t>";
    };
    private _words = _txt splitString " ";
    private _last = _words select ((count _words) - 1);
    private _typing = ((_last select [0, 1]) isEqualTo "/") && {!(_txt select [(count _txt) - 1] isEqualTo " ")} && {(_words findIf { (_x select [0, 1]) isNotEqualTo "/" }) < 0};
    if (_typing) exitWith {
        private _all = ["routine", "prioritaire", "urgent", "contact", "tic", "sitrep", "salute", "lace", "medevac", "intel", "ordre", "log", "aide"];
        private _m = _all select { (_x select [0, (count _last) - 1]) isEqualTo (toLower (_last select [1])) };
        _p ctrlSetStructuredText parseText format ["<t size='0.8' color='#8a9a93'>Commandes : </t><t size='0.8' font='EtelkaMonospacePro' color='#f2ab33'>%1</t>", (_m apply { "/" + _x }) joinString "  "];
    };
    ([_txt] call comspec_atak_native_fnc_chatCommand) params ["", "", "_text", "_unknown", "_tags"];
    private _chips = (_tags apply { format ["<t font='RobotoCondensedBold' color='%1'>[%2]</t>", ["#f2ab33", "#e5483a"] select (_x in ["URGENT", "CONTACT", "TIC", "MEDEVAC"]), _x] }) joinString " ";
    private _bad = ["", format ["  <t color='#e5483a'>inconnu : %1</t>", _unknown joinString " "]] select ((count _unknown) > 0);
    private _safe = [[_text, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace;
    _p ctrlSetStructuredText parseText format ["<t size='0.8' color='#8a9a93'>Aperçu : </t><t size='0.8'>%1 %2</t>%3", _chips, _safe, _bad];
}];
[uiNamespace getVariable ["COMSPEC_ATAK_ChatDraft", ""]] call (uiNamespace getVariable "COMSPEC_ATAK_ChatPreviewCode");
_edit ctrlAddEventHandler ["KeyUp", { params ["_c"]; [ctrlText _c] call (uiNamespace getVariable ["COMSPEC_ATAK_ChatPreviewCode", {}]); }];
_edit ctrlSetFontHeight _font;
uiNamespace setVariable ["COMSPEC_ATAK_ChatEdit", _edit];
private _send = ["COMSPEC_RscButtonPrimary", [_gw - _pad - _sendW, _bh - _rowH - _pad, _sendW, _rowH], "ENVOYER"] call comspec_atak_native_fnc_pageCtrl;
_send ctrlSetFontHeight (_l get "fontSmall");
private _doSend = {
    private _peer = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["chatPeer", "ATHENA"];
    private _edit = uiNamespace getVariable ["COMSPEC_ATAK_ChatEdit", controlNull];
    if (isNull _edit) exitWith {};
    if (_peer isEqualTo "ATHENA" || {(_peer select [0, 3]) isEqualTo "CH:"}) then {
        [] call comspec_atak_native_fnc_chatSend;
    } else {
        if ([_peer, ctrlText _edit] call comspec_atak_native_fnc_p2pSend) then {
            _edit ctrlSetText "";
            uiNamespace setVariable ["COMSPEC_ATAK_ChatDraft", ""];
            [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        };
    };
};
uiNamespace setVariable ["COMSPEC_ATAK_ChatSendCode", _doSend];
_send ctrlAddEventHandler ["ButtonClick", { [] call (uiNamespace getVariable ["COMSPEC_ATAK_ChatSendCode", {}]); }];
_edit ctrlAddEventHandler ["KeyDown", {
    params ["", "_key"];
    if (_key in [28, 156]) exitWith { [] call (uiNamespace getVariable ["COMSPEC_ATAK_ChatSendCode", {}]); true };
    false
}];
if !(_s getOrDefault ["chatNewOpen", false]) then { ctrlSetFocus _edit; };
true
