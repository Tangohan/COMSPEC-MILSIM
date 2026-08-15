private _state = call Iceman_fnc_roip_getState;
private _radios = call Iceman_fnc_roip_getRadios;
_state set ["lastRadios", _radios];

private _connectedId = _state getOrDefault ["connectedRadioId", ""];
if (_connectedId != "") then {
    private _mpu5 = "";
    if !(isNil "acre_api_fnc_getRadioByType") then {
        private _candidate = ["ACRE_MPU5"] call acre_api_fnc_getRadioByType;
        if (!(isNil "_candidate") && {_candidate isEqualType ""}) then {_mpu5 = _candidate};
    };

    private _radioIndex = _radios findIf {(_x # 0) == _connectedId};
    if (_mpu5 == "") then {
        [true, "MPU-5 removed; ROIP link dropped."] call Iceman_fnc_roip_disconnect;
        ["ROIP", "MPU-5 removed; link dropped.", 3] call Iceman_fnc_roip_notify;
    } else {
        if (_radioIndex < 0) then {
            [true, "Connected legacy radio removed; ROIP link dropped."] call Iceman_fnc_roip_disconnect;
            ["ROIP", "Connected legacy radio removed; link dropped.", 3] call Iceman_fnc_roip_notify;
        } else {
            private _record = _radios # _radioIndex;
            if !(_record # 4) then {
                [true, "Connected legacy radio powered off; ROIP link dropped."] call Iceman_fnc_roip_disconnect;
                ["ROIP", "Connected legacy radio powered off; link dropped.", 3] call Iceman_fnc_roip_notify;
            } else {
                private _tg = (_state getOrDefault ["connectedTalkgroup", 1]) max 1 min 16;
                private _link = [_record, _tg] call Iceman_fnc_roip_buildLink;
                if (_link isNotEqualTo []) then {
                    private _publishSignature = str [_link # 1, _link # 2, _link # 3, _link # 4, _link # 5, _link # 6, _link # 7, _link # 8, _link # 12];
                    private _previousSignature = _state getOrDefault ["lastPublishedSignature", ""];
                    if (_publishSignature != _previousSignature) then {
                        private _wasPublished = _previousSignature != "";
                        _state set ["lastPublishedSignature", _publishSignature];
                        _state set ["localLink", _link];
                        _state set ["selectedRadioId", _record # 0];

                        player setVariable ["Iceman_ROIP_active", true, true];
                        player setVariable ["Iceman_ROIP_link", _link, true];
                        player setVariable ["Iceman_WR_bridgeActive", true, true];
                        player setVariable ["Iceman_Bridge_activeRecordId", _link # 1, true];
                        player setVariable ["Iceman_Bridge_activeRadioClass", _link # 5, true];
                        player setVariable ["Iceman_Bridge_activeChannelIndex", (_link # 6) - 1, true];
                        player setVariable ["Iceman_Bridge_activeOwner", _link # 9, true];

                        _state set ["appliedSignature", "__LINK_CHANGED__"];
                        if (_wasPublished) then {
                            ["ROIP", format ["Link followed %1 to CH%2 - %3.", ["PRC-117F", "PRC-152"] select ((_link # 5) == "ACRE_PRC152"), _link # 6, _link # 7], 3] call Iceman_fnc_roip_notify;
                        };
                    };
                };
            };
        };
    };
};

call Iceman_fnc_roip_applyLinks;

private _page = uiNamespace getVariable ["Iceman_ATAK_ROIP_group", controlNull];
if (!isNull _page) then {call Iceman_fnc_roip_updatePanel};
true
