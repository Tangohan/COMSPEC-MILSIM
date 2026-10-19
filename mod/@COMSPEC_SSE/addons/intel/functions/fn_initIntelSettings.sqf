/*
    Réglages CBA — moteur intel V0.6
*/
[
    "comspec_sse_trainingMode",
    "CHECKBOX",
    ["Mode entraînement", "Après chaque exploitation, un message explique ce qui a été bien fait et ce qui a été raté."],
    ["COMSPEC SSE", "Intel"],
    false,
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_operatorSkill",
    "LIST",
    ["Niveau de l'opérateur SSE", "Plus le niveau est haut, plus les exploitations sont rapides et donnent de bons résultats."],
    ["COMSPEC SSE", "Intel"],
    [[0, 1, 2, 3], ["Novice", "Qualifié", "Expérimenté", "Expert renseignement technique"], 1],
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_autoTriage",
    "CHECKBOX",
    ["Tri automatique après une fouille", "Classe tout seul ce qui a été trouvé : à exploiter tout de suite, à emporter, à photographier seulement..."],
    ["COMSPEC SSE", "Intel"],
    true,
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_autoPivot",
    "CHECKBOX",
    ["Recoupement automatique", "Cherche d'autres personnes ou objets qui ont le même numéro, le même surnom ou la même position."],
    ["COMSPEC SSE", "Intel"],
    true,
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;


[
    "comspec_sse_crossMissionPersist",
    "CHECKBOX",
    ["Garder les liens SSE d'une mission à l'autre", "Le tableau des liens SSE (qui connaît qui) est gardé dans le profil du joueur d'une mission à l'autre."],
    ["COMSPEC SSE", "Intel"],
    false,
    0,
    {},
    true
] call comspec_sse_fnc_addSetting;

true
