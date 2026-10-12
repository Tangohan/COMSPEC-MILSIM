/*
    Studio portrait du SEEK : le visage est photographié sur un double du sujet,
    pas sur le sujet lui-même.

    Filmer le vrai sujet sur le terrain donnait des clichés ratés : tête qui passe
    sous le sol avec le ragdoll d'un corps, caméra dans un mur ou un véhicule,
    contre-jour, nuit noire. Le double reprend le visage (face), la tenue, le
    couvre-chef, les lunettes et les blessures au visage du sujet, se tient droit
    à 1 500 m d'altitude au-dessus de l'opérateur (ciel neutre en fond), éclairé
    par une lampe de studio locale. Le viseur et le cliché le cadrent exactement
    de la même façon : ce que l'opérateur voit est ce qui part.

    La qualité du cliché reste celle du terrain (masque, nuit, blessures, décès) :
    elle est calculée sur le vrai sujet par fn_sseFaceFraming.

    Params: [action, sujet]
      "frame"  → [camPosASL, viséeASL, têteASL] pour l'angle et le zoom demandés
                 (crée le double si besoin) ; [] si impossible
      "close"  → supprime le double et la lampe
    Désactivable : comspec_sse_bio_faceStudio = false (réglage CBA).
*/
params [["_act", "frame", [""]], ["_t", objNull, [objNull]], ["_angle", "face", [""]]];
if (!hasInterface) exitWith { [] };

private _st = uiNamespace getVariable ["COMSPEC_SseStudio", createHashMap];
uiNamespace setVariable ["COMSPEC_SseStudio", _st];

private _close = {
    { if (!isNull _x) then { detach _x; deleteVehicle _x; }; } forEach [_st getOrDefault ["clone", objNull], _st getOrDefault ["light", objNull], _st getOrDefault ["anchor", objNull]];
    { _st deleteAt _x; } forEach ["clone", "light", "anchor", "target"];
};

if (_act isEqualTo "close") exitWith { call _close; [] };
if (isNull _t || {!(_t isKindOf "CAManBase")}) exitWith { [] };

private _clone = _st getOrDefault ["clone", objNull];
if ((_st getOrDefault ["target", objNull]) isNotEqualTo _t || {isNull _clone}) then {
    call _close;
    private _base = (getPosASL player) vectorAdd [0, 0, 1500];
    private _anchor = "Land_HelipadEmpty_F" createVehicleLocal [0, 0, 0];
    _anchor setPosASL _base;
    _anchor setDir 0;
    _clone = createAgent [typeOf _t, [0, 0, 0], [], 0, "CAN_COLLIDE"];
    if (isNull _clone) exitWith { deleteVehicle _anchor; };
    _clone setVariable ["comspec_sse_studioClone", true, true];
    _clone allowDamage false;
    { _clone disableAI _x; } forEach ["ALL"];
    _clone attachTo [_anchor, [0, 0, 0]];
    _clone setDir 0;
    _clone switchMove "AmovPercMstpSnonWnonDnon";
    _clone setMimic "neutral";
    _clone setFace (face _t);
    removeAllWeapons _clone;
    removeHeadgear _clone;
    removeGoggles _clone;
    removeVest _clone;
    removeBackpack _clone;
    if ((uniform _t) isNotEqualTo "") then { _clone forceAddUniform (uniform _t); } else { removeUniform _clone; };
    if ((vest _t) isNotEqualTo "") then { _clone addVest (vest _t); };
    if ((headgear _t) isNotEqualTo "") then { _clone addHeadgear (headgear _t); };
    if ((goggles _t) isNotEqualTo "") then { _clone addGoggles (goggles _t); };
    // Blessures au visage : textures de sang des points d'impact de la tête.
    {
        private _d = _t getHitPointDamage _x;
        if (!isNil "_d" && {_d > 0}) then { _clone setHitPointDamage [_x, _d min 0.85]; };
    } forEach ["HitFace", "HitNeck", "HitHead"];
    // Lampe de studio : lumière douce de face, plus forte la nuit.
    private _light = "#lightpoint" createVehicleLocal [0, 0, 0];
    _light setPosASL (_base vectorAdd [0.25, 1.4, 1.75]);
    _light setLightColor [1, 0.97, 0.92];
    _light setLightAmbient [0.55, 0.53, 0.5];
    _light setLightBrightness ([0.35, 1.1] select (sunOrMoon < 0.5));
    _light setLightAttenuation [0.3, 0, 3, 0];
    _st set ["clone", _clone];
    _st set ["light", _light];
    _st set ["anchor", _anchor];
    _st set ["target", _t];
};
if (isNull _clone) exitWith { [] };

// Le double tourne, la caméra reste au nord du visage : face, trois quarts, profil.
private _deg = switch (_angle) do { case "trois_quarts": { 38 }; case "profil": { 88 }; default { 0 }; };
_clone setDir _deg;

// Point de visée : l'œil (point mémoire « pilot »), relevé au centre de la tête. Le LOD de tir
// (« head », FireGeometry) n'est animé que pour une unité simulée à l'écran : le double, à
// 1 500 m au-dessus, y gardait sa pose de repos, la tête était visée trop haut et décalée
// du cou, et le viseur montrait la tête en bas du cadre, à côté de l'ovale.
private _feet = getPosASLVisual _clone;
private _fnc_ok = {
    params ["_p"];
    ((_p distance2D _feet) < 0.35) && {((_p select 2) - (_feet select 2)) > 1.2} && {((_p select 2) - (_feet select 2)) < 2.0}
};
private _head = [];
{
    private _p = _clone selectionPosition _x;
    if ((_p isEqualType []) && {(count _p) == 3} && {_p isNotEqualTo [0, 0, 0]}) then {
        private _w = _clone modelToWorldVisualWorld _p;
        if ([_w] call _fnc_ok) exitWith { _head = _w vectorAdd [0, 0, 0.03]; };
    };
    if (_head isNotEqualTo []) exitWith {};
} forEach [["pilot", "Memory"], ["head", "Memory"]];
if (_head isEqualTo []) then {
    private _e = eyePos _clone;
    _head = if ([_e] call _fnc_ok) then { _e vectorAdd [0, 0, 0.03] } else { _feet vectorAdd [0, 0, 1.66] };
};

private _cam = _head vectorAdd [0, 0.85, 0.01];
private _look = _head vectorAdd [0, 0, -0.06];
[_cam, _look, _head]
