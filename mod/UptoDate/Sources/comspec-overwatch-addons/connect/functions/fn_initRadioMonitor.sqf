/*
    Initialise la surveillance radio (ACRE/TFAR optionnels) :
    - cache des radios distantes via événements ACRE
    - boucle légère de proximité → missionNamespace pour tablette / UI
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_RadioMonitorInited", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_RadioMonitorInited", true, false];
missionNamespace setVariable ["COMSPEC_RemoteRadioCache", createHashMap, false];
missionNamespace setVariable ["COMSPEC_RadioProximityList", [], false];
missionNamespace setVariable ["COMSPEC_RadioModuleOk", false, false];

private _moduleOk = isClass (configFile >> "CfgPatches" >> "acre_main")
    || isClass (configFile >> "CfgPatches" >> "tfar_core");
missionNamespace setVariable ["COMSPEC_RadioModuleOk", _moduleOk, false];

// Cache canal / radioId des émetteurs distants (ACRE)
if (isClass (configFile >> "CfgPatches" >> "acre_main")) then {
    if (isNil "COMSPEC_acreRemoteSpeakEH") then {
        COMSPEC_acreRemoteSpeakEH = ["acre_remoteStartedSpeaking", {
            params ["_unit", "_speakingType", "_radioId"];
            if (isNull _unit) exitWith {};
            private _cache = missionNamespace getVariable ["COMSPEC_RemoteRadioCache", createHashMap];
            private _uid = getPlayerUID _unit;
            if (_uid == "") then { _uid = str _unit; };
            private _channel = "";
            private _freq = "";
            if (_radioId isEqualType "" && {_radioId != ""} && {!isNil "acre_api_fnc_getRadioChannel"}) then {
                _channel = str ([_radioId] call acre_api_fnc_getRadioChannel);
            };
            if (_radioId isEqualType "" && {_radioId != ""} && {!isNil "acre_api_fnc_getChannelData"}) then {
                private _data = [_radioId] call acre_api_fnc_getChannelData;
                if (!isNil "_data" && {_data isEqualType []} && {(count _data) > 0}) then {
                    _freq = str (_data select 0);
                };
            };
            _cache set [_uid, [_radioId, _channel, _freq, diag_tickTime, _speakingType]];
            missionNamespace setVariable ["COMSPEC_RemoteRadioCache", _cache, false];
        }] call CBA_fnc_addEventHandler;
    };
};

if (!(missionNamespace getVariable ["comspec_overwatch_radio_proximity_enabled", true])) exitWith {};

private _interval = missionNamespace getVariable ["comspec_overwatch_radio_proximity_interval", 2];
if (!(_interval isEqualType 0)) then { _interval = 2; };
_interval = (_interval max 1) min 15;

[{
    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
    if (!(missionNamespace getVariable ["comspec_overwatch_radio_proximity_enabled", true])) exitWith {};

    private _list = [] call comspec_overwatch_connect_fnc_scanRadioProximity;
    missionNamespace setVariable ["COMSPEC_RadioProximityList", _list, false];

    private _txCount = {
        (_x getOrDefault ["tx", false]) || {_x getOrDefault ["speaking", false]}
    } count _list;
    missionNamespace setVariable ["COMSPEC_RadioProximityTxCount", _txCount, false];
}, _interval, []] call CBA_fnc_addPerFrameHandler;
