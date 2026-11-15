/*
    État médical d'une personne pour la biométrie : ce que ses blessures permettent de relever.

    Lecture défensive, sans dépendance dure : ACE Medical d'abord (variables ace_medical_*), puis ce que KAT Advanced
    Medical ajoute par-dessus (même modèle de blessures), puis les points de vie vanilla (getHitPointDamage) quand
    aucun système médical n'est chargé. Toute forme inattendue rend « rien à signaler » plutôt qu'une erreur.
    « ACM » n'est défini nulle part dans ce dépôt : le mod est reconnu par CfgPatches (acm_*), et ses données sont lues
    par les mêmes variables ace_medical_* ; sans elles, repli vanilla.

    [_unit] call comspec_sse_fnc_bioMedical -> HashMap
      "source"   "ace" | "kat" | "acm" | "vanilla"
      "enabled"  false si le réglage comspec_seek_fx_medical est décoché (tout est alors neutre)
      "hand"     [droite, gauche] : chacune [état, points, motif]
                   état  "ok" | "degrade" | "bloque" ; points = pénalité de qualité (0-60) ; motif = texte lisible
      "head"     [état, points, motif] pour le visage et les yeux
      "tremor"   0..1 : tremblement des bras (pour les mini-jeux de l'opérateur)
      "uncon" "dead"  Booléens (information : la capture reste possible, avec mention)
*/
params [["_unit", objNull, [objNull]]];

private _ok = ["ok", 0, ""];
private _out = createHashMapFromArray [
    ["source", "vanilla"], ["enabled", true], ["hand", [+_ok, +_ok]], ["head", +_ok], ["tremor", 0],
    ["uncon", false], ["dead", false]
];
if (isNull _unit) exitWith { _out };
if !(missionNamespace getVariable ["comspec_seek_fx_medical", true]) exitWith { _out set ["enabled", false]; _out };

_out set ["dead", !alive _unit];
_out set ["uncon", alive _unit && {(lifeState _unit) isEqualTo "INCAPACITATED" || {_unit getVariable ["ACE_isUnconscious", false]}}];

private _src = "vanilla";
if (isClass (configFile >> "CfgPatches" >> "ace_medical")) then { _src = "ace"; };
if (isClass (configFile >> "CfgPatches" >> "kat_main") || {isClass (configFile >> "CfgPatches" >> "kat_advancedMedical")} || {isClass (configFile >> "CfgPatches" >> "kat_medical")}) then { _src = "kat"; };
if (isClass (configFile >> "CfgPatches" >> "acm_main") || {isClass (configFile >> "CfgPatches" >> "acm_core")} || {isClass (configFile >> "CfgPatches" >> "acm_medical")}) then { _src = "acm"; };
_out set ["source", _src];

// Parties : 0 tête, 1 torse, 2 bras gauche, 3 bras droit, 4 jambe gauche, 5 jambe droite (ordre ACE).
private _dmg = _unit getVariable ["ace_medical_bodyPartDamage", []];
if (!(_dmg isEqualType []) || {(count _dmg) < 4}) then { _dmg = [0, 0, 0, 0, 0, 0]; };
private _hp = { params ["_n"]; private _d = _unit getHitPointDamage _n; if (isNil "_d") then { 0 } else { _d } };
private _v = ((["HitHands"] call _hp) max (["HitArms"] call _hp)) * 1.5;
private _sev = [
    ((_dmg param [0, 0]) max (((["HitFace"] call _hp) max (["HitHead"] call _hp)) * 1.5)),
    0,
    ((_dmg param [2, 0]) max _v),
    ((_dmg param [3, 0]) max _v)
];

private _tq = _unit getVariable ["ace_medical_tourniquets", []];
if (!(_tq isEqualType [])) then { _tq = []; };
private _fr = _unit getVariable ["ace_medical_fractures", []];
if (!(_fr isEqualType [])) then { _fr = []; };

// Nombre de plaies par partie du corps dans une table ACE (HashMap récent ou Array ancien) : [ouvertes, brûlures].
private _countWounds = {
    params ["_name", "_part"];
    private _w = _unit getVariable [_name, nil];
    private _n = 0;
    private _burn = 0;
    private _each = {
        params ["_e"];
        if ((_e isEqualType []) && {(count _e) >= 2} && {(_e select 1) isEqualTo _part}) then {
            _n = _n + 1;
            private _cls = _e select 0;
            private _names = missionNamespace getVariable ["ace_medical_damage_woundDetails", []];
            if (_cls isEqualType 0 && {_names isEqualType []} && {_cls >= 0} && {_cls < count _names}) then {
                private _d = _names select _cls;
                if ((_d isEqualType []) && {(count _d) > 0} && {(toLower str (_d select 0)) find "burn" >= 0}) then { _burn = _burn + 1; };
            };
        };
    };
    if (!isNil "_w") then {
        if (_w isEqualType createHashMap) then { { [_y] call _each; } forEach _w; };
        if (_w isEqualType []) then { { [_x] call _each; } forEach _w; };
    };
    [_n, _burn]
};

private _tremor = 0;
// Mains : droite = bras 3, gauche = bras 2 (le tableau de sortie est [droite, gauche]).
private _hands = [];
{
    _x params ["_part", "_side"];
    private _s = _sev select _part;
    private _tqv = _tq param [_part, 0];
    private _frv = _fr param [_part, 0];
    private _open = ["ace_medical_openWounds", _part] call _countWounds;
    private _bnd = ["ace_medical_bandagedWounds", _part] call _countWounds;
    private _state = "ok";
    private _pts = 0;
    private _why = "";
    private _rank = { params ["_s"]; ["ok", "degrade", "bloque"] find _s };
    private _bump = {
        params ["_st", "_p", "_t"];
        private _r1 = [_st] call _rank;
        private _r0 = [_state] call _rank;
        if (_r1 > _r0 || {_r1 == _r0 && {_p > _pts}}) then { _state = _st; _pts = _p; _why = _t; };
    };
    if (_tqv isEqualType 0 && {_tqv > 0}) then { ["bloque", 60, "garrot posé"] call _bump; };
    if (_frv isEqualType 0 && {_frv < 0}) then { ["bloque", 60, "attelle posée"] call _bump; };
    if (_frv isEqualType 0 && {_frv > 0}) then { ["degrade", 35, "fracture"] call _bump; };
    if ((_open select 1) > 0) then { ["degrade", 45, "brûlure"] call _bump; };
    if ((_bnd select 0) > 0) then { ["degrade", 30, "bandage"] call _bump; };
    if ((_open select 0) > 0) then { ["degrade", 15 + 5 * ((_open select 0) min 4), "blessure ouverte"] call _bump; };
    if (_s >= 1.2) then { ["bloque", 60, "blessure grave"] call _bump; };
    if (_s >= 0.4 && {_state isEqualTo "ok"}) then { ["degrade", round (25 * _s), "blessure"] call _bump; };
    _tremor = _tremor max (((_s min 1) * 0.8) + ([0, 0.2] select (_state isEqualTo "degrade")));
    _hands set [_side, [_state, round _pts, _why]];
} forEach [[3, 0], [2, 1]];
_out set ["hand", _hands];
_out set ["tremor", (_tremor min 1) max 0];

// Tête : bandage, blessure ouverte, brûlure, sévérité.
private _hs = _sev select 0;
private _hOpen = ["ace_medical_openWounds", 0] call _countWounds;
private _hBnd = ["ace_medical_bandagedWounds", 0] call _countWounds;
private _head = ["ok", 0, ""];
private _rankH = { params ["_s"]; ["ok", "degrade", "bloque"] find _s };
private _setHead = {
    params ["_st", "_p", "_t"];
    private _r1 = [_st] call _rankH;
    private _r0 = [_head select 0] call _rankH;
    if (_r1 > _r0 || {_r1 == _r0 && {_p > (_head select 1)}}) then { _head = [_st, _p, _t]; };
};
if (_hs >= 1.5) then { ["bloque", 70, "blessure grave au visage"] call _setHead; };
if ((_hOpen select 1) > 0) then { ["degrade", 40, "brûlure au visage"] call _setHead; };
if ((_hBnd select 0) > 0) then { ["degrade", 35, "bandage de tête"] call _setHead; };
if ((_hOpen select 0) > 0) then { ["degrade", 20, "plaie au visage"] call _setHead; };
if (_hs >= 0.4 && {(_head select 0) isEqualTo "ok"}) then { ["degrade", round (25 * _hs), "blessure à la tête"] call _setHead; };
_out set ["head", _head];

_out
