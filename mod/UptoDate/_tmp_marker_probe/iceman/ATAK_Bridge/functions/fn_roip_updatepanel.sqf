private _state = call Iceman_fnc_roip_getState;
if (_state getOrDefault ["updating", false]) exitWith {false};
_state set ["updating", true];

private _controls = call Iceman_fnc_roip_findControls;
private _status = _controls getOrDefault ["status", controlNull];
private _radioList = _controls getOrDefault ["radioList", controlNull];
private _tgList = _controls getOrDefault ["tgList", controlNull];
private _detail = _controls getOrDefault ["detail", controlNull];
private _connect = _controls getOrDefault ["connect", controlNull];
private _disconnect = _controls getOrDefault ["disconnect", controlNull];

private _radios = call Iceman_fnc_roip_getRadios;
_state set ["lastRadios", _radios];
private _connectedId = _state getOrDefault ["connectedRadioId", ""];
private _connectedTg = _state getOrDefault ["connectedTalkgroup", 0];
private _activeLinks = _state getOrDefault ["activeLinks", []];

private _hasMpu5 = false;
if !(isNil "acre_api_fnc_getRadioByType") then {
    private _candidate = ["ACRE_MPU5"] call acre_api_fnc_getRadioByType;
    _hasMpu5 = !(isNil "_candidate") && {_candidate isEqualType ""} && {_candidate != ""};
};

private _selectedRadioId = _state getOrDefault ["selectedRadioId", ""];
private _radioSelection = _radios findIf {(_x # 0) == _selectedRadioId};
if (_radioSelection < 0 && {_radios isNotEqualTo []}) then {
    _radioSelection = ((_state getOrDefault ["radioSelection", 0]) max 0) min ((count _radios) - 1);
    _selectedRadioId = (_radios # _radioSelection) # 0;
    _state set ["selectedRadioId", _selectedRadioId];
    _state set ["radioSelection", _radioSelection];
};

private _radioSignature = str (_radios apply {[_x # 0, _x # 1, _x # 2, _x # 3, _x # 4, (_x # 0) == _connectedId]});
if (!isNull _radioList && {_radioSignature != (_state getOrDefault ["uiRadioSignature", ""])}) then {
    lbClear _radioList;
    {
        _x params ["_radioId", "_base", "_channel", "_label", "_on"];
        private _name = ["PRC-117F", "PRC-152"] select (_base == "ACRE_PRC152");
        private _tags = [];
        if (_radioId == _connectedId) then {_tags pushBack "LINKED"};
        if (!_on) then {_tags pushBack "OFF"};
        private _suffix = if (_tags isEqualTo []) then {""} else {format [" | %1", _tags joinString "/"]};
        private _row = _radioList lbAdd format ["%1 | CH%2 | %3%4", _name, [_channel, 2] call CBA_fnc_formatNumber, _label, _suffix];
        _radioList lbSetData [_row, _radioId];
    } forEach _radios;
    if (_radios isEqualTo []) then {_radioList lbAdd "No PRC-152 / PRC-117F detected"};
    if (_radioSelection >= 0) then {_radioList lbSetCurSel _radioSelection};
    _state set ["uiRadioSignature", _radioSignature];
};

private _wrState = if !(isNil "Iceman_fnc_wr_getState") then {call Iceman_fnc_wr_getState} else {createHashMap};
private _txSlots = if !(isNil "Iceman_fnc_wr_getTxSlots") then {call Iceman_fnc_wr_getTxSlots} else {_wrState getOrDefault ["txSlots", [1, 0, 0, 0]]};
private _monitors = +(_wrState getOrDefault ["monitorTalkgroups", []]);
private _tgSelection = (_state getOrDefault ["tgSelection", 1]) max 1 min 16;
private _tgSignature = str [_tgSelection, _txSlots, _monitors, _activeLinks apply {private _l = _x # 0; [_l # 1, _l # 3, _l # 9, _l # 6]}];

if (!isNull _tgList && {_tgSignature != (_state getOrDefault ["uiTgSignature", ""])}) then {
    lbClear _tgList;
    for "_tg" from 1 to 16 do {
        private _tags = [];
        private _txIndex = _txSlots find _tg;
        if (_txIndex >= 0) then {_tags pushBack format ["TX%1", _txIndex + 1]};
        if (_tg in _monitors) then {
            private _ear = if !(isNil "Iceman_fnc_wr_getMonitorEar") then {[_tg] call Iceman_fnc_wr_getMonitorEar} else {"BOTH"};
            _tags pushBack format ["MON %1", _ear];
        };
        private _linkIndex = _activeLinks findIf {(((_x # 0) # 3) == _tg)};
        if (_linkIndex >= 0) then {
            private _link = (_activeLinks # _linkIndex) # 0;
            _tags pushBack format ["ROIP %1", _link # 9];
        };
        if (_tg == 16) then {_tags pushBack "EMERGENCY"};
        private _suffix = if (_tags isEqualTo []) then {""} else {format [" | %1", _tags joinString " / "]};
        _tgList lbAdd format ["TG %1%2", [_tg, 2] call CBA_fnc_formatNumber, _suffix];
    };
    _tgList lbSetCurSel (_tgSelection - 1);
    _state set ["uiTgSignature", _tgSignature];
};

private _localLink = _state getOrDefault ["localLink", []];
if (!isNull _status) then {
    private _statusText = "READY | Select a radio and talk group";
    private _statusColor = "#DDE7EA";
    if (!_hasMpu5) then {
        _statusText = "OFFLINE | MPU-5 NOT DETECTED";
        _statusColor = "#FFB66B";
    } else {
        if (_radios isEqualTo []) then {
            _statusText = "STANDBY | NO LEGACY RADIO";
            _statusColor = "#FFCC66";
        };
    };
    if (_localLink isEqualType [] && {(count _localLink) >= 13} && {_connectedId != ""}) then {
        private _name = ["PRC-117F", "PRC-152"] select ((_localLink # 5) == "ACRE_PRC152");
        _statusText = format ["LIVE | %1 CH%2 &lt;-&gt; TG%3", _name, _localLink # 6, _localLink # 3];
        _statusColor = "#8FE6B0";
    };
    _status ctrlSetStructuredText parseText format ["<t align='center' color='%1'>%2</t>", _statusColor, _statusText];
};

private _detailLines = [];
private _selectedRecord = [];
if (_radioSelection >= 0 && {_radioSelection < count _radios}) then {_selectedRecord = _radios # _radioSelection};
if (_selectedRecord isEqualTo []) then {
    _detailLines = [
        "<t color='#b8e8ef'>PHYSICAL LINK</t>",
        "Legacy radio: NOT AVAILABLE",
        format ["MPU-5: %1", ["NOT DETECTED", "READY"] select _hasMpu5],
        format ["Reachable ROIP links: %1", count _activeLinks]
    ];
} else {
    _selectedRecord params ["", "_base", "_channel", "_label", "_on"];
    private _name = ["PRC-117F", "PRC-152"] select (_base == "ACRE_PRC152");
    private _txIndex = _txSlots find _tgSelection;
    private _txText = if (_txIndex >= 0) then {format ["TX%1", _txIndex + 1]} else {"NO TX SLOT"};
    private _monText = if (_tgSelection in _monitors) then {
        private _ear = if !(isNil "Iceman_fnc_wr_getMonitorEar") then {[_tgSelection] call Iceman_fnc_wr_getMonitorEar} else {"BOTH"};
        format ["MON %1", _ear]
    } else {"NOT MONITORED"};

    _detailLines = [
        format ["<t color='#b8e8ef'>%1 CH%2</t> | %3", _name, [_channel, 2] call CBA_fnc_formatNumber, _label],
        format ["Radio power: %1", ["OFF", "ON"] select _on],
        format ["Selected: TG%1 | %2 | %3", _tgSelection, _txText, _monText],
        format ["Mesh-visible ROIP links: %1", count _activeLinks]
    ];
    if (_localLink isEqualType [] && {(count _localLink) >= 13}) then {
        _detailLines pushBack format ["<t color='#8FE6B0'>ACTIVE:</t> %1 CH%2 &lt;-&gt; TG%3", ["PRC-117F", "PRC-152"] select ((_localLink # 5) == "ACRE_PRC152"), _localLink # 6, _localLink # 3];
    };
};

if (!isNull _detail) then {_detail ctrlSetStructuredText parseText (_detailLines joinString "<br/>")};
if (!isNull _connect) then {
    _connect ctrlEnable (_hasMpu5 && {_selectedRecord isNotEqualTo []} && {_selectedRecord # 4});
    _connect ctrlSetText (["Connect", "Update"] select (_connectedId != ""));
};
if (!isNull _disconnect) then {_disconnect ctrlEnable (_connectedId != "")};

_state set ["updating", false];
true
