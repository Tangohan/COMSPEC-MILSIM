/*
    Marque sur les écrans de chargement d'Arma, en bas à droite : logo Athena du site (brand_athena_ca), nom et version
    du mod, sur un cartouche sombre lisible quelle que soit l'image de fond, fondu à l'apparition.
    Posée sur l'écran de démarrage par l'intro (fn_introSplash) et sur les chargements de mission
    (RscDisplayLoadMission, RscDisplayLoading : Extended_DisplayLoad de config.cpp). Appelée par chemin, comme l'intro.
    Sur l'écran de démarrage les CfgFunctions ne sont pas encore compilées : fn_tr seulement s'il existe.
    Params : [display]
*/
params [["_display", displayNull, [displayNull]]];
if (isNull _display) exitWith {};
if (!isNull (_display getVariable ["COMSPEC_ATAK_Badge", controlNull])) exitWith {};
private _data = "\z\comspec_atak_native\addons\main\data\";
private _tr = { if (isNil "comspec_atak_native_fnc_tr") then { _this } else { _this call comspec_atak_native_fnc_tr } };
private _ver = getText (configFile >> "CfgPatches" >> "comspec_atak_native_main" >> "versionStr");
// Cartouche : hauteur fixe en part d'écran, logo carré à gauche (largeur convertie par le rapport des pixels).
private _h = 0.082 * safeZoneH;
private _pad = _h * 0.14;
private _logoH = _h - _pad * 2;
private _logoW = _logoH * pixelW / pixelH;
private _padW = _pad * pixelW / pixelH;
private _textW = 0.135 * safeZoneW;
private _w = _padW * 3 + _logoW + _textW;
private _bx = safeZoneX + safeZoneW - _w - 0.014 * safeZoneW;
private _by = safeZoneY + safeZoneH - _h - 0.07 * safeZoneH;
private _panel = _display ctrlCreate ["RscText", -1];
_panel ctrlSetBackgroundColor [0.035, 0.05, 0.045, 0.72];
_panel ctrlSetPosition [_bx, _by, _w, _h];
private _line = _display ctrlCreate ["RscText", -1];
_line ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95];
_line ctrlSetPosition [_bx, _by, _w, pixelH * 2];
private _logo = _display ctrlCreate ["RscPicture", -1];
_logo ctrlSetText (_data + "brand_athena_ca.paa"); // i18n:brut (image, ou déjà traduit par _tr)
_logo ctrlSetTextColor [1, 1, 1, 0.96];
_logo ctrlSetPosition [_bx + _padW, _by + _pad, _logoW, _logoH];
private _txt = _display ctrlCreate ["RscStructuredText", -1];
_txt ctrlSetPosition [_bx + _padW * 2 + _logoW, _by + _pad * 0.75, _textW, _h - _pad * 1.2];
_txt ctrlSetStructuredText parseText ((format [
    "<t font='PuristaSemibold' size='1.05' color='#f2f6f3'>COMSPEC ATAK</t><br/><t font='PuristaLight' size='0.8' color='#a9b8b1'>Plateforme Athena</t><br/><t font='EtelkaMonospacePro' size='0.72' color='#5cc76b'>%1</t>",
    ["", "v" + _ver] select (_ver isNotEqualTo "")
]) call _tr); // i18n:brut (image, ou déjà traduit par _tr)
{ _x ctrlSetFade 1; _x ctrlCommit 0; } forEach [_panel, _line, _logo, _txt];
{ _x ctrlSetFade 0; _x ctrlCommit 0.6; } forEach [_panel, _line, _logo, _txt];
_display setVariable ["COMSPEC_ATAK_Badge", _logo];
[_panel, _line, _logo, _txt]
