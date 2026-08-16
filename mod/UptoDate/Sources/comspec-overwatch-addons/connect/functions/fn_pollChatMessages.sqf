/*
    Interroge Athena (GetChatMessages) et pousse les messages web / TOC
    vers l’inbox Athena (app Messages) ET vers l’UI Iceman « Group Messages ».

    Les envois locaux (empreinte COMSPEC_ChatSentFingerprints) sont ignorés
    pour éviter les doublons jeu→web→jeu. On n’émet PAS d’événement CBA
    Iceman_ATAK_GroupMessage (sinon re-bridge Athena en boucle).
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };

private _txGate = [true] call comspec_overwatch_connect_fnc_canTransmit;
if !(_txGate getOrDefault ["can_transmit", true]) exitWith { false };

private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
if (_mapId isEqualTo "" || {_mapId isEqualTo "0"}) then { _mapId = "1"; };

private _seen = missionNamespace getVariable ["COMSPEC_ChatSeenIds", []];
if (!(_seen isEqualType [])) then { _seen = []; };

// afterId = max id déjà vu (poll incrémental — évite de recharger 50 gros messages).
private _afterId = "0";
{
    private _n = parseNumber _x;
    if (_n > (parseNumber _afterId)) then { _afterId = str (floor _n); };
} forEach _seen;

private _bootstrapped = missionNamespace getVariable ["COMSPEC_ChatPollBootstrapped", false];
// Premier passage : snapshot récent (sans after) pour mémoriser l’historique.
private _limit = if (_bootstrapped) then { "25" } else { "40" };
private _args = if (_bootstrapped && {_afterId isNotEqualTo "0"}) then {
    [_mapId, _limit, _afterId]
} else {
    [_mapId, _limit]
};

private _raw = ["COMSPECExtension" callExtension ["GetChatMessages", _args]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { false };
if ((_raw select [0, 3]) != "OK|") exitWith { false };

private _body = _raw select [3];
private _lines = _body splitString (toString [10]);
private _tab = toString [9];

private _myCs = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _myCs = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (_myCs isEqualTo "") then { _myCs = name player; };
private _myCsU = toUpper _myCs;
private _myGroupId = groupId group player;

private _sentFp = missionNamespace getVariable ["COMSPEC_ChatSentFingerprints", []];
if (!(_sentFp isEqualType [])) then { _sentFp = []; };

private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];
if (!(_inbox isEqualType [])) then { _inbox = []; };

private _added = 0;
private _groupPanelDirty = false;

// Injection locale dans l’historique Iceman (sans CBA global).
private _fnPushIcemanGroup = {
    params ["_senderName", "_groupId", "_grid", "_text", "_timeStr", ["_pos", []]];
    if ((trim _text) isEqualTo "") exitWith { false };
    if (_groupId isEqualTo "") then { _groupId = groupId group player; };
    if (_grid isEqualTo "") then { _grid = mapGridPosition player; };
    if (_timeStr isEqualTo "") then { _timeStr = [daytime, "HH:MM"] call BIS_fnc_timeToString; };
    if ((count _pos) < 2) then { _pos = getPosATL player; };

    private _messages = +(missionNamespace getVariable ["Iceman_ATAK_Group_messages", []]);
    if (!(_messages isEqualType [])) then { _messages = []; };
    _messages pushBack [_timeStr, _senderName, _groupId, _grid, _text, _pos, false];
    while { (count _messages) > 50 } do { _messages deleteAt 0; };
    missionNamespace setVariable ["Iceman_ATAK_Group_messages", _messages, false];
    Iceman_ATAK_Group_messages = _messages;
    Iceman_ATAK_Group_selected = (count _messages) - 1;
    true
};

// Premier passage : mémoriser l’historique sans rejouer (évite spam à la liaison).
if (!_bootstrapped) exitWith {
    {
        private _line = _x;
        if (_line isEqualTo "") then { continue };
        private _cols = _line splitString _tab;
        if ((count _cols) < 3) then { continue };
        private _id = _cols select 0;
        if (_id isNotEqualTo "" && {!(_id in _seen)}) then { _seen pushBack _id; };
    } forEach _lines;
    while { (count _seen) > 200 } do { _seen deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_ChatSeenIds", _seen, false];
    missionNamespace setVariable ["COMSPEC_ChatPollBootstrapped", true, false];
    false
};

{
    private _line = _x;
    if (_line isEqualTo "") then { continue };
    private _cols = _line splitString _tab;
    if ((count _cols) < 3) then { continue };

    private _id = _cols select 0;
    if (_id isEqualTo "" || {_id in _seen}) then { continue };
    _seen pushBack _id;

    private _author = _cols select 1;
    private _msgBody = _cols select 2;
    private _created = if ((count _cols) > 3) then { _cols select 3 } else { "" };

    private _bodyU = toUpper _msgBody;
    if ((_bodyU find "REGLAGES AFFICHAGE") == 0) then { continue };
    if ((_bodyU find "AFFICHAGE|ADVERSAIRE=") == 0) then { continue };

    // Anti-écho des envois locaux (empreinte) — ne bloque plus les messages TOC
    // signés avec le même indicatif que le joueur.
    private _fpLen = (count _msgBody) min 80;
    private _fp = toUpper (_author + "|" + (_msgBody select [0, _fpLen]));
    if (_fp in _sentFp) then { continue };

    // Strip préfixe radio [HH:MM:SS][CHAN][PRIO][KIND]
    private _plain = _msgBody;
    private _rx = _msgBody regexFind [
        "^\\[\\d{1,2}:\\d{2}:\\d{2}\\]\\[[A-Za-z0-9_]+\\]\\[[A-Za-z0-9_]+\\]\\[[A-Za-z0-9_]+\\]\\s*(.*)$",
        0
    ];
    if ((count _rx) > 0) then {
        private _m = _rx select 0;
        if ((count _m) > 1) then {
            private _cap = (_m select 1) select 0;
            if (_cap isEqualType "" && {_cap isNotEqualTo ""}) then { _plain = _cap; };
        };
    };

    private _plainU = toUpper _plain;

    private _timeStr = [daytime, "HH:MM"] call BIS_fnc_timeToString;
    if ((count _created) >= 16) then {
        private _tPos = _created find "T";
        if (_tPos < 0) then { _tPos = _created find " "; };
        if (_tPos >= 0 && {(count _created) >= (_tPos + 6)}) then {
            _timeStr = _created select [_tPos + 1, 5];
        };
    };

    // GROUPE|groupId|cs|grid|texte : message de groupe (web→jeu ou rejeu filtré).
    if ((_plainU find "GROUPE|") == 0 || {_plainU isEqualTo "GROUPE"}) then {
        if ((_plainU find "GROUPE|") == 0) then {
            private _gParts = _plain splitString "|";
            private _gGroupId = if ((count _gParts) > 1) then { trim (_gParts select 1) } else { "" };
            private _gCs = if ((count _gParts) > 2) then { trim (_gParts select 2) } else { _author };
            private _gGrid = if ((count _gParts) > 3) then { trim (_gParts select 3) } else { mapGridPosition player };
            private _gText = if ((count _gParts) >= 5) then {
                private _tail = +_gParts;
                _tail deleteRange [0, 4];
                _tail joinString "|"
            } else {
                if ((count _gParts) >= 2) then { _gParts select ((count _gParts) - 1) } else { "" }
            };
            _gText = trim _gText;

            // TOC web : groupId souvent = indicatif ; accepter si égal au groupe local OU à l’indicatif TOC.
            private _groupOk = (
                _gGroupId isEqualTo ""
                || {_gGroupId isEqualTo _myGroupId}
                || {(toUpper _gGroupId) isEqualTo _myCsU}
                || {(toUpper _gGroupId) isEqualTo (toUpper _author)}
                || {_gGroupId isEqualTo "TOC"}
            );

            if (_gText isNotEqualTo "" && {_groupOk}) then {
                private _fromLabel = if (_gCs isNotEqualTo "") then { _gCs } else { _author };
                if ((toUpper _fromLabel) isEqualTo _myCsU) then {
                    _fromLabel = format ["%1 (TOC)", _fromLabel];
                };
                systemChat format ["[%1] %2", _fromLabel, _gText];
                _inbox pushBack ["GROUP", "Message de groupe", _gText, _gGrid, _timeStr, _fromLabel];
                if ([_fromLabel, _myGroupId, _gGrid, _gText, _timeStr] call _fnPushIcemanGroup) then {
                    _groupPanelDirty = true;
                };
                _added = _added + 1;
                if (!isNil "cTab_fnc_addNotification") then {
                    ["GROUP", format ["Message de %1", _fromLabel], 6] call cTab_fnc_addNotification;
                };
                if (!isNil "comspec_overwatch_connect_fnc_playAtakNotification") then {
                    ["chat"] call comspec_overwatch_connect_fnc_playAtakNotification;
                };
            };
        };
        continue;
    };

    // Alertes / ordres déjà gérés par d’autres polls
    if ((_plainU find "ALERTE TACTIQUE") == 0) then { continue };
    if ((_plainU find "ALERTE MEDICALE") == 0 || {(_plainU find "ALERTE MÉDICALE") == 0}) then { continue };
    if ((_plainU find "ORDER|") == 0) then { continue };

    private _isHq = (
        (_plainU find "[HQ]") >= 0
        || {(_msgBody find "[HQ]") >= 0}
        || {(_plainU find "][HQ] ") >= 0}
        || {(_plain find "(grille ") >= 0 && {(_msgBody find "][HQ]") >= 0}}
    );
    // Kind HQ dans le préfixe radio
    if (!_isHq && {(_msgBody find "][HQ] ") >= 0}) then { _isHq = true; };
    if (!_isHq && {(_msgBody find "][COMMAND][IMPORTANT][HQ]") >= 0}) then { _isHq = true; };

    private _title = if (_isHq) then { "Message HQ" } else { "Message radio" };
    private _detail = _plain;
    private _hqPos = _detail find "[HQ]";
    if (_hqPos >= 0) then {
        _detail = trim (_detail select [_hqPos + 4]);
    };
    // Message HQ jeu : « texte (grille XXX) »
    if (_detail isEqualTo "") then { _detail = _msgBody; };

    // Si l’auteur est soi-même (TOC web avec le même indicatif), préciser l’origine.
    private _fromLabel = _author;
    if ((toUpper _author) isEqualTo _myCsU) then {
        _fromLabel = format ["%1 (TOC)", _author];
        if (!_isHq) then { _title = "Message TOC"; };
    };

    _inbox pushBack [
        if (_isHq) then { "HQ" } else { "NOTIFY" },
        _title,
        _detail,
        mapGridPosition player,
        _timeStr,
        _fromLabel
    ];

    // Les messages radio / TOC du journal web doivent aussi apparaître dans Group Messages.
    if (!_isHq) then {
        if ([_fromLabel, _myGroupId, mapGridPosition player, _detail, _timeStr] call _fnPushIcemanGroup) then {
            _groupPanelDirty = true;
        };
    };

    _added = _added + 1;

    if (!isNil "cTab_fnc_addNotification") then {
        private _preview = if ((count _detail) > 40) then { _detail select [0, 40] } else { _detail };
        ["MSG", format ["%1 — %2", _fromLabel, _preview], 5] call cTab_fnc_addNotification;
    };
    if (!isNil "comspec_overwatch_connect_fnc_playAtakNotification") then {
        ["chat"] call comspec_overwatch_connect_fnc_playAtakNotification;
    };
} forEach _lines;

while { (count _seen) > 200 } do { _seen deleteAt 0; };
missionNamespace setVariable ["COMSPEC_ChatSeenIds", _seen, false];

if (_added > 0) then {
    while { (count _inbox) > 40 } do { _inbox deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];
    ["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;
};

if (_groupPanelDirty && {!isNil "Iceman_fnc_group_updatePanel"}) then {
    call Iceman_fnc_group_updatePanel;
};

_added > 0
