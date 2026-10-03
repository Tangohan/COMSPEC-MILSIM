/* Libellés d'un bilan BDA : [cible, résultat, reprise]. Params : [bilan (HashMap)] */
params [["_r", createHashMap]];
[
    createHashMapFromArray [["PERS", "Personnel"], ["VEH", "Véhicule"], ["BLDG", "Bâtiment"], ["POS", "Position"]] getOrDefault [_r getOrDefault ["type", ""], "Cible"],
    createHashMapFromArray [["DESTROYED", "détruit"], ["DAMAGED", "endommagé"], ["NEUTRALIZED", "neutralisé"], ["NONE", "sans effet"]] getOrDefault [_r getOrDefault ["res", ""], "?"],
    ["Pas de reprise", "Reprise requise"] select ((_r getOrDefault ["reatk", "NO"]) isEqualTo "YES")
]
