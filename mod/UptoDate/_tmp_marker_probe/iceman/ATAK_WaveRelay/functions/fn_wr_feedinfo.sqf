params [["_feed", []], ["_nodes", []], ["_subscriptions", []], ["_capacity", 100]];

private _obj = [_feed] call Iceman_fnc_wr_feedObject;
private _videoMbps = missionNamespace getVariable ["Iceman_WR_videoMbps", 8];
private _subLoad = (count _subscriptions) * _videoMbps;
private _id = if (_feed isEqualType [] && {(count _feed) >= 4}) then {
    format ["%1:%2:%3", _feed # 1, _feed # 2, _feed # 3]
} else {
    ""
};

private _label = _feed param [0, "Unknown Feed"];
private _type = _feed param [1, ""];
private _grid = "--------";
private _alt = 0;
private _owner = "Unknown";
private _distance = -1;
private _quality = "LOST";
private _reason = "No source";
private _node = createHashMap;
private _hops = -1;
private _qValue = 0;

if (!isNull _obj && {alive _obj}) then {
    private _pos = getPosATL _obj;
    _grid = [_pos, 8] call Iceman_fnc_wr_formatGrid;
    _alt = round (_pos # 2);
    _distance = round (player distance _obj);
    _owner = switch (true) do {
        case (_obj isKindOf "CAManBase"): {name _obj};
        case ((count crew _obj) > 0): {name ((crew _obj) # 0)};
        default {
            private _cfgName = getText (configOf _obj >> "displayName");
            if (_cfgName == "") then {typeOf _obj} else {_cfgName}
        };
    };

    private _match = -1;
    if (_type == "helmet") then {
        _match = _nodes findIf {(_x getOrDefault ["unit", objNull]) isEqualTo _obj};
    };
    if (_match < 0) then {
        private _bestDistance = 1e10;
        {
            if ((_x getOrDefault ["hops", -1]) >= 0) then {
                private _candidate = _x getOrDefault ["unit", objNull];
                if (!isNull _candidate) then {
                    private _d = _candidate distance _obj;
                    if (_d < _bestDistance) then {
                        _bestDistance = _d;
                        _match = _forEachIndex;
                    };
                };
            };
        } forEach _nodes;
    };

    if (_match >= 0) then {
        _node = _nodes # _match;
        _hops = _node getOrDefault ["hops", -1];
        _qValue = _node getOrDefault ["quality", 0];
        private _throughput = _node getOrDefault ["throughput", _capacity];
        private _saturated = _subLoad > (_throughput max 1);

        if (_hops < 0) then {
            _quality = "LOST";
            _reason = "No Wave Relay path";
        } else {
            _quality = "GOOD";
            _reason = format ["%1 hop(s), Q%2%3", _hops, _qValue, "%"];
            if (_qValue < 55 || {_saturated}) then {
                _quality = "DEGRADED";
                _reason = if (_saturated) then {
                    format ["Bandwidth saturated: %1/%2 Mbps", _subLoad, round (_throughput * 10) / 10]
                } else {
                    format ["Weak link: Q%1%2", _qValue, "%"]
                };
            };
        };
    } else {
        _quality = "LOST";
        _reason = "No relay node near source";
    };
};

createHashMapFromArray [
    ["id", _id],
    ["label", _label],
    ["object", _obj],
    ["quality", _quality],
    ["reason", _reason],
    ["grid", _grid],
    ["alt", _alt],
    ["owner", _owner],
    ["distance", _distance],
    ["hops", _hops],
    ["qualityValue", _qValue],
    ["node", _node],
    ["lastUpdate", round CBA_missionTime]
]
