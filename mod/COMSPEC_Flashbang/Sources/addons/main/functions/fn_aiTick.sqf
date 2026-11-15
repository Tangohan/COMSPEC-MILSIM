/*
    comspec_fb_fnc_aiTick : réveil d'une IA touchée (une seule échéance valide par unité, variable comspec_fb_aiTickAt).
    Sans date (appel de fn_aiHit) : calcule la prochaine échéance et la planifie si elle est plus proche que celle déjà prévue.
    Avec date (réveil planifié) : rétablit ce qui est échu ; à la fin de l'effet, ou si l'IA est morte, appelle fn_aiRestore ;
    si l'IA est passée sur une autre machine, lui envoie la restauration complète (événement CBA ciblé : l'unité n'est
    plus locale ici). IA inconsciente (ACE Médical, KAT) : le rétablissement attend (5 s, 12 fois au plus) que sa machine
    ne la voie plus inconsciente, pour ne pas défaire ce que la médecine a coupé. Au plus 40 réveils, aucune attente non bornée.
    [_unit, _date] call comspec_fb_fnc_aiTick
*/
params [["_unit", objNull, [objNull]], ["_due", -1, [0]]];
if (isNull _unit) exitWith {};
private _plan = _due < 0;
if (!_plan && {(_unit getVariable ["comspec_fb_aiTickAt", -1]) isNotEqualTo _due}) exitWith {};
private _now = CBA_missionTime;
private _end = _unit getVariable ["comspec_fb_aiEnd", 0];
private _wakes = (_unit getVariable ["comspec_fb_aiWakes", 0]) + ([1, 0] select _plan);
if (!_plan && {!local _unit}) exitWith {
    ["comspec_fb_aiRestore", [_unit, [_unit] call comspec_fb_fnc_aiState], _unit] call CBA_fnc_targetEvent;
    {
        _unit setVariable [_x, nil];
    } forEach ["comspec_fb_aiSkills", "comspec_fb_aiPeak", "comspec_fb_aiEnd", "comspec_fb_aiFeat", "comspec_fb_aiPos", "comspec_fb_aiPosSet", "comspec_fb_aiTickAt", "comspec_fb_aiRetry", "comspec_fb_aiWakes"];
};
private _asleep = (_unit getVariable ["ace_isunconscious", false]) || {lifeState _unit == "INCAPACITATED"};
private _retry = _unit getVariable ["comspec_fb_aiRetry", 0];
private _over = !_plan && {_wakes > 40 || {!alive _unit} || {_now >= _end - 0.02}};
if (_over && {_asleep} && {alive _unit} && {_retry < 12} && {_wakes <= 40}) then {
    // Inconsciente : on attend encore avant de rétablir.
    _retry = _retry + 1;
    _unit setVariable ["comspec_fb_aiRetry", _retry];
    _over = false;
    _end = _now + 5;
    _unit setVariable ["comspec_fb_aiEnd", _end];
};
if (_over) exitWith {
    [_unit, [_unit] call comspec_fb_fnc_aiState] call comspec_fb_fnc_aiRestore;
};
if (!_plan) then {
    _unit setVariable ["comspec_fb_aiWakes", _wakes];
    private _keep = [];
    {
        _x params ["_name", "_until"];
        if (_now < _until - 0.02) then {
            _keep pushBack _x;
        } else {
            switch (true) do {
                case (_name == "@pos"): {
                    private _pos = _unit getVariable ["comspec_fb_aiPos", ""];
                    private _posSet = _unit getVariable ["comspec_fb_aiPosSet", ""];
                    if (_pos isNotEqualTo "" && {toLower (unitPos _unit) == toLower _posSet}) then { _unit setUnitPos (toUpper _pos) };
                    _unit setVariable ["comspec_fb_aiPos", nil];
                    _unit setVariable ["comspec_fb_aiPosSet", nil];
                };
                case (_name == "@anim"): {
                    if (alive _unit && {((toLower (animationState _unit)) find "acts_civilshocked") == 0}) then {
                        _unit playMoveNow (["AmovPercMstpSnonWnonDnon", "AmovPercMstpSrasWrflDnon"] select (primaryWeapon _unit != ""));
                    };
                };
                default {
                    if (_asleep && {_retry < 12}) then {
                        _keep pushBack [_name, _now + 5];
                        _unit setVariable ["comspec_fb_aiRetry", _retry + 1];
                    } else {
                        _unit enableAI _name;
                    };
                };
            };
        };
    } forEach (_unit getVariable ["comspec_fb_aiFeat", []]);
    _unit setVariable ["comspec_fb_aiFeat", _keep];
};
// Prochaine échéance : la plus proche entre la fin de l'effet et celle des fonctions encore coupées.
private _next = _end;
{ _next = _next min (_x select 1) } forEach (_unit getVariable ["comspec_fb_aiFeat", []]);
_next = _next max (_now + 0.05);
private _at = _unit getVariable ["comspec_fb_aiTickAt", -1];
if (_plan && {_at > _now} && {_at <= _next + 0.02}) exitWith {};
_unit setVariable ["comspec_fb_aiTickAt", _next];
[comspec_fb_fnc_aiTick, [_unit, _next], _next - _now] call CBA_fnc_waitAndExecute;
