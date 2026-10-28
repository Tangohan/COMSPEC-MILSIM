/*
    Relevé biométrique d'une personne dans les conditions réelles du terrain.

    La qualité ne se tire plus au hasard : elle part du matériel, puis perd des
    points selon l'état du sujet (non maîtrisé, inconscient, mort depuis combien de
    temps), la nuit, la pluie, les lunettes ou la cagoule, l'état de ses doigts et
    ses blessures ACE. Au-delà du seuil réglé, une main, le visage ou l'iris trop
    endommagés ne peuvent plus être relevés du tout.

    Chaque tentative est numérotée : une recapture donne un autre résultat, mais
    toujours le même pour le même numéro (rejouable, identique pour tous).

    Params: [_target, _operator, _modality, _device, _dryRun]
      _modality  "empreintes" | "iris" | "visage" | "adn"
      _device    "seek" | "bii" | "kit" | "terminal"
      _dryRun    true : vérifie seulement si le relevé est possible (tentative non consommée)
    Returns: HashMap
      ok        Bool — false : relevé impossible (raison dans "blocked")
      quality   0-100 (qualité globale du relevé)
      nfiqs     10 notes NFIQ (1 excellent … 5 inexploitable ; 0 non relevé,
                -1 doigt amputé, -2 main trop blessée)
      eyes      [OD, OG] qualité 0-100 (-1 pas d'iris : prothèse ; -2 trop blessé)
      factors   facteurs lisibles qui ont pesé sur la qualité
      attempt   numéro de tentative ; labRef référence d'échantillon
*/
params [
    ["_target", objNull, [objNull]],
    ["_operator", objNull, [objNull]],
    ["_modality", "empreintes", [""]],
    ["_device", "terminal", [""]],
    ["_dryRun", false, [false]]
];

_modality = toLower _modality;
private _out = createHashMapFromArray [
    ["ok", false], ["blocked", ""], ["quality", 0], ["nfiqs", []], ["eyes", []],
    ["factors", []], ["attempt", 0], ["labRef", ""], ["key", ""], ["modality", _modality]
];
if (isNull _target) exitWith { _out set ["blocked", "Aucun sujet."]; _out };

private _key = [_target] call comspec_sse_fnc_bioPersonKey;
private _profile = [_target, _key] call comspec_sse_fnc_bioProfile;
private _strict = missionNamespace getVariable ["comspec_sse_bio_strictness", 1];
private _block = (missionNamespace getVariable ["comspec_sse_bio_injuryBlock", 1.2]) max 0.1;
_out set ["key", _key];

// Tentative suivante pour cette modalité (partagée : un autre opérateur continue la série).
private _attempts = +(_target getVariable ["comspec_sse_bioAttempts", []]);
if (!(_attempts isEqualType [])) then { _attempts = []; };
private _idx = _attempts findIf { (_x select 0) isEqualTo _modality };
private _attempt = 1;
if (_idx >= 0) then { _attempt = ((_attempts select _idx) select 1) + 1; _attempts set [_idx, [_modality, _attempt]]; } else { _attempts pushBack [_modality, 1]; };
if (!_dryRun) then { _target setVariable ["comspec_sse_bioAttempts", _attempts, true]; };
_out set ["attempt", _attempt];

private _r = { params ["_salt"]; [_key, format ["cap|%1|%2|%3", _modality, _attempt, _salt]] call comspec_sse_fnc_bioRand };
private _factors = [];
private _pen = 0;
private _addPen = {
    params ["_label", "_points"];
    private _p = _points * _strict;
    if (_p >= 1) then {
        _pen = _pen + _p;
        _factors pushBack format ["%1 (-%2)", _label, round _p];
    };
};

// --- Blessures (ACE, sinon points de vie vanilla) ---------------------------------
// ace_medical_bodyPartDamage : [tête, torse, bras G, bras D, jambe G, jambe D].
private _ace = _target getVariable ["ace_medical_bodyPartDamage", []];
if (!(_ace isEqualType []) || {(count _ace) < 4}) then { _ace = [0, 0, 0, 0]; };
private _hp = { params ["_name"]; private _d = _target getHitPointDamage _name; if (isNil "_d") then { 0 } else { _d } };
private _sevHead = (_ace select 0) max (((["HitFace"] call _hp) max (["HitHead"] call _hp)) * 1.5);
private _vHands = ((["HitHands"] call _hp) max (["HitArms"] call _hp)) * 1.5;
private _sevLeft = (_ace select 2) max _vHands;
private _sevRight = (_ace select 3) max _vHands;

// --- État du sujet --------------------------------------------------------------------
private _alive = alive _target;
private _deadH = 0;
if (!_alive) then {
    private _t = _target getVariable ["comspec_sse_bioDeathTime", -1];
    private _now = if (isNil "CBA_missionTime") then { time } else { CBA_missionTime };
    _deadH = if (_t isEqualType 0 && {_t >= 0}) then { ((_now - _t) max 0) / 3600 } else { 0.5 };
};
private _uncon = _alive && {(lifeState _target) isEqualTo "INCAPACITATED" || {_target getVariable ["ACE_isUnconscious", false]}};
private _restrained = _target getVariable ["ace_captives_isHandcuffed", false];
private _surrendered = _target getVariable ["ace_captives_isSurrendering", false];
private _cooperative = !_alive || _uncon || _restrained || _surrendered || {captive _target} || {(_profile getOrDefault ["sideId", 3]) == 3};

private _inside = false;
private _eye = eyePos _target;
if ((count (lineIntersectsSurfaces [_eye, _eye vectorAdd [0, 0, 12], _target, objNull, true, 1])) > 0) then { _inside = true; };
private _night = sunOrMoon < 0.5;
private _wet = (rain > 0.2) && !_inside;
private _goggles = toLower (goggles _target);

// --- Matériel ---------------------------------------------------------------------------
private _base = switch (toLower _device) do {
    case "seek": { 90 };
    case "bii": { 88 };
    case "kit": { [80, 86] select (_modality isEqualTo "iris") };
    default { 84 };
};
private _skill = 0;
if (!isNull _operator) then {
    private _s = _operator getVariable ["comspec_sse_bioSkill", 0.5];
    if (_s isEqualType 0) then { _skill = ((_s max 0) min 1) - 0.5; };
};
if (_skill > 0.1) then { _factors pushBack "Opérateur formé (+)"; };

switch (_modality) do {
    // ===================================================================== EMPREINTES
    case "empreintes": {
        if (!_alive) then {
            switch (true) do {
                case (_deadH < 1): { ["Sujet décédé (doigts encore souples)", 3] call _addPen; };
                case (_deadH < 6): { ["Rigidité cadavérique", 10] call _addPen; };
                case (_deadH < 24): { ["Rigidité et dessèchement", 20] call _addPen; };
                default { ["Décomposition", 35] call _addPen; };
            };
        };
        if (_uncon) then { ["Sujet inconscient", 3] call _addPen; };
        if (!_cooperative) then { ["Sujet non maîtrisé : doigts qui bougent", 15] call _addPen; };
        if (_wet) then { ["Doigts mouillés (pluie)", 12] call _addPen; };

        private _q = _base - _pen + (_skill * 10) + (((["glob"] call _r) - 0.5) * 10);
        private _fingers = _profile getOrDefault ["fingers", []];
        private _nfiqs = [];
        private _handNote = [false, false];
        for "_i" from 0 to 9 do {
            private _f = _fingers param [_i, ["", "", "normal"]];
            private _state = _f select 2;
            private _sev = [_sevRight, _sevLeft] select (_i >= 5);
            private _n = 0;
            switch (true) do {
                case (_state isEqualTo "ampute"): { _n = -1; };
                case (_sev >= _block): { _n = -2; };
                default {
                    private _qi = _q + (((["f" + str _i] call _r) - 0.5) * 14);
                    if (_state isEqualTo "use") then { _qi = _qi - 12 * (_strict max 0.5); };
                    if (_state isEqualTo "cicatrice") then { _qi = _qi - 8 * (_strict max 0.5); };
                    if (_sev >= _block * 0.35) then {
                        _qi = _qi - (25 * (_sev / _block) * _strict);
                        _handNote set [[0, 1] select (_i >= 5), true];
                    };
                    _n = switch (true) do {
                        case (_qi >= 88): { 1 };
                        case (_qi >= 78): { 2 };
                        case (_qi >= 66): { 3 };
                        case (_qi >= 50): { 4 };
                        default { 5 };
                    };
                };
            };
            _nfiqs pushBack _n;
        };
        if (_handNote select 0) then { _factors pushBack "Sang / plaies main droite"; };
        if (_handNote select 1) then { _factors pushBack "Sang / plaies main gauche"; };
        if (_sevRight >= _block) then { _factors pushBack "Main droite trop blessée : non relevable"; };
        if (_sevLeft >= _block) then { _factors pushBack "Main gauche trop blessée : non relevable"; };
        private _amp = { _x == -1 } count _nfiqs;
        if (_amp > 0) then { _factors pushBack format ["%1 doigt(s) amputé(s)", _amp]; };
        if ((_profile getOrDefault ["worker", false]) && {({ (_x select 2) isEqualTo "use" } count _fingers) > 0}) then {
            _factors pushBack "Crêtes usées (travail manuel)";
        };

        private _usable = _nfiqs select { _x >= 1 };
        if ((count _usable) == 0) exitWith {
            _out set ["blocked", "Aucun doigt relevable : mains trop blessées ou absentes."];
            _out set ["nfiqs", _nfiqs];
        };
        private _score = 0;
        { _score = _score + ([0, 95, 85, 70, 50, 25] select _x); } forEach _usable;
        _out set ["quality", round (_score / (count _usable))];
        _out set ["nfiqs", _nfiqs];
        _out set ["ok", true];
    };

    // ===================================================================== IRIS
    case "iris": {
        // Capté en proche infrarouge : la nuit ne gêne pas, les lunettes et la mort si.
        if (["is", _target] call comspec_sse_fnc_headBag) exitWith {
            _out set ["blocked", "Sac de tête en place : retirez-le pour relever l'iris."];
        };
        if (_sevHead >= _block * 1.25) exitWith {
            _out set ["blocked", "Visage et yeux trop endommagés : iris non relevable."];
        };
        if (!_alive) then {
            if (_deadH >= 4) exitWith { _out set ["blocked", "Décès trop ancien : cornée opacifiée, iris non exploitable."]; };
            switch (true) do {
                case (_deadH < 0.5): { ["Sujet décédé récemment", 5] call _addPen; };
                default { ["Cornée qui s'opacifie", 25] call _addPen; };
            };
        };
        if ((_out get "blocked") isNotEqualTo "") exitWith {};
        if (_uncon) then { ["Paupières à maintenir ouvertes", 12] call _addPen; };
        if (!_cooperative) then { ["Sujet non maîtrisé : regard fuyant", 18] call _addPen; };
        if (_goggles isNotEqualTo "" && {(_goggles find "beard") < 0} && {(_goggles find "balaclava") < 0} && {(_goggles find "bandan") < 0}) then {
            ["Lunettes à retirer", 10] call _addPen;
        };
        if (_sevHead >= _block * 0.35) then { ["Blessure au visage", 20 * (_sevHead / _block)] call _addPen; };

        private _q = _base - _pen + (_skill * 10);
        private _eyes = [];
        {
            private _state = _x select 2;
            private _qe = _q + (((["e" + str _forEachIndex] call _r) - 0.5) * 12);
            if (_state isEqualTo "cataracte") then { _qe = _qe - 30; };
            if (_state isEqualTo "lesion") then { _qe = _qe - 40; };
            _eyes pushBack (if (_state isEqualTo "prothese") then { -1 } else { round ((_qe max 5) min 99) });
        } forEach (_profile getOrDefault ["eyes", [["", "", "normal"], ["", "", "normal"]]]);
        { if ((_x select 2) isEqualTo "cataracte") exitWith { _factors pushBack "Cataracte"; }; } forEach (_profile getOrDefault ["eyes", []]);
        if (-1 in _eyes) then { _factors pushBack "Prothèse oculaire : un seul iris"; };
        if ((_eyes select { _x >= 0 }) isEqualTo []) exitWith { _out set ["blocked", "Aucun iris exploitable."]; _out set ["eyes", _eyes]; };
        private _valid = _eyes select { _x >= 0 };
        private _sum = 0;
        { _sum = _sum + _x; } forEach _valid;
        _out set ["quality", round (_sum / (count _valid))];
        _out set ["eyes", _eyes];
        _out set ["ok", true];
    };

    // ===================================================================== VISAGE
    case "visage": {
        if (["is", _target] call comspec_sse_fnc_headBag) exitWith {
            _out set ["blocked", "Sac de tête en place : retirez-le pour photographier le visage."];
        };
        if (_sevHead >= _block) exitWith {
            _out set ["blocked", "Visage trop endommagé pour une comparaison faciale."];
        };
        if ((_goggles find "balaclava") >= 0) then { ["Cagoule à retirer", 45] call _addPen; };
        if ((_goggles find "bandan") >= 0 || {(_goggles find "shemag") >= 0} || {(_goggles find "scarf") >= 0}) then { ["Visage partiellement couvert", 25] call _addPen; };
        if ((headgear _target) isNotEqualTo "") then { ["Couvre-chef", 4] call _addPen; };
        if (_night && !_inside) then { ["Nuit : flash du terminal", 8] call _addPen; };
        if (!_alive && {_deadH >= 6}) then { ["Traits altérés (décès ancien)", 15] call _addPen; };
        if (!_cooperative) then { ["Sujet qui détourne la tête", 10] call _addPen; };
        if (_sevHead >= _block * 0.35) then { ["Blessure au visage", 25 * (_sevHead / _block)] call _addPen; };
        _out set ["quality", round (((_base - _pen + (_skill * 8)) max 5) min 99)];
        _out set ["penalty", round _pen];
        _out set ["ok", true];
    };

    // ===================================================================== ADN
    default {
        if (!_alive && {_deadH >= 48}) then { ["ADN dégradé (décès ancien)", 20] call _addPen; };
        if (_wet) then { ["Écouvillon humide", 4] call _addPen; };
        _out set ["quality", round (((92 - _pen + (((["d"] call _r) - 0.5) * 8)) max 20) min 99)];
        _out set ["ok", true];
    };
};

_out set ["factors", _factors];
private _pfx = switch (_modality) do { case "iris": { "IRI" }; case "adn": { "ADN" }; case "visage": { "VIS" }; default { "EMP" }; };
private _n = ([format ["%1|%2|%3", _key, _modality, _attempt]] call comspec_sse_fnc_bioHash) mod 9000;
_out set ["labRef", format ["LAB-%1-%2%3", date select 0, _pfx, 1000 + _n]];
_out
