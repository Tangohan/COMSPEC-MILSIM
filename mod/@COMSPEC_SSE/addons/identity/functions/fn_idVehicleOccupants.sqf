/*
    Contrôle d'un véhicule depuis la portière (menu ACE du véhicule).
    ["can", _veh]               -> Boolean : arrêté (< 2 km/h), occupé, moi à pied
    ["run", _veh, "vehicle"]    contrôle des occupants (2 s) : chaque IA répond, chaque joueur reçoit la demande
    ["run", _veh, "dismount"]   faire descendre (2 s) : les IA descendent, les joueurs sont prévenus
*/
params [["_op", ""], ["_v", objNull], ["_what", "vehicle"]];
if (isNull _v) exitWith { false };
if (_op isEqualTo "can") exitWith {
    alive _v && {isNull objectParent player} && {(abs (speed _v)) < 2} && {((crew _v) findIf { alive _x && {_x isNotEqualTo player} }) >= 0}
};
["send", ["begin", [player, _what, _v]]] call comspec_sse_fnc_idClient;
[2, ["Contrôle des occupants…", "Vous faites descendre les occupants…"] select (_what isEqualTo "dismount"), {
    params ["_v", "_what"];
    ["send", [_what, [player, _v]]] call comspec_sse_fnc_idClient;
}, [_v, _what], _v] call comspec_sse_fnc_progressAction
