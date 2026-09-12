/*
    Applique Afficher la barre de liaison : mission + CBA + profil.
    Params: [_enabled, _persist]
*/
params [["_enabled", true, [true]], ["_persist", true, [true]]];

if (!(_enabled isEqualType true)) then { _enabled = true; };

missionNamespace setVariable ["comspec_overwatch_show_link_strip", _enabled, false];

if (_persist) then {
    profileNamespace setVariable ["COMSPEC_LinkStripVisible", _enabled];
    saveProfileNamespace;
};

if (!isNil "cba_settings_fnc_set") then {
    ["comspec_overwatch_show_link_strip", _enabled, 2, "client"] call cba_settings_fnc_set;
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_updateLinkStrip") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updateLinkStrip;
};

_enabled
