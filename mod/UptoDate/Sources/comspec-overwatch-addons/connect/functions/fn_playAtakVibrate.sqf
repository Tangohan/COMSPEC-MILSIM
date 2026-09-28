/*
    Vibration ATAK unique avec anti-spam global.
    Params: [_intensity (0..1 optionnel), _force (bool)]
    - Un seul bip (plus de salves ×3)
    - Cooldown global ~10 s sauf _force (commande TOC « Faire vibrer »)
    N’affecte que le canal ATAK (playSoundUI), pas ACRE / le jeu.
*/
params [
    ["_intensity", 1, [0]],
    ["_force", false, [false]]
];

if (!hasInterface) exitWith { false };

private _style = missionNamespace getVariable ["comspec_overwatch_notif_sound", "silent_vib"];
if (!(_style isEqualType "")) then { _style = "silent_vib"; };
if ((toLower _style) isEqualTo "mute") exitWith { false };

private _now = diag_tickTime;
private _last = missionNamespace getVariable ["COMSPEC_AtakVibrateAt", -1e9];
if (!_force && {(_now - _last) < 10}) exitWith { false };

private _vol = ["vibrate"] call comspec_overwatch_connect_fnc_getAtakSoundVolume;
_vol = _vol * ((_intensity max 0) min 1);
if (_vol <= 0.01) exitWith { false };

missionNamespace setVariable ["COMSPEC_AtakVibrateAt", _now, false];
playSoundUI ["COMSPEC_ATAK_Vibrate", _vol, 1];
true
