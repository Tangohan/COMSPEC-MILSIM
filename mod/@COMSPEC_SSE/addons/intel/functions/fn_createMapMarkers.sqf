/*
    Ancien poseur de marqueurs locaux SSE (icône « ? » orange, texte « SSE … »).
    Retiré : plusieurs points d’intérêt au même endroit empilaient le libellé
    et rendaient la carte illisible. Les dossiers restent dans le terminal et sur Athena.

    Cette fonction ne fait plus que supprimer les marqueurs déjà posés.
    [_entity, _intelItems] call comspec_sse_fnc_createMapMarkers
*/
params [
    ["_entity", objNull, [objNull]],
    ["_intelItems", [], [[]]]
];

if (!hasInterface) exitWith { [] };

{
    private _txt = markerText _x;
    if (
        (_x find "_comspec_sse_") == 0
        || {(_txt select [0, 4]) isEqualTo "SSE "}
    ) then {
        deleteMarkerLocal _x;
    };
} forEach allMapMarkers;

[]
