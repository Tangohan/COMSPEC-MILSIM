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

// Cache canal / radioId des émetteurs distants (ACRE) — lecture seule.
// Ne jamais lever d’erreur dans cet EH : un plantage pourrait empêcher
// d’autres handlers ACRE / CBA (sons, gestes) de s’exécuter.
if (isClass (configFile >> "CfgPatches" >> "acre_main")) then {
    if (isNil "COMSPEC_acreRemoteSpeakEH") then {
        COMSPEC_acreRemoteSpeakEH = ["acre_remoteStartedSpeaking", {
            params ["_unit", ["_speakingType", 0], ["_radioId", ""]];
            if (isNull _unit) exitWith {};
            private _cache = missionNamespace getVariable ["COMSPEC_RemoteRadioCache", createHashMap];
            if (!(_cache isEqualType createHashMap)) then { _cache = createHashMap; };
            private _uid = getPlayerUID _unit;
            if (_uid == "") then { _uid = str _unit; };
            private _channel = "";
            private _freq = "";
            if (_radioId isEqualType "" && {_radioId != ""}) then {
                if (!isNil "acre_api_fnc_getRadioChannel") then {
                    private _ch = [_radioId] call acre_api_fnc_getRadioChannel;
                    if (!isNil "_ch") then { _channel = str _ch; };
                };
                if (!isNil "acre_api_fnc_getChannelData") then {
                    private _data = [_radioId] call acre_api_fnc_getChannelData;
                    // ACRE peut renvoyer un tableau ou un hashmap selon version.
                    if (!isNil "_data") then {
                        if (_data isEqualType [] && {(count _data) > 0}) then {
                            _freq = str (_data select 0);
                        };
                        if (_data isEqualType createHashMap) then {
                            private _f = _data getOrDefault ["frequencyTX", _data getOrDefault ["frequency", ""]];
                            if (!(_f isEqualTo "")) then { _freq = str _f; };
                        };
                    };
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
