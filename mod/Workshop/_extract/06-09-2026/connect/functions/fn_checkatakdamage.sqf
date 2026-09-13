/*
    Vérifie l'état de l'ATAK selon les blessures, chocs et environnement.
    3 niveaux : éteint temporaire | écran endommagé | appareil détruit
*/

if (!hasInterface) exitWith {};
if (!alive player) exitWith {};

private _realism = missionNamespace getVariable ["comspec_overwatch_atak_realism", 0];
if (_realism == 0) exitWith {};

private _atakState = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
if (_atakState isEqualTo createHashMap) then {
    _atakState = createHashMapFromArray [
        ["powered_on", true],
        ["screen_destroyed", false],
        ["device_destroyed", false],
        ["last_check", time]
    ];
    missionNamespace setVariable ["COMSPEC_AtakState", _atakState, false];
};

if (_atakState getOrDefault ["device_destroyed", false]) exitWith {};

// Choc / explosion récente (définie par EH Hit / Explosion)
private _impact = missionNamespace getVariable ["COMSPEC_LastAtakImpact", 0];
if (_impact > 0.25) then {
    missionNamespace setVariable ["COMSPEC_LastAtakImpact", 0, false];
    if (_realism >= 2 && {random 100 < (_impact * 50)}) then {
        _atakState set ["screen_destroyed", true];
        ["Écran ATAK endommagé par choc", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
        ["disconnect"] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
        [
            "WARN",
            "Terminal",
            "Écran endommagé par choc",
            "system",
            format ["intensité choc=%1", (_impact toFixed 2)]
        ] call comspec_overwatch_connect_fnc_logAtakEvent;
        [true] call comspec_overwatch_connect_fnc_logAtakStateChange;
    } else {
        if (_realism >= 1 && {random 100 < (_impact * 40)}) then {
            _atakState set ["powered_on", false];
            ["ATAK éteint par choc", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
            [
                "WARN",
                "Terminal",
                "ATAK éteint par choc",
                "system",
                format ["intensité choc=%1", (_impact toFixed 2)]
            ] call comspec_overwatch_connect_fnc_logAtakEvent;
            [true] call comspec_overwatch_connect_fnc_logAtakStateChange;
            [{
                private _state = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
                if (_state getOrDefault ["device_destroyed", false]) exitWith {};
                _state set ["powered_on", true];
                missionNamespace setVariable ["COMSPEC_AtakState", _state, false];
            }, [], 20] call CBA_fnc_waitAndExecute;
        };
    };
};

private _unit = player;
private _chestDamage = 0;
private _armDamage = 0;

if (isClass (configFile >> "CfgPatches" >> "ace_medical")) then {
    private _hitpoints = getAllHitPointsDamage _unit;
    if (count _hitpoints >= 2) then {
        private _bodyParts = _hitpoints select 0;
        private _damages = _hitpoints select 2;
        {
            private _index = _bodyParts find _x;
            if (_index >= 0) then {
                private _d = _damages select _index;
                if (_x in ["HitChest", "HitBody", "HitTorso"]) then {
                    _chestDamage = _chestDamage max _d;
                };
                if (_x in ["HitLeftArm", "HitRightArm", "HitHands"]) then {
                    _armDamage = _armDamage max _d;
                };
            };
        } forEach _bodyParts;
    };
};

// KAM : pneumothorax / thorax → aggravation dommages torse
private _hasKam = isClass (configFile >> "CfgPatches" >> "kat_advancedMedical")
    || {isClass (configFile >> "CfgPatches" >> "kat_medical")};
if (_hasKam) then {
    if (_unit getVariable ["kat_pneumothorax", false]) then { _chestDamage = _chestDamage max 0.75; };
    if (_unit getVariable ["kat_hemonpneumothorax", false]) then { _chestDamage = _chestDamage max 0.85; };
    private _spo2 = _unit getVariable ["kat_bloodGas_spo2", 100];
    if (_spo2 isEqualType 0 && {_spo2 < 85}) then {
        _atakState set ["sensor_hr_unreliable", true];
    };
};

// Bras très blessé → impossibilité de tenir l'appareil (niveau 1)
if (_armDamage > 0.65 && {_realism >= 1} && {random 100 < 25}) then {
    if (_atakState getOrDefault ["powered_on", true]) then {
        _atakState set ["powered_on", false];
        ["Impossible de tenir l'ATAK — bras blessé", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
        [{
            private _state = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
            if (_state getOrDefault ["device_destroyed", false]) exitWith {};
            _state set ["powered_on", true];
            missionNamespace setVariable ["COMSPEC_AtakState", _state, false];
        }, [], 45] call CBA_fnc_waitAndExecute;
    };
};

private _wasPowered = _atakState getOrDefault ["powered_on", true];
private _wasScreenDestroyed = _atakState getOrDefault ["screen_destroyed", false];

switch (_realism) do {
    case 1: {
        if (_chestDamage > 0.5 && {random 100 < 30}) then {
            _atakState set ["powered_on", false];
            if (_wasPowered) then {
                ["ATAK hors service", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
                ["disconnect"] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
                [{
                    private _state = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
                    if (_state getOrDefault ["device_destroyed", false]) exitWith {};
                    _state set ["powered_on", true];
                    missionNamespace setVariable ["COMSPEC_AtakState", _state, false];
                    ["ATAK rétabli", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
                }, [], 30] call CBA_fnc_waitAndExecute;
            };
        };
    };
    case 2: {
        if (_chestDamage > 0.7 && {!_wasScreenDestroyed} && {random 100 < 40}) then {
            _atakState set ["screen_destroyed", true];
            ["Écran ATAK hors service", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
            ["disconnect"] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
            [
                "WARN",
                "Terminal",
                "Écran hors service (blessure torse)",
                "system",
                format ["dommages torse=%1", (_chestDamage toFixed 2)]
            ] call comspec_overwatch_connect_fnc_logAtakEvent;
            [true] call comspec_overwatch_connect_fnc_logAtakStateChange;
        };
    };
    case 3: {
        if (_chestDamage > 0.8 && {random 100 < 50}) then {
            _atakState set ["device_destroyed", true];
            _atakState set ["screen_destroyed", true];
            _atakState set ["powered_on", false];
            ["ATAK hors service — liaison coupée", "system", "critical"] call comspec_overwatch_connect_fnc_ambientHint;
            ["disconnect"] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
            [
                "ERROR",
                "Terminal",
                "Appareil hors service — liaison coupée",
                "system",
                format ["dommages torse=%1", (_chestDamage toFixed 2)]
            ] call comspec_overwatch_connect_fnc_logAtakEvent;
            [true] call comspec_overwatch_connect_fnc_logAtakStateChange;
            [] call comspec_overwatch_connect_fnc_disconnect;
        };
    };
};

missionNamespace setVariable ["COMSPEC_AtakState", _atakState, false];
