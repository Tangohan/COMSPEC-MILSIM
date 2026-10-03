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

// Destinataire
private _combo = ["COMSPEC_RscCombo", [_pad, _pad, _gw - 2 * _pad, _rowH]] call comspec_atak_native_fnc_pageCtrl;
_combo ctrlSetFontHeight _font;
private _peers = (allPlayers select { _x isNotEqualTo player && {side group _x isEqualTo side group player} }) apply { name _x };
{ if ((_x getOrDefault ["peer", ""]) isNotEqualTo "") then { _peers pushBackUnique (_x get "peer"); }; } forEach (_data getOrDefault ["p2p", []]);
_peers sort true;
private _i = _combo lbAdd "TOC — canal Athena";
_combo lbSetData [_i, "ATHENA"];
private _sel = 0;
{
    private _name = _x;
    private _n = { (_x getOrDefault ["peer", ""]) isEqualTo _name && {(_x getOrDefault ["dir", ""]) isEqualTo "in"} && {!(_x getOrDefault ["read", false])} } count (_data getOrDefault ["p2p", []]);
    private _k = _combo lbAdd ([_name, format ["%1 (%2)", _name, _n]] select (_n > 0));
    _combo lbSetData [_k, _x];
    if (_x isEqualTo _peer) then { _sel = _k; };
} forEach _peers;
if (_sel isEqualTo 0) then { _peer = "ATHENA"; };
_s set ["chatPeer", _peer];
_combo lbSetCurSel _sel;
_combo ctrlAddEventHandler ["LBSelChanged", {
    params ["_c", "_index"];
    (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["chatPeer", _c lbData _index];
    [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];

// Fil de discussion : une bulle par message, à droite pour mes messages, puces pour les préfixes Athena.
private _esc = {
    params ["_t"];
    if !(_t isEqualType "") then { _t = str _t; };
    _t = [_t, "&", "&amp;"] call CBA_fnc_replace;
    _t = [_t, "<", "&lt;"] call CBA_fnc_replace;
    [_t, ">", "&gt;"] call CBA_fnc_replace
};
private _items = []; // [moi, en-tête, texte]
if (_peer isEqualTo "ATHENA") then {
    private _msgs = [] call comspec_atak_native_fnc_messagesAll;
    {
        private _mine = _x get "mine";
        private _tags = (_x get "tags") apply { format ["<t font='RobotoCondensedBold' color='%1'>%2</t>", ["#f2ab33", "#e5483a"] select (_x in ["URGENT", "FLASH", "PRIORITAIRE", "IMMEDIATE"]), [_x] call _esc] };
        private _status = switch (_x get "status") do { case "FAILED": { " <t color='#e5483a'>non envoyé</t>" }; case "SENT": { " <t color='#8a9a93'>envoi…</t>" }; default { "" }; };
        _items pushBack [_mine,
            format ["<t font='RobotoCondensedBold' color='%1'>%2</t> <t color='#8a9a93'>%3</t> %4%5", ["#5cc76b", "#9be3a5"] select _mine, [["Moi", _x get "author"] select !_mine] call _esc, [_x get "time"] call _esc, _tags joinString " ", _status],
            [_x get "body"] call _esc];
    } forEach (_msgs select [((count _msgs) - 40) max 0]);
    _s set ["seenAthena", count _msgs];
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
private _threadY = _pad * 2 + _rowH;
private _threadH = _bh - _threadY - _pad - ([0, _rowH + _pad] select _interactive);
private _thread = ["COMSPEC_RscControlsGroup", [_pad, _threadY, _gw - 2 * _pad, _threadH]] call comspec_atak_native_fnc_pageCtrl;
private _d = [] call comspec_atak_native_fnc_display;
private _tw = _gw - 2 * _pad - 0.012;
private _y = 0;
if ((count _items) isEqualTo 0) then {
    private _t = _d ctrlCreate ["COMSPEC_RscStructuredText", -1, _thread];
    _t ctrlSetPosition [0, 0, _tw, _threadH];
    _t ctrlCommit 0;
    _t ctrlSetStructuredText parseText (["<t color='#8a9a93' align='center'>Aucun message. Écrivez ci-dessous pour démarrer la conversation.</t>", "<t color='#8a9a93' align='center'>Aucun message du TOC pour l'instant.</t>"] select (_peer isEqualTo "ATHENA"));
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
    if (_peer isEqualTo "ATHENA") then {
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
ctrlSetFocus _edit;
true
