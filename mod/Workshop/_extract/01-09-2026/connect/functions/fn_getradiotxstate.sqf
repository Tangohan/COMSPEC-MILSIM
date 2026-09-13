/*
    État radio enrichi pour BFT / proximité.
    Retourne : [net, freq, channel, speaking, tx, radioId, moduleOk]
    - net : "ACRE" | "TFAR" | "none"
    - speaking : voix active (radio ou direct si ACRE isSpeaking)
    - tx : émission radio (ACRE isBroadcasting ; TFAR ≈ speaking)
    Dépendances optionnelles ACRE2 / TFAR — jamais d’erreur fatale si absents.
*/
params [["_unit", objNull]];

private _net = "none";
private _freq = "";
private _channel = "";
private _speaking = false;
private _tx = false;
private _radioId = "";
private _moduleOk = false;

if (isNull _unit) exitWith { [_net, _freq, _channel, _speaking, _tx, _radioId, _moduleOk] };

private _isLocal = local _unit;

if (isClass (configFile >> "CfgPatches" >> "acre_main")) then {
    _moduleOk = true;
    _net = "ACRE";

    if (!isNil "acre_api_fnc_isBroadcasting") then {
        _tx = [_unit] call acre_api_fnc_isBroadcasting;
    };
    if (!isNil "acre_api_fnc_isSpeaking") then {
        _speaking = [_unit] call acre_api_fnc_isSpeaking;
    };
    if (_tx) then { _speaking = true; };

    // Canal / fréq. : radio active locale, sinon cache événement distant
    if (_isLocal && {!isNil "acre_api_fnc_getCurrentRadioList"}) then {
        private _radios = [] call acre_api_fnc_getCurrentRadioList;
        if ((count _radios) > 0) then {
            _radioId = _radios select 0;
            if (!isNil "acre_api_fnc_getCurrentRadio") then {
                private _cur = [] call acre_api_fnc_getCurrentRadio;
                if (_cur isEqualType "" && {_cur != ""}) then { _radioId = _cur; };
            };
            if (!isNil "acre_api_fnc_getRadioChannel") then {
                _channel = str ([_radioId] call acre_api_fnc_getRadioChannel);
            };
            if (!isNil "acre_api_fnc_getCurrentRadioChannelNumber") then {
                private _chNum = [] call acre_api_fnc_getCurrentRadioChannelNumber;
                if (!isNil "_chNum" && {_chNum >= 0}) then { _channel = str _chNum; };
            };
            if (!isNil "acre_api_fnc_getChannelData" && {_channel != ""}) then {
                private _data = [_radioId] call acre_api_fnc_getChannelData;
                if (!isNil "_data" && {_data isEqualType []} && {(count _data) > 0}) then {
                    _freq = str (_data select 0);
                };
            };
        };
    } else {
        private _cache = missionNamespace getVariable ["COMSPEC_RemoteRadioCache", createHashMap];
        private _uid = getPlayerUID _unit;
        if (_uid == "") then { _uid = str _unit; };
        private _entry = _cache getOrDefault [_uid, []];
        if ((count _entry) >= 3) then {
            _radioId = _entry select 0;
            _channel = str (_entry select 1);
            _freq = str (_entry select 2);
        };
    };
} else {
    if (isClass (configFile >> "CfgPatches" >> "tfar_core")) then {
        _moduleOk = true;
        _net = "TFAR";
        _speaking = _unit getVariable ["tf_isSpeaking", false];
        _tx = _speaking;
        if (_isLocal) then {
            private _radio = _unit call TFAR_fnc_activeSwRadio;
            if (!isNil "_radio" && {_radio != ""}) then {
                _radioId = str _radio;
                private _f = _unit call TFAR_fnc_getCurrentSwFrequency;
                if (!isNil "_f") then { _freq = str _f; };
                _channel = "0";
            };
        };
    };
};

[_net, _freq, _channel, _speaking, _tx, _radioId, _moduleOk]
