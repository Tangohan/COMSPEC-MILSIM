/*
    Applique les effets de la zone roleplay actuelle au joueur.
    Appelé périodiquement par PFH (~2 s).
*/

if (!hasInterface) exitWith {};

// Auto-activer le roleplay dès qu’il existe des zones (Zeus / Eden / portail)
private _zones = if (isNil "COMSPEC_RoleplayZones") then { [] } else { COMSPEC_RoleplayZones };
if (_zones isEqualType [] && {count _zones > 0}) then {
    if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_enabled", false])) then {
        missionNamespace setVariable ["comspec_overwatch_roleplay_enabled", true, false];
    };
    if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_visual_effects", true])) then {
        missionNamespace setVariable ["comspec_overwatch_roleplay_visual_effects", true, false];
    };
    if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_network_failures", true])) then {
        missionNamespace setVariable ["comspec_overwatch_roleplay_network_failures", true, false];
    };
};

if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_enabled", false])) exitWith {};

private _zone = [] call comspec_overwatch_connect_fnc_getPlayerRoleplayZone;
private _lastZone = missionNamespace getVariable ["COMSPEC_LastRoleplayZone", nil];

// Entrée / sortie
if (!isNil "_zone" && {isNil "_lastZone" || {(_zone get "id") isNotEqualTo (_lastZone get "id")}}) then {
    private _zoneName = _zone getOrDefault ["name", "Zone"];
    private _intensity = _zone getOrDefault ["intensity", 50];
    private _msg = format ["Entrée en %1 (intensité %2%%)", _zoneName, round _intensity];
    [_msg, "link", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    // Toast ATAK même en immersion (le hint écran est souvent masqué)
    if (!isNil "comspec_overwatch_connect_fnc_announce") then {
        [_msg, "link", "warn"] call comspec_overwatch_connect_fnc_announce;
    };
    ["warning"] call comspec_overwatch_connect_fnc_playRoleplaySound;
    missionNamespace setVariable ["COMSPEC_LastRoleplayZone", _zone, false];
    missionNamespace setVariable ["COMSPEC_InRoleplayZone", true, false];
    diag_log format ["[COMSPEC Roleplay] Joueur entré dans zone: %1", _zoneName];
};

if (isNil "_zone" && {!isNil "_lastZone"}) then {
    ["Sortie de zone dégradée — liaison nominale", "link", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    if (!isNil "comspec_overwatch_connect_fnc_announce") then {
        ["Sortie de zone dégradée", "link", "info"] call comspec_overwatch_connect_fnc_announce;
    };
    missionNamespace setVariable ["COMSPEC_LastRoleplayZone", nil, false];
    missionNamespace setVariable ["COMSPEC_InRoleplayZone", false, false];
    missionNamespace setVariable ["COMSPEC_JammerDropUntil", -1, false];
    [] call comspec_overwatch_connect_fnc_refreshLinkState;
    diag_log "[COMSPEC Roleplay] Joueur sorti de zone";
};

if (!isNil "_zone") then {
    private _type = toLower (_zone getOrDefault ["type", "degraded"]);
    private _intensity = (_zone getOrDefault ["intensity", 50]) max 0 min 100;
    private _zoneName = _zone getOrDefault ["name", "Zone"];

    // Perte de paquets « plancher » selon intensité (0–100 %), indépendante du compteur mort
    private _lossFloor = switch (_type) do {
        case "no_coverage": { 100 };
        case "jammer": { (_intensity * 0.85) max 25 };
        case "interference": { (_intensity * 0.70) max 15 };
        case "degraded": { (_intensity * 0.45) max 8 };
        default { _intensity * 0.3 };
    };

    // Chance de drop TX soft (hors force_disconnect)
    private _txDrop = switch (_type) do {
        case "no_coverage": { 100 };
        case "jammer": { (_intensity * 0.55) max 10 };
        case "interference": { (_intensity * 0.40) max 5 };
        case "degraded": { (_intensity * 0.20) max 0 };
        default { 0 };
    };

    private _forceDisconnect = false;
    private _latencyAdd = 0;

    switch (_type) do {
        case "no_coverage": {
            _forceDisconnect = true;
            _latencyAdd = 2000;
        };
        case "interference": {
            _latencyAdd = (_intensity / 100) * 800;
        };
        case "degraded": {
            _latencyAdd = (_intensity / 100) * 500;
        };
        case "jammer": {
            // Coupures intermittentes stables (cooldown), pas un random à chaque tick
            private _until = missionNamespace getVariable ["COMSPEC_JammerDropUntil", -1];
            if (time < _until) then {
                _forceDisconnect = true;
            } else {
                private _chance = (_intensity * 0.4) max 8; // 8–40 %
                if (random 100 < _chance) then {
                    private _dur = 1.5 + (_intensity / 100) * 5 + random 2;
                    missionNamespace setVariable ["COMSPEC_JammerDropUntil", time + _dur, false];
                    _forceDisconnect = true;
                };
            };
            _latencyAdd = (_intensity / 100) * 1200;

            // « Casse » : gel terminal si intensité forte (cooldown 40 s)
            if (
                _intensity >= 65
                && {missionNamespace getVariable ["comspec_overwatch_roleplay_network_failures", true]}
                && {time - (missionNamespace getVariable ["COMSPEC_ZoneCrashAt", -999]) > 40}
                && {random 100 < ((_intensity - 50) * 0.55)}
            ) then {
                missionNamespace setVariable ["COMSPEC_ZoneCrashAt", time, false];
                private _crashDur = 6 + (_intensity / 100) * 14;
                [_crashDur] call comspec_overwatch_connect_fnc_triggerAtakCrash;
            };
        };
    };

    private _zoneEffects = createHashMapFromArray [
        ["type", _type],
        ["intensity", _intensity],
        ["name", _zoneName],
        ["force_disconnect", _forceDisconnect],
        ["packet_loss_floor", _lossFloor],
        ["packet_loss_multiplier", 1 + (_intensity / 100)],
        ["tx_drop_chance", _txDrop],
        ["latency_add", _latencyAdd]
    ];
    missionNamespace setVariable ["COMSPEC_ZoneEffects", _zoneEffects, false];

    if (_forceDisconnect) then {
        missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    } else {
        [] call comspec_overwatch_connect_fnc_refreshLinkState;
    };

    // Effets visuels proportionnels à l’intensité (tous types sauf couverture nulle pure → plus fort)
    private _ppIntensity = switch (_type) do {
        case "no_coverage": { 0.95 };
        case "jammer": { (0.25 + (_intensity / 100) * 0.75) min 1 };
        case "interference": { (0.18 + (_intensity / 100) * 0.55) min 0.9 };
        case "degraded": { (0.10 + (_intensity / 100) * 0.35) min 0.6 };
        default { (_intensity / 100) * 0.4 };
    };
    if (_ppIntensity > 0.08 && {missionNamespace getVariable ["comspec_overwatch_roleplay_visual_effects", true]}) then {
        [_ppIntensity, 4, false] call comspec_overwatch_connect_fnc_applyRoleplayPpEffects;
    };
} else {
    missionNamespace setVariable ["COMSPEC_ZoneEffects", nil, false];
    [0, 0, true] call comspec_overwatch_connect_fnc_applyRoleplayPpEffects;
    [] call comspec_overwatch_connect_fnc_refreshLinkState;
};

