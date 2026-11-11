/*
    comspec_fb_fnc_aiHit : une détonation atteint une IA locale (serveur ou headless).
    Effet réaliste = désorientation, pas de statue : précision, vitesse de visée et capacité de repérage fortement
    réduites, suppression (l'IA hésite, cherche à se couvrir, tire moins), réflexe de détourner la tête si le flash est
    fort, cibles « oubliées » au-delà d'un fort éblouissement. Aucun disableAI, aucun changement de comportement.
    Durée : 3 s + 9 s x force (x préréglage), prolongée par les flashs suivants, plafonnée (réglage).
    Compétences d'origine mémorisées une seule fois sur l'unité puis rendues exactement (fn_aiRestore) ;
    unité supprimée : rien à rendre ; unité passée sur une autre machine : restauration envoyée à sa machine.
    [_unit, _vis, _aud, _posASL, _info] call comspec_fb_fnc_aiHit
*/
params ["_unit", "_vis", "_aud", "_pos", "_info"];
private _s = ((_vis max (0.7 * _aud)) min 1) max 0;
if (_s < 0.08 || {!alive _unit}) exitWith {};
private _now = CBA_missionTime;
private _pd = _info getOrDefault ["durMult", 1];
private _cap = (missionNamespace getVariable ["comspec_fb_maxStack", 15]) * (_pd max 1);
private _end = _unit getVariable ["comspec_fb_aiEnd", 0];
_end = ((_end max (_now + (3 + 9 * _s) * _pd)) min (_now + _cap));
_unit setVariable ["comspec_fb_aiEnd", _end];

private _skills = ["aimingAccuracy", "aimingShake", "aimingSpeed", "spotDistance", "spotTime", "courage"];
private _factors = [0.85, 0.8, 0.75, 0.9, 0.85, 0.5];
private _orig = _unit getVariable ["comspec_fb_aiSkills", []];
private _first = _orig isEqualTo [];
if (_first) then {
    _orig = _skills apply { _unit skill _x };
    _unit setVariable ["comspec_fb_aiSkills", _orig];
};
private _peak = (_unit getVariable ["comspec_fb_aiPeak", 0]) max _s;
_unit setVariable ["comspec_fb_aiPeak", _peak];
{
    _unit setSkill [_x, (_orig select _forEachIndex) * (1 - (_factors select _forEachIndex) * _peak)];
} forEach _skills;

// Hésitation : suppression (l'IA tire moins et cherche un abri), sans la figer.
_unit setSuppression (((getSuppression _unit) max (0.5 + 0.5 * _s)) min 1);
// Réflexe : détourner la tête d'un flash fort vu de face (à pied seulement).
if (_vis > 0.6 && {isNull objectParent _unit}) then {
    _unit setDir ((_unit getDir (ASLToAGL _pos)) + (60 + random 50) * selectRandom [-1, 1]);
};
// Fort éblouissement : l'IA perd la trace des ennemis proches (elle devra les repérer à nouveau).
if (_vis > 0.7) then {
    { _unit forgetTarget _x } forEach (_unit targets [true, 60]);
};
["ia", format ["%1 force=%2 (vision %3, audition %4) jusqu'à t=%5", _unit, _s toFixed 2, _vis toFixed 2, _aud toFixed 2, _end toFixed 1]] call comspec_fb_fnc_log;

if (_first) then {
    [{
        params ["_unit"];
        isNull _unit || {!alive _unit} || {CBA_missionTime >= (_unit getVariable ["comspec_fb_aiEnd", 0])}
    }, {
        params ["_unit"];
        if (isNull _unit) exitWith {};
        private _orig = _unit getVariable ["comspec_fb_aiSkills", []];
        if (local _unit) then {
            [_unit, _orig] call comspec_fb_fnc_aiRestore;
        } else {
            [_unit, _orig] remoteExecCall ["comspec_fb_fnc_aiRestore", _unit];
            { _unit setVariable [_x, nil] } forEach ["comspec_fb_aiSkills", "comspec_fb_aiPeak", "comspec_fb_aiEnd"];
        };
    }, [_unit]] call CBA_fnc_waitUntilAndExecute;
};
