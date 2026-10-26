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
// Une seule passe (postInit) : plus rien ne pose ces marqueurs.
if (missionNamespace getVariable ["comspec_sse_legacyMarkersCleaned", false]) exitWith { [] };
comspec_sse_legacyMarkersCleaned = true;

// Seul le préfixe de nom SSE compte : un marqueur joueur intitulé « SSE … » reste en place.
{
    if ((_x find "_comspec_sse_") == 0) then {
        deleteMarkerLocal _x;
    };
} forEach allMapMarkers;

[]
