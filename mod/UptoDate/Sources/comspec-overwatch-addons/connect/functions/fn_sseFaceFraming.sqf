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

private _sel = _t selectionPosition "head";
if (_sel isEqualTo [0, 0, 0]) then { _sel = _t selectionPosition "pilot"; };
private _head = if (_sel isEqualTo [0, 0, 0]) then {
    (getPosASL _t) vectorAdd [0, 0, 1.6]
} else {
    _t modelToWorldVisualWorld _sel
};
_head = _head vectorAdd [0, 0, 0.02];

private _eye = if (isNull player) then { _head vectorAdd [0, -1, 0] } else { eyePos player };
private _toOp = _eye vectorDiff _head;
_toOp set [2, 0];
if ((vectorMagnitude _toOp) < 0.05) then { _toOp = [0, -1, 0]; };
_toOp = vectorNormalized _toOp;

private _down = !alive _t
    || {(lifeState _t) isEqualTo "INCAPACITATED"}
    || {_t getVariable ["ACE_isUnconscious", false]}
    || {((_head select 2) - ((getPosASL _t) select 2)) < 0.7};
private _dist = 0.85;
private _cam = [];
private _look = _head;

if (_down) then {
    // Au sol : l'opérateur se penche au-dessus du visage, léger décalage vers lui
    // (une visée parfaitement verticale fait basculer l'horizon de la caméra).
    _cam = _head vectorAdd [0, 0, _dist] vectorAdd (_toOp vectorMultiply 0.18);
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

// Mur ou véhicule entre le visage et l'objectif : on rapproche la caméra.
private _hit = lineIntersectsSurfaces [_head, _cam, _t, player, true, 1, "VIEW", "FIRE"];
if ((count _hit) > 0) then {
    private _p = (_hit select 0) select 0;
    _cam = _p vectorAdd ((vectorNormalized (_head vectorDiff _cam)) vectorMultiply 0.06);
};

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
_q = (_q max 5) min 99;

[_cam, _look, _fov, createHashMapFromArray [
    ["distance", _opDist],
    ["couche", ["debout", "sol"] select _down],
    ["nuit", _night],
    ["masque", _masked],
    ["qualite", round _q],
    ["note", _notes joinString ", "]
]]
