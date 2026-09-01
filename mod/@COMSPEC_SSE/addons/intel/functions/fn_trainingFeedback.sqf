params [
    ["_entity", objNull, [objNull]],
    ["_level", "", [""]]
];
if (!hasInterface) exitWith {};
private _tips = switch (_level) do {
    case "TACTICAL": { "Astuce : passez en Field (fouille) pour documents et alias." };
    case "FIELD": { "Astuce : exploitation Detailed pour numéros, grids et données supprimées." };
    case "DETAILED": { "Astuce : Fusion croise les sources et augmente la confiance." };
    default { "Procédure SSE : Tactical → Field → Detailed → Fusion." };
};
hint format ["SSE Entraînement\nNiveau %1 — %2", _level, _tips];
true
