/*
    Profils SSE prêts à l'emploi.

    Un chef de mission qui pose vingt PNJ n'a pas le temps de renseigner neuf champs
    par sujet. Ces quatre profils couvrent les situations qui changent réellement le
    déroulement d'un contrôle ; tout le reste est laissé à la génération déterministe,
    qui produit déjà des sujets crédibles et différents les uns des autres.

    Params: ["list"] → liste des clés
            ["labels"] → libellés à afficher, dans l'ordre des clés
            [_key] → profil, au format attendu par sseApplyProfile

    Aucune donnée nominative n'est imposée ici : les presets règlent le verdict de
    requête et la confiance, pas l'état civil, qui reste propre à chaque sujet.
*/
params [["_key", "list", [""]]];

private _keys = ["auto", "inconnu", "signale", "recherche"];

if ((toLower _key) isEqualTo "list") exitWith { _keys };

if ((toLower _key) isEqualTo "labels") exitWith {
    [
        "Génération automatique (défaut)",
        "Inconnu des bases",
        "Signalé — correspondance partielle",
        "Recherché — correspondance confirmée"
    ]
};

switch (toLower _key) do {
    // Rend la main à la génération déterministe : c'est le cas normal.
    case "auto": {
        [["match", "auto"], ["confidence", -1]]
    };
    // Le sujet ressort proprement négatif — utile pour que « connu » veuille dire
    // quelque chose : sans négatifs, un verdict positif n'informe plus.
    case "inconnu": {
        [["match", "none"], ["confidence", 0]]
    };
    case "signale": {
        [["match", "possible"], ["confidence", 58]]
    };
    case "recherche": {
        [["match", "confirmed"], ["confidence", 93]]
    };
    default { [] };
};
