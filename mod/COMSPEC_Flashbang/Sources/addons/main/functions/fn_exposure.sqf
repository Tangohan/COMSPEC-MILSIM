/*
    comspec_fb_fnc_exposure : exposition d'une unité à UNE détonation -> [vision 0..1+, audition 0..1, infos].

    Audition (niveau crête, modèle de propagation sphérique) :
      L = 170 dB à 1,5 m (fiche M84 : 170-180 dB à 1,5 m) - 20 log10(d / 1,5)
          + réverbération du local (petite pièce +6, couloir +5, pièce +4, hangar +2 dB)
          - 25 dB si un mur / une coque sépare l'unité du flash (bang sourd, pas d'éblouissement)
          - protection auditive (bouchons ACE, casque ACE ; réglage, 25 dB par défaut)
      audition = ((L - 140) / 30) ^ 0,7, bornée 0..1 : 140 dB = valeur limite d'exposition crête de la directive
      2003/10/CE (en dessous : pas d'acouphène), 170 dB = effet maximal.
    Vision : table de calibration de fn_visionTable (pupille ouverte), DEUX tables :
        local fermé (grenade sous un toit)   0-3,5 m 1,00 | 5 m 0,85 | 10 m 0,50 | 15 m 0,30 | 25 m 0,04
        extérieur dégagé                     0-2 m   1,00 | 5 m 0,60 | 10 m 0,25 | 15 m 0,08 | 19 m 0
      (dans le champ ; de côté et de dos : voir fn_visionTable). Dehors la lumière se perd : chute plus rapide ;
      réglages « Portée de l'éblouissement en extérieur » (50 à 150 %, étire l'axe des distances de la table
      extérieure) et « Intensité des effets en extérieur » (multiplie le résultat dehors).
      Les réglages « Rayon plein effet » (3 m) et « Rayon maximal » (25 m) étirent l'axe des distances des deux tables.
      adaptation de l'œil (fn_pupil, luminosité réelle = soleil x ciel) : plein soleil x0,45, aube / crépuscule x0,7,
        nuit ou local sombre x multiplicateur nuit (1,0) ; sous un toit, luminosité bornée à 0,25 ; sous 2 m le flash
        sature de toute façon (pas de réduction), elle s'installe jusqu'à 6 m ;
      ligne de vue : 2 rayons (centre et 35 cm au-dessus) ; murs, portes fermées, coques arrêtent le flash ;
        même local fermé sans vue directe : lumière réfléchie par les murs = 25 % ;
      local fermé : au moins 30 % de l'effet en face, puis x multiplicateur intérieur ;
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
private _L = 170 - 20 * (log (_d / 1.5) / log 10); // SQF log = népérien : log10(x) = ln(x) / ln(10)
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
// Axe des distances ramené à la table (0-3 m plein effet, 25 m négligeable) d'après les deux rayons réglables.
private _rFull = (missionNamespace getVariable ["comspec_fb_radiusFull", 3]) max 0.5;
private _rMax = (missionNamespace getVariable ["comspec_fb_radiusMax", 25]) max (_rFull + 1);
private _mult = _env getOrDefault ["mult", 1];
if (_enclosed) then { _rFull = _rFull * _mult; _rMax = _rMax * _mult };
private _dn = if (_d <= _rFull) then { 3 * _d / _rFull } else { 3 + 22 * (_d - _rFull) / (_rMax - _rFull) };
// Extérieur dégagé (grenade sans toit) : table à chute rapide, axe étiré par « Portée de l'éblouissement en extérieur ».
private _outdoor = !_enclosed;
if (_outdoor) then { _dn = _dn / (((missionNamespace getVariable ["comspec_fb_outdoorRange", 1]) max 0.5) min 1.5) };
_info set ["exterieur", _outdoor];
private _fIn = ([_dn, 0, _outdoor] call comspec_fb_fnc_visionTable) select 1;

private _vis = 0;
if (_fIn > 0) then {
    // Direction du regard : caméra pour le joueur, eyeDirection pour l'IA.
    private _look = if (_isPlayer) then {
        (AGLToASL (positionCameraToWorld [0, 0, 1])) vectorDiff (AGLToASL (positionCameraToWorld [0, 0, 0]))
    } else {
        eyeDirection _unit
    };
    private _ang = acos (((vectorNormalized _look) vectorDotProduct (_eye vectorFromTo _pos)) max -1 min 1);
    private _fd = ([_dn, _ang, _outdoor] call comspec_fb_fnc_visionTable) select 0;
    _info set ["angle", _ang];
    if (_blocked) then {
        _vis = if (_sameRoom) then { 0.25 * _fIn } else { 0 };
    } else {
        _vis = _fd;
        if (_enclosed && {_sameRoom || _indoorUnit}) then { _vis = _vis max (0.3 * _fIn) };
        if (_enclosed) then { _vis = _vis * _mult };
    };
    if (_vis > 0) then {
        // Adaptation à la lumière (fn_pupil) : pupille fermée en plein soleil (x0,45), aube / crépuscule ou ciel très
        // couvert (x0,7), grande ouverte la nuit ou dans un local sombre (x multiplicateur nuit). Luminosité réelle :
        // soleil ou lune levés x ciel dégagé ; sous un toit, au plus 0,25.
        private _lum = sunOrMoon * (1 - 0.3 * overcast);
        if (_indoorUnit) then { _lum = _lum min 0.25 };
        private _pupil = [_lum, _d, missionNamespace getVariable ["comspec_fb_nightMult", 1]] call comspec_fb_fnc_pupil;
        _vis = _vis * _pupil;
        _info set ["pupille", _pupil];
        if (_outdoor) then { _vis = _vis * (missionNamespace getVariable ["comspec_fb_outdoorIntensity", 1]) };
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
