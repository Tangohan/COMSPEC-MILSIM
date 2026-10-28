[
    "comspec_sse_debug",
    "CHECKBOX",
    ["Logs de debug SSE", "Écrit les messages de debug du SSE dans le RPT et le chat système."],
    ["COMSPEC SSE", "Général"],
    false,
    0,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_log_to_file",
    "CHECKBOX",
    ["Logs SSE dans un fichier", "Écrit aussi les logs SSE dans un fichier par session, dans le même dossier que ceux d'Overwatch."],
    ["COMSPEC SSE", "Général"],
    true,
    0,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_requireEquipment",
    "CHECKBOX",
    ["Matériel SSE obligatoire", "Coché : chaque action demande le bon matériel (ou un équivalent) : appareil photo pour les photos, kit d'empreintes pour les empreintes, kit ADN, terminal SEEK II pour le SEEK et l'iris, sachet de preuve pour ramasser et mettre sous scellé."],
    ["COMSPEC SSE", "Matériel"],
    true,
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_allowModItemSubstitutes",
    "CHECKBOX",
    ["Accepter le matériel d'autres mods", "Les items de cTab, ACE, les tablettes ATAK, etc. peuvent remplacer le matériel SSE (si le mod est chargé)."],
    ["COMSPEC SSE", "Matériel"],
    true,
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_extraEquipmentAliases",
    "EDITBOX",
    ["Équivalences de matériel en plus", "D'autres items qui comptent comme matériel SSE, au format role:Classe1,Classe2;role2:Classe3. Rôles possibles : camera, evidence_bag, fingerprint, dna, seek, gloves, face, radio"],
    ["COMSPEC SSE", "Matériel"],
    "",
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_timeInspect",
    "SLIDER",
    ["Durée : inspection (s)", "Durée de la barre de progression pour inspecter."],
    ["COMSPEC SSE", "Durées"],
    [1, 30, 5, 0],
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_timeSearchQuick",
    "SLIDER",
    ["Durée : fouille rapide (s)", ""],
    ["COMSPEC SSE", "Durées"],
    [1, 60, 5, 0],
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_timeSearchFull",
    "SLIDER",
    ["Durée : fouille complète (s)", ""],
    ["COMSPEC SSE", "Durées"],
    [5, 120, 15, 0],
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_timePhoto",
    "SLIDER",
    ["Durée : photo (s)", ""],
    ["COMSPEC SSE", "Durées"],
    [1, 30, 3, 0],
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_timeFingerprint",
    "SLIDER",
    ["Durée : empreintes (s)", ""],
    ["COMSPEC SSE", "Durées"],
    [2, 60, 8, 0],
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_timePhoneMin",
    "SLIDER",
    ["Exploitation d'un téléphone : durée mini (s)", ""],
    ["COMSPEC SSE", "Durées"],
    [5, 120, 15, 0],
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_timePhoneMax",
    "SLIDER",
    ["Exploitation d'un téléphone : durée maxi (s)", ""],
    ["COMSPEC SSE", "Durées"],
    [10, 180, 60, 0],
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_timeComputerMin",
    "SLIDER",
    ["Exploitation d'un ordinateur : durée mini (s)", ""],
    ["COMSPEC SSE", "Durées"],
    [10, 180, 30, 0],
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_timeComputerMax",
    "SLIDER",
    ["Exploitation d'un ordinateur : durée maxi (s)", ""],
    ["COMSPEC SSE", "Durées"],
    [30, 300, 120, 0],
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_noiseProbability",
    "SLIDER",
    ["Part d'infos sans intérêt", "Part des infos trouvées qui ne mènent à rien, pour plus de réalisme."],
    ["COMSPEC SSE", "Génération"],
    [0, 1, 0.25, 2],
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_falseLeadProbability",
    "SLIDER",
    ["Part de fausses pistes", ""],
    ["COMSPEC SSE", "Génération"],
    [0, 1, 0.05, 2],
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

[
    "comspec_sse_missionId",
    "EDITBOX",
    ["Nom de code de la mission", "Repris dans ce qui est envoyé à Athena (ex. CERBERUS_01)."],
    ["COMSPEC SSE", "Réseau"],
    "UNKNOWN_MISSION",
    1,
    {},
    true
] call comspec_sse_fnc_addSetting;

true
