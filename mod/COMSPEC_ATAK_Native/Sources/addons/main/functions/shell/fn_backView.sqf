/*
    « Voir l'arrière » : le téléphone retourné au centre de l'écran (coque à la couleur choisie, plaque de fixation,
    appareil photo, étiquette, antenne vissée s'il y en a une). Clic, touche ou 12 s : fermé.
*/
disableSerialization;
private _parent = findDisplay 46;
if (isNull _parent || {!isNull (uiNamespace getVariable ["COMSPEC_ATAK_BackDisplay", displayNull])}) exitWith { false };
private _d = _parent createDisplay "RscDisplayEmpty";
if (isNull _d) exitWith { false };
uiNamespace setVariable ["COMSPEC_ATAK_BackDisplay", _d];
private _tint = profileNamespace getVariable ["COMSPEC_ATAK_ShellTint", "coyote"];
private _suffix = ["", "_" + _tint] select (_tint in ["od", "black"]);
private _bg = _d ctrlCreate ["COMSPEC_RscPanel", -1];
_bg ctrlSetPosition [safeZoneX, safeZoneY, safeZoneW, safeZoneH];
_bg ctrlSetBackgroundColor [0, 0, 0, 0.65];
_bg ctrlCommit 0;
// Carré de la texture : la coque occupe 82 % de sa largeur ; centrée à l'écran (centre de la coque à 57 % de la hauteur).
private _w = safeZoneW * 0.78;
private _h = _w * pixelH / pixelW;
if (_h > safeZoneH * 1.5) then { _h = safeZoneH * 1.5; _w = _h * pixelW / pixelH; };
private _x = safeZoneX + (safeZoneW - _w) / 2;
private _y = safeZoneY + safeZoneH / 2 - _h * 0.57;
private _img = _d ctrlCreate ["COMSPEC_RscPicture", -1];
_img ctrlSetText format ["\z\comspec_atak_native\addons\main\data\phone_back%1.paa", _suffix];
_img ctrlSetTextColor [1, 1, 1, 1];
_img ctrlSetPosition [_x, _y, _w, _h];
_img ctrlCommit 0;
private _ant = ["get"] call comspec_atak_native_fnc_antenna;
if (_ant isNotEqualTo []) then {
    private _a = _d ctrlCreate ["COMSPEC_RscPicture", -1];
    _a ctrlSetText format ["\z\comspec_atak_native\addons\main\data\antenna_%1_back.paa", _ant select 0];
    _a ctrlSetTextColor [1, 1, 1, 1];
    _a ctrlSetPosition [_x + _w * 1426 / 2048, _y + _h * -832 / 2048, _w * 384 / 2048, _h * 1536 / 2048];
    _a ctrlCommit 0;
};
private _ident = [player] call comspec_atak_native_fnc_phoneIdent;
private _cap = _d ctrlCreate ["COMSPEC_RscStructuredText", -1];
_cap ctrlSetPosition [safeZoneX, safeZoneY + safeZoneH * 0.86, safeZoneW, safeZoneH * 0.1];
_cap ctrlCommit 0;
_cap ctrlSetStructuredText parseText format [
    "<t align='center' size='1.1' color='#e8eadf'>Dos du téléphone ATAK</t><br/><t align='center' size='0.85' color='#9aa59f'>Coque %1 · IMEI %2 · Antenne : %3<br/>Clic ou touche pour fermer</t>",
    createHashMapFromArray [["coyote", "coyote"], ["od", "vert OD"], ["black", "noire"]] getOrDefault [_tint, "coyote"],
    _ident param [1, "?"], [_ant param [2, ""], "aucune (port SMA bouché)"] select (_ant isEqualTo [])
];
// Apparition en fondu.
{ _x ctrlSetFade 1; _x ctrlCommit 0; _x ctrlSetFade 0; _x ctrlCommit 0.25; } forEach (allControls _d);
_d displayAddEventHandler ["MouseButtonDown", { (_this select 0) closeDisplay 1; }];
_d displayAddEventHandler ["KeyDown", { (_this select 0) closeDisplay 1; true }];
[{ (_this select 0) closeDisplay 1; }, [_d], 12] call CBA_fnc_waitAndExecute;
true
