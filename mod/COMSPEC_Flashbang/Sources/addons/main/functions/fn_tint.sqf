/*
    comspec_fb_fnc_tint : couleur d'UNE détonation, tirée au hasard (réglage « Variation aléatoire » ; à 0 % : blanc
    pur à chaque fois). Réaliste : la température de couleur d'une charge magnésium / aluminium varie autour du blanc.

      nom                 cœur (lumière)        couronne du halo       fin de flash
      blanc pur           blanc                 blanc à peine bleuté   idem
      blanc bleuté        blanc froid           bleu clair             idem
      blanc chaud         blanc chaud           jaune pâle             orangé
      doré, bord bleuté   blanc doré            doré / orangé          bleuté
      pointe verte        blanc                 blanc verdâtre         vert pâle (magnésium)
      pointe magenta      blanc                 blanc rosé             magenta pâle (aluminium)
    BANG spéciaux : une teinte de la palette du thème (teintes[] de CfgSons.hpp), nuancée de +/-12 % par canal.
    [_palette] call comspec_fb_fnc_tint -> [_nom, _coeur, _couronne, _fin]     (couleurs RGB 0..1)
*/
params [["_palette", [], [[]]]];
private _v = ((missionNamespace getVariable ["comspec_fb_variation", 0.6]) max 0) min 1;
if (_palette isNotEqualTo []) exitWith {
    private _c = (+(selectRandom _palette)) apply { ((_x * (1 + (random 2 - 1) * 0.2 * _v)) max 0) min 1 };
    ["thème", _c, _c, _c apply { (_x * 0.85) + 0.15 }]
};
if (_v <= 0) exitWith { ["blanc pur", [1, 1, 1], [0.92, 0.95, 1], [0.92, 0.95, 1]] };
private _pick = selectRandomWeighted [
    ["blanc pur", [1, 1, 1], [0.92, 0.95, 1], [0.92, 0.95, 1]], 0.22,
    ["blanc bleuté", [0.88, 0.93, 1], [0.62, 0.76, 1], [0.62, 0.76, 1]], 0.26,
    ["blanc chaud", [1, 0.95, 0.85], [1, 0.84, 0.58], [1, 0.7, 0.4]], 0.24,
    ["doré, bord bleuté", [1, 0.9, 0.72], [1, 0.74, 0.4], [0.62, 0.76, 1]], 0.14,
    ["pointe verte", [0.95, 1, 0.95], [0.86, 1, 0.9], [0.6, 1, 0.72]], 0.07,
    ["pointe magenta", [1, 0.95, 0.99], [1, 0.88, 0.96], [1, 0.62, 0.9]], 0.07
];
// Force de la couleur proportionnelle à la variation (pleine à 60 % et plus).
private _s = (_v / 0.6) min 1;
_pick params ["_name", "_core", "_corona", "_late"];
[_name, _core apply { 1 + (_x - 1) * _s }, _corona apply { 1 + (_x - 1) * _s }, _late apply { 1 + (_x - 1) * _s }]
