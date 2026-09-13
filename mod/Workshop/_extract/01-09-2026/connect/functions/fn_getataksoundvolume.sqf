/*
    Volume effectif pour un canal sonore ATAK.
    Params: [_channel] — "master" | "notif" | "vibrate" | "fx"
    Retour : 0..~1.6 (vibrate peut dépasser 1 pour rester audible)
*/
params [["_channel", "notif", [""]]];

private _master = missionNamespace getVariable ["comspec_overwatch_sound_master", 1];
if (!(_master isEqualType 0)) then { _master = 1; };
_master = (_master max 0) min 1;
if (_master <= 0.001) exitWith { 0 };

private _chan = switch (toLower _channel) do {
    case "master": { 1 };
    case "vibrate";
    case "vib": {
        private _v = missionNamespace getVariable ["comspec_overwatch_sound_vibrate_vol", 1];
        if (!(_v isEqualType 0)) then { _v = 1; };
        (_v max 0) min 1
    };
    case "fx";
    case "roleplay";
    case "enhanced": {
        private _v = missionNamespace getVariable ["comspec_overwatch_sound_fx_vol", 0.8];
        if (!(_v isEqualType 0)) then { _v = 0.8; };
        (_v max 0) min 1
    };
    default {
        private _v = missionNamespace getVariable ["comspec_overwatch_sound_notif_vol", 1];
        if (!(_v isEqualType 0)) then { _v = 1; };
        (_v max 0) min 1
    };
};

private _out = _master * _chan;
// Vibration : un peu plus présente à volume max (équivalent ancien 1.6)
if ((toLower _channel) in ["vibrate", "vib"]) then {
    _out = _out * 1.6;
};
(_out max 0) min 2
