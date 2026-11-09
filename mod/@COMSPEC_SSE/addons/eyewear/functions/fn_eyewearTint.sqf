/*
    Option « Rendu visuel des verres » (réglage CBA comspec_sse_eyewear_tint, coupé par défaut) : l'écran prend la teinte
    du verre porté par le joueur, quel que soit le slot (ppEffect ColorCorrections propre au verre, config tint[]).
    Appelée toutes les 0,5 s (XEH_postInitClient) et au changement de vue ou de mode de vision ; transition de 0,5 s.

    Coupée : JVN ou thermique allumées (currentVisionMode), vue extérieure (la caméra n'est plus derrière les verres ;
    choix de réalisme, la teinte resterait sinon collée à l'écran en vue à la troisième personne), Zeus, caméra sur une
    autre unité, joueur mort. Renvoie l'id du verre appliqué ("" si aucun).
*/
private _want = "";
if (missionNamespace getVariable ["comspec_sse_eyewear_tint", false]) then {
    private _id = ([player] call comspec_sse_fnc_eyewearOf) select 0;
    if (_id isNotEqualTo ""
        && {alive player}
        && {(currentVisionMode player) isEqualTo 0}
        && {cameraView in ["INTERNAL", "GUNNER"]}
        && {isNull curatorCamera}
        && {cameraOn isEqualTo (vehicle player)}) then {
        _want = _id;
    };
};
private _force = ((missionNamespace getVariable ["comspec_sse_eyewear_tint_force", 0.6]) max 0) min 1;
private _key = format ["%1|%2", _want, _force];
if (_key isEqualTo (missionNamespace getVariable ["comspec_sse_eyewear_tintKey", ""])) exitWith { _want };
missionNamespace setVariable ["comspec_sse_eyewear_tintKey", _key];
missionNamespace setVariable ["comspec_sse_eyewear_tintOn", _want];

private _pp = missionNamespace getVariable ["comspec_sse_eyewear_tintPP", -1];
if (_want isEqualTo "") exitWith {
    if (_pp >= 0) then {
        _pp ppEffectAdjust [1, 1, 0, [0, 0, 0, 0], [1, 1, 1, 1], [0.299, 0.587, 0.114, 0]];
        _pp ppEffectCommit 0.5;
        // Effet coupé après la transition, si aucun verre n'a été remis entre-temps.
        [{
            params ["_pp"];
            if ((missionNamespace getVariable ["comspec_sse_eyewear_tintOn", ""]) isEqualTo "") then { _pp ppEffectEnable false; };
        }, [_pp], 0.6] call CBA_fnc_waitAndExecute;
    };
    ""
};

if (_pp < 0) then {
    private _prio = 1553;
    _pp = ppEffectCreate ["ColorCorrections", _prio];
    while { _pp < 0 && {_prio < 1600} } do {
        _prio = _prio + 1;
        _pp = ppEffectCreate ["ColorCorrections", _prio];
    };
    missionNamespace setVariable ["comspec_sse_eyewear_tintPP", _pp];
};
if (_pp < 0) exitWith { "" };

private _e = (([] call comspec_sse_fnc_eyewearCatalog) select 0) getOrDefault [_want, createHashMap];
(_e getOrDefault ["tint", []]) params [["_bright", 1], ["_contrast", 1], ["_mix", [0, 0, 0, 0]], ["_sat", 1]];
_mix params [["_mr", 0], ["_mg", 0], ["_mb", 0], ["_ma", 0]];
private _params = [
    1 + (_bright - 1) * _force,
    1 + (_contrast - 1) * _force,
    0,
    [_mr, _mg, _mb, _ma * _force],
    [1, 1, 1, 1 + (_sat - 1) * _force],
    [0.299, 0.587, 0.114, 0]
];
_pp ppEffectEnable true;
_pp ppEffectAdjust _params;
_pp ppEffectCommit 0.5;
missionNamespace setVariable ["comspec_sse_eyewear_tintParams", _params];
_want
