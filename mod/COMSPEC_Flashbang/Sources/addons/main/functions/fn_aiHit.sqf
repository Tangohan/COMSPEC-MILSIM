/*
    comspec_fb_fnc_aiHit : une détonation atteint une IA LOCALE (serveur, headless, ou joueur dont l'IA est du groupe).
    À appeler sur la machine qui possède l'unité (fn_aiBatch le garantit) : les commandes setSkill, disableAI, setUnitPos,
    forgetTarget et playMoveNow n'agissent que sur une unité locale.
    [_unit, _vis, _aud, _posASL, _info, _réglages (fn_aiParams)] call comspec_fb_fnc_aiHit

    Réaction « Réaliste » (réglage « Réaction des IA », voir fn_aiParams) :
      - Aveuglement 2 à 10 s (vision >= 0,2) : disableAI "TARGET" et "AUTOTARGET" : l'IA ne repère ni ne vise plus ; les
        ennemis proches sont oubliés dès 0,45.
      - Désorientation : arrêt net 0,5 à 3,5 s (disableAI "MOVE", IA à pied, jamais le conducteur d'un véhicule) et, si le
        flash est fort et vu de face, geste réflexe (animation vanilla « choc », sans elle : se baisse) ; sinon elle
        se baisse (MIDDLE ou DOWN) ou hésite.
      - Précision : aimingAccuracy, aimingShake, aimingSpeed, spotDistance, spotTime, courage fortement réduits,
        et commanding (surdité : elle transmet mal les contacts) pendant 5 à 20 s, suppression (elle cherche un abri :
        un courage bas la pousse à fuir si la mission l'autorise ; allowFleeing et setBehaviour ne sont jamais touchés).
      - Immunité : une part des IA (réglage, 8 %, plus pour les bons soldats, lunettes teintées, protection auditive)
        n'est pas touchée, tirée une fois par grenade et par unité à partir de l'identifiant de la grenade.
    Réaction « Discrète » : comportement d'avant 1.0.1 (compétences et suppression, aucun disableAI).
    Retour à la normale : fn_aiTick (une échéance par IA, au plus 40 réveils) puis fn_aiRestore. Les valeurs d'origine sont
    mémorisées une seule fois sur l'unité ; une valeur changée entre-temps par une autre source (LAMBS, VCOM, mission)
    n'est pas écrasée ; une fonction (disableAI) déjà coupée avant le flash n'est jamais rétablie.
*/
params ["_unit", "_vis", "_aud", "_pos", "_info", ["_cfg", [], [[]]]];
if (_cfg isEqualTo []) then { _cfg = [] call comspec_fb_fnc_aiParams };
_cfg params ["_mode", "_aiR", "_aiP", "_inten", "_durK", "_immun", "_animOn"];
private _legacy = _mode == 0;
private _now = CBA_missionTime;

// Immunité : tirée une fois par grenade et par unité (même résultat à chaque détonation, sur toutes les machines).
private _immune = false;
if (!_legacy && {alive _unit} && {_immun > 0}) then {
    private _seed = 0;
    { _seed = (_seed * 31 + _x) mod 100003 } forEach (toArray format ["%1%2", _info getOrDefault ["id", ""], netId _unit]);
    private _chance = _immun * (0.6 + 0.8 * (skill _unit));
    if (getNumber (configFile >> "CfgGlasses" >> goggles _unit >> "ACE_TintAmount") > 0) then { _chance = _chance + 0.05 };
    if (_unit getVariable ["ACE_hasEarPlugsIn", false] || {getNumber (configFile >> "CfgWeapons" >> headgear _unit >> "ace_hearing_protection") > 0}) then { _chance = _chance + 0.05 };
    _immune = ((_seed mod 1000) / 1000) < (_chance min 0.6);
};
if (_immune) exitWith {
    ["ia", format ["%1 : immunisée à cette grenade (hasard par unité)", _unit]] call comspec_fb_fnc_log;
};

// Sursaut (réglage « Sursaut », comme le joueur) : l'IA à pied s'arrête net 0,3 à 0,6 s (forceSpeed 0, rendu ensuite par
// forceSpeed -1 : une vitesse imposée par une mission sur cette IA serait perdue), même si le bang ne l'éblouit ni ne
// l'assourdit ; pas de nouveau sursaut avant 3 s.
private _startle = ((_info getOrDefault ["startle", 0]) min 1) * ([0, 1] select (isNull objectParent _unit));
if (_startle > 0.15 && {alive _unit} && {missionNamespace getVariable ["comspec_fb_startle", true]} && {_now > (_unit getVariable ["comspec_fb_aiStartleEnd", 0])}) then {
    private _stopT = 0.3 + 0.3 * _startle;
    _unit setVariable ["comspec_fb_aiStartleEnd", _now + _stopT + 3];
    _unit forceSpeed 0;
    [{ if (alive _this) then { _this forceSpeed -1 } }, _unit, _stopT] call CBA_fnc_waitAndExecute;
};

// Intensité : vision, ou audition (0,85 : une IA sourde mais qui voit encore est déroutée, moins qu'éblouie).
private _scale = [_inten, 1] select _legacy;
private _vI = ((_vis * _scale) min 1) max 0;
private _s = (((_vis max (([0.85, 0.7] select _legacy) * _aud)) * _scale) min 1) max 0;
if (_s < 0.08 || {!alive _unit}) exitWith {};

// Durées. Réaliste : dégradé 5 à 20 s, aveugle 2 à 10 s (plafonds x réglage « Durée »), prolongées par les flashs
// suivants (jamais cumulées). Discrète : 3 à 12 s, plafond « Cumul max » comme avant.
private _pd = _info getOrDefault ["durMult", 1];
// Durée propre à chaque IA (+/-30 % à 60 % de « Variation aléatoire ») : aucune IA ne récupère pareil.
private _k = 1 + ([0.5] call comspec_fb_fnc_rand);
private _degr = if (_legacy) then { (3 + 9 * _s) * _pd * _k } else { (5 + 15 * _s) * _pd * _durK * _k };
private _cap = if (_legacy) then { (missionNamespace getVariable ["comspec_fb_maxStack", 15]) * (_pd max 1) } else { 20 * _durK * (_pd max 1) };
private _blind = if (!_legacy && {_vI >= 0.2}) then { (2 + 8 * _vI) * _pd * _durK * _k } else { 0 };
_blind = (_blind min (10 * _durK * (_pd max 1))) min _degr;
private _end = _unit getVariable ["comspec_fb_aiEnd", 0];
_end = ((_end max (_now + _degr)) min (_now + _cap));
_unit setVariable ["comspec_fb_aiEnd", _end];

// Compétences : valeurs d'origine mémorisées une seule fois ; réduction selon la crête d'intensité.
private _skills = ["aimingAccuracy", "aimingShake", "aimingSpeed", "spotDistance", "spotTime", "courage", "commanding"];
private _factors = if (_legacy) then { [0.85, 0.8, 0.75, 0.9, 0.85, 0.5, 0] } else { [0.95, 0.9, 0.9, 0.95, 0.9, 0.6, 0.7] };
private _st = _unit getVariable ["comspec_fb_aiSkills", []];
if (_st isEqualTo []) then { _st = [_skills, _skills apply { _unit skill _x }, []] };
private _orig = _st select 1;
private _peak = (_unit getVariable ["comspec_fb_aiPeak", 0]) max _s;
_unit setVariable ["comspec_fb_aiPeak", _peak];
{
    _unit setSkill [_x, (_orig select _forEachIndex) * (1 - (_factors select _forEachIndex) * _peak)];
} forEach _skills;
// Valeurs lues juste après : au retour, une compétence qui a changé depuis n'est pas écrasée (autre mod, mission).
_st set [2, _skills apply { _unit skill _x }];
_unit setVariable ["comspec_fb_aiSkills", _st];

// Hésitation : suppression (l'IA tire moins et cherche un abri), sans la figer.
_unit setSuppression (((getSuppression _unit) max (0.5 + 0.5 * _s)) min 1);
private _onFoot = isNull objectParent _unit;
private _awake = !(_unit getVariable ["ace_isunconscious", false]) && {lifeState _unit != "INCAPACITATED"};
private _react = "hésite";
private _feat = _unit getVariable ["comspec_fb_aiFeat", []];
// Échéance d'une fonction d'IA coupée (disableAI) ou d'un retour (« @pos » posture, « @anim » geste). Une fonction déjà
// coupée par autre chose est laissée telle quelle : ni touchée, ni rétablie.
private _holdAi = {
    params ["_name", "_until"];
    _until = _until min _end;
    private _pseudo = (_name select [0, 1]) == "@";
    private _i = _feat findIf { (_x select 0) == _name };
    if (_i >= 0) then {
        (_feat select _i) set [1, ((_feat select _i) select 1) max _until];
    } else {
        if (_pseudo || {_unit checkAIFeature _name}) then {
            if (!_pseudo) then { _unit disableAI _name };
            _feat pushBack [_name, _until];
        };
    };
};
private _still = 0;
if (!_legacy && {_awake}) then {
    // Aveuglée : plus de ciblage tant que dure l'éblouissement.
    if (_blind > 0) then {
        ["TARGET", _now + _blind] call _holdAi;
        ["AUTOTARGET", _now + _blind] call _holdAi;
        _react = "aveuglée";
    };
    // Désorientée : arrêt net (à pied seulement ; le conducteur d'un véhicule garde le volant).
    if (_onFoot && {_s > 0.35}) then {
        _still = (((0.8 + 2.2 * _s) * _k) min 3.5) max 0.5;
        ["MOVE", _now + _still] call _holdAi;
        _react = _react + " + arrêt";
    };
};
if (_onFoot) then {
    // Réflexe : détourner la tête d'un flash fort vu de face.
    if (_vI > 0.6) then {
        _unit setDir ((_unit getDir (ASLToAGL _pos)) + (60 + random 50) * selectRandom [-1, 1]);
    };
    private _v = missionNamespace getVariable ["comspec_fb_variation", 0.6];
    private _pReact = if (_legacy) then { _v * _s } else { 0.55 + 0.45 * _s };
    // Geste réflexe vanilla (mains au visage) : seulement si l'animation existe dans la config du jeu et que l'IA est
    // debout ou accroupie ; sinon elle se baisse comme avant. Rendue par fn_aiRestore / fn_aiTick (« @anim »).
    private _anims = ["Acts_CivilShocked_1", "Acts_CivilShocked_2"] select { isClass (configFile >> "CfgMovesMaleSdr" >> "States" >> _x) };
    if (!_legacy && {_animOn} && {_awake} && {_vI > 0.5} && {_anims isNotEqualTo []} && {(stance _unit) in ["STAND", "CROUCH"]}
        && {_still > 0} && {random 1 < 0.7} && {((toLower (animationState _unit)) find "acts_") != 0}) then {
        _unit playMoveNow (selectRandom _anims);
        ["@anim", _now + _still + 0.6] call _holdAi;
        _react = _react + " + mains au visage";
    } else {
        if (random 1 < _pReact) then {
            if (_legacy && {random 1 < 0.35} && {primaryWeapon _unit != ""} && {_vI > 0.5}) then {
                // Courte rafale réflexe au jugé (réaction « Discrète » seulement).
                _react = "rafale";
                for "_r" from 1 to (1 + floor random 4) do {
                    [{ if (alive _this) then { _this forceWeaponFire [primaryWeapon _this, "Single"] } }, _unit, _r * (0.12 + random 0.2)] call CBA_fnc_waitAndExecute;
                };
            } else {
                _react = _react + " + se couvre";
                if (isNil { _unit getVariable "comspec_fb_aiPos" }) then { _unit setVariable ["comspec_fb_aiPos", unitPos _unit] };
                private _newPos = selectRandom ["MIDDLE", "DOWN"];
                _unit setUnitPos _newPos;
                _unit setVariable ["comspec_fb_aiPosSet", _newPos];
                // Réaliste : posture rendue à la fin de l'aveuglement (3 s au moins), pas au bout des 20 s de
                // compétences réduites. Discrète : à la fin de l'effet, comme avant.
                ["@pos", [(_blind max _still max 2) + _now + 1, _end] select _legacy] call _holdAi;
            };
        };
    };
};
// Fort éblouissement : l'IA perd la trace des ennemis proches (elle devra les repérer à nouveau).
if (_vI > ([0.7, 0.45] select !_legacy)) then {
    { _unit forgetTarget _x } forEach (_unit targets [true, 120]);
};
_unit setVariable ["comspec_fb_aiFeat", _feat];

// Journal (réglage « Journal des IA », désactivé par défaut : rien n'est formaté sans lui).
if (missionNamespace getVariable ["comspec_fb_aiDebug", false] || {[true] call comspec_fb_fnc_logOn}) then {
    ["ia", format ["%1 force=%2 (vision %3, audition %4) mode=%5 aveugle %6 s, dégradée jusqu'à t=%7, arrêt %8 s, durée x%9, réaction %10", _unit, _s toFixed 2,
        _vis toFixed 2, _aud toFixed 2, _mode, _blind toFixed 1, _end toFixed 1, _still toFixed 1, _k toFixed 2, _react]] call comspec_fb_fnc_log;
};
// Planifie (ou avance) le prochain réveil de cette unité : fn_aiTick.
[_unit] call comspec_fb_fnc_aiTick;
