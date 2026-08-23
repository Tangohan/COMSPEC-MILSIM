[
    "comspec_sse_debug",
    "CHECKBOX",
    ["Logs debug SSE", "Affiche les messages de diagnostic SSE dans le journal Arma et le chat système."],
    ["COMSPEC SSE", "Général"],
    false,
    0,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_log_to_file",
    "CHECKBOX",
    ["Journal SSE dans un fichier", "Enregistre les lignes SSE dans le même dossier que le journal Overwatch, à chaque session."],
    ["COMSPEC SSE", "Général"],
    true,
    0,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_requireEquipment",
    "CHECKBOX",
    ["Exiger le matériel SSE", "Si activé, photographie, empreintes, ADN et collecte nécessitent les items dédiés (ou un substitut compatible)."],
    ["COMSPEC SSE", "Matériel"],
    true,
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_allowModItemSubstitutes",
    "CHECKBOX",
    ["Accepter les items d'autres mods", "Autorise cTab, ACE, tablettes ATAK, etc. comme substituts du matériel SSE natif (si l'item est chargé)."],
    ["COMSPEC SSE", "Matériel"],
    true,
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_extraEquipmentAliases",
    "EDITBOX",
    ["Alias matériels additionnels", "Format: role:Classe1,Classe2;role2:Classe3 — rôles: camera, evidence_bag, fingerprint, dna, seek, gloves, face, radio"],
    ["COMSPEC SSE", "Matériel"],
    "",
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_timeInspect",
    "SLIDER",
    ["Durée — Inspecter (s)", "Temps de la barre de progression pour une inspection."],
    ["COMSPEC SSE", "Durées"],
    [1, 30, 5, 0],
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_timeSearchQuick",
    "SLIDER",
    ["Durée — Fouille rapide (s)", ""],
    ["COMSPEC SSE", "Durées"],
    [1, 60, 5, 0],
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_timeSearchFull",
    "SLIDER",
    ["Durée — Fouille complète (s)", ""],
    ["COMSPEC SSE", "Durées"],
    [5, 120, 15, 0],
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_timePhoto",
    "SLIDER",
    ["Durée — Photographie (s)", ""],
    ["COMSPEC SSE", "Durées"],
    [1, 30, 3, 0],
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_timeFingerprint",
    "SLIDER",
    ["Durée — Empreintes (s)", ""],
    ["COMSPEC SSE", "Durées"],
    [2, 60, 8, 0],
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_timePhoneMin",
    "SLIDER",
    ["Durée téléphone — minimum (s)", ""],
    ["COMSPEC SSE", "Durées"],
    [5, 120, 15, 0],
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_timePhoneMax",
    "SLIDER",
    ["Durée téléphone — maximum (s)", ""],
    ["COMSPEC SSE", "Durées"],
    [10, 180, 60, 0],
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_timeComputerMin",
    "SLIDER",
    ["Durée ordinateur — minimum (s)", ""],
    ["COMSPEC SSE", "Durées"],
    [10, 180, 30, 0],
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_timeComputerMax",
    "SLIDER",
    ["Durée ordinateur — maximum (s)", ""],
    ["COMSPEC SSE", "Durées"],
    [30, 300, 120, 0],
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_noiseProbability",
    "SLIDER",
    ["Probabilité de bruit / données banales", "Part des informations peu utiles générées automatiquement."],
    ["COMSPEC SSE", "Génération"],
    [0, 1, 0.25, 2],
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_falseLeadProbability",
    "SLIDER",
    ["Probabilité de fausse piste", ""],
    ["COMSPEC SSE", "Génération"],
    [0, 1, 0.05, 2],
    1,
    {},
    true
] call CBA_fnc_addSetting;

[
    "comspec_sse_missionId",
    "EDITBOX",
    ["Identifiant de mission", "Utilisé dans les transmissions Athena (ex. CERBERUS_01)."],
    ["COMSPEC SSE", "Réseau"],
    "UNKNOWN_MISSION",
    1,
    {},
    true
] call CBA_fnc_addSetting;

true
