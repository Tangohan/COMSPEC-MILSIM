/*
    Applique le réglage Affichage situation (JVN) : mission + CBA + profil.
    Params: [_enabled, _persist] — _persist écrit le profil (défaut true).
*/
params [["_enabled", false, [true]], ["_persist", true, [true]]];

if (!(_enabled isEqualType true)) then { _enabled = false; };

missionNamespace setVariable ["comspec_overwatch_ecoti_hud", _enabled, false];

if (_persist) then {
    profileNamespace setVariable ["COMSPEC_EcotiHudEnabled", _enabled];
    saveProfileNamespace;
};

if (!isNil "cba_settings_fnc_set") then {
    ["comspec_overwatch_ecoti_hud", _enabled, 2, "client"] call cba_settings_fnc_set;
};

_enabled
