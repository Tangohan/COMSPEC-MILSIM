private _state = call Iceman_fnc_wr_getState;
if (_state getOrDefault ["updating", false]) exitWith {false};

_state set ["updating", true];

private _controls = call Iceman_fnc_wr_findControls;
private _status = _controls getOrDefault ["status", controlNull];
private _list = _controls getOrDefault ["list", controlNull];
private _detail = _controls getOrDefault ["detail", controlNull];
private _actionOne = _controls getOrDefault ["actionOne", controlNull];
private _actionTwo = _controls getOrDefault ["actionTwo", controlNull];
private _actionThree = _controls getOrDefault ["actionThree", controlNull];
private _actionFour = _controls getOrDefault ["actionFour", controlNull];
private _actionFive = _controls getOrDefault ["actionFive", controlNull];
private _actionSix = _controls getOrDefault ["actionSix", controlNull];
private _freqCtrl = _controls getOrDefault ["frequency", controlNull];
private _profileCtrl = _controls getOrDefault ["profile", controlNull];

private _tab = _state getOrDefault ["tab", "home"];
private _selection = _state getOrDefault ["selection", 0];
private _hasRadio = [player] call Iceman_fnc_wr_hasRadio;
private _nodes = call Iceman_fnc_wr_collectNodes;
private _feeds = call Iceman_fnc_wr_getFeeds;
private _subscriptions = _state getOrDefault ["subscriptions", []];
private _frequency = _state getOrDefault ["frequency", "32.0"];
private _profileName = _state getOrDefault ["profileName", "Default"];
private _activeTG = _state getOrDefault ["activeTalkgroup", 1];
private _txSlots = call Iceman_fnc_wr_getTxSlots;
private _monitorTalkgroups = +(_state getOrDefault ["monitorTalkgroups", [1, 2]]);
private _txSlot = _state getOrDefault ["txSlot", 1];
private _gateway = _state getOrDefault ["gateway", false];
private _localVersion = missionNamespace getVariable ["Iceman_WR_buildVersion", "unknown"];
private _videoMbps = missionNamespace getVariable ["Iceman_WR_videoMbps", 8];
private _baseMbps = missionNamespace getVariable ["Iceman_WR_baseMbps", 100];
private _formatEar = {
    params [["_tg", 1]];
    private _ear = [_tg] call Iceman_fnc_wr_getMonitorEar;
    switch (_ear) do {
        case "L": {"L"};
        case "R": {"R"};
        default {"BOTH"};
    }
};

private _onlineNodes = {(_x getOrDefault ["hops", -1]) >= 0} count _nodes;
private _subLoad = (count _subscriptions) * _videoMbps;
private _versionMismatchCount = {
    (_x getOrDefault ["nodeType", "player"]) == "player"
    && {!((_x getOrDefault ["unit", objNull]) isEqualTo player)}
    && {(_x getOrDefault ["version", "unknown"]) != _localVersion}
} count _nodes;

if (!isNull _status) then {
    private _radioText = ["NO MPU-5", "MPU-5 READY"] select _hasRadio;
    private _safeTxSlot = (round _txSlot) max 1 min 4;
    private _slotTalkgroup = _txSlots # (_safeTxSlot - 1);
    private _txText = if (_slotTalkgroup > 0) then {
        format ["TX%1>TG%2", _safeTxSlot, _slotTalkgroup]
    } else {
        format ["TX%1>OFF", _safeTxSlot]
    };
    private _syncText = if (_versionMismatchCount > 0) then {
        format [" | <t color='#ffd166'>%1 VERSION MISMATCH</t>", _versionMismatchCount]
    } else {
        ""
    };
    _status ctrlSetStructuredText parseText format [
        "<t align='center'>%1 | %2 node(s) | F%3 | %4%5</t>",
        _radioText,
        _onlineNodes,
        _frequency,
        _txText,
        _syncText
    ];
    private _statusColor = if (!_hasRadio) then {
        [0.38,0.10,0.10,0.72]
    } else {
        [[0.05,0.28,0.32,0.72], [0.42,0.25,0.04,0.78]] select (_versionMismatchCount > 0)
    };
    _status ctrlSetBackgroundColor _statusColor;
};

if (!isNull _freqCtrl && {ctrlText _freqCtrl == ""}) then {
    _freqCtrl ctrlSetText _frequency;
};
if (!isNull _profileCtrl && {ctrlText _profileCtrl == ""}) then {
    _profileCtrl ctrlSetText _profileName;
};

{
    _x params ["_name", "_tabName"];
    private _ctrl = _controls getOrDefault [_name, controlNull];
    if (!isNull _ctrl) then {
        _ctrl ctrlSetBackgroundColor ([[0.08,0.12,0.14,0.85], [0.10,0.42,0.50,0.95]] select (_tab == _tabName));
    };
} forEach [
    ["tabHome", "home"],
    ["tabTalkgroups", "talkgroups"],
    ["tabFeeds", "feeds"],
    ["tabGateways", "gateways"],
    ["tabPli", "pli"],
    ["tabDiag", "diag"]
];

if (!isNull _list) then {lbClear _list};

private _detailLines = [];
private _actionLabels = ["Refresh", "Gateway", "Locate", "", "", ""];

if (!_hasRadio) then {
    if (!isNull _list) then {_list lbAdd "MPU-5 radio not detected"};
    _detailLines = [
        "<t color='#ffcc66'>No MPU-5 is currently detected in your inventory.</t>",
        "Grab the MPU-5 Persistent Systems radio from Arsenal, then reopen or refresh Wave Relay.",
        "TX, monitor audio, pop-ups, and the radio gesture remain disabled until the radio is back in your kit."
    ];
    _actionLabels = ["Refresh", "", "", "", "", ""];
} else {
    switch (_tab) do {
        case "talkgroups": {
            private _txEditSlot = round (_state getOrDefault ["txEditSlot", 1]);
            _txEditSlot = _txEditSlot max 1 min 4;
            private _tgSel = (_selection + 1) max 1 min 16;
            private _assignedSlot = [_tgSel] call Iceman_fnc_wr_txSlotForTg;
            private _txAction = if (_assignedSlot == _txEditSlot) then {
                format ["Clear TX%1", _assignedSlot]
            } else {
                if (_assignedSlot > 0) then {
                    format ["Move TX%1", _txEditSlot]
                } else {
                    format ["Set TX%1", _txEditSlot]
                }
            };
            _actionLabels = [_txAction, "Slot +", "MON", "EAR", "VOL -", "VOL +"];
            for "_tg" from 1 to 16 do {
                private _tags = [];
                private _txIndex = _txSlots find _tg;
                if (_txIndex >= 0) then {_tags pushBack format ["TX%1", _txIndex + 1]};
                if (_tg in _monitorTalkgroups) then {_tags pushBack format ["MON-%1", [_tg] call _formatEar]};
                private _volume = [_tg] call Iceman_fnc_wr_getMonitorVolume;
                if (_volume < 1) then {_tags pushBack format ["VOL%1", round (_volume * 100)]};
                if (_tg == _activeTG) then {_tags pushBack "ACT"};
                if (_tg == 16) then {_tags pushBack "EMERG"};
                private _row = if (!isNull _list) then {
                    _list lbAdd format ["TG-%1%2  %3", _tg, ["", " EMERGENCY"] select (_tg == 16), _tags joinString "/"]
                } else {-1};
                if (_row >= 0) then {
                    _list lbSetData [_row, str _tg];
                    private _rowColor = [0.86,0.91,0.92,1];
                    if (_tg in _monitorTalkgroups) then {_rowColor = [0.55,0.86,0.94,1]};
                    if (_txIndex >= 0) then {_rowColor = [0.48,0.94,0.68,1]};
                    if (_tg == 16) then {_rowColor = [1.00,0.72,0.24,1]};
                    _list lbSetColor [_row, _rowColor];
                };
            };
            private _selectedTxText = if (_assignedSlot > 0) then {format ["TX%1", _assignedSlot]} else {"None"};
            private _selectedMonText = if (_tgSel in _monitorTalkgroups) then {[_tgSel] call _formatEar} else {"Off"};
            private _selectedVolText = format ["%1%2", round (([_tgSel] call Iceman_fnc_wr_getMonitorVolume) * 100), "%"];
            _detailLines = [
                format ["<t color='#b8e8ef'>Selected:</t> TG-%1", _tgSel],
                format ["TX assignment: %1 | Target: TX%2", _selectedTxText, _txEditSlot],
                format ["Monitor: %1 | Volume: %2", _selectedMonText, _selectedVolText],
                format ["All TX slots: %1", [_txSlots] call Iceman_fnc_wr_formatTxSlots],
                if (_tgSel == 16) then {
                    "<t color='#ffcc66'>Emergency all-call:</t> TX on TG16 is heard by every MPU-5 user on this frequency bank."
                } else {
                    "Set/Move/Clear only changes the selected TG. Slot + changes the target TX key."
                }
            ];
        };
        case "feeds": {
            _actionLabels = ["View", "TOC", "Pin", "Sub", "", ""];
            private _reachableNodes = _nodes select {(_x getOrDefault ["hops", -1]) >= 0};
            private _capacity = _baseMbps;
            {
                _capacity = _capacity min (_x getOrDefault ["throughput", _baseMbps]);
            } forEach _reachableNodes;

            private _feedInfos = [];
            {
                private _id = format ["%1:%2:%3", _x # 1, _x # 2, _x # 3];
                private _info = [_x, _nodes, _subscriptions, _capacity] call Iceman_fnc_wr_feedInfo;
                _feedInfos pushBack _info;
                private _prefix = ["", "* "] select (_id in _subscriptions);
                private _quality = _info get "quality";
                private _grid = _info get "grid";
                private _alt = _info get "alt";
                private _row = if (!isNull _list) then {
                    _list lbAdd format ["%1[%2] %3  %4  ALT %5m", _prefix, _quality, _x # 0, _grid, _alt]
                } else {-1};
                if (_row >= 0) then {_list lbSetData [_row, _id]};
            } forEach _feeds;
            _state set ["lastFeedInfo", _feedInfos];

            if (_feeds isEqualTo []) then {
                if (!isNull _list) then {_list lbAdd "No camera feeds discovered"};
                _detailLines = ["No video feeds are currently visible to Wave Relay."];
            } else {
                private _feed = _feeds # ((_selection max 0) min ((count _feeds) - 1));
                private _info = _feedInfos # ((_selection max 0) min ((count _feedInfos) - 1));
                private _id = format ["%1:%2:%3", _feed # 1, _feed # 2, _feed # 3];
                private _subscribed = _id in _subscriptions;
                _detailLines = [
                    format ["<t color='#b8e8ef'>Feed:</t> %1", _feed # 0],
                    format ["Signal: %1 | %2", _info get "quality", _info get "reason"],
                    format ["Owner/source: %1 | Grid: %2 | Alt: %3m", _info get "owner", _info get "grid", _info get "alt"],
                    format ["Distance: %1m | Hops: %2 | Last update: T+%3s", _info get "distance", _info get "hops", _info get "lastUpdate"],
                    format ["Status: %1 | Cost: %2 Mbps | Network load: %3 Mbps", ["available", "subscribed"] select _subscribed, _videoMbps, _subLoad],
                    "View opens the ATAK camera page. TOC pushes to a nearby screen. Pin replaces the ATAK map area."
                ];
            };
        };
        case "gateways": {
            _actionLabels = ["Refresh", "Gateway", "Locate", "", "", ""];
            {
                private _gw = _x getOrDefault ["gateway", false];
                private _row = if (!isNull _list) then {
                    _list lbAdd format ["%1%2  %3", ["", "* "] select _gw, _x get "name", _x get "status"]
                } else {-1};
                if (_row >= 0) then {_list lbSetData [_row, str _forEachIndex]};
            } forEach _nodes;

            if (_nodes isEqualTo []) then {
                _detailLines = ["No Wave Relay nodes are currently reachable."];
            } else {
                private _node = _nodes # ((_selection max 0) min ((count _nodes) - 1));
                _detailLines = [
                    format ["<t color='#b8e8ef'>Node:</t> %1", _node get "name"],
                format ["Gateway: %1", ["NO", "YES"] select (_node getOrDefault ["gateway", false])],
                format ["FREQ %1 / Active TG-%2", _node getOrDefault ["frequency", "32.0"], _node getOrDefault ["activeTalkgroup", 1]],
                format ["Hops: %1 | Quality: %2%3", _node get "hops", _node get "quality", "%"],
                format ["Build: %1", _node getOrDefault ["version", "unknown"]],
                format ["Services: %1", (_node getOrDefault ["services", []]) joinString ", "]
            ];
            };
        };
        case "pli": {
            _actionLabels = ["Locate", "Refresh", "Gateway", "", "", ""];
            {
                private _row = if (!isNull _list) then {
                    _list lbAdd format ["%1  %2  %3m", _x get "name", _x get "grid", round (_x get "distance")]
                } else {-1};
                if (_row >= 0) then {_list lbSetData [_row, str _forEachIndex]};
            } forEach _nodes;

            if (_nodes isEqualTo []) then {
                _detailLines = ["No PLI nodes are currently reachable."];
            } else {
                private _node = _nodes # ((_selection max 0) min ((count _nodes) - 1));
                _detailLines = [
                    format ["<t color='#b8e8ef'>%1</t>", _node get "name"],
                    format ["Node ID: %1", _node get "nodeId"],
                    format ["Grid: %1", _node get "grid"],
                    format ["FREQ %1 / TG-%2", _node getOrDefault ["frequency", "32.0"], _node getOrDefault ["activeTalkgroup", 1]],
                    format ["Build: %1", _node getOrDefault ["version", "unknown"]],
                    format ["Status: %1 | Build: %2", _node get "status", _node getOrDefault ["version", "unknown"]]
                ];
            };
        };
        case "diag": {
            _actionLabels = ["Save", "Load", "Delete", "", "", ""];
            private _profiles = call Iceman_fnc_wr_getProfiles;
            {
                _x params ["_name", "_freq", "_banks"];
                private _row = if (!isNull _list) then {
                    _list lbAdd format ["%1  FREQ %2  %3 bank(s)", _name, _freq, count _banks]
                } else {-1};
                if (_row >= 0) then {_list lbSetData [_row, _name]};
            } forEach _profiles;

            if (_profiles isEqualTo []) then {
                if (!isNull _list) then {_list lbAdd "No saved profiles"};
                _detailLines = [
                    format ["Current profile: %1", _profileName],
                    format ["Current frequency: %1", _frequency],
                    format ["TX: %1", [_txSlots] call Iceman_fnc_wr_formatTxSlots],
                    format ["Monitor: %1", [_monitorTalkgroups] call Iceman_fnc_wr_formatTgList]
                ];
            } else {
                private _profile = _profiles # ((_selection max 0) min ((count _profiles) - 1));
                _profile params ["_name", "_freq", "_banks", "_subs", "_gw"];
                _detailLines = [
                    format ["<t color='#b8e8ef'>Profile:</t> %1", _name],
                    format ["Default frequency: %1", _freq],
                    format ["Saved banks: %1", count _banks],
                    format ["Video subscriptions: %1", count _subs],
                    format ["Gateway: %1", ["OFF", "ON"] select _gw]
                ];
            };
        };
        default {
            _actionLabels = ["Refresh", "Gateway", "Locate", "", "", ""];
            private _reachableNodes = _nodes select {(_x getOrDefault ["hops", -1]) >= 0};
            private _degradedNodes = _reachableNodes select {(_x getOrDefault ["status", "OFFLINE"]) == "DEGRADED"};
            private _gatewayNodes = _reachableNodes select {_x getOrDefault ["gateway", false]};
            private _bridgeNodes = _reachableNodes select {"Bridge" in (_x getOrDefault ["services", []])};
            private _amplifierNodes = _reachableNodes select {_x getOrDefault ["amplifier", false]};
            private _activeBridgeId = player getVariable ["Iceman_Bridge_activeRecordId", ""];
            private _bridgeActive = player getVariable ["Iceman_WR_bridgeActive", false];
            private _bridgeMonitorRecords = player getVariable ["Iceman_Bridge_monitorRecords", []];
            private _gatewayObjectCount = if !(isNil "Iceman_fnc_gw_getGatewayObjects") then {
                count (call Iceman_fnc_gw_getGatewayObjects)
            } else {
                0
            };

            private _capacity = _baseMbps;
            private _minQuality = 0;
            if !(_reachableNodes isEqualTo []) then {
                _capacity = _baseMbps;
                _minQuality = 100;
                {
                    _capacity = _capacity min (_x getOrDefault ["throughput", _baseMbps]);
                    _minQuality = _minQuality min (_x getOrDefault ["quality", 100]);
                } forEach _reachableNodes;
            };

            private _netState = "OFFLINE";
            if ((count _reachableNodes) > 0) then {
                _netState = "GOOD";
                if ((count _degradedNodes) > 0 || {_subLoad > _capacity}) then {_netState = "DEGRADED"};
            };

            private _videoState = "IDLE";
            if ((count _subscriptions) > 0) then {
                _videoState = ["STREAMING", "SATURATED"] select (_subLoad > _capacity);
            };

            private _healthRows = [];
            private _pushHealth = {
                params ["_key", "_label", "_lines", ["_target", objNull]];
                _healthRows pushBack [_key, _label, _lines, _target];
                if (!isNull _list) then {
                    private _row = _list lbAdd _label;
                    _list lbSetData [_row, _key];
                };
            };

            [
                "overview",
                format ["NET HEALTH  %1  %2/%3 nodes", _netState, count _reachableNodes, count _nodes],
                [
                    format ["<t color='#b8e8ef'>Network Health:</t> %1", _netState],
                    format ["Nodes: %1 online / %2 total | degraded %3", count _reachableNodes, count _nodes, count _degradedNodes],
                    format ["Quality floor: %1%2 | estimated path capacity: %3 Mbps", _minQuality, "%", round (_capacity * 10) / 10],
                    format ["Video load: %1 Mbps from %2 subscription(s)", _subLoad, count _subscriptions],
                    format ["Services: %1 gateway | %2 bridge | %3 amplifier", count _gatewayNodes, count _bridgeNodes, count _amplifierNodes]
                ]
            ] call _pushHealth;

            [
                "voice",
                format ["VOICE  F%1  TX %2  MON %3", _frequency, [_txSlots] call Iceman_fnc_wr_formatTxSlots, [_monitorTalkgroups] call Iceman_fnc_wr_formatTgList],
                [
                    "<t color='#b8e8ef'>Voice Network</t>",
                    format ["Frequency bank: %1", _frequency],
                    format ["Active TG: TG-%1", _activeTG],
                    format ["TX slots: %1", [_txSlots] call Iceman_fnc_wr_formatTxSlots],
                    format ["Monitor list: %1", [_monitorTalkgroups] call Iceman_fnc_wr_formatTgList],
                    format ["Local build: %1", _localVersion],
                    "TX1-TX4 follow the slot assignments shown in the TG page."
                ]
            ] call _pushHealth;

            [
                "video",
                format ["VIDEO  %1  %2/%3 feeds  %4 Mbps", _videoState, count _subscriptions, count _feeds, _subLoad],
                [
                    "<t color='#b8e8ef'>Video Streams</t>",
                    format ["Discovered feeds: %1", count _feeds],
                    format ["Subscribed feeds: %1", count _subscriptions],
                    format ["Estimated load: %1 Mbps at %2 Mbps/feed", _subLoad, _videoMbps],
                    format ["Path capacity estimate: %1 Mbps", round (_capacity * 10) / 10],
                    "Use VID to subscribe, unsubscribe, and locate feed sources."
                ]
            ] call _pushHealth;

            [
                "bridge",
                format ["BRIDGE  %1  %2 monitored  %3 visible", ["STANDBY", "ACTIVE"] select _bridgeActive, count _bridgeMonitorRecords, count _bridgeNodes],
                [
                    "<t color='#b8e8ef'>Bridge Services</t>",
                    format ["Local bridge: %1", ["OFF", "ON"] select _bridgeActive],
                    format ["Active bridge record: %1", ["None", _activeBridgeId] select (_activeBridgeId != "")],
                    format ["Local monitor records: %1", count _bridgeMonitorRecords],
                    format ["Reachable bridge nodes: %1", count _bridgeNodes],
                    format ["Reachable users: %1", count _reachableNodes],
                    "Bridge channels inherit legacy radio settings and ride the Wave Relay mesh."
                ],
                if ((count _bridgeNodes) > 0) then {(_bridgeNodes # 0) get "unit"} else {objNull}
            ] call _pushHealth;

            [
                "gateway",
                format ["GATEWAY  LOCAL %1  %2 visible  %3 rack(s)", ["OFF", "ON"] select _gateway, count _gatewayNodes, _gatewayObjectCount],
                [
                    "<t color='#b8e8ef'>Gateways</t>",
                    format ["Local gateway advertisement: %1", ["OFF", "ON"] select _gateway],
                    format ["Reachable gateway nodes: %1", count _gatewayNodes],
                    format ["Gateway rack objects: %1", _gatewayObjectCount],
                    "Gateway nodes advertise bridge/radio services into the mesh."
                ],
                if ((count _gatewayNodes) > 0) then {(_gatewayNodes # 0) get "unit"} else {objNull}
            ] call _pushHealth;

            private _degradedText = if (_degradedNodes isEqualTo []) then {
                "None"
            } else {
                ((_degradedNodes select [0, 4]) apply {
                    format ["%1 (%2%3, %4 hop)", _x get "name", _x getOrDefault ["quality", 0], "%", _x getOrDefault ["hops", -1]]
                }) joinString "<br/>"
            };
            [
                "degraded",
                format ["DEGRADED LINKS  %1", count _degradedNodes],
                [
                    "<t color='#b8e8ef'>Degraded Links</t>",
                    _degradedText,
                    "Degraded usually means too many hops, weak quality, or video load exceeding path capacity."
                ],
                if ((count _degradedNodes) > 0) then {(_degradedNodes # 0) get "unit"} else {objNull}
            ] call _pushHealth;

            {
                [
                    format ["node_%1", _forEachIndex],
                    format ["NODE  %1  %2  Q%3%4", _x get "name", _x get "status", _x getOrDefault ["quality", 0], "%"],
                    [
                        format ["<t color='#b8e8ef'>Node:</t> %1", _x get "name"],
                        format ["Node ID: %1", _x get "nodeId"],
                        format ["Grid: %1 | Distance: %2m", _x get "grid", round (_x get "distance")],
                        format ["Hops: %1 | Throughput: %2 Mbps | Quality: %3%4", _x get "hops", _x get "throughput", _x get "quality", "%"],
                        format ["Build: %1", _x getOrDefault ["version", "unknown"]],
                        format ["Services: %1", (_x getOrDefault ["services", []]) joinString ", "]
                    ],
                    _x get "unit"
                ] call _pushHealth;
            } forEach _reachableNodes;

            _state set ["lastHealthRows", _healthRows];

            if (_healthRows isEqualTo []) then {
                _detailLines = ["No Wave Relay health data available."];
            } else {
                private _healthRow = _healthRows # ((_selection max 0) min ((count _healthRows) - 1));
                _detailLines = _healthRow # 2;
            };
        };
    };
};

if (!isNull _list && {lbSize _list > 0}) then {
    private _safeSelection = (_selection max 0) min ((lbSize _list) - 1);
    _list lbSetCurSel _safeSelection;
    _state set ["selection", _safeSelection];
};

if (!isNull _detail) then {
    _detail ctrlSetStructuredText parseText (_detailLines joinString "<br/>");
};

{
    _x params ["_ctrl", "_text"];
    if (!isNull _ctrl) then {
        _ctrl ctrlSetText _text;
        _ctrl ctrlShow (_text != "");
        _ctrl ctrlEnable (_text != "");
    };
} forEach [
    [_actionOne, _actionLabels # 0],
    [_actionTwo, _actionLabels # 1],
    [_actionThree, _actionLabels # 2],
    [_actionFour, _actionLabels # 3],
    [_actionFive, _actionLabels # 4],
    [_actionSix, _actionLabels # 5]
];

_state set ["updating", false];
true
