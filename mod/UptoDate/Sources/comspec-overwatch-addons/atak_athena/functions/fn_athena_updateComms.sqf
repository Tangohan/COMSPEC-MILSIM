/*
    Rafraîchit la liste des canaux et le fil du canal actif.
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Comms_group", controlNull];
if (isNull _group) exitWith {};

private _page = toLower ((["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings) param [0, ""]);
if (
    (_page isNotEqualTo "")
    && {!(_page in ["atakcomms", "comspec_atak_comms", "atak_comms", "comms", "messagerie"])}
) exitWith {};

private _lblChannel = _group controlsGroupCtrl 9921;
private _lbChannels = _group controlsGroupCtrl 9922;
private _lbMessages = _group controlsGroupCtrl 9923;
if (isNull _lbChannels || {isNull _lbMessages}) exitWith {};

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

uiNamespace setVariable ["COMSPEC_ATAK_Comms_rebuilding", true];
lbClear _lbChannels;
private _selCh = 0;
{
    _x params ["_key", "_label", ["_kind", "custom"]];
    _key = toLower (trim _key);
    if (_label isEqualTo "") then { _label = [_key] call _labelFor; };
    private _idx = _lbChannels lbAdd _label;
    _lbChannels lbSetData [_idx, _key];
    if (_key isEqualTo _active) then { _selCh = _idx; };
} forEach _channels;
if ((lbSize _lbChannels) > 0) then {
    _lbChannels lbSetCurSel _selCh;
};
uiNamespace setVariable ["COMSPEC_ATAK_Comms_rebuilding", false];

private _activeLabel = [_active] call _labelFor;
{
    if (((_x select 0) isEqualTo _active) && {(count _x) > 1}) then {
        _activeLabel = _x select 1;
    };
} forEach _channels;

if (!isNull _lblChannel) then {
    _lblChannel ctrlSetStructuredText parseText format [
        "<t align='center' size='0.95'>Canal actif : <t color='#7fd6f0'>%1</t></t>",
        _activeLabel
    ];
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

lbClear _lbMessages;
if (_filtered isEqualTo []) then {
    private _idx = _lbMessages lbAdd "Aucun message sur ce canal pour le moment.";
    _lbMessages lbSetData [_idx, ""];
} else {
    private _myCs = "";
    if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
        _myCs = [] call comspec_overwatch_connect_fnc_getCallsign;
    };
    if (_myCs isEqualTo "") then { _myCs = name player; };
    private _myCsU = toUpper _myCs;

    {
        _x params ["_id", "_author", "_body", "_timeStr", "_channelKey", ["_isMine", false]];
        private _from = if (_author isEqualTo "") then { "Poste" } else { _author };
        if (!_isMine && {(toUpper _from) isEqualTo _myCsU}) then {
            _from = format ["%1 (TOC)", _from];
        };
        if (_isMine) then { _from = "Vous"; };
        private _preview = _body;
        if ((count _preview) > 90) then { _preview = (_preview select [0, 90]) + "…"; };
        private _line = format ["%1  %2 — %3", _timeStr, _from, _preview];
        private _idx = _lbMessages lbAdd _line;
        _lbMessages lbSetData [_idx, str _id];
    } forEach _filtered;

    private _last = (lbSize _lbMessages) - 1;
    if (_last >= 0) then { _lbMessages lbSetCurSel _last; };
};
