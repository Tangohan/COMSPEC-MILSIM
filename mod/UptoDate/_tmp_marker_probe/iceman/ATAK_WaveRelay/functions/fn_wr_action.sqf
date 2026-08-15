params [["_slot", 0]];

private _state = call Iceman_fnc_wr_getState;
private _tab = _state getOrDefault ["tab", "home"];
private _selection = _state getOrDefault ["selection", 0];
call Iceman_fnc_wr_readUi;

if !([player] call Iceman_fnc_wr_hasRadio) exitWith {
    call Iceman_fnc_wr_updatePanel;
    true
};

private _toggleGateway = {
    private _newValue = !((call Iceman_fnc_wr_getState) getOrDefault ["gateway", false]);
    (call Iceman_fnc_wr_getState) set ["gateway", _newValue];
    player setVariable ["Iceman_WR_gateway", _newValue, true];
    call Iceman_fnc_wr_saveState;
    ["WAVE RELAY", ["Gateway disabled.", "Gateway enabled."] select _newValue, 3] call cTab_fnc_addNotification;
};

switch (_tab) do {
    case "talkgroups": {
        private _tg = (_selection + 1) max 1 min 16;
        switch (_slot) do {
            case 0: {
                private _targetSlot = round (_state getOrDefault ["txEditSlot", 1]);
                _targetSlot = _targetSlot max 1 min 4;
                private _currentSlot = [_tg] call Iceman_fnc_wr_txSlotForTg;
                private _newSlot = if (_currentSlot == _targetSlot) then {0} else {_targetSlot};
                [_tg, _newSlot] call Iceman_fnc_wr_setTxSlot;
                private _assignedSlot = [_tg] call Iceman_fnc_wr_txSlotForTg;
                private _acreApplied = false;
                if (_assignedSlot > 0) then {
                    _acreApplied = [_tg] call Iceman_fnc_wr_applyAcreTalkgroup;
                };
                private _message = if (_assignedSlot > 0) then {
                    format ["TG-%1 assigned to TX%2%3.", _tg, _assignedSlot, ["", " and MPU-5 ACRE channel updated"] select _acreApplied]
                } else {
                    format ["TG-%1 removed from TX keybinds.", _tg]
                };
                ["WAVE RELAY", _message, 3] call cTab_fnc_addNotification;
            };
            case 1: {
                private _targetSlot = round (_state getOrDefault ["txEditSlot", 1]);
                _targetSlot = _targetSlot + 1;
                if (_targetSlot > 4) then {_targetSlot = 1};
                _state set ["txEditSlot", _targetSlot];
                call Iceman_fnc_wr_saveState;
                ["WAVE RELAY", format ["Target TX slot set to TX%1.", _targetSlot], 2] call cTab_fnc_addNotification;
            };
            case 2: {
                ["monitor", _tg] call Iceman_fnc_wr_toggleTg;
                ["WAVE RELAY", format ["Monitor list updated for TG-%1.", _tg], 3] call cTab_fnc_addNotification;
            };
            case 3: {
                private _ear = [_tg] call Iceman_fnc_wr_cycleMonitorEar;
                call Iceman_fnc_wr_readUi;
                ["WAVE RELAY", format ["TG-%1 monitor audio set to %2.", _tg, _ear], 3] call cTab_fnc_addNotification;
            };
            case 4: {
                private _volume = [_tg, -0.25] call Iceman_fnc_wr_adjustMonitorVolume;
                call Iceman_fnc_wr_readUi;
                ["WAVE RELAY", format ["TG-%1 monitor volume %2%3.", _tg, round (_volume * 100), "%"], 2] call cTab_fnc_addNotification;
            };
            case 5: {
                private _volume = [_tg, 0.25] call Iceman_fnc_wr_adjustMonitorVolume;
                call Iceman_fnc_wr_readUi;
                ["WAVE RELAY", format ["TG-%1 monitor volume %2%3.", _tg, round (_volume * 100), "%"], 2] call cTab_fnc_addNotification;
            };
        };
        call Iceman_fnc_wr_saveState;
    };
    case "diag": {
        switch (_slot) do {
            case 0: {call Iceman_fnc_wr_saveProfile};
            case 1: {[_selection] call Iceman_fnc_wr_loadProfile};
            case 2: {call Iceman_fnc_wr_deleteProfile};
        };
    };
    case "feeds": {
        private _feeds = _state getOrDefault ["lastFeeds", []];
        if (_selection >= 0 && {_selection < count _feeds}) then {
            private _feed = _feeds # _selection;
            switch (_slot) do {
                case 0: {
                    [_feed, false] call Iceman_fnc_wr_selectFeed;
                };
                case 1: {
                    [_feed] call Iceman_fnc_wr_sendFeedToToc;
                };
                case 2: {
                    [_feed, true] call Iceman_fnc_wr_selectFeed;
                };
                case 3: {
                private _id = format ["%1:%2:%3", _feed # 1, _feed # 2, _feed # 3];
                private _subscriptions = +(_state getOrDefault ["subscriptions", []]);
                private _subscribed = _id in _subscriptions;
                if (_subscribed) then {
                    _subscriptions = _subscriptions - [_id];
                } else {
                    _subscriptions pushBackUnique _id;
                };
                _state set ["subscriptions", _subscriptions];
                call Iceman_fnc_wr_saveState;
                ["WAVE RELAY", format ["%1 %2.", ["Subscribed to", "Unsubscribed from"] select _subscribed, _feed # 0], 3] call cTab_fnc_addNotification;
                };
            };
        };
    };
    case "pli": {
        if (_slot == 0) then {
            call Iceman_fnc_wr_locateSelected;
        };
        if (_slot == 2) then {
            call _toggleGateway;
        };
    };
    default {
        if (_slot == 1) then {
            call _toggleGateway;
        };
        if (_slot == 2) then {
            call Iceman_fnc_wr_locateSelected;
        };
    };
};

call Iceman_fnc_wr_updatePanel;
true
