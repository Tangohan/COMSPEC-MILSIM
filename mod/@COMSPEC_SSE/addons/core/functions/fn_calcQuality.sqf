/*
    Calcule un score de qualité 0-100.
    [_base, _hasKit, _durationFactor, _objectCondition, _degradeFactor, _operator, _target, _kind] call comspec_sse_fnc_calcQuality

    _degradeFactor (optionnel) : 0..1 — dégradation custody / manipulation / EOD (1 = intact).
    _operator, _target, _kind (optionnels) : avec un opérateur, la qualité dépend du terrain au lieu des seules
    constantes de l'appelant (qui donnaient toujours le même score, et des seuils qui ne basculaient jamais) :
      - gants portés (sans gants : contamination par l'opérateur) ;
      - éclairage : nuit sans lampe ni vision nocturne ;
      - pluie, à découvert ;
      - état de la cible (blessure ou dégâts ; corps : temps écoulé depuis la mort) ;
      - compétence : infirmier / médecin ACE pour la biométrie, ingénieur pour le numérique ;
      - temps déjà passé sur la cible (familiarité) ;
      - nombre de tentatives du même geste sur la même cible (chaque reprise manipule la trace) ;
      - un léger aléa (main, angle, poussière).
    _kind : "collect", "inspect", "search", "documents", "photo", "radio", "digital", "bio"… (clé des tentatives).
*/
params [
    ["_base", 50, [0]],
    ["_hasKit", true, [true]],
    ["_durationFactor", 1, [0]],
    ["_objectCondition", 1, [0]],
    ["_degradeFactor", 1, [0]],
    ["_operator", objNull, [objNull]],
    ["_target", objNull, [objNull]],
    ["_kind", "", [""]]
];

private _q = _base;
if (_hasKit) then { _q = _q + 20 } else { _q = _q - 15 };

if (!isNull _operator) then {
    _kind = toLower _kind;
    private _digital = _kind in ["digital", "radio", "device", "computer"];

    // Gants : un relevé ou une collecte à mains nues laisse les traces de l'opérateur.
    if (!_digital) then {
        _q = _q + ([-10, 4] select ([_operator, "gloves"] call comspec_sse_fnc_hasEquipment));
    };

    // Éclairage : soleil ou lune ; la nuit, une lampe (arme ou casque) ou la vision nocturne compensent en partie.
    private _light = sunOrMoon max (moonIntensity * 0.35);
    if (_light < 0.4 && {!_digital}) then {
        private _lamp = (_operator isFlashlightOn (currentWeapon _operator)) || {(hmd _operator) isNotEqualTo "" && {currentVisionMode _operator > 0}};
        _q = _q - ([18, 7] select _lamp) * (1 - (_light / 0.4));
    };

    // Pluie, si la cible est à découvert (rien au-dessus d'elle sur 12 m).
    if (rain > 0.1 && {!_digital}) then {
        private _ref = [_operator, _target] select (!isNull _target);
        private _p = getPosASL _ref;
        private _covered = (lineIntersectsSurfaces [_p vectorAdd [0, 0, 1.5], _p vectorAdd [0, 0, 12], _ref, _operator, true, 1]) isNotEqualTo [];
        if (!_covered) then { _q = _q - 15 * rain; };
    };

    // État de la cible : blessure ou dégâts ; un corps se dégrade avec le temps.
    if (!isNull _target) then {
        _q = _q * (1 - 0.35 * ((damage _target) min 1));
        private _died = _target getVariable ["comspec_sse_bioDeathTime", -1];
        if (_died isEqualType 0 && {_died >= 0} && {!alive _target}) then {
            private _now = [time, CBA_missionTime] select (!isNil "CBA_missionTime");
            _q = _q - ((((_now - _died) / 60) * 0.1) min 20);
        };
    };

    // Compétence : infirmier (trait du jeu) ou classe médicale ACE pour la biométrie, ingénieur pour le numérique.
    if (_digital) then {
        if (_operator getUnitTrait "engineer") then { _q = _q + 6; };
    } else {
        private _medic = (_operator getVariable ["ace_medical_medicClass", [0, 1] select (_operator getUnitTrait "medic")]) max 0;
        if (_kind in ["bio", "collect", "inspect", "dogtag"]) then { _q = _q + 4 * (_medic min 2); };
    };

    if (!isNull _target) then {
        // Temps déjà passé sur la cible (actions réussies, en s) : familiarité, jusqu'à +8.
        _q = _q + (((_target getVariable ["comspec_sse_workTime", 0]) / 30) min 8);
        // Tentatives du même geste : la première est la meilleure, chaque reprise coûte 4 points (20 au plus).
        if (_kind isNotEqualTo "") then {
            private _var = format ["comspec_sse_tries_%1", _kind];
            private _n = _target getVariable [_var, 0];
            _q = _q - ((_n * 4) min 20);
            _target setVariable [_var, _n + 1, true];
        };
    };

    // Doctrine de collecte : pièce photographiée en place puis marquée d'un cône avant d'être déplacée.
    if (_kind isEqualTo "collect" && {!isNull _target}) then {
        if (_target getVariable ["comspec_sse_photoInSitu", false]) then { _q = _q + 8; } else { _q = _q - 6; };
        if ((_target getVariable ["comspec_sse_markerNo", 0]) > 0) then { _q = _q + 4; };
    };

    // Aléa de geste.
    _q = _q + (random 8) - 4;
};

_q = _q * (0.6 + 0.4 * (_durationFactor min 1.5));
_q = _q * (_objectCondition max 0.2 min 1.2);
_q = _q * ((_degradeFactor max 0.15) min 1);
_q = (_q max 5) min 99;
round _q
