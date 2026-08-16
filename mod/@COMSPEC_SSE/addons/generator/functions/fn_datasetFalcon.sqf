/*
    Dataset mission FALCON — cellule Irak ~2012 (référence entraînement).
    [] call comspec_sse_fnc_datasetFalcon
*/
private _roles = [
    createHashMapFromArray [
        ["roleId", "falcon_hvt"],
        ["label", "Chef de secteur (HVT)"],
        ["modelId", "builtin_iq_2010_2020_hvt"],
        ["profile", "COMMANDER"],
        ["complexity", "HIGH_VALUE"],
        ["minLevel", 0],
        ["forcedIdentity", createHashMapFromArray [
            ["name", "Karim Al-Rashid"],
            ["alias", "ABU KARIM"],
            ["nationality", "Irakienne"],
            ["role", "Émir de secteur Nord"]
        ]],
        ["forcedPhone", createHashMapFromArray [
            ["ownerAlias", "ABU KARIM"],
            ["primaryNumber", "+964-770-2012-01"]
        ]],
        ["tags", ["falcon", "hvt", "commandement"]]
    ],
    createHashMapFromArray [
        ["roleId", "falcon_ied"],
        ["label", "Technicien IED"],
        ["modelId", "builtin_iq_2010_2020_ied"],
        ["profile", "TECHNICIAN"],
        ["complexity", "HIGH_VALUE"],
        ["minLevel", 1],
        ["forcedIdentity", createHashMapFromArray [
            ["name", "Yusuf Mahdi"],
            ["alias", "L INGENIEUR"],
            ["nationality", "Irakienne"],
            ["role", "Technicien engins"]
        ]],
        ["tags", ["falcon", "ied"]]
    ],
    createHashMapFromArray [
        ["roleId", "falcon_courier"],
        ["label", "Courrier frontière"],
        ["modelId", "builtin_iq_2010_2020_courrier"],
        ["profile", "COURIER"],
        ["complexity", "STANDARD"],
        ["minLevel", 1],
        ["forcedIdentity", createHashMapFromArray [
            ["name", "Hassan Qadir"],
            ["alias", "LE CHAUFFEUR"],
            ["nationality", "Irakienne"],
            ["role", "Courrier"]
        ]],
        ["tags", ["falcon", "courrier"]]
    ],
    createHashMapFromArray [
        ["roleId", "falcon_finance"],
        ["label", "Relais financier"],
        ["modelId", "builtin_iq_2010_2020_financier"],
        ["profile", "FINANCIER"],
        ["complexity", "DETAILED"],
        ["minLevel", 2],
        ["forcedIdentity", createHashMapFromArray [
            ["name", "Omar Saadi"],
            ["alias", "LE CHANGEUR"],
            ["nationality", "Irakienne"],
            ["role", "Relais financier"]
        ]],
        ["tags", ["falcon", "finance"]]
    ],
    createHashMapFromArray [
        ["roleId", "falcon_safehouse"],
        ["label", "Gardien de planque"],
        ["modelId", "builtin_iq_2010_2020_safehouse"],
        ["profile", "INSURGENT"],
        ["complexity", "DETAILED"],
        ["minLevel", 2],
        ["forcedIdentity", createHashMapFromArray [
            ["name", "Bilal Nuri"],
            ["alias", "LE LOCATAIRE"],
            ["nationality", "Irakienne"],
            ["role", "Gardien planque"]
        ]],
        ["tags", ["falcon", "planque"]]
    ],
    createHashMapFromArray [
        ["roleId", "falcon_noise"],
        ["label", "Civil bruit de fond"],
        ["modelId", ""],
        ["profile", "CIVILIAN"],
        ["complexity", "LIGHT"],
        ["minLevel", 0],
        ["forcedIdentity", createHashMapFromArray [
            ["name", "Ahmed Saleh"],
            ["alias", ""],
            ["nationality", "Irakienne"],
            ["role", "Commerçant"]
        ]],
        ["tags", ["falcon", "bruit"]]
    ]
];

private _links = [
    createHashMapFromArray [["from", "falcon_hvt"], ["to", "falcon_ied"], ["relation", "ORDERS"]],
    createHashMapFromArray [["from", "falcon_hvt"], ["to", "falcon_courier"], ["relation", "TASKS"]],
    createHashMapFromArray [["from", "falcon_hvt"], ["to", "falcon_finance"], ["relation", "FUNDS"]],
    createHashMapFromArray [["from", "falcon_courier"], ["to", "falcon_safehouse"], ["relation", "DROPS"]],
    createHashMapFromArray [["from", "falcon_ied"], ["to", "falcon_safehouse"], ["relation", "STASHES"]]
];

private _levels = createHashMapFromArray [
    ["0", createHashMapFromArray [
        ["code", "SURFACE"],
        ["label", "Surface"],
        ["hint", "Couverture civile et alias uniquement — pas de confirmation d’identité."]
    ]],
    ["1", createHashMapFromArray [
        ["code", "TACTICAL"],
        ["label", "Tactique"],
        ["hint", "Réseau apparent (courrier + IED) ; téléphones partiels."]
    ]],
    ["2", createHashMapFromArray [
        ["code", "FIELD"],
        ["label", "Terrain"],
        ["hint", "Finance + planque ; documents et biométrie accessibles."]
    ]],
    ["3", createHashMapFromArray [
        ["code", "FULL"],
        ["label", "Vérité complète"],
        ["hint", "HVT + graphe entier — réservé Zeus / debrief."]
    ]]
];

createHashMapFromArray [
    ["id", "falcon"],
    ["name", "FALCON — Cellule Irak 2012"],
    ["seed", "FALCON-IQ-2012-A"],
    ["region", "IRAQ"],
    ["theme", "weapons_cache"],
    ["era", "2010-2020"],
    ["networkId", "NET-FALCON-2012"],
    ["brief", "Cellule FALCON : un chef de secteur (ABU KARIM), un technicien engins, un courrier, un relais financier et une planque urbaine. Bruit civil inclus."],
    ["zeusNotes", "Dataset d’entraînement. Ne confirmez jamais l’identité HVT par génération seule — validation humaine côté Athena."],
    ["roles", _roles],
    ["links", _links],
    ["levels", _levels],
    ["defaultLevel", 1],
    ["source", "BUILTIN_DATASET"]
]
