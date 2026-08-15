private _hasMpu5 = false;
if !(isNil "acre_api_fnc_getRadioByType") then {
    private _mpu5 = ["ACRE_MPU5"] call acre_api_fnc_getRadioByType;
    _hasMpu5 = !(isNil "_mpu5") && {_mpu5 isEqualType ""} && {_mpu5 != ""};
};
if (!_hasMpu5) exitWith {[]};

private _wrState = if !(isNil "Iceman_fnc_wr_getState") then {call Iceman_fnc_wr_getState} else {createHashMap};
private _localBank = _wrState getOrDefault ["frequency", player getVariable ["Iceman_WR_frequency", "32.0"]];
private _localBankNumber = if (_localBank isEqualType 0) then {_localBank} else {parseNumber _localBank};

private _nodes = if !(isNil "Iceman_fnc_wr_collectNodes") then {call Iceman_fnc_wr_collectNodes} else {[]};
private _candidates = [];

{
    private _owner = _x;
    private _link = _owner getVariable ["Iceman_ROIP_link", []];
    if (
        alive _owner &&
        {_owner getVariable ["Iceman_ROIP_active", false]} &&
        {_link isEqualType []} &&
        {(count _link) >= 13}
    ) then {
        private _linkBank = _link # 2;
        private _linkBankNumber = if (_linkBank isEqualType 0) then {_linkBank} else {parseNumber _linkBank};
        if (abs (_linkBankNumber - _localBankNumber) <= 0.001) then {
            private _hops = -1;
            private _quality = 0;
            private _reachable = _owner isEqualTo player;

            if (!_reachable) then {
                private _nodeIndex = _nodes findIf {(_x getOrDefault ["unit", objNull]) isEqualTo _owner};
                if (_nodeIndex >= 0) then {
                    private _node = _nodes # _nodeIndex;
                    _hops = _node getOrDefault ["hops", -1];
                    _quality = _node getOrDefault ["quality", 0];
                    _reachable = _hops >= 0;
                };
            } else {
                _hops = 0;
                _quality = 100;
            };

            if (_reachable) then {
                _candidates pushBack [+_link, _owner, _hops, _quality];
            };
        };
    };
} forEach allPlayers;

_candidates = [_candidates, [], {(_x # 0) # 1}, "ASCEND"] call BIS_fnc_sortBy;

private _selected = [];
private _usedTalkgroups = [];
{
    private _tg = (_x # 0) # 3;
    if !(_tg in _usedTalkgroups) then {
        _usedTalkgroups pushBack _tg;
        _selected pushBack _x;
    };
} forEach _candidates;

_selected
