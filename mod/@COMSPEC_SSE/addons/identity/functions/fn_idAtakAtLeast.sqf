/*
    Version de l'ATAK natif chargée au moins égale à [majeur, mineur, correctif] (COMSPEC_ATAK_NativeVersion « 3.0.2 ») :
    sert aux entrées publiques ajoutées par une version (ATAK 3.0.2 : partage d'une fiche Contrôle ID, vibreur seul,
    SPOTREP pré-rempli, « Sur mon téléphone » Contrôle ID). Sans ATAK : faux.
    [3, 0, 2] call comspec_sse_fnc_idAtakAtLeast -> Boolean
*/
params [["_ma", 0], ["_mi", 0], ["_pa", 0]];
private _v = ((missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", "0.0.0"]) splitString ".") apply { parseNumber _x };
_v params [["_a", 0], ["_b", 0], ["_c", 0]];
(_a > _ma) || {_a isEqualTo _ma && {(_b > _mi) || {_b isEqualTo _mi && {_c >= _pa}}}}
