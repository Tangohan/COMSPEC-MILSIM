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

// Fil de discussion
private _esc = {
    params ["_t"];
    _t = [_t, "&", "&amp;"] call CBA_fnc_replace;
    _t = [_t, "<", "&lt;"] call CBA_fnc_replace;
    [_t, ">", "&gt;"] call CBA_fnc_replace
};
private _lines = [];
if (_peer isEqualTo "ATHENA") then {
    private _msgs = _data getOrDefault ["messages", []];
    { _lines pushBack format ["<t size='0.8' color='#8a9a93'>%1 · %2</t><br/>%3", [_x getOrDefault ["time", "--:--"]] call _esc, [_x getOrDefault ["author", "TOC"]] call _esc, [_x getOrDefault ["body", ""]] call _esc]; } forEach _msgs;
    _s set ["seenAthena", count _msgs];
} else {
    {
        if ((_x getOrDefault ["peer", ""]) isEqualTo _peer) then {
            private _out = (_x getOrDefault ["dir", ""]) isEqualTo "out";
            _lines pushBack format ["<t align='%1'><t size='0.8' color='#8a9a93'>%2 · %3</t><br/><t color='%4'>%5</t></t>",
                ["left", "right"] select _out, [_x getOrDefault ["time", "--:--"]] call _esc, [[_peer] call _esc, "Moi"] select _out, ["#dfe7e2", "#9be3a5"] select _out, [_x getOrDefault ["body", ""]] call _esc];
            _x set ["read", true];
        };
    } forEach (_data getOrDefault ["p2p", []]);
};
if ((count _lines) isEqualTo 0) then {
    _lines pushBack (["<t color='#8a9a93'>Aucun message. Écrivez ci-dessous pour démarrer la conversation.</t>", "<t color='#8a9a93'>Aucun message du TOC pour l'instant.</t>"] select (_peer isEqualTo "ATHENA"));
};
private _threadY = _pad * 2 + _rowH;
private _threadH = _bh - _threadY - _rowH - _pad * 2;
private _thread = ["COMSPEC_RscControlsGroup", [_pad, _threadY, _gw - 2 * _pad, _threadH]] call comspec_atak_native_fnc_pageCtrl;
private _text = (findDisplay 88500) ctrlCreate ["COMSPEC_RscStructuredText", -1, _thread];
_text ctrlSetPosition [0, 0, _gw - 2 * _pad - 0.012, _threadH];
_text ctrlCommit 0;
_text ctrlSetStructuredText parseText (_lines joinString "<br/><br/>");
_text ctrlSetPosition [0, 0, _gw - 2 * _pad - 0.012, (ctrlTextHeight _text) max _threadH];
_text ctrlCommit 0;
_thread ctrlSetScrollValues [1, -1];

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
