/*
    Floutage automatique des visages (mode photo) : repère les têtes visibles dans la vue au moment de la prise et
    renvoie les détails de la prise de vue que le site lit pour flouter (PhotoFaceBlur, à la réception de la photo).
    Réglages CBA « ATAK · Photo » : type (mouvement, pixel, carré noir, flou gaussien, ou aucun), visages floutés
    (alliés seulement ou tout le monde), force, opacité, taille du cadre, angle du flou de mouvement.
    La touche B du mode photo change le type pour la session (COMSPEC_ATAK_BlurType).
    Params : [selfie (bool)]
    Retour : [["blur", "type;force;opacité;taille;angle"], ["face1", "x;y;l;h"]...] ou [] (rien à flouter)
    Chaque prise écrit une ligne dans le journal (RPT, catégorie PHOTO) : visages floutés, écartés, et pourquoi.
*/
params [["_selfie", false]];
private _type = uiNamespace getVariable ["COMSPEC_ATAK_BlurType", missionNamespace getVariable ["comspec_atak_native_blur_type", "gauss"]];
if !(_type in ["motion", "pixel", "black", "gauss"]) exitWith {
    ["INFO", "PHOTO", "Floutage des visages : réglé sur Aucun, photo envoyée telle quelle"] call comspec_atak_native_fnc_log;
    []
};
private _allies = (missionNamespace getVariable ["comspec_atak_native_blur_target", "allies"]) isEqualTo "allies";
private _range = missionNamespace getVariable ["comspec_atak_native_blur_range", 150];
private _eye = AGLToASL (positionCameraToWorld [0, 0, 0]);
// Axes de l'objectif dans le monde : un cadre de tête projeté garde ses proportions à l'écran.
private _up = (AGLToASL (positionCameraToWorld [0, 1, 0])) vectorDiff _eye;
private _right = (AGLToASL (positionCameraToWorld [1, 0, 0])) vectorDiff _eye;
private _mySide = side group player;
// Téléphone tenu en main (fn_gestureSelfie) : il est devant l'objectif, il ne cache aucune tête.
private _phone = missionNamespace getVariable ["COMSPEC_ATAK_SelfiePhone", objNull];
private _faces = [];
private _hidden = 0;
private _edge = 0;
private _foes = 0;
{
    private _u = _x;
    if (count _faces >= 12) then { break };
    if (_u isEqualTo player && {!_selfie}) then { continue };
    if (_allies && {_u isNotEqualTo player} && {((side group _u) getFriend _mySide) < 0.6}) then { _foes = _foes + 1; continue };
    // Centre de la tête : un peu au-dessus et en arrière des yeux.
    private _head = (eyePos _u) vectorAdd ((vectorDirVisual _u) vectorMultiply -0.04) vectorAdd [0, 0, 0.02];
    if ((_eye vectorDistance _head) > _range) then { continue };
    private _c = worldToScreen (ASLToAGL _head);
    if (_c isEqualTo []) then { continue };
    // Tête cachée (mur, terrain, véhicule) : pas de cadre. Le selfie vise le visage du joueur : jamais caché.
    // L'ancien test (checkVisibility depuis l'objectif) pouvait écarter des têtes bien visibles : le rayon partait
    // du téléphone tenu en main, au ras de la main, du casque ou des lunettes, et le selfie de TTRD (26/10) est
    // arrivé sur Athena sans aucun visage flouté. Seuls le terrain et les objets du décor cachent une tête :
    // un soldat, son équipement, le téléphone et le véhicule où il est assis ne comptent pas ; le rayon part 30 cm
    // devant l'objectif.
    if (_u isNotEqualTo player) then {
        private _dist = _eye vectorDistance _head;
        private _from = _eye vectorAdd ((vectorNormalized (_head vectorDiff _eye)) vectorMultiply (0.3 min (_dist / 2)));
        private _hits = lineIntersectsSurfaces [_from, _head, player, _u, true, 6, "VIEW", "GEOM"];
        private _wall = _hits findIf {
            private _o = _x select 2;
            private _p = _x select 3;
            if (isNull _o && {isNull _p}) exitWith { true };
            private _root = [_p, _o] select (isNull _p);
            !((_root isKindOf "CAManBase") || {(attachedTo _root) isKindOf "CAManBase"} || {_root isEqualTo _phone} || {_root isEqualTo (vehicle _u)})
        };
        if (_wall >= 0) then { _hidden = _hidden + 1; continue };
    };
    // Cadre de la tête à l'écran. Un bord hors de l'image (tête au bord du cadre) est pris symétrique de l'autre
    // autour du centre : le visage coupé par le bord est flouté quand même.
    private _t = worldToScreen (ASLToAGL (_head vectorAdd (_up vectorMultiply 0.15)));
    private _b = worldToScreen (ASLToAGL (_head vectorAdd (_up vectorMultiply -0.17)));
    private _l = worldToScreen (ASLToAGL (_head vectorAdd (_right vectorMultiply -0.12)));
    private _r = worldToScreen (ASLToAGL (_head vectorAdd (_right vectorMultiply 0.12)));
    private _mirror = { params ["_a", "_c"]; [2 * (_c select 0) - (_a select 0), 2 * (_c select 1) - (_a select 1)] };
    if (_t isEqualTo [] && {_b isNotEqualTo []}) then { _t = [_b, _c] call _mirror; };
    if (_b isEqualTo [] && {_t isNotEqualTo []}) then { _b = [_t, _c] call _mirror; };
    if (_l isEqualTo [] && {_r isNotEqualTo []}) then { _l = [_r, _c] call _mirror; };
    if (_r isEqualTo [] && {_l isNotEqualTo []}) then { _r = [_l, _c] call _mirror; };
    if ([_t, _b, _l, _r] findIf { _x isEqualTo [] } >= 0) then { _edge = _edge + 1; continue };
    private _w = (abs (((_r select 0) - (_l select 0)))) / safeZoneW;
    private _h = (abs (((_b select 1) - (_t select 1)))) / safeZoneH;
    private _x0 = (((_c select 0) - safeZoneX) / safeZoneW) - _w / 2;
    private _y0 = (((_c select 1) - safeZoneY) / safeZoneH) - _h / 2;
    if (_w <= 0.002 || {_h <= 0.002} || {_w > 1} || {_h > 1}) then { continue };
    _faces pushBack format ["%1;%2;%3;%4", _x0 toFixed 4, _y0 toFixed 4, _w toFixed 4, _h toFixed 4];
} forEach ((allUnits + allDeadMen) select { _x isKindOf "CAManBase" && {(_x distance player) < _range + 20} });
["INFO", "PHOTO", format ["Floutage (%1, %2) : %3 visage(s) envoyé(s) à Athena, %4 caché(s), %5 hors cadre, %6 ennemi(s) laissé(s) nets", _type, ["alliés", "tout le monde"] select !_allies, count _faces, _hidden, _edge, _foes]] call comspec_atak_native_fnc_log;
if (_faces isEqualTo []) exitWith { [] };
private _spec = format ["%1;%2;%3;%4;%5", _type,
    round (missionNamespace getVariable ["comspec_atak_native_blur_force", 60]),
    round (missionNamespace getVariable ["comspec_atak_native_blur_opacity", 100]),
    round (missionNamespace getVariable ["comspec_atak_native_blur_size", 130]),
    round (missionNamespace getVariable ["comspec_atak_native_blur_angle", 0])];
private _out = [["blur", _spec]];
{ _out pushBack [format ["face%1", _forEachIndex + 1], _x]; } forEach _faces;
_out
