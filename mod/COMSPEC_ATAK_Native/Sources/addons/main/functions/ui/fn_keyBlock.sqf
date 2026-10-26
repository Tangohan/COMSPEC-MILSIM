/*
    Saisie dans un champ du téléphone : le téléphone en main est un display ouvert par createDisplay, qui laisse passer
    les touches au jeu (taper « a » ou « e » faisait pencher le personnage). Appelé à la fin de chaque gestionnaire
    KeyDown du display du téléphone (fn_displayLoad, fn_open, app Drone, menu de la carte) et sur le display de mission
    (46) : → true (touche gardée par le téléphone, le jeu ne la voit pas) quand un champ de texte a le focus.
    Les lettres arrivent au champ par l'événement Char, indépendant de KeyDown : bloquer KeyDown ne gêne pas la frappe.
    Restent libres les touches d'édition traitées par le champ lui-même (effacer, flèches gauche / droite, début, fin,
    tabulation, Entrée, Échap) et Ctrl+A / C / V / X / Z (presse-papiers).
    Mode appareil photo (fn_photoMode) : ses raccourcis sont aussi gardés.
    Params : les paramètres de KeyDown [display, touche, Maj, Ctrl, Alt].
*/
params ["_disp", "_key", ["_shift", false], ["_ctrl", false], ["_alt", false]];
// Appareil photo ouvert : ses touches (Espace, R, F flash, T, B flou, H / F1 aide, zoom) restent au téléphone. Sans
// cela, le gestionnaire posé ensuite sur le display 46 rendait la main au jeu (F : mode de tir, l'arme ressortait ;
// B et H allaient aussi au jeu).
if ((uiNamespace getVariable ["COMSPEC_ATAK_PhotoMode", false]) && {_key in [0x39, 0x13, 0x21, 0x14, 0x30, 0x23, 0x3B, 0x0D, 0x4E, 0x0C, 0x4A]}) exitWith { true };
// Champ qui a le focus : dans le téléphone, ou dans la fenêtre qui reçoit la touche (menu de la carte, plan, recherche).
// Avant, seul le téléphone « en main » (état interactive) comptait : une saisie dans une fenêtre ouverte par le
// téléphone, ou juste après un changement de mode, laissait passer A / E (pencher) au personnage (retour de TTRD, 25/10).
private _phone = uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull];
private _isEdit = { !isNull _this && {(ctrlType _this) isEqualTo 2} && {ctrlEnabled _this} && {ctrlShown _this} };
private _f = controlNull;
if (!isNull _phone) then { _f = focusedCtrl _phone; };
if !(_f call _isEdit) then { _f = [controlNull, focusedCtrl _disp] select (!isNull _disp && {_disp isNotEqualTo (findDisplay 46)}); };
if !(_f call _isEdit) exitWith { false };
// Échap, effacer, tabulation, Entrée, Entrée du pavé, début, gauche, droite, fin, suppr.
if (_key in [1, 14, 15, 28, 156, 199, 203, 205, 207, 211]) exitWith { false };
// Flèches haut / bas : utiles dans une zone de texte sur plusieurs lignes seulement.
if (_key in [200, 208] && {((ctrlStyle _f) mod 32) >= 16}) exitWith { false };
if (_ctrl && {_key in [30, 44, 45, 46, 47]}) exitWith { false };
true
