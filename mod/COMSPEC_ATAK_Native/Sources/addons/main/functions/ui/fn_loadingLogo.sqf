/*
    Écran de chargement d'Arma (RscDisplayLoadMission, RscDisplayLoading : Extended_DisplayLoad de config.cpp) : fond
    sombre plein écran et le logo ATHENA en très grand, au centre. Rien d'autre : pas de texte, pas de son, pas de vidéo,
    pas d'animation (TTRD, 14/11). Remplace l'ancien cartouche du coin (fn_loadingBadge, gardé pour l'intro seule).
    Les contrôles sont créés par script (ctrlCreate) : ils passent devant ceux de l'écran du jeu, sans patcher ses classes.
    Appelée par chemin : sur ces écrans les CfgFunctions ne sont pas toujours compilées.
    Texture : data\loading_logo_ca.paa (2048 x 2048, blanc sur transparent, tools/src/athena_logo.svg).
    Params : [display]
*/
params [["_display", displayNull, [displayNull]]];
if (isNull _display) exitWith {};
if (!isNull (_display getVariable ["COMSPEC_ATAK_LoadingLogo", controlNull])) exitWith {};
private _bg = _display ctrlCreate ["RscText", -1];
_bg ctrlSetBackgroundColor [0.02, 0.025, 0.022, 1];
_bg ctrlSetPosition [safeZoneX, safeZoneY, safeZoneW, safeZoneH];
_bg ctrlCommit 0;
// Carré à l'écran : la largeur suit le rapport des pixels. 86 % de la hauteur, borné par la largeur (écrans étroits).
private _h = (0.86 * safeZoneH) min (0.94 * safeZoneW * pixelH / pixelW);
private _w = _h * pixelW / pixelH;
private _logo = _display ctrlCreate ["RscPicture", -1];
_logo ctrlSetText "\z\comspec_atak_native\addons\main\data\loading_logo_ca.paa"; // i18n:brut (image)
_logo ctrlSetTextColor [1, 1, 1, 1];
_logo ctrlSetPosition [safeZoneX + (safeZoneW - _w) / 2, safeZoneY + (safeZoneH - _h) / 2, _w, _h];
_logo ctrlCommit 0;
_display setVariable ["COMSPEC_ATAK_LoadingLogo", _logo];
[_bg, _logo]
