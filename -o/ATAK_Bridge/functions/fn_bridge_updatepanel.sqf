private _state = call Iceman_fnc_bridge_getstate;
if (_state getOrDefault ["updating", false]) exitWith {false};

_state set ["updating", true];

private _controls = call Iceman_fnc_bridge_findcontrols;
private _status = _controls getOrDefault ["status", controlNull];
private _radio152 = _controls getOrDefault ["radio152", controlNull];
private _radio117 = _controls getOrDefault ["radio117", controlNull];
private _list = _controls getOrDefault ["list", controlNull];
private _detail = _controls getOrDefault ["detail", controlNull];
private _actionOne = _controls getOrDefault ["actionOne", controlNull];
private _actionTwo = _controls getOrDefault ["actionTwo", controlNull];
private _actionThree = _controls getOrDefault ["actionThree", controlNull];
private _actionFour = _controls getOrDefault ["actionFour", controlNull];

private _radioClass = _state getOrDefault ["radioClass", "ACRE_PRC152"];
private _selection = _state getOrDefault ["selection", 0];
private _hasRadio = [player] call Iceman_fnc_bridge_hasradio;
private _hasLegacy = [_radioClass] call Iceman_fnc_bridge_haslegacyradio;
private _tx = +(_state getOrDefault ["txChannels", []]);
private _mon = +(_state getOrDefault ["monitorChannels", []]);
private _active = _state getOrDefault ["activeRecord", []];
private _channels = [_radioClass] call Iceman_fnc_bridge_getchannels;
_state set ["lastChannels", _channels];

if (!isNull _status) then {
    private _radioText = ["NO MPU-5", "MPU-5 READY"] select _hasRadio;
    private _radioName = ["PRC-117F", "PRC-152"] select (_radioClass == "ACRE_PRC152");
    private _legacyText = [format ["NO %1", _radioName], format ["%1 READY", _radioName]] select _hasLegacy;
    _status ctrlSetStructuredText parseText format [
        "<t align='center'>%1 | %2 | %3 channels | TX %4 / MON %5</t>",
        _radioText,
        _legacyText,
        count _channels,
        count _tx,
        count _mon
    ];
};

if (!isNull _radio152) then {
    _radio152 ctrlSetBackgroundColor ([[0.08,0.12,0.14,0.85], [0.10,0.42,0.50,0.95]] select (_radioClass == "ACRE_PRC152"));
};
if (!isNull _radio117) then {
    _radio117 ctrlSetBackgroundColor ([[0.08,0.12,0.14,0.85], [0.10,0.42,0.50,0.95]] select (_radioClass == "ACRE_PRC117F"));
};

if (!isNull _list) then {
    lbClear _list;
    {
        _x params ["_class", "_idx", "_label"];
        private _id = [_x] call Iceman_fnc_bridge_recordid;
        private _tags = [];
        private _txIndex = _tx findIf {([_x] call Iceman_fnc_bridge_recordid) == _id};
        if (_txIndex >= 0) then {_tags pushBack format ["TX%1", _txIndex + 1]};
        if ((_mon findIf {([_x] call Iceman_fnc_bridge_recordid) == _id}) >= 0) then {_tags pushBack "MON"};
        if (_active isNotEqualTo [] && {([_active] call Iceman_fnc_bridge_recordid) == _id}) then {_tags pushBack "ACT"};
        private _row = _list lbAdd format ["CH%1 - %2  %3", _idx + 1, _label, _tags joinString "/"];
        _list lbSetData [_row, _id];
    } forEach _channels;

    if (_channels isEqualTo []) then {
        _list lbAdd "No ACRE preset channels discovered";
    } else {
        private _safeSelection = (_selection max 0) min ((lbSize _list) - 1);
        _list lbSetCurSel _safeSelection;
        _state set ["selection", _safeSelection];
    };
};

private _detailLines = [];
if (!_hasRadio) then {
    _detailLines = [
        "<t color='#ffcc66'>No MPU-5 detected.</t>",
        "Bridge uses the MPU-5 as the voice device, so grab the MPU-5 Persistent Systems radio first."
    ];
} else {
    if (!_hasLegacy) then {
        private _radioName = ["PRC-117F", "PRC-152"] select (_radioClass == "ACRE_PRC152");
        _detailLines = [
            format ["<t color='#ffcc66'>%1 not detected.</t>", _radioName],
            format ["Bridge %1 channels require a %1 in your kit or in the cargo of your current vehicle.", _radioName],
            "You can still view channels, but TX, MON, and ACT will not apply until the legacy radio is present."
        ];
    } else {
        if (_channels isEqualTo []) then {
        _detailLines = [
            "<t color='#ffcc66'>No channel data available.</t>",
            "ACRE preset channel data was not available for this radio family."
        ];
        } else {
        private _selected = _channels # ((_selection max 0) min ((count _channels) - 1));
        _selected params ["_class", "_idx", "_label", "_pairs"];
        private _channel = [_selected] call Iceman_fnc_bridge_recordtochannel;
        private _txList = if (_tx isEqualTo []) then {"None"} else {
            (_tx apply {format ["TX%1: %2 CH%3 - %4", (_tx find _x) + 1, ["117F", "152"] select ((_x # 0) == "ACRE_PRC152"), (_x # 1) + 1, _x # 2]}) joinString "<br/>"
        };
        private _monList = if (_mon isEqualTo []) then {"None"} else {
            (_mon apply {format ["%1 CH%2 - %3", ["117F", "152"] select ((_x # 0) == "ACRE_PRC152"), (_x # 1) + 1, _x # 2]}) joinString "<br/>"
        };
        private _fmtPower = {
            params ["_rawPower"];
            private _watts = _rawPower / 1000;
            private _rounded = if (_watts < 1) then {
                round (_watts * 100) / 100
            } else {
                round (_watts * 10) / 10
            };
            format ["%1W", _rounded]
        };
        private _legacyPower = round (_channel getVariable ["Iceman_Bridge_legacyPower", _channel getVariable ["power", 5000]]);
        private _effectivePower = round (_channel getVariable ["Iceman_Bridge_effectivePower", _channel getVariable ["power", 5000]]);
        _detailLines = [
            format ["<t color='#b8e8ef'>Selected:</t> %1 CH%2 - %3", ["PRC-117F", "PRC-152"] select (_class == "ACRE_PRC152"), _idx + 1, _label],
            format ["TX FREQ: %1 | RX FREQ: %2", _channel getVariable ["frequencyTX", "?"], _channel getVariable ["frequencyRX", "?"]],
            format ["Power: legacy %1 | bridge %2", [_legacyPower] call _fmtPower, [_effectivePower] call _fmtPower],
            format ["Mod: %1 | Enc: %2", _channel getVariable ["modulation", "FM"], _channel getVariable ["encryption", 0]],
            format ["<t color='#b8e8ef'>Bridge TX slots</t><br/>%1", _txList],
            format ["<t color='#b8e8ef'>Bridge MON</t><br/>%1", _monList],
            "ACT sets the MPU-5 current channel for normal ACRE PTT. Bridge TX1-TX4 keybinds use the TX slot list."
        ];
        };
    };
};

if (!isNull _detail) then {
    _detail ctrlSetStructuredText parseText (_detailLines joinString "<br/>");
};

{
    _x params ["_ctrl", "_text"];
    if (!isNull _ctrl) then {_ctrl ctrlSetText _text};
} forEach [
    [_actionOne, "Radio"],
    [_actionTwo, "TX"],
    [_actionThree, "MON"],
    [_actionFour, "ACT"]
];

{
    if (!isNull _x) then {_x ctrlEnable (_hasRadio && {_hasLegacy})};
} forEach [_actionTwo, _actionThree, _actionFour];

_state set ["updating", false];
true
