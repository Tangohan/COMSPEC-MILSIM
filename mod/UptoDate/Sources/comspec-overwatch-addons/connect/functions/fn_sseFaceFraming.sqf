/*
    Cadrage portrait du visage pour le terminal SEEK (viseur et cliché).

    La caméra se place devant le visage du sujet, comme pour une photo d'identité :
    de face, de trois quarts ou de profil (côté de l'opérateur). Un corps au sol ou
    un blessé inconscient est photographié du dessus.

    Params : [sujet, angle ("face" | "trois_quarts" | "profil"), zoom (1 … 2.5)]
    Retour : [] sans sujet, sinon [camPosASL, viséeASL, fov, infos]
      infos : hashmap distance, couche ("debout" | "sol"), nuit, masque, qualité (0-100), note
*/
params [["_t", objNull, [objNull]], ["_angle", "face", [""]], ["_zoom", 1, [0]]];
if (isNull _t || {!(_t isKindOf "CAManBase")}) exitWith { [] };

// Qualité réelle du visage (moteur biométrique du pack SSE) : masque, nuit, blessures,
// décès. Un visage trop endommagé ne se photographie pas pour comparaison.
private _bioQ = -1;
private _bioNote = "";
if (!isNil "comspec_sse_fnc_bioCapture" && {missionNamespace getVariable ["comspec_sse_bio_enabled", true]}) then {
    private _dry = [_t, player, "visage", "seek", true] call comspec_sse_fnc_bioCapture;
    if (_dry getOrDefault ["ok", false]) then {
        _bioQ = _dry getOrDefault ["quality", -1];
        _bioNote = ((_dry getOrDefault ["factors", []]) apply { (_x splitString "(") select 0 }) joinString ", ";
    } else {
        _bioQ = 0;
        _bioNote = _dry getOrDefault ["blocked", ""];
    };
};

// Studio : le cadrage se fait sur un double du sujet, droit, éclairé, ciel en fond.
if (missionNamespace getVariable ["comspec_sse_bio_faceStudio", true]) then {
    private _s = ["frame", _t, _angle] call comspec_overwatch_connect_fnc_sseFaceStudio;
    if ((count _s) == 3) then {
        _s params ["_cam", "_look"];
        _zoom = (_zoom max 1) min 2.5;
        private _q = 95 - ([0, 5, 15] select ((["face", "trois_quarts", "profil"] find _angle) max 0)) - ([0, 5] select (_zoom > 1.8));
        private _notes = [];
        if (_bioQ >= 0) then {
            _q = _q min _bioQ;
            if (_bioNote isNotEqualTo "") then { _notes pushBack _bioNote; };
        };
        _notes pushBack "portrait studio";
        private _opDist = player distance _t;
        _t setVariable ["comspec_sse_studioFrame", [_cam, _look, 0.36 / _zoom, createHashMapFromArray [
            ["distance", _opDist], ["couche", "debout"], ["nuit", false], ["masque", false],
            ["qualite", round ((_q max 5) min 99)], ["note", _notes joinString " · "], ["studio", true]
        ]]];
    } else {
        _t setVariable ["comspec_sse_studioFrame", nil];
    };
};
private _studio = _t getVariable ["comspec_sse_studioFrame", []];
if ((count _studio) == 4) exitWith { _t setVariable ["comspec_sse_studioFrame", nil]; _studio };

// --- Position de la tête, vérifiée ---
// « selectionPosition "head" » (LOD de résolution) ne suit pas toujours le ragdoll
// d'un corps au sol : la tête pouvait tomber sous le terrain, et la caméra avec
// elle (viseur dans l'herbe, ciel en fond). On essaie plusieurs LOD et on garde
// le premier point cohérent : proche du corps, pas sous le sol.
private _bodyASL = getPosASL _t;
private _fnc_selASL = {
    params ["_name", "_lod"];
    private _p = if (_lod isEqualTo "") then { _t selectionPosition _name } else { _t selectionPosition [_name, _lod] };
    if (!(_p isEqualType []) || {(count _p) < 3} || {_p isEqualTo [0, 0, 0]}) exitWith { [] };
    _t modelToWorldVisualWorld _p
};
private _fnc_sane = {
    params ["_p"];
    if ((count _p) < 3) exitWith { false };
    if ((_p distance2D _bodyASL) > 2.4) exitWith { false };
    if (((_p select 2) - (_bodyASL select 2)) > 2.2) exitWith { false };
    (_p select 2) > ((getTerrainHeightASL _p) - 0.05)
};
private _head = [];
{
    private _p = _x call _fnc_selASL;
    if ([_p] call _fnc_sane) exitWith { _head = _p; };
} forEach [["head", "FireGeometry"], ["head", "HitPoints"], ["head", ""], ["pilot", "Memory"], ["pilot", ""]];
if (_head isEqualTo []) then {
    private _e = eyePos _t;
    _head = if ([_e] call _fnc_sane) then { _e } else { _bodyASL vectorAdd [0, 0, [1.6, 0.25] select (!alive _t)] };
};
_head = _head vectorAdd [0, 0, 0.02];
// Jamais sous le sol (un sol de bâtiment est au-dessus du terrain : ce plancher ne gêne pas).
private _groundAtHead = getTerrainHeightASL _head;
if ((_head select 2) < (_groundAtHead + 0.10)) then { _head set [2, _groundAtHead + 0.10]; };

private _eye = if (isNull player) then { _head vectorAdd [0, -1, 0] } else { eyePos player };
private _toOp = _eye vectorDiff _head;
_toOp set [2, 0];
if ((vectorMagnitude _toOp) < 0.05) then { _toOp = [0, -1, 0]; };
_toOp = vectorNormalized _toOp;

private _down = !alive _t
    || {(lifeState _t) isEqualTo "INCAPACITATED"}
    || {_t getVariable ["ACE_isUnconscious", false]}
    || {((_head select 2) - (_bodyASL select 2)) < 0.7};
private _dist = 0.85;
private _cam = [];
private _look = _head;

if (_down) then {
    // Au sol : l'objectif se place au-dessus du visage, décalé vers la poitrine
    // (axe cou → tête) : le haut de l'image est le sommet du crâne et la visée
    // reste à ~25° de la verticale — une visée verticale fait basculer l'horizon.
    private _axis = [];
    {
        private _n = _x call _fnc_selASL;
        if ([_n] call _fnc_sane && {(_n distance2D _head) > 0.08}) exitWith {
            _axis = _head vectorDiff _n;
        };
    } forEach [["neck", "FireGeometry"], ["neck", ""], ["spine3", "FireGeometry"], ["spine3", ""]];
    private _back = if (_axis isEqualTo []) then { _toOp } else {
        _axis set [2, 0];
        if ((vectorMagnitude _axis) < 0.05) then { _toOp } else { (vectorNormalized _axis) vectorMultiply -1 }
    };
    _cam = _head vectorAdd [0, 0, 0.75] vectorAdd (_back vectorMultiply 0.35);
    _look = _head;
} else {
    private _f = eyeDirection _t;
    if ((vectorMagnitude _f) < 0.1) then { _f = vectorDir _t; };
    _f set [2, ((_f select 2) max -0.25) min 0.25];
    _f = vectorNormalized _f;
    // Trois quarts et profil : on tourne autour de la tête, du côté où se tient l'opérateur.
    private _deg = switch (_angle) do { case "trois_quarts": { 38 }; case "profil": { 88 }; default { 0 }; };
    if (_deg isNotEqualTo 0) then {
        private _side = ((_f select 0) * (_toOp select 1)) - ((_f select 1) * (_toOp select 0));
        if (_side < 0) then { _deg = -_deg; };
        _f = [
            ((_f select 0) * cos _deg) - ((_f select 1) * sin _deg),
            ((_f select 0) * sin _deg) + ((_f select 1) * cos _deg),
            _f select 2
        ];
    };
    _cam = _head vectorAdd (_f vectorMultiply _dist);
    _cam set [2, (_cam select 2) + 0.01];
    _look = _head vectorAdd [0, 0, -0.06];
};

// Mur, plafond ou véhicule entre le visage et l'objectif : on rapproche la caméra.
// Le terrain n'est pas un obstacle (objet nul) : la caméra resterait au ras du sol ;
// un rapprochement à moins de 35 cm est ignoré pour la même raison.
private _from = _head vectorAdd [0, 0, 0.05];
private _hit = lineIntersectsSurfaces [_from, _cam, _t, player, true, 1, "VIEW", "FIRE"];
if ((count _hit) > 0) then {
    (_hit select 0) params ["_p", "", "_obj"];
    if (!isNull _obj) then {
        private _c = _p vectorAdd ((vectorNormalized (_head vectorDiff _cam)) vectorMultiply 0.06);
        if ((_c distance _head) > 0.35) then { _cam = _c; };
    };
};

// Garde-fous finaux : objectif au-dessus du terrain, et au-dessus du visage pour un sujet au sol.
private _groundAtCam = getTerrainHeightASL _cam;
if ((_cam select 2) < (_groundAtCam + 0.30)) then { _cam set [2, _groundAtCam + 0.30]; };
if (_down && {(_cam select 2) < ((_head select 2) + 0.45)}) then { _cam set [2, (_head select 2) + 0.45]; };

_zoom = (_zoom max 1) min 2.5;
private _fov = 0.36 / _zoom;

// Qualité estimée du cliché (affichée et transmise avec la photo).
private _night = sunOrMoon < 0.5;
private _gog = toLower (goggles _t);
private _masked = (_gog find "balaclava" >= 0) || {_gog find "bandanna" >= 0} || {_gog find "mask" >= 0} || {_gog find "shemag" >= 0};
private _opDist = if (isNull player) then { 0 } else { player distance _t };
private _q = 95;
private _notes = [];
if (_night) then { _q = _q - 25; _notes pushBack "nuit, flash"; };
if (_masked) then { _q = _q - 40; _notes pushBack "visage masqué"; };
if (_down) then { _q = _q - 10; _notes pushBack "sujet au sol"; };
switch (_angle) do {
    case "trois_quarts": { _q = _q - 5; };
    case "profil": { _q = _q - 15; };
};
if (_zoom > 1.8) then { _q = _q - 5; };
if (_bioQ >= 0) then {
    _q = _q min _bioQ;
    if (_bioNote isNotEqualTo "") then { _notes pushBack _bioNote; };
};
_q = (_q max 5) min 99;

[_cam, _look, _fov, createHashMapFromArray [
    ["distance", _opDist],
    ["couche", ["debout", "sol"] select _down],
    ["nuit", _night],
    ["masque", _masked],
    ["qualite", round _q],
    ["note", _notes joinString ", "]
]]
