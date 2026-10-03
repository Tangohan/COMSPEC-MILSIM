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
private _mbw = [0, _gw * 0.14] select _manage;
private _combo = ["COMSPEC_RscCombo", [_pad, _pad, _gw - 2 * _pad - ([0, 2 * (_mbw + _pad / 2)] select _manage), _rowH]] call comspec_atak_native_fnc_pageCtrl;
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
    private _k = _combo lbAdd ([_name, format ["%1 (%2)", _name, _n]] select (_n > 0));
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
private _items = []; // [moi, en-tête, texte]
if (_isChannel) then {
    private _msgs = [_s getOrDefault ["chatChannel", ""]] call comspec_atak_native_fnc_messagesAll;
    {
        private _mine = _x get "mine";
        private _tags = (_x get "tags") apply { format ["<t font='RobotoCondensedBold' color='%1'>%2</t>", ["#f2ab33", "#e5483a"] select (_x in ["URGENT", "FLASH", "PRIORITAIRE", "IMMEDIATE"]), [_x] call _esc] };
        private _status = switch (_x get "status") do { case "FAILED": { " <t color='#e5483a'>non envoyé</t>" }; case "SENT": { " <t color='#8a9a93'>envoi…</t>" }; default { "" }; };
        _items pushBack [_mine,
            format ["<t font='RobotoCondensedBold' color='%1'>%2</t> <t color='#8a9a93'>%3</t> %4%5", ["#5cc76b", "#9be3a5"] select _mine, [["Moi", _x get "author"] select !_mine] call _esc, [_x get "time"] call _esc, _tags joinString " ", _status],
            [_x get "body"] call _esc];
    } forEach (_msgs select [((count _msgs) - 40) max 0]);
    _s set ["seenAthena", count ([] call comspec_atak_native_fnc_messagesAll)];
} else {
    {
        if ((_x getOrDefault ["peer", ""]) isEqualTo _peer) then {
            private _out = (_x getOrDefault ["dir", ""]) isEqualTo "out";
            _items pushBack [_out,
                format ["<t font='RobotoCondensedBold' color='%1'>%2</t> <t color='#8a9a93'>%3</t>", ["#5cc76b", "#9be3a5"] select _out, [[_peer] call _esc, "Moi"] select _out, [_x getOrDefault ["time", "--:--"]] call _esc],
                [_x getOrDefault ["body", ""]] call _esc];
            _x set ["read", true];
        };
    } forEach (_data getOrDefault ["p2p", []]);
};
private _interactive = _l get "interactive";
private _threadY = _topY;
private _threadH = _bh - _threadY - _pad - ([0, _rowH + _pad] select _interactive);
private _thread = ["COMSPEC_RscControlsGroup", [_pad, _threadY, _gw - 2 * _pad, _threadH]] call comspec_atak_native_fnc_pageCtrl;
private _d = [] call comspec_atak_native_fnc_display;
private _tw = _gw - 2 * _pad - 0.012;
private _y = 0;
if ((count _items) isEqualTo 0) then {
    private _t = _d ctrlCreate ["COMSPEC_RscStructuredText", -1, _thread];
    _t ctrlSetPosition [0, 0, _tw, _threadH];
    _t ctrlCommit 0;
    _t ctrlSetStructuredText parseText (["<t color='#8a9a93' align='center'>Aucun message. Écrivez ci-dessous pour démarrer la conversation.</t>", "<t color='#8a9a93' align='center'>Aucun message du TOC pour l'instant.</t>"] select _isChannel);
};
private _bubbleW = _tw * 0.8;
{
    _x params ["_mine", "_head", "_body"];
    private _b = _d ctrlCreate [["COMSPEC_RscBubbleIn", "COMSPEC_RscBubbleOut"] select _mine, -1, _thread];
    private _bx = [0, _tw - _bubbleW] select _mine;
    _b ctrlSetPosition [_bx, _y, _bubbleW, _rowH];
    _b ctrlCommit 0;
    _b ctrlSetStructuredText parseText format ["<t size='0.8'>%1</t><br/>%2", _head, _body];
    private _h = (ctrlTextHeight _b) + _font * 0.25;
    _b ctrlSetPosition [_bx, _y, _bubbleW, _h];
    _b ctrlCommit 0;
    _y = _y + _h + _font * 0.35;
} forEach _items;
_thread ctrlSetScrollValues [1, -1];
if !(_interactive) exitWith { true };

// Saisie
private _sendW = _gw * 0.26;
private _edit = ["COMSPEC_RscEdit", [_pad, _bh - _rowH - _pad, _gw - 3 * _pad - _sendW, _rowH], uiNamespace getVariable ["COMSPEC_ATAK_ChatDraft", ""]] call comspec_atak_native_fnc_pageCtrl;
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
