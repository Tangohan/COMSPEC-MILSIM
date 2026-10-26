/*
    Actions de l'app Poste TOC (fn_pageToc).
      "prio" niveau | "send" (message au camp) | "order" clé de groupe ("" : tous) | "sms" nom | "nav" page | "saveCs"
      "recv" [indicatif, texte, heure, priorité, camp, id, émetteur]   message du poste reçu (événement comspec_atak_native_tocMsg)
*/
params [["_a", ""], ["_v", ""]];
private _draft = uiNamespace getVariable ["COMSPEC_ATAK_TocDraft", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_TocDraft", _draft];
private _redraw = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "TOC") then { ["TOC"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _keep = {
    private _f = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
    if (!isNull (_f getOrDefault ["tocMsg", controlNull])) then { _draft set ["msg", ["tocMsg"] call comspec_atak_native_fnc_formValue]; };
};
switch (_a) do {
    case "prio": { call _keep; _draft set ["prio", _v]; call _redraw; };
    case "send": {
        call _keep;
        private _body = trim (_draft getOrDefault ["msg", ""]);
        if (_body isEqualTo "") exitWith { ["WARNING", "Écrivez le message à envoyer", 3, 30] call comspec_atak_native_fnc_notify; };
        if ((count _body) > 400) then { _body = _body select [0, 400]; };
        private _prio = _draft getOrDefault ["prio", "ROUTINE"];
        private _side = str side group player;
        private _ev = [["callsign"] call comspec_atak_native_fnc_tocPost, _body, [dayTime, "HH:MM"] call BIS_fnc_timeToString, _prio, _side,
            format ["T-%1-%2", getPlayerUID player, floor (serverTime * 10)], getPlayerUID player];
        [{ ["comspec_atak_native_sideEvent", ["comspec_atak_native_tocMsg", _this, _this select 4]] call CBA_fnc_serverEvent; }, _ev, "TOC", 2] call comspec_atak_native_fnc_netSend;
        _draft set ["msg", ""];
        ["INFO", format ["Message du poste %1 envoyé au camp", _ev select 0], 3, 20] call comspec_atak_native_fnc_notify;
        call _redraw;
    };
    case "recv": {
        _v params [["_cs", "TOC"], ["_body", ""], ["_time", ""], ["_prio", "ROUTINE"], ["_side", ""], ["_id", ""], ["_uid", ""]];
        if (!hasInterface || {isNull player} || {_side isNotEqualTo str side group player} || {_uid isEqualTo getPlayerUID player}) exitWith {};
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
        private _txt = [format ["[%1] %2", _prio, _body], _body] select (_prio isEqualTo "ROUTINE");
        [format ["TOC · %1", _cs], _txt, _time, _id] call comspec_atak_native_fnc_p2pReceive;
        if (_prio in ["URGENT", "FLASH"] && {!(missionNamespace getVariable ["COMSPEC_ATAK_Replaying", false])}) then {
            ["TACTICAL", format ["%1 · TOC %2 : %3", _prio, _cs, _body select [0, 80]], 10, 75, "CHAT"] call comspec_atak_native_fnc_notify;
            [] call comspec_atak_native_fnc_vibrate;
        };
    };
    case "order": {
        private _od = uiNamespace getVariable ["COMSPEC_ATAK_OrderDraft", createHashMap];
        _od set ["target", ["all||Tous", format ["group|%1|%1", _v]] select (_v isNotEqualTo "")];
        uiNamespace setVariable ["COMSPEC_ATAK_OrderDraft", _od];
        ["TASK"] call comspec_atak_native_fnc_navigate;
        [{ ["open"] call comspec_atak_native_fnc_orderAction; }] call CBA_fnc_execNextFrame;
    };
    case "sms": {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _s set ["chatPeer", _v];
        _s set ["chatView", "CONV"];
        _s set ["chatPick", false];
        ["CHAT"] call comspec_atak_native_fnc_navigate;
    };
    case "nav": { [_v] call comspec_atak_native_fnc_navigate; };
    case "saveCs": {
        private _cs = toUpper trim (["tocCs"] call comspec_atak_native_fnc_formValue);
        if (_cs isEqualTo "") exitWith { ["WARNING", "Indicatif vide", 3, 30] call comspec_atak_native_fnc_notify; };
        ((["get"] call comspec_atak_native_fnc_localCfg) get "toc") set ["callsign", _cs select [0, 24]];
        ["save"] call comspec_atak_native_fnc_localCfg;
        player setVariable ["COMSPEC_ATAK_TocOn", _cs select [0, 24], true];
        ["INFO", format ["Indicatif du poste : %1", _cs], 3, 20] call comspec_atak_native_fnc_notify;
        call _redraw;
    };
};
true
