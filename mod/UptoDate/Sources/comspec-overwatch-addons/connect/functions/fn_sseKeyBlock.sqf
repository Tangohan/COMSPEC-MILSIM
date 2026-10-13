/*
    Terminal SEEK ouvert : les touches ne partent plus au jeu.

    Le SEEK est un display ouvert par createDisplay sur le jeu (46) : il laissait passer le
    clavier, si bien que taper un nom faisait marcher, pencher ou ressortir l'arme
    (« 1 », « F »…). Posé en KeyDown sur le SEEK et sur le display 46 (fn_sseTerminalGuard).
      - sur le SEEK, dans un champ : les lettres arrivent par l'événement Char, indépendant de
        KeyDown ; restent libres les touches d'édition (effacer, flèches, début, fin, Entrée,
        tabulation) et Ctrl+A / C / V / X / Z ;
      - partout ailleurs : tout est gardé, sauf Échap (ferme le terminal) et Verr. Maj
        (alternat radio TFAR / ACRE par défaut).
    Params : les paramètres de KeyDown [display, touche, Maj, Ctrl, Alt].
    Retour : true = touche gardée.
*/
params ["_disp", "_key", ["_shift", false], ["_ctrl", false], ["_alt", false]];
private _seek = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _seek) exitWith { false };
if (_key in [0x01, 0x3A]) exitWith { false };
// Hors du SEEK (jeu) ou sans champ de saisie actif : tout est gardé.
if (_disp isNotEqualTo _seek) exitWith { true };
private _f = focusedCtrl _seek;
if (isNull _f || {(ctrlType _f) isNotEqualTo 2} || {!ctrlEnabled _f}) exitWith { true };
// Effacer, tabulation, Entrée, Entrée du pavé, début, gauche, droite, fin, suppr.
if (_key in [14, 15, 28, 156, 199, 203, 205, 207, 211]) exitWith { false };
// Haut / bas : zone de texte sur plusieurs lignes seulement.
if (_key in [200, 208] && {((ctrlStyle _f) mod 32) >= 16}) exitWith { false };
if (_ctrl && {_key in [30, 44, 45, 46, 47]}) exitWith { false };
true
