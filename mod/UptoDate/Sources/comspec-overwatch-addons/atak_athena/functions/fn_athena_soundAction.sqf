/*
    Actions de l’app Sons ATAK.
    Params: [_action, _delta]
*/
params [
    ["_action", "", [""]],
    ["_delta", 0, [0]]
];

if (!hasInterface) exitWith {};

private _clamp01 = {
    params ["_v"];
    ((_v max 0) min 1)
};

private _stepVol = {
    params ["_name", "_d"];
    private _cur = missionNamespace getVariable [_name, 1];
    if (!(_cur isEqualType 0)) then { _cur = 1; };
    private _next = [_cur + _d] call _clamp01;
    // Arrondi au dixième pour un affichage propre
    _next = (round (_next * 10)) / 10;
    [_name, _next] call comspec_overwatch_connect_fnc_setAtakSoundSetting;
};

switch (toLower _action) do {
    case "cycle_style": {
        private _styles = ["silent_vib", "stalker", "health", "mute"];
        private _cur = missionNamespace getVariable ["comspec_overwatch_notif_sound", "silent_vib"];
        if (!(_cur isEqualType "")) then { _cur = "silent_vib"; };
        private _idx = _styles find (toLower _cur);
        if (_idx < 0) then { _idx = 0; };
        private _next = _styles select ((_idx + 1) mod (count _styles));
        ["comspec_overwatch_notif_sound", _next] call comspec_overwatch_connect_fnc_setAtakSoundSetting;
    };
    case "vol_master": {
        ["comspec_overwatch_sound_master", _delta] call _stepVol;
    };
    case "vol_notif": {
        ["comspec_overwatch_sound_notif_vol", _delta] call _stepVol;
    };
    case "vol_vibrate": {
        ["comspec_overwatch_sound_vibrate_vol", _delta] call _stepVol;
    };
    case "vol_fx": {
        ["comspec_overwatch_sound_fx_vol", _delta] call _stepVol;
    };
    case "toggle_quiet": {
        private _cur = missionNamespace getVariable ["comspec_overwatch_quiet_mode", false];
        ["comspec_overwatch_quiet_mode", !_cur] call comspec_overwatch_connect_fnc_setAtakSoundSetting;
    };
    case "toggle_screen": {
        private _cur = missionNamespace getVariable ["comspec_overwatch_screen_notifications", false];
        ["comspec_overwatch_screen_notifications", !_cur] call comspec_overwatch_connect_fnc_setAtakSoundSetting;
    };
    case "toggle_roleplay": {
        private _cur = missionNamespace getVariable ["comspec_overwatch_roleplay_visual_effects", false];
        ["comspec_overwatch_roleplay_visual_effects", !_cur] call comspec_overwatch_connect_fnc_setAtakSoundSetting;
    };
    case "test": {
        private _pref = missionNamespace getVariable ["comspec_overwatch_notif_sound", "silent_vib"];
        if ((toLower _pref) isEqualTo "mute") then {
            ["Tester — silence total actif. Changez le style d’alerte pour entendre un son.", "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
        } else {
            if ((toLower _pref) isEqualTo "silent_vib") then {
                private _vol = ["vibrate"] call comspec_overwatch_connect_fnc_getAtakSoundVolume;
                if (_vol > 0.01) then {
                    playSoundUI ["COMSPEC_ATAK_Vibrate", _vol, 1];
                } else {
                    ["Tester — volume trop bas pour la vibration.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
                };
            } else {
                ["order"] call comspec_overwatch_connect_fnc_playAtakNotification;
            };
        };
    };
};

[] call comspec_overwatch_atak_athena_fnc_athena_updateSound;
