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

private _fnc_zeusActionLabel = {
    params ["_act"];
    switch (_act) do {
        case "power_off": { "Éteindre l’ATAK" };
        case "screen_break": { "Casser l’écran" };
        case "device_destroy": { "Détruire l’appareil" };
        case "crash": { "Gel / crash terminal" };
        case "jam": { "Brouiller la liaison" };
        case "capture": { "Capturer l’appareil" };
        case "compromise": { "Compromettre l’appareil" };
        case "clear_compromise": { "Lever capture / compromission" };
        case "repair";
        case "clear": { "Réparer / rétablir" };
        default { _act };
    };
};

private _fnc_logZeus = {
    params ["_act", "_dur"];
    private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
    if (_cs isEqualTo "") then { _cs = name player; };
    [
        "INFO",
        "Zeus",
        format ["%1 — %2", [_act] call _fnc_zeusActionLabel, _cs],
        "system",
        if (_dur > 0) then { format ["durée=%1 s", round _dur] } else { nil }
    ] call comspec_overwatch_connect_fnc_logAtakEvent;
    [true] call comspec_overwatch_connect_fnc_logAtakStateChange;
};

private _fnc_refreshUi = {
    missionNamespace setVariable ["comspec_overwatch_roleplay_visual_effects", true, false];
    [] call comspec_overwatch_connect_fnc_updateDeviceOverlay;
    if (!isNull (uiNamespace getVariable ["COMSPEC_Hub_Display", displayNull])) then {
        [] call comspec_overwatch_connect_fnc_updateAtakEnhancedRoleplay;
    };
};

private _atakState = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
if (!(_atakState isEqualType createHashMap) || {_atakState isEqualTo createHashMap}) then {
    _atakState = createHashMapFromArray [
        ["powered_on", true],
        ["screen_destroyed", false],
        ["device_destroyed", false],
        ["device_crashed", false]
    ];
};

private _zeusApplied = false;

switch (_action) do {
    case "power_off": {
        _zeusApplied = true;
        _atakState set ["powered_on", false];
        missionNamespace setVariable ["COMSPEC_AtakState", _atakState, false];
        player setVariable ["COMSPEC_AtakState", _atakState, true];
        ["ATAK éteint (Zeus)", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
        ["disconnect"] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
        call _fnc_refreshUi;
    };

    case "screen_break": {
        _zeusApplied = true;
        _atakState set ["screen_destroyed", true];
        missionNamespace setVariable ["COMSPEC_AtakState", _atakState, false];
        player setVariable ["COMSPEC_AtakState", _atakState, true];
        ["Écran ATAK endommagé (Zeus)", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
        ["disconnect"] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
        [0.55, 8, false] call comspec_overwatch_connect_fnc_applyRoleplayPpEffects;
        call _fnc_refreshUi;
    };

    case "device_destroy": {
        _zeusApplied = true;
        _atakState set ["device_destroyed", true];
        _atakState set ["screen_destroyed", true];
        _atakState set ["powered_on", false];
        missionNamespace setVariable ["COMSPEC_AtakState", _atakState, false];
        player setVariable ["COMSPEC_AtakState", _atakState, true];
        missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
        ["ATAK hors service — liaison coupée (Zeus)", "system", "critical"] call comspec_overwatch_connect_fnc_ambientHint;
        ["disconnect"] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
        [0.9, 12, false] call comspec_overwatch_connect_fnc_applyRoleplayPpEffects;
        call _fnc_refreshUi;
    };

    case "crash": {
        _zeusApplied = true;
        [_durationSec] call comspec_overwatch_connect_fnc_triggerAtakCrash;
        call _fnc_refreshUi;
    };

    case "jam": {
        _zeusApplied = true;
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
        call _fnc_refreshUi;

        [{
            private _net = missionNamespace getVariable ["COMSPEC_NetworkDisconnectState", createHashMap];
            if (!(_net getOrDefault ["is_disconnected", false])) exitWith {};
            if (time < (_net getOrDefault ["disconnect_until", -1])) exitWith {};
            _net set ["is_disconnected", false];
            _net set ["disconnect_until", -1];
            missionNamespace setVariable ["COMSPEC_NetworkDisconnectState", _net, false];
            missionNamespace setVariable ["COMSPEC_ZoneEffects", nil, false];
            missionNamespace setVariable ["COMSPEC_InRoleplayZone", false, false];
            [0, 0, true] call comspec_overwatch_connect_fnc_applyRoleplayPpEffects;
            [] call comspec_overwatch_connect_fnc_updateDeviceOverlay;
            [] call comspec_overwatch_connect_fnc_refreshLinkState;
            ["Liaison ATAK rétablie", "link", "info"] call comspec_overwatch_connect_fnc_ambientHint;
            ["reconnect"] call comspec_overwatch_connect_fnc_playRoleplaySound;
            ["INFO", "Zeus", "Fin du brouillage — liaison rétablie", "system"] call comspec_overwatch_connect_fnc_logAtakEvent;
            [true] call comspec_overwatch_connect_fnc_logAtakStateChange;
        }, [], _durationSec + 0.5] call CBA_fnc_waitAndExecute;
    };

    case "capture";
    case "compromise": {
        _zeusApplied = true;
        private _state = if (_action isEqualTo "compromise") then { "compromised" } else { "captured" };
        missionNamespace setVariable ["COMSPEC_CompromiseState", _state, false];
        player setVariable ["COMSPEC_CompromiseState", _state, true];
        ["Appareil capturé — clé incorrecte", "system", "critical"] call comspec_overwatch_connect_fnc_ambientHint;
        ["disconnect"] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
        [0.45, 6, false] call comspec_overwatch_connect_fnc_applyRoleplayPpEffects;
        [] call comspec_overwatch_connect_fnc_syncTerminalCompromise;
        call _fnc_refreshUi;
    };

    case "clear_compromise": {
        _zeusApplied = true;
        missionNamespace setVariable ["COMSPEC_CompromiseState", "none", false];
        player setVariable ["COMSPEC_CompromiseState", "none", true];
        ["Contrôle appareil rétabli", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
        ["reconnect"] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
        [] call comspec_overwatch_connect_fnc_syncTerminalCompromise;
        call _fnc_refreshUi;
    };

    case "repair";
    case "clear": {
        _zeusApplied = true;
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
        [0, 0, true] call comspec_overwatch_connect_fnc_applyRoleplayPpEffects;
        ["ATAK rétabli (Zeus)", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
        ["reconnect"] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
        [] call comspec_overwatch_connect_fnc_syncTerminalCompromise;
        [] call comspec_overwatch_connect_fnc_refreshLinkState;
        call _fnc_refreshUi;
    };

    default {
        ["WARN", "Zeus", format ["Action inconnue : %1", _action], "system"] call comspec_overwatch_connect_fnc_logAtakEvent;
        diag_log format ["[COMSPEC] Zeus ATAK action inconnue: %1", _action];
    };
};

if (_zeusApplied) then {
    [_action, _durationSec] call _fnc_logZeus;
};

player setVariable ["COMSPEC_AtakState", missionNamespace getVariable ["COMSPEC_AtakState", createHashMap], true];
player setVariable ["COMSPEC_LinkState", missionNamespace getVariable ["COMSPEC_LinkState", "offline"], true];
