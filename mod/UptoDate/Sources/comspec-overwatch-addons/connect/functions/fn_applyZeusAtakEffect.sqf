/*
    Applique un effet ATAK forcé par Zeus (côté client cible).
    Params: [_action, _durationSec]
    Actions: power_off | screen_break | device_destroy | crash | jam | repair | clear
*/
params [
    ["_action", "", [""]],
    ["_durationSec", 30, [0]]
];

if (!hasInterface) exitWith {};
if (!alive player) exitWith {};

_action = toLower _action;
_durationSec = (_durationSec max 5) min 600;

private _atakState = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
if (!(_atakState isEqualType createHashMap) || {_atakState isEqualTo createHashMap}) then {
    _atakState = createHashMapFromArray [
        ["powered_on", true],
        ["screen_destroyed", false],
        ["device_destroyed", false],
        ["device_crashed", false]
    ];
};

switch (_action) do {
    case "power_off": {
        _atakState set ["powered_on", false];
        missionNamespace setVariable ["COMSPEC_AtakState", _atakState, false];
        ["ATAK éteint (Zeus)", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
        playSound "addItemFailed";
    };

    case "screen_break": {
        _atakState set ["screen_destroyed", true];
        _atakState set ["powered_on", false];
        missionNamespace setVariable ["COMSPEC_AtakState", _atakState, false];
        player setVariable ["COMSPEC_AtakState", _atakState, true];
        ["Écran ATAK endommagé (Zeus)", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
        playSound "FD_CP_Not_Clear_F";
        [0.55, 8, false] call comspec_overwatch_connect_fnc_applyRoleplayPpEffects;
    };

    case "device_destroy": {
        _atakState set ["device_destroyed", true];
        _atakState set ["screen_destroyed", true];
        _atakState set ["powered_on", false];
        missionNamespace setVariable ["COMSPEC_AtakState", _atakState, false];
        player setVariable ["COMSPEC_AtakState", _atakState, true];
        missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
        ["ATAK hors service — liaison coupée (Zeus)", "system", "critical"] call comspec_overwatch_connect_fnc_ambientHint;
        playSound "FD_CP_Not_Clear_F";
        [0.9, 12, false] call comspec_overwatch_connect_fnc_applyRoleplayPpEffects;
    };

    case "crash": {
        [_durationSec] call comspec_overwatch_connect_fnc_triggerAtakCrash;
    };

    case "jam": {
        if (isNil {missionNamespace getVariable "COMSPEC_NetworkDisconnectState"}) then {
            missionNamespace setVariable ["COMSPEC_NetworkDisconnectState", createHashMap, false];
        };
        private _net = missionNamespace getVariable ["COMSPEC_NetworkDisconnectState", createHashMap];
        _net set ["is_disconnected", true];
        _net set ["disconnect_until", time + _durationSec];
        _net set ["disconnect_count", (_net getOrDefault ["disconnect_count", 0]) + 1];
        missionNamespace setVariable ["COMSPEC_NetworkDisconnectState", _net, false];
        missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];

        private _zoneFx = createHashMapFromArray [
            ["type", "jammer"],
            ["intensity", 90],
            ["name", "Brouillage Zeus"],
            ["packet_loss_multiplier", 2.5]
        ];
        missionNamespace setVariable ["COMSPEC_ZoneEffects", _zoneFx, false];
        missionNamespace setVariable ["COMSPEC_InRoleplayZone", true, false];
        missionNamespace setVariable ["comspec_overwatch_roleplay_enabled", true, false];
        missionNamespace setVariable ["comspec_overwatch_roleplay_visual_effects", true, false];

        [format ["Brouillage ATAK actif (%1s) — Zeus", round _durationSec], "link", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
        ["disconnect"] call comspec_overwatch_connect_fnc_playRoleplaySound;
        [0.7, _durationSec min 20, false] call comspec_overwatch_connect_fnc_applyRoleplayPpEffects;

        [{
            private _net = missionNamespace getVariable ["COMSPEC_NetworkDisconnectState", createHashMap];
            if (!(_net getOrDefault ["is_disconnected", false])) exitWith {};
            if (time < (_net getOrDefault ["disconnect_until", -1])) exitWith {};
            _net set ["is_disconnected", false];
            _net set ["disconnect_until", -1];
            missionNamespace setVariable ["COMSPEC_NetworkDisconnectState", _net, false];
            missionNamespace setVariable ["COMSPEC_ZoneEffects", nil, false];
            missionNamespace setVariable ["COMSPEC_InRoleplayZone", false, false];
            missionNamespace setVariable ["COMSPEC_LinkState", "linked", false];
            [0, 0, true] call comspec_overwatch_connect_fnc_applyRoleplayPpEffects;
            ["Liaison ATAK rétablie", "link", "info"] call comspec_overwatch_connect_fnc_ambientHint;
            ["reconnect"] call comspec_overwatch_connect_fnc_playRoleplaySound;
        }, [], _durationSec + 0.5] call CBA_fnc_waitAndExecute;
    };

    case "capture";
    case "compromise": {
        private _state = if (_action isEqualTo "compromise") then { "compromised" } else { "captured" };
        missionNamespace setVariable ["COMSPEC_CompromiseState", _state, false];
        player setVariable ["COMSPEC_CompromiseState", _state, true];
        ["Appareil capturé — clé incorrecte", "system", "critical"] call comspec_overwatch_connect_fnc_ambientHint;
        playSound "FD_CP_Not_Clear_F";
        [0.45, 6, false] call comspec_overwatch_connect_fnc_applyRoleplayPpEffects;
        [] call comspec_overwatch_connect_fnc_syncTerminalCompromise;
    };

    case "clear_compromise": {
        missionNamespace setVariable ["COMSPEC_CompromiseState", "none", false];
        player setVariable ["COMSPEC_CompromiseState", "none", true];
        ["Contrôle appareil rétabli", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
        playSound "FD_CP_Clear_F";
        [] call comspec_overwatch_connect_fnc_syncTerminalCompromise;
    };

    case "repair";
    case "clear": {
        _atakState set ["powered_on", true];
        _atakState set ["screen_destroyed", false];
        _atakState set ["device_destroyed", false];
        _atakState set ["device_crashed", false];
        _atakState set ["crash_until", -1];
        missionNamespace setVariable ["COMSPEC_AtakState", _atakState, false];
        player setVariable ["COMSPEC_AtakState", _atakState, true];
        missionNamespace setVariable ["COMSPEC_CompromiseState", "none", false];
        player setVariable ["COMSPEC_CompromiseState", "none", true];

        if (isNil {missionNamespace getVariable "COMSPEC_NetworkDisconnectState"}) then {
            missionNamespace setVariable ["COMSPEC_NetworkDisconnectState", createHashMap, false];
        };
        private _net = missionNamespace getVariable ["COMSPEC_NetworkDisconnectState", createHashMap];
        _net set ["is_disconnected", false];
        _net set ["disconnect_until", -1];
        missionNamespace setVariable ["COMSPEC_NetworkDisconnectState", _net, false];
        missionNamespace setVariable ["COMSPEC_ZoneEffects", nil, false];
        missionNamespace setVariable ["COMSPEC_InRoleplayZone", false, false];
        missionNamespace setVariable ["COMSPEC_LinkState", "linked", false];
        [0, 0, true] call comspec_overwatch_connect_fnc_applyRoleplayPpEffects;
        ["ATAK rétabli (Zeus)", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
        playSound "FD_CP_Clear_F";
        [] call comspec_overwatch_connect_fnc_syncTerminalCompromise;
    };

    default {
        diag_log format ["[COMSPEC] Zeus ATAK action inconnue: %1", _action];
    };
};

player setVariable ["COMSPEC_AtakState", missionNamespace getVariable ["COMSPEC_AtakState", createHashMap], true];
player setVariable ["COMSPEC_LinkState", missionNamespace getVariable ["COMSPEC_LinkState", "offline"], true];
