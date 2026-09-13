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

// En solo, « owner » renvoie 0 pour toutes les unités : le contrôle de propriétaire
// rejetait donc tous les effets Zeus hors MP. On route sur la localité, qui est vraie
// dans les deux cas, et l’identifiant de propriétaire ne sert plus qu’au journal.
if (local _target) then {
    // Cible locale (solo, ou hôte non dédié qui joue l’unité) : appel direct.
    [_action, _durationSec] call comspec_overwatch_connect_fnc_applyZeusAtakEffect;
} else {
    private _owner = owner _target;
    if (_owner <= 0) then {
        diag_log format ["[COMSPEC] relayZeusAtakEffect: owner invalide pour %1", name _target];
    } else {
        [_action, _durationSec] remoteExecCall ["comspec_overwatch_connect_fnc_applyZeusAtakEffect", _target];
    };
};

diag_log format [
    "[COMSPEC Overwatch][INFO][Zeus] Relais serveur → %1 : %2 (%3 s)",
    name _target,
    _action,
    round _durationSec
];
