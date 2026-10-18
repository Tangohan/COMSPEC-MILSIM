/*
    Floutage automatique des visages (mode photo) : repère les têtes visibles dans la vue au moment de la prise et
    renvoie les détails de la prise de vue que le site lit pour flouter (PhotoFaceBlur, à la réception de la photo).
    Réglages CBA « ATAK · Photo » : type (mouvement, pixel, carré noir, flou gaussien, ou aucun), visages floutés
    (alliés seulement ou tout le monde), force, opacité, taille du cadre, angle du flou de mouvement.
    La touche B du mode photo change le type pour la session (COMSPEC_ATAK_BlurType).
    Params : [selfie (bool)]
    Retour : [["blur", "type;force;opacité;taille;angle"], ["face1", "x;y;l;h"]...] ou [] (rien à flouter)
*/
params [["_selfie", false]];
private _type = uiNamespace getVariable ["COMSPEC_ATAK_BlurType", missionNamespace getVariable ["comspec_atak_native_blur_type", "gauss"]];
if !(_type in ["motion", "pixel", "black", "gauss"]) exitWith { [] };
private _allies = (missionNamespace getVariable ["comspec_atak_native_blur_target", "allies"]) isEqualTo "allies";
private _range = missionNamespace getVariable ["comspec_atak_native_blur_range", 150];
private _eye = AGLToASL (positionCameraToWorld [0, 0, 0]);
// Axes de l'objectif dans le monde : un cadre de tête projeté garde ses proportions à l'écran.
private _up = (AGLToASL (positionCameraToWorld [0, 1, 0])) vectorDiff _eye;
private _right = (AGLToASL (positionCameraToWorld [1, 0, 0])) vectorDiff _eye;
private _mySide = side group player;
private _faces = [];
{
    private _u = _x;
    if (count _faces >= 12) then { break };
    if (_u isEqualTo player && {!_selfie}) then { continue };
    if (_allies && {_u isNotEqualTo player} && {((side group _u) getFriend _mySide) < 0.6}) then { continue };
    // Centre de la tête : un peu au-dessus et en arrière des yeux.
    private _head = (eyePos _u) vectorAdd ((vectorDirVisual _u) vectorMultiply -0.04) vectorAdd [0, 0, 0.02];
    if ((_eye vectorDistance _head) > _range) then { continue };
    private _c = worldToScreen (ASLToAGL _head);
    if (_c isEqualTo []) then { continue };
    // Tête cachée (mur, terrain, véhicule) : pas de cadre.
    if (([player, "VIEW", _u] checkVisibility [_eye, _head]) < 0.2) then { continue };
    private _t = worldToScreen (ASLToAGL (_head vectorAdd (_up vectorMultiply 0.15)));
    private _b = worldToScreen (ASLToAGL (_head vectorAdd (_up vectorMultiply -0.17)));
    private _l = worldToScreen (ASLToAGL (_head vectorAdd (_right vectorMultiply -0.12)));
    private _r = worldToScreen (ASLToAGL (_head vectorAdd (_right vectorMultiply 0.12)));
    if ([_t, _b, _l, _r] findIf { _x isEqualTo [] } >= 0) then { continue };
    private _w = (abs (((_r select 0) - (_l select 0)))) / safeZoneW;
    private _h = (abs (((_b select 1) - (_t select 1)))) / safeZoneH;
    private _x0 = (((_c select 0) - safeZoneX) / safeZoneW) - _w / 2;
    private _y0 = (((_c select 1) - safeZoneY) / safeZoneH) - _h / 2;
    if (_w <= 0.002 || {_h <= 0.002} || {_w > 1} || {_h > 1}) then { continue };
    _faces pushBack format ["%1;%2;%3;%4", _x0 toFixed 4, _y0 toFixed 4, _w toFixed 4, _h toFixed 4];
} forEach ((allUnits + allDeadMen) select { _x isKindOf "CAManBase" && {(_x distance player) < _range + 20} });
if (_faces isEqualTo []) exitWith { [] };
private _spec = format ["%1;%2;%3;%4;%5", _type,
    round (missionNamespace getVariable ["comspec_atak_native_blur_force", 60]),
    round (missionNamespace getVariable ["comspec_atak_native_blur_opacity", 100]),
    round (missionNamespace getVariable ["comspec_atak_native_blur_size", 130]),
    round (missionNamespace getVariable ["comspec_atak_native_blur_angle", 0])];
private _out = [["blur", _spec]];
{ _out pushBack [format ["face%1", _forEachIndex + 1], _x]; } forEach _faces;
_out
