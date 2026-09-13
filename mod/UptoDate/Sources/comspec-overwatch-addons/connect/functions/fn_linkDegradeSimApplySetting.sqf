/*
    Applique Simulation de liaison dégradée : mission + CBA + profil.
    OFF = pas de fausse dégradation (état réel seulement).
    Params: [_enabled, _persist]
*/
params [["_enabled", false, [true]], ["_persist", true, [true]]];

if (!(_enabled isEqualType true)) then { _enabled = false; };

missionNamespace setVariable ["comspec_overwatch_link_degrade_sim", _enabled, false];

if (_persist) then {
    profileNamespace setVariable ["COMSPEC_LinkDegradeSimEnabled", _enabled];
    saveProfileNamespace;
};

if (!isNil "cba_settings_fnc_set") then {
    ["comspec_overwatch_link_degrade_sim", _enabled, 2, "client"] call cba_settings_fnc_set;
};

if (!_enabled) then {
    private _state = missionNamespace getVariable ["COMSPEC_NetworkDisconnectState", createHashMap];
    if (_state isEqualType createHashMap) then {
        if (_state getOrDefault ["sim_local", false]) then {
            _state set ["is_disconnected", false];
            _state set ["disconnect_until", -1];
            _state set ["sim_local", false];
        };
    };
    missionNamespace setVariable ["COMSPEC_LinkDegradeSimState", createHashMap, false];
    if (!isNil "comspec_overwatch_connect_fnc_refreshLinkState") then {
        [] call comspec_overwatch_connect_fnc_refreshLinkState;
    };
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_updateLinkStrip") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updateLinkStrip;
};

_enabled
