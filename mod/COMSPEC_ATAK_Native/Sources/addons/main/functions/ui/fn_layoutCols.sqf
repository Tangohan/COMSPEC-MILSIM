/*
    Colonnes de la mise en page « paysage d'abord » (4.2.2) : le seul endroit où se décident les colonnes des apps.
    Le téléphone est surtout tenu à l'horizontale : une largeur qui tient deux colonnes lisibles en reçoit deux (trois sur
    une tablette), le portrait et le petit téléphone retombent d'eux-mêmes sur une colonne. Seuils en caractères du texte
    courant (layoutGet « colChar », qui suit la police, la taille du texte et le plancher en pixels réels), jamais
    d'après l'orientation seule : un petit téléphone en paysage n'est pas une tablette.
    Params : [largeur disponible (unités écran), genre]
      genre de contenu ("section", "info", "switch", "row", …) -> nombre de colonnes, de 1 à 3
      "split" -> maître-détail : [largeur de la liste, écart, largeur de la fiche], ou [] si la largeur ne tient pas
          une liste lisible et une fiche lisible côte à côte
*/
params [["_w", 0], ["_kind", "section"]];
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _ch = _l getOrDefault ["colChar", 0.01];
private _gap = _l getOrDefault ["sp2", 0.01];
if (_kind isEqualTo "split") exitWith {
    private _listMin = 30 * _ch;
    private _detMin = 40 * _ch;
    private _lw = (_w * 0.38) max _listMin;
    [[], [_lw, _gap, _w - _lw - _gap]] select (_w >= _listMin + _gap + _detMin)
};
// Largeur minimale d'une colonne, en caractères, par genre de contenu (sections : titre, aide et interrupteur ; info :
// libellé et valeur ; lignes de liste : titre et sous-titre ; personnes : texte et boutons à droite).
private _min = (createHashMapFromArray [
    ["section", 34], ["info", 26], ["check", 34], ["switch", 36], ["toggle", 28], ["slider", 40],
    ["row", 32], ["person", 44], ["notif", 40], ["status", 40]
]) getOrDefault [_kind, 34];
((floor ((_w + _gap) / ((_min * _ch) + _gap))) max 1) min 3
