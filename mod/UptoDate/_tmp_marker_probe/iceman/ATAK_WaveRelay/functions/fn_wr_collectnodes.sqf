private _state = call Iceman_fnc_wr_getState;
private _range = missionNamespace getVariable ["Iceman_WR_rangeM", 3000];
private _spikeRange = missionNamespace getVariable ["Iceman_WR_spikeRangeM", 5000];
private _baseMbps = missionNamespace getVariable ["Iceman_WR_baseMbps", 100];
private _hopLoss = missionNamespace getVariable ["Iceman_WR_hopLossFactor", 0.5];

private _units = allPlayers select {
    alive _x &&
    {
        (_x getVariable ["Iceman_WR_hasMPU5", false]) ||
        {[_x] call Iceman_fnc_wr_hasRadio}
    }
};
if !([player] call Iceman_fnc_wr_hasRadio) then {
    _state set ["lastNodes", []];
    []
} else {
    if !((player in _units)) then {
        _units insert [0, [player]];
    };

    private _nodes = [];
    {
        private _unit = _x;
        private _pos = getPosASL _unit;
        private _services = ["Voice", "PLI"];
        if (_unit getVariable ["Iceman_WR_gateway", false]) then {_services pushBack "Gateway"};
        if (_unit getVariable ["Iceman_WR_bridgeActive", false]) then {_services pushBackUnique "Bridge"};
        if (_unit == player && {_state getOrDefault ["gateway", false]}) then {_services pushBackUnique "Gateway"};
        private _freq = _unit getVariable ["Iceman_WR_frequency", "32.0"];
        private _activeTg = _unit getVariable ["Iceman_WR_activeTG", 1];
        private _txSlots = _unit getVariable ["Iceman_WR_txSlots", [1, 0, 0, 0]];
        private _txList = _unit getVariable ["Iceman_WR_txTalkgroups", (_txSlots select {_x > 0})];
        private _monList = _unit getVariable ["Iceman_WR_monitorTalkgroups", [1, 2]];
        private _bridgeActive = _unit getVariable ["Iceman_WR_bridgeActive", false];
        private _bridgeRecordId = _unit getVariable ["Iceman_Bridge_activeRecordId", ""];
        private _bridgeRadioClass = _unit getVariable ["Iceman_Bridge_activeRadioClass", ""];
        private _bridgeChannelIndex = _unit getVariable ["Iceman_Bridge_activeChannelIndex", -1];
        private _bridgeOwner = _unit getVariable ["Iceman_Bridge_activeOwner", name _unit];
        private _version = _unit getVariable ["Iceman_WR_buildVersion", "unknown"];

        _nodes pushBack (createHashMapFromArray [
            ["unit", _unit],
            ["name", name _unit],
            ["nodeId", [_unit] call Iceman_fnc_wr_getNodeId],
            ["pos", _pos],
            ["grid", [ASLToATL _pos, 8] call Iceman_fnc_wr_formatGrid],
            ["distance", player distance _unit],
            ["frequency", _freq],
            ["activeTalkgroup", _activeTg],
            ["txTalkgroups", _txList],
            ["txSlots", _txSlots],
            ["monitorTalkgroups", _monList],
            ["gateway", (_unit getVariable ["Iceman_WR_gateway", false]) || {_unit == player && {_state getOrDefault ["gateway", false]}}],
            ["bridgeActive", _bridgeActive],
            ["bridgeRecordId", _bridgeRecordId],
            ["bridgeRadioClass", _bridgeRadioClass],
            ["bridgeChannelIndex", _bridgeChannelIndex],
            ["bridgeOwner", _bridgeOwner],
            ["version", _version],
            ["services", _services],
            ["amplifier", false],
            ["linkRange", _range],
            ["nodeType", "player"],
            ["hops", -1],
            ["throughput", 0],
            ["quality", 0],
            ["status", "OFFLINE"]
        ]);
    } forEach _units;

    private _spikes = [];
    {
        {
            if (!isNull _x && {alive _x}) then {
                _spikes pushBackUnique _x;
            };
        } forEach (allMissionObjects _x);
    } forEach ["vhf30108spike", "vhf30108Item"];

    {
        private _obj = _x;
        private _pos = getPosASL _obj;
        private _type = typeOf _obj;
        private _displayName = getText (configFile >> "CfgVehicles" >> _type >> "displayName");
        if (_displayName == "") then {_displayName = "ACRE Ground Spike"};

        private _nodeSuffix = netId _obj;
        if (_nodeSuffix == "0:0") then {
            _nodeSuffix = format ["%1-%2-%3", round (_pos # 0), round (_pos # 1), round (_pos # 2)];
        };

        _nodes pushBack (createHashMapFromArray [
            ["unit", _obj],
            ["name", _displayName],
            ["nodeId", format ["GSA-%1", _nodeSuffix]],
            ["pos", _pos],
            ["grid", [ASLToATL _pos, 8] call Iceman_fnc_wr_formatGrid],
            ["distance", player distance _obj],
            ["frequency", "Relay"],
            ["activeTalkgroup", 0],
            ["txTalkgroups", []],
            ["monitorTalkgroups", []],
            ["gateway", false],
            ["services", ["Relay", "Amplifier"]],
            ["amplifier", true],
            ["linkRange", _spikeRange],
            ["nodeType", "gsa"],
            ["hops", -1],
            ["throughput", 0],
            ["quality", 0],
            ["status", "OFFLINE"]
        ]);
    } forEach _spikes;

    private _nodeCount = count _nodes;
    private _localIndex = _nodes findIf {(_x get "unit") == player};
    if (_localIndex < 0) then {_localIndex = 0};

    private _hops = [];
    for "_i" from 0 to (_nodeCount - 1) do {_hops pushBack -1};
    _hops set [_localIndex, 0];

    private _queue = [_localIndex];
    while {(count _queue) > 0} do {
        private _current = _queue deleteAt 0;
        private _currentNode = _nodes # _current;
        private _currentPos = _currentNode get "pos";
        private _currentLinkRange = _currentNode getOrDefault ["linkRange", _range];
        private _currentHops = _hops # _current;

        for "_i" from 0 to (_nodeCount - 1) do {
            if ((_hops # _i) < 0) then {
                private _candidateNode = _nodes # _i;
                private _candidatePos = _candidateNode get "pos";
                private _candidateLinkRange = _candidateNode getOrDefault ["linkRange", _range];
                private _linkRange = _range max _currentLinkRange;
                _linkRange = _linkRange max _candidateLinkRange;
                if ((_currentPos distance _candidatePos) <= _linkRange) then {
                    _hops set [_i, _currentHops + 1];
                    _queue pushBack _i;
                };
            };
        };
    };

    for "_i" from 0 to (_nodeCount - 1) do {
        private _node = _nodes # _i;
        private _hopCount = _hops # _i;
        private _directDistance = player distance (_node get "unit");

        if (_hopCount >= 0) then {
            private _lossPower = (_hopCount - 1) max 0;
            private _throughput = _baseMbps * (_hopLoss ^ _lossPower);
            private _qualityRange = (_node getOrDefault ["linkRange", _range]) max _range;
            private _distancePenalty = 45 min ((_directDistance / (_qualityRange max 1)) * 35);
            private _hopPenalty = (_hopCount max 0) * 12;
            private _quality = round (100 - _distancePenalty - _hopPenalty);
            _quality = 5 max (100 min _quality);

            _node set ["hops", _hopCount];
            _node set ["throughput", round (_throughput * 10) / 10];
            _node set ["quality", _quality];
            _node set ["status", ["DEGRADED", "ONLINE"] select (_quality >= 55)];
        };
    };

    _nodes = [_nodes, [], {private _h = _x getOrDefault ["hops", -1]; if (_h < 0) then {99} else {_h}}, "ASCEND"] call BIS_fnc_sortBy;
    _state set ["lastNodes", _nodes];
    _nodes
}
