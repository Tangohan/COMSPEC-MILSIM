/*
    Axe final d'attaque (FAH) saisi dans les remarques : « 270 » (± 20°), « 250-290 » (fenêtre) ou « 270 ± 15 ».
    Params : [texte]. Renvoie [début, fin] en degrés (fenêtre dans le sens horaire), ou [] sans valeur lisible.
*/
params [["_fah", ""]];
private _nums = ((_fah splitString " -±+/°abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ") apply { parseNumber _x }) select { _x >= 0 && {_x <= 360} };
if ((count _nums) isEqualTo 0) exitWith { [] };
switch (true) do {
    case ((count _nums) >= 2 && {(_fah find "±") >= 0 || {(_fah find "+/-") >= 0}}): { [(_nums select 0) - (_nums select 1), (_nums select 0) + (_nums select 1)] };
    case ((count _nums) >= 2): { [_nums select 0, _nums select 1] };
    default { [(_nums select 0) - 20, (_nums select 0) + 20] };
}
