/*
    comspec_fb_fnc_exposure : exposition d'une unité à UNE détonation -> [vision 0..1+, audition 0..1, infos].

    Audition (niveau crête, modèle de propagation sphérique) :
      L = 170 dB à 1,5 m (fiche M84 : 170-180 dB à 1,5 m) - 20 log10(d / 1,5)
          + réverbération du local (petite pièce +6, couloir +5, pièce +4, hangar +2 dB)
          - 25 dB si un mur / une coque sépare l'unité du flash (bang sourd, pas d'éblouissement)
          - protection auditive (bouchons ACE, casque ACE ; réglage, 25 dB par défaut)
      audition = ((L - 140) / 30) ^ 0,7, bornée 0..1 : 140 dB = valeur limite d'exposition crête de la directive
      2003/10/CE (en dessous : pas d'acouphène), 170 dB = effet maximal.
    Vision :
      distance : plein effet jusqu'au rayon « plein effet », puis décroissance jusqu'au rayon maximal (réglages) ;
      ligne de vue : 2 rayons (centre et 35 cm au-dessus) ; murs, portes fermées, coques arrêtent le flash ;
        même local fermé sans vue directe : lumière réfléchie par les murs = 25 % ;
      orientation : plein effet si le flash est à moins de 25° du regard, 35 % à 90°, 10 % dos tourné ;
        en local fermé, au moins 30 % (la pièce entière s'illumine) ;
      adaptation : jour 0,65, nuit = multiplicateur nuit (sunOrMoon) ; à l'intérieur, au moins mi-pénombre ;
      JVN allumées : x multiplicateur JVN ; optique / jumelles pointées sur le flash : x1,5 (halo) ;
      lunettes teintées (ACE_TintAmount) : x0,75 ; véhicule fermé : la coque bloque la ligne de vue.
    Préréglage (Réaliste / Arcade / Léger) et intensité globale s'appliquent aux deux.
    [_unit, _ctx, _posASL, _env, _isPlayer] call comspec_fb_fnc_exposure
*/
params ["_unit", "_ctx", "_pos", "_env", ["_isPlayer", false]];
private _device = _ctx get "device";
private _eye = eyePos _unit;
private _d = (_eye vectorDistance _pos) max 0.3;
private _info = createHashMapFromArray [["dist", _d], ["nvg", false], ["optic", false], ["blocked", false]];

private _preset = [[1, 1], [1.25, 1.6], [0.7, 0.6]] select (missionNamespace getVariable ["comspec_fb_preset", 0]);
private _gain = (missionNamespace getVariable ["comspec_fb_intensity", 1]) * (_preset select 0);
_info set ["durMult", _preset select 1];

// Ligne de vue : deux rayons ; bloquée seulement si les deux le sont (grenade calée contre une marche, un rebord).
private _src = _pos vectorAdd [0, 0, 0.12];
private _blocked = (lineIntersectsSurfaces [_src, _eye, _unit, _device, true, 1, "VIEW", "GEOM"]) isNotEqualTo [];
if (_blocked) then {
    _blocked = (lineIntersectsSurfaces [_src vectorAdd [0, 0, 0.35], _eye, _unit, _device, true, 1, "VIEW", "GEOM"]) isNotEqualTo [];
};
_info set ["blocked", _blocked];

private _enclosed = _env getOrDefault ["enclosed", false];
private _class = _env getOrDefault ["class", "exterieur"];
private _indoorUnit = [_eye, _unit, vehicle _unit] call comspec_fb_fnc_roofAbove;
private _sameRoom = _enclosed && {_indoorUnit} && {_d < ((_env getOrDefault ["maxAxis", 8]) max 4)};

// ------------------------------------------------------------------ audition
private _L = 170 - 20 * log (_d / 1.5);
_L = _L + (switch (_class) do { case "petite_piece": {6}; case "couloir": {5}; case "piece": {4}; case "hangar": {2}; default {0} });
if (_blocked && {!_sameRoom}) then { _L = _L - 25 };
private _protDb = missionNamespace getVariable ["comspec_fb_earProtectionDb", 25];
private _prot = 0;
if (_unit getVariable ["ACE_hasEarPlugsIn", false]) then { _prot = 1 };
_prot = _prot max (getNumber (configFile >> "CfgWeapons" >> headgear _unit >> "ace_hearing_protection"));
_prot = _prot max (getNumber (configFile >> "CfgGlasses" >> goggles _unit >> "ace_hearing_protection"));
_L = _L - _protDb * (_prot min 1);
private _aud = (((_L - 140) / 30) max 0) min 1;
_aud = (_aud ^ 0.7) * _gain;

// ------------------------------------------------------------------ vision
private _rFull = missionNamespace getVariable ["comspec_fb_radiusFull", 6];
private _rMax = (missionNamespace getVariable ["comspec_fb_radiusMax", 25]) max (_rFull + 1);
private _mult = _env getOrDefault ["mult", 1];
if (_enclosed) then { _rFull = _rFull * _mult; _rMax = _rMax * _mult };
private _fd = if (_d <= _rFull) then { 1 } else { ((1 - (_d - _rFull) / (_rMax - _rFull)) max 0) ^ 1.6 };

private _vis = 0;
if (_fd > 0) then {
    // Direction du regard : caméra pour le joueur, eyeDirection pour l'IA.
    private _look = if (_isPlayer) then {
        (AGLToASL (positionCameraToWorld [0, 0, 1])) vectorDiff (AGLToASL (positionCameraToWorld [0, 0, 0]))
    } else {
        eyeDirection _unit
    };
    private _ang = acos (((vectorNormalized _look) vectorDotProduct (_eye vectorFromTo _pos)) max -1 min 1);
    private _fa = switch (true) do {
        case (_ang <= 25): { 1 };
        case (_ang <= 90): { linearConversion [25, 90, _ang, 1, 0.35, true] };
        default { linearConversion [90, 180, _ang, 0.35, 0.1, true] };
    };
    _info set ["angle", _ang];
    if (_blocked) then {
        _vis = if (_sameRoom) then { 0.25 * _fd } else { 0 };
    } else {
        _vis = _fd * _fa;
        if (_enclosed && {_sameRoom || _indoorUnit}) then { _vis = _vis max (0.3 * _fd) };
        if (_enclosed) then { _vis = _vis * _mult };
    };
    if (_vis > 0) then {
        // Adaptation à la lumière : pupille fermée en plein jour, grande ouverte la nuit ou dans une pièce sombre.
        private _dark = 1 - sunOrMoon;
        if (_indoorUnit) then { _dark = _dark max 0.5 };
        _vis = _vis * linearConversion [0, 1, _dark, 0.65, missionNamespace getVariable ["comspec_fb_nightMult", 1.3], true];
        if (currentVisionMode _unit == 1) then {
            _vis = _vis * (missionNamespace getVariable ["comspec_fb_nvgMult", 2]);
            _info set ["nvg", true];
        };
        if (_isPlayer && {cameraView == "GUNNER"} && {_ang < 12}) then {
            _vis = _vis * 1.5;
            _info set ["optic", true];
        };
        if (getNumber (configFile >> "CfgGlasses" >> goggles _unit >> "ACE_TintAmount") > 0) then { _vis = _vis * 0.75 };
        _vis = _vis * (1 - ((_unit getVariable ["comspec_fb_eyeProtection", 0]) min 1)) * _gain;
    };
};
[(_vis max 0) min 2, (_aud max 0) min 1.25, _info]
