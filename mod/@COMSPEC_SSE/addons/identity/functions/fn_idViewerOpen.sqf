/*
    Ouvre la visionneuse sur un document imprimé reçu du serveur (jamais la vérité).
    [_doc, _own, _auto] call comspec_sse_fnc_idViewerOpen
      _own  : mon propre document (lecture seule, pas de bouton « Scanner avec l'ATAK » ni « Photographier »)
      _auto : relevé fait tout seul à l'ouverture, puis retour à l'app Contrôle ID (bouton Relever de l'app)
      _subj : personne qui a tendu le document (relevé de contrôle)
    Le téléphone est rangé le temps de la lecture (la visionneuse garde la souris).
*/
params [["_doc", createHashMap, [createHashMap]], ["_own", false], ["_auto", false], ["_subj", objNull, [objNull]]];
if (!hasInterface || {(count _doc) isEqualTo 0}) exitWith { false };
private _res = (["current"] call comspec_sse_fnc_idHistory) getOrDefault ["levels", createHashMap];
uiNamespace setVariable ["comspec_sse_idView", createHashMapFromArray [
    ["doc", _doc], ["own", _own], ["side", "recto"], ["zoom", false], ["auto", _auto && {!_own}], ["shot", false], ["subj", _subj], ["status", ""],
    ["n0", if (_own) then { createHashMap } else { _res getOrDefault [0, createHashMap] }]
]];
if (!isNull ([] call comspec_atak_native_fnc_display)) then { [] call comspec_atak_native_fnc_close; };
[{ closeDialog 0; createDialog "COMSPEC_SSE_IdViewer"; }] call CBA_fnc_execNextFrame;
true
