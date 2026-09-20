/*
    Calque HUD tube : vignette panoramique + boussole / grille / distance / heure.
    Retourne le display ou displayNull.
*/
if (!hasInterface) exitWith { displayNull };

private _disp = uiNamespace getVariable ["COMSPEC_EcotiChromeDisp", displayNull];
if (!isNull _disp) exitWith { _disp };

7756 cutRsc ["COMSPEC_EcotiChromeHud", "PLAIN", 0, false];
_disp = uiNamespace getVariable ["COMSPEC_EcotiChromeDisp", displayNull];
if (isNull _disp) exitWith { displayNull };

missionNamespace setVariable ["COMSPEC_EcotiChromeLayerOn", true, false];

private _vig = _disp displayCtrl 77400;
if (isNull _vig) then {
    _vig = _disp ctrlCreate ["RscPicture", 77400];
};
_vig ctrlSetText "\z\comspec_overwatch\addons\connect\img\ecoti\ecoti_vignette_ca.png";
_vig ctrlSetPosition [safeZoneX, safeZoneY, safeZoneW, safeZoneH];
_vig ctrlSetTextColor [1, 1, 1, 1];
_vig ctrlCommit 0;

private _fnc_st = {
    params ["_idc", "_x", "_y", "_w", "_h"];
    private _c = _disp displayCtrl _idc;
    if (isNull _c) then {
        _c = _disp ctrlCreate ["RscStructuredText", _idc];
    };
    _c ctrlSetPosition [_x, _y, _w, _h];
    _c ctrlCommit 0;
    _c
};

[77401, safeZoneX + 0.18 * safeZoneW, safeZoneY + 0.045 * safeZoneH, 0.64 * safeZoneW, 0.045 * safeZoneH] call _fnc_st;
[77402, safeZoneX + 0.32 * safeZoneW, safeZoneY + 0.088 * safeZoneH, 0.36 * safeZoneW, 0.032 * safeZoneH] call _fnc_st;
[77403, safeZoneX + 0.18 * safeZoneW, safeZoneY + 0.905 * safeZoneH, 0.18 * safeZoneW, 0.032 * safeZoneH] call _fnc_st;
[77404, safeZoneX + 0.36 * safeZoneW, safeZoneY + 0.905 * safeZoneH, 0.28 * safeZoneW, 0.032 * safeZoneH] call _fnc_st;
[77405, safeZoneX + 0.64 * safeZoneW, safeZoneY + 0.905 * safeZoneH, 0.18 * safeZoneW, 0.032 * safeZoneH] call _fnc_st;
[77406, safeZoneX + 0.78 * safeZoneW, safeZoneY + 0.12 * safeZoneH, 0.16 * safeZoneW, 0.032 * safeZoneH] call _fnc_st;

_disp
