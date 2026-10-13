/*
    Marque COMSPEC sur les écrans de chargement d'Arma : emblème + « COMSPEC » (intro_logo_ca), et en dessous
    « OVERWATCH · ATAK NATIF » avec la version du mod, en bas à droite, fondu à l'apparition.
    Posée sur l'écran de démarrage à la fin de l'intro (fn_introSplash) et sur les chargements de mission
    (RscDisplayLoadMission, RscDisplayLoading : Extended_DisplayLoad de config.cpp). Appelée par chemin, comme l'intro.
    Params : [display]
*/
params [["_display", displayNull, [displayNull]]];
if (isNull _display) exitWith {};
if (!isNull (_display getVariable ["COMSPEC_ATAK_Badge", controlNull])) exitWith {};
private _data = "\z\comspec_atak_native\addons\main\data\";
private _ver = getText (configFile >> "CfgPatches" >> "comspec_atak_native_main" >> "versionStr");
// Emblème 1024x512 : 17 % de la largeur de l'écran, collé au coin bas droit.
private _lw = 0.17 * safeZoneW;
private _lh = _lw * 0.5 * pixelH / pixelW;
private _lx = safeZoneX + safeZoneW - _lw - 0.015 * safeZoneW;
private _ly = safeZoneY + safeZoneH - _lh - 0.075 * safeZoneH;
private _logo = _display ctrlCreate ["RscPicture", -1];
_logo ctrlSetText (((_data + "intro_logo_ca.paa")) call comspec_atak_native_fnc_tr);
_logo ctrlSetTextColor [0.93, 0.97, 0.95, 0.9];
_logo ctrlSetPosition [_lx, _ly, _lw, _lh];
_logo ctrlSetFade 1;
_logo ctrlCommit 0;
private _txt = _display ctrlCreate ["RscStructuredText", -1];
_txt ctrlSetPosition [_lx - _lw * 0.3, _ly + _lh * 0.92, _lw * 1.3, 0.05 * safeZoneH];
_txt ctrlSetStructuredText parseText ((format [
    "<t align='right' font='PuristaSemibold' size='0.9' color='#5cc76b'>OVERWATCH  ·  ATAK NATIF</t><t align='right' font='EtelkaMonospacePro' size='0.75' color='#8a9a92'>   %1</t>",
    ["", "v" + _ver] select (_ver isNotEqualTo "")
]) call comspec_atak_native_fnc_tr);
_txt ctrlSetFade 1;
_txt ctrlCommit 0;
{ _x ctrlSetFade 0; _x ctrlCommit 0.6; } forEach [_logo, _txt];
_display setVariable ["COMSPEC_ATAK_Badge", _logo];
[_logo, _txt]
