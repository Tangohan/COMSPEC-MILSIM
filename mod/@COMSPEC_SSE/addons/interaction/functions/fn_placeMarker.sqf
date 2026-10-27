/*
    Pose un cône de preuve numéroté au pied d'une pièce (jeu de cônes du kit, 12 par jeu).
    Le numéro suit l'ordre du site (compteur serveur) ; il figure sur la carte (marqueur « #n »),
    dans la fiche (section evidenceState) et au journal de la pièce. Une pièce conée puis photographiée
    avant d'être déplacée garde la meilleure qualité de collecte.
    [_target, _player] call comspec_sse_fnc_placeMarker
*/
params [["_target", objNull, [objNull]], ["_player", player, [objNull]]];
if (isNull _target) exitWith { false };
if ((_target getVariable ["comspec_sse_markerNo", 0]) > 0) exitWith {
    [format ["Pièce déjà marquée (cône n°%1).", _target getVariable ["comspec_sse_markerNo", 0]], "WARNING"] call comspec_sse_fnc_notify;
    false
};

[3, "Pose du cône de preuve...", {
    params ["_target", "_player"];
    // Pièce marquée par un équipier pendant la pose : le serveur refuserait le cône, l'usage du kit n'est pas consommé.
    if ((_target getVariable ["comspec_sse_markerNo", 0]) > 0) exitWith {
        [format ["Pièce déjà marquée (cône n°%1).", _target getVariable ["comspec_sse_markerNo", 0]], "WARNING"] call comspec_sse_fnc_notify;
    };
    if (([_player, "COMSPEC_SSE_EvidenceMarkers", 12] call comspec_sse_fnc_kitUse) isEqualTo 0) exitWith {
        ["Jeu de cônes de preuve requis.", "WARNING"] call comspec_sse_fnc_notify;
    };
    ["comspec_sse_placeMarker", [_target, _player]] call CBA_fnc_serverEvent;
}, [_target, _player]] call comspec_sse_fnc_progressAction;
true
