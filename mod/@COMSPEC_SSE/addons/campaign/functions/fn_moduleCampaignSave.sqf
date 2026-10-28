/*
    Module Eden « Sauvegarder la campagne » : à synchroniser avec un déclencheur
    (fin d'objectif, retour à la base). Une campagne temporaire est vidée à la place.
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
if (!isServer || {!_activated}) exitWith { false };
private _what = _logic getVariable ["Action", "save"];
if (_what isEqualTo "end") exitWith { ["end", []] call comspec_sse_fnc_campaignOp };
["save", [_logic getVariable ["Reason", "déclencheur de mission"]]] call comspec_sse_fnc_campaignOp
