/*
    Applique le thème de couleurs ECOTI : mission + CBA + profil.
    Params: [_themeId, _persist]
*/
params [["_theme", "nvg", [""]], ["_persist", true, [true]]];

if (!(_theme isEqualType "") || {_theme isEqualTo ""}) then { _theme = "nvg"; };
_theme = toLower _theme;
if !(_theme in ["nvg", "lime", "amber", "white", "blue"]) then { _theme = "nvg"; };

missionNamespace setVariable ["comspec_overwatch_ecoti_theme", _theme, false];

if (_persist) then {
    profileNamespace setVariable ["COMSPEC_EcotiTheme", _theme];
    saveProfileNamespace;
};

if (!isNil "cba_settings_fnc_set") then {
    ["comspec_overwatch_ecoti_theme", _theme, 2, "client"] call cba_settings_fnc_set;
};

_theme
