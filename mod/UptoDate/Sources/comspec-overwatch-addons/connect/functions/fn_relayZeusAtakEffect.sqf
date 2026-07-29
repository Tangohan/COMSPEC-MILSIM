/*
    Relais serveur → client cible pour les effets Zeus ATAK (MP fiable).
    Params: [_target, _action, _durationSec]
*/
if (!isServer) exitWith {};

params [
    ["_target", objNull, [objNull]],
    ["_action", "", [""]],
    ["_durationSec", 30, [0]]
];

if (isNull _target || {!isPlayer _target}) exitWith {
    diag_log format ["[COMSPEC] relayZeusAtakEffect: cible invalide (%1)", _target];
};

private _owner = owner _target;
if (_owner <= 0) exitWith {
    diag_log format ["[COMSPEC] relayZeusAtakEffect: owner invalide pour %1", name _target];
};

[_action, _durationSec] remoteExecCall ["comspec_overwatch_connect_fnc_applyZeusAtakEffect", _target];

diag_log format [
    "[COMSPEC Overwatch][INFO][Zeus] Relais serveur → %1 : %2 (%3 s)",
    name _target,
    _action,
    round _durationSec
];
