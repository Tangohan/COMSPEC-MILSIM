/*
    Réglages CBA — moteur intel V0.6
*/
[
    "comspec_sse_trainingMode",
    "CHECKBOX",
    ["Mode entraînement", "Affiche un retour pédagogique après chaque exploitation."],
    ["COMSPEC SSE", "Intel"],
    false,
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_operatorSkill",
    "LIST",
    ["Niveau opérateur SSE", "Influence durée et qualité d'exploitation."],
    ["COMSPEC SSE", "Intel"],
    [[0, 1, 2, 3], ["Novice", "Qualifié", "Expérimenté", "Expert TECHINT"], 1],
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_autoTriage",
    "CHECKBOX",
    ["Triage automatique après fouille", "Classe les éléments : EXPLOIT NOW / COLLECT / DOCUMENT ONLY / …"],
    ["COMSPEC SSE", "Intel"],
    true,
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_autoPivot",
    "CHECKBOX",
    ["Pivot automatique", "Cherche d'autres entités partageant numéro, alias ou grid."],
    ["COMSPEC SSE", "Intel"],
    true,
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_autoMarkers",
    "CHECKBOX",
    ["Marqueurs géospatiaux", "Crée des marqueurs locaux pour les points d'intérêt découverts."],
    ["COMSPEC SSE", "Intel"],
    true,
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_crossMissionPersist",
    "CHECKBOX",
    ["Persistance inter-missions (profil)", "Conserve le graphe logique SSE dans le profil joueur entre missions."],
    ["COMSPEC SSE", "Intel"],
    false,
    0,
    {},
    true
] call CBA_fnc_addSetting;

true
