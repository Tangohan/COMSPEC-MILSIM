/*
    Saisie dans un champ du téléphone : le téléphone en main est un display ouvert par createDisplay, qui laisse passer
    les touches au jeu (taper « a » ou « e » faisait pencher le personnage). Appelé à la fin de chaque gestionnaire
    KeyDown du display du téléphone (fn_displayLoad, fn_open, app Drone, menu de la carte) et sur le display de mission
    (46) : → true (touche gardée par le téléphone, le jeu ne la voit pas) quand un champ de texte a le focus.
    Les lettres arrivent au champ par l'événement Char, indépendant de KeyDown : bloquer KeyDown ne gêne pas la frappe.
    Restent libres les touches d'édition traitées par le champ lui-même (effacer, flèches gauche / droite, début, fin,
    tabulation, Entrée, Échap) et Ctrl+A / C / V / X / Z (presse-papiers).
    Params : les paramètres de KeyDown [display, touche, Maj, Ctrl, Alt].
*/
params ["_disp", "_key", ["_shift", false], ["_ctrl", false], ["_alt", false]];
private _phone = uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull];
if (isNull _phone || {!((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false])}) exitWith { false };
private _f = focusedCtrl _phone;
if (isNull _f || {(ctrlType _f) isNotEqualTo 2} || {!ctrlEnabled _f}) exitWith { false };
// Échap, effacer, tabulation, Entrée, Entrée du pavé, début, gauche, droite, fin, suppr.
if (_key in [1, 14, 15, 28, 156, 199, 203, 205, 207, 211]) exitWith { false };
// Flèches haut / bas : utiles dans une zone de texte sur plusieurs lignes seulement.
if (_key in [200, 208] && {((ctrlStyle _f) mod 32) >= 16}) exitWith { false };
if (_ctrl && {_key in [30, 44, 45, 46, 47]}) exitWith { false };
true
