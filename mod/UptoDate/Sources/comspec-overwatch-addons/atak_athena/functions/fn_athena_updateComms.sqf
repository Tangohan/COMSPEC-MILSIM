/*
    Rafraîchit Messagerie : liste des canaux (non-lus) ou fil du canal ouvert.
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Comms_group", controlNull];
if (isNull _group) exitWith {};

if (!([] call comspec_overwatch_atak_athena_fnc_athena_commsIsOpen)) exitWith {};

private _view = missionNamespace getVariable ["COMSPEC_Comms_View", "list"];
if !(_view in ["list", "thread"]) then { _view = "list"; };
missionNamespace setVariable ["COMSPEC_Comms_View", _view, false];

private _lblList = _group controlsGroupCtrl 9921;
private _lbChannels = _group controlsGroupCtrl 9922;
private _msgView = _group controlsGroupCtrl 9923;
private _createEdit = _group controlsGroupCtrl 9927;
private _btnCreate = _group controlsGroupCtrl 9928;
private _btnDelete = _group controlsGroupCtrl 9929;
private _btnBack = _group controlsGroupCtrl 9930;
private _createTitle = _group controlsGroupCtrl 9931;
private _msgBody = if (!isNull _msgView) then { _msgView controlsGroupCtrl 9933 } else { controlNull };
private _lblThread = _group controlsGroupCtrl 9934;
private _compose = _group controlsGroupCtrl 9924;
private _btnSend = _group controlsGroupCtrl 9925;
private _btnClear = _group controlsGroupCtrl 9926;

private _showCtrl = {
    params ["_c", "_show"];
    if (isNull _c) exitWith {};
    _c ctrlEnable _show;
    _c ctrlShow _show;
    _c ctrlSetFade ([1, 0] select _show);
    _c ctrlCommit 0;
};

private _isList = _view isEqualTo "list";
[_lblList, _isList] call _showCtrl;
[_lbChannels, _isList] call _showCtrl;
[_createTitle, _isList] call _showCtrl;
[_createEdit, _isList] call _showCtrl;
[_btnCreate, _isList] call _showCtrl;
[_btnBack, !_isList] call _showCtrl;
[_lblThread, !_isList] call _showCtrl;
[_msgView, !_isList] call _showCtrl;
[_compose, !_isList] call _showCtrl;
[_btnSend, !_isList] call _showCtrl;
[_btnClear, !_isList] call _showCtrl;

if (!isNull _createEdit && {(ctrlText _createEdit) isEqualTo ""}) then {
    if ((ctrlTooltip _createEdit) isEqualTo "") then {
        _createEdit ctrlSetTooltip "Nom du nouveau canal (ex. Escouade Bravo)";
    };
};

if (!isNull _createTitle) then {
    _createTitle ctrlSetStructuredText parseText "<t align='left'>  Création</t>";
};

private _labelFor = {
    params ["_key"];
    _key = toLower (trim _key);
    switch (_key) do {
        case "groupe": { "Groupe" };
        case "commandement": { "Commandement" };
        case "general"; case "squad"; case "global": { "Général" };
        case "jtac": { "JTAC" };
        case "air": { "Air" };
        default {
            if (_key isEqualTo "") then { "Général" } else {
                private _pretty = _key;
                _pretty = (_pretty splitString "_") joinString " ";
                _pretty
            };
        };
    };
};

private _channelColorRgb = {
    params ["_key"];
    _key = toLower (trim _key);
    switch (_key) do {
        case "groupe": { [0.49, 1.00, 0.60, 1] };
        case "commandement": { [1.00, 0.82, 0.48, 1] };
        case "general": { [0.60, 0.91, 1.00, 1] };
        case "jtac": { [1.00, 0.60, 0.35, 1] };
        case "air": { [0.48, 0.72, 1.00, 1] };
        default {
            private _h = 0;
            { _h = _h + _x; } forEach (toArray toUpper _key);
            private _palette = [
                [0.79, 0.65, 1.00, 1],
                [1.00, 0.71, 0.82, 1],
                [0.66, 0.94, 0.82, 1],
                [0.94, 0.82, 0.50, 1]
            ];
            _palette select (_h mod (count _palette))
        };
    };
};

private _channelColorHex = {
    params ["_key"];
    _key = toLower (trim _key);
    switch (_key) do {
        case "groupe": { "#7CFF9A" };
        case "commandement": { "#FFD27A" };
        case "general": { "#9AE8FF" };
        case "jtac": { "#FF9A5A" };
        case "air": { "#7AB8FF" };
        default {
            private _h = 0;
            { _h = _h + _x; } forEach (toArray toUpper _key);
            private _palette = ["#C9A7FF", "#FFB4D0", "#A8F0D0", "#F0D080"];
            _palette select (_h mod (count _palette))
        };
    };
};

private _authorColorHex = {
    params ["_name", ["_isMine", false]];
    if (_isMine) exitWith { "#7EE0FF" };
    private _low = toLower _name;
    if (_low isEqualTo "poste" || {(_low find "poste") >= 0} || {(_low find "toc") >= 0}) exitWith { "#FFE27A" };
    private _h = 0;
    { _h = _h + _x; } forEach (toArray toUpper _name);
    private _palette = ["#FFB86B", "#B5E07A", "#E8A0FF", "#FF8A9B", "#8AB4FF", "#9AE8C8", "#F0C27A", "#7EE0FF"];
    _palette select (_h mod (count _palette))
};

private _escHtml = {
    params ["_s"];
    _s = (_s splitString "&") joinString "&amp;";
    _s = (_s splitString "<") joinString "&lt;";
    _s = (_s splitString ">") joinString "&gt;";
    _s = (_s splitString "%") joinString "%%";
    _s = (_s splitString toString [13]) joinString "";
    _s = (_s splitString toString [10]) joinString "<br/>";
    _s
};

private _forceWrap = {
    params ["_s", ["_every", 28]];
    private _out = "";
    private _run = 0;
    private _len = count _s;
    if (_len <= 0) exitWith { "" };
    for "_i" from 0 to (_len - 1) do {
        private _ch = _s select [_i, 1];
        if (_ch isEqualTo " " || {_ch isEqualTo toString [10]} || {_ch isEqualTo "-"}) then {
            _run = 0;
            _out = _out + _ch;
        } else {
            if (_run >= _every) then {
                _out = _out + toString [10];
                _run = 0;
            };
            _out = _out + _ch;
            _run = _run + 1;
        };
    };
    _out
};

private _channels = missionNamespace getVariable ["COMSPEC_Comms_Channels", []];
if (!(_channels isEqualType []) || {_channels isEqualTo []}) then {
    _channels = [
        ["groupe", "Groupe", "system"],
        ["commandement", "Commandement", "system"],
        ["general", "Général", "system"],
        ["jtac", "JTAC", "system"],
        ["air", "Air", "system"]
    ];
    missionNamespace setVariable ["COMSPEC_Comms_Channels", _channels, false];
};

private _active = toLower (trim (missionNamespace getVariable ["COMSPEC_Comms_Channel", "general"]));
if (_active in ["squad", "global", ""]) then { _active = "general"; };
if (_active in ["hq", "c2", "command"]) then { _active = "commandement"; };
if (_active in ["group"]) then { _active = "groupe"; };
missionNamespace setVariable ["COMSPEC_Comms_Channel", _active, false];

private _activeKind = "system";
private _activeLabel = [_active] call _labelFor;
{
    if ((toLower (trim (_x select 0))) isEqualTo _active) then {
        if ((count _x) > 1 && {(_x select 1) isNotEqualTo ""}) then { _activeLabel = _x select 1; };
        if ((count _x) > 2) then { _activeKind = _x select 2; };
    };
} forEach _channels;

private _isCustom = !(_activeKind isEqualTo "system") && {!(_active in ["groupe", "commandement", "general", "jtac", "air"])};
[_btnDelete, (!_isList) && {_isCustom}] call _showCtrl;

private _title = _group controlsGroupCtrl 9920;
if (!isNull _title) then {
    if (_isList) then {
        _title ctrlSetTooltip "Revenir au tiroir des applications.";
    } else {
        _title ctrlSetTooltip "Revenir à la liste des canaux.";
    };
};

if (!isNull _lblList) then {
    _lblList ctrlSetStructuredText parseText "<t align='center' size='1.02' color='#F0F6FA'>Canaux radio</t>";
};
if (!isNull _lblThread) then {
    _lblThread ctrlSetStructuredText parseText format [
        "<t align='center' size='1.02' color='%1'>%2</t>",
        [_active] call _channelColorHex,
        [_activeLabel] call _escHtml
    ];
};

private _unread = missionNamespace getVariable ["COMSPEC_Comms_Unread", createHashMap];
if (!(_unread isEqualType createHashMap)) then { _unread = createHashMap; };

if (!isNull _lbChannels && {_isList}) then {
    private _listSig = "";
    {
        _x params ["_key", "_label", ["_kind", "custom"]];
        _key = toLower (trim _key);
        private _n = _unread getOrDefault [_key, 0];
        if (!(_n isEqualType 0)) then { _n = 0; };
        _listSig = _listSig + _key + ":" + str _n + ";";
    } forEach _channels;

    if (_listSig isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_Comms_listSig", ""])) then {
        uiNamespace setVariable ["COMSPEC_ATAK_Comms_listSig", _listSig];
        uiNamespace setVariable ["COMSPEC_ATAK_Comms_rebuilding", true];
        uiNamespace setVariable ["COMSPEC_ATAK_Comms_ignoreSelUntil", diag_tickTime + 0.4];
        lbClear _lbChannels;
        {
            _x params ["_key", "_label", ["_kind", "custom"]];
            _key = toLower (trim _key);
            if (_label isEqualTo "") then { _label = [_key] call _labelFor; };
            private _n = _unread getOrDefault [_key, 0];
            if (!(_n isEqualType 0)) then { _n = 0; };
            private _idx = _lbChannels lbAdd _label;
            _lbChannels lbSetData [_idx, _key];
            _lbChannels lbSetColor [_idx, [_key] call _channelColorRgb];
            if (_n > 0) then {
                _lbChannels lbSetTextRight [_idx, format ["[%1]", _n]];
                _lbChannels lbSetColorRight [_idx, [1, 0.86, 0.28, 1]];
                _lbChannels lbSetTooltip [_idx, format ["%1 — %2 message%3 non lu%3", _label, _n, if (_n > 1) then { "s" } else { "" }]];
            } else {
                _lbChannels lbSetTextRight [_idx, ""];
                _lbChannels lbSetTooltip [_idx, format ["Ouvrir le canal %1", _label]];
            };
        } forEach _channels;
        _lbChannels lbSetCurSel -1;
        private _rebuildToken = diag_tickTime;
        uiNamespace setVariable ["COMSPEC_ATAK_Comms_rebuildToken", _rebuildToken];
        [_rebuildToken] spawn {
            params ["_rebuildToken"];
            uiSleep 0.25;
            if ((uiNamespace getVariable ["COMSPEC_ATAK_Comms_rebuildToken", -1]) isEqualTo _rebuildToken) then {
                uiNamespace setVariable ["COMSPEC_ATAK_Comms_rebuilding", false];
            };
        };
    };
};

if (_isList) exitWith {
    [] call comspec_overwatch_atak_athena_fnc_athena_commsApplyChrome;
};

private _all = missionNamespace getVariable ["COMSPEC_Comms_Messages", []];
if (!(_all isEqualType [])) then { _all = []; };

private _filtered = _all select {
    private _ck = toLower (trim (_x param [4, "general"]));
    if (_ck in ["squad", "global", ""]) then { _ck = "general"; };
    if (_ck in ["hq", "c2", "command"]) then { _ck = "commandement"; };
    if (_ck in ["group"]) then { _ck = "groupe"; };
    _ck isEqualTo _active
};

private _sig = format ["%1|%2", _active, count _filtered];
if ((count _filtered) > 0) then {
    _sig = _sig + "|" + str ((_filtered select ((count _filtered) - 1)) param [0, ""]);
};
if (_sig isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_Comms_renderSig", ""])) exitWith {};
uiNamespace setVariable ["COMSPEC_ATAK_Comms_renderSig", _sig];

if (isNull _msgBody) exitWith {};

private _html = "";
if (_filtered isEqualTo []) then {
    _html = "<t size='0.95' color='#8aa0b4'>Aucun message sur ce canal.</t>";
} else {
    private _myCs = "";
    if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
        _myCs = [] call comspec_overwatch_connect_fnc_getCallsign;
    };
    if (_myCs isEqualTo "") then { _myCs = name player; };
    private _myCsU = toUpper _myCs;

    {
        _x params ["_id", "_author", "_body", "_timeStr", "_channelKey", ["_isMine", false]];
        if ((count _body) > 400) then { _body = (_body select [0, 400]) + "…"; };
        private _from = if (_author isEqualTo "") then { "Poste" } else { _author };
        if (!_isMine && {(toUpper _from) isEqualTo _myCsU}) then {
            _from = format ["%1 (poste)", _from];
        };

        private _who = if (_isMine) then {
            "Vous"
        } else {
            if (_from isEqualTo "Poste" || {(toLower _from) find "poste" >= 0} || {(toLower _from) find "toc" >= 0}) then {
                "Du poste"
            } else {
                format ["De %1", _from]
            };
        };

        private _whoCol = [_who, _isMine] call _authorColorHex;
        private _stamp = if (_timeStr isEqualTo "") then { "" } else { _timeStr };
        private _wrapped = [_body, 28] call _forceWrap;
        private _safeBody = [_wrapped] call _escHtml;
        private _safeWho = [_who] call _escHtml;
        private _safeStamp = [_stamp] call _escHtml;

        _html = _html + format [
            "<t size='0.86' color='%1'>%2</t>  <t size='0.72' color='#8aa0b4'>%3</t><br/><t size='0.98' color='#F4F7F8'>%4</t><br/><br/>",
            _whoCol,
            _safeWho,
            _safeStamp,
            _safeBody
        ];
    } forEach _filtered;
};

try {
    _msgBody ctrlSetStructuredText parseText _html;
} catch {
    _msgBody ctrlSetStructuredText parseText "<t color='#ff8a7a'>Impossible d’afficher ce fil.</t>";
};

private _h = ctrlTextHeight _msgBody;
private _phoneW = safezoneW * 0.8;
private _phoneH = _phoneW * 4 / 3;
private _minH = ((4.06 * 60) / 2048) * _phoneH;
if (_h < _minH) then { _h = _minH; };
_h = _h + ((18 / 2048) * _phoneH);
private _pos = ctrlPosition _msgBody;
_msgBody ctrlSetPosition [_pos select 0, _pos select 1, _pos select 2, _h];
_msgBody ctrlCommit 0;
if (!isNull _msgView) then {
    _msgView ctrlSetScrollValues [1, 0];
};

[] call comspec_overwatch_atak_athena_fnc_athena_commsApplyChrome;
