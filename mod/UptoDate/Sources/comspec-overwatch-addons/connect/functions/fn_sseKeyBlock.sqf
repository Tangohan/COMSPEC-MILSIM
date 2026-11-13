/*
    Terminal SEEK ouvert : les touches ne partent plus au jeu.

    Le SEEK est un display ouvert par createDisplay sur le jeu (46) : il laissait passer le
    clavier, si bien que taper un nom faisait marcher, pencher ou ressortir l'arme
    (« 1 », « F »…). Posé en KeyDown sur le SEEK et sur le display 46 (fn_sseTerminalGuard).
      - sur le SEEK, dans un champ : les lettres arrivent par l'événement Char, indépendant de
        KeyDown ; restent libres les touches d'édition (effacer, flèches, début, fin, Entrée,
        tabulation) et Ctrl+A / C / V / X / Z ;
      - sur le SEEK hors saisie : touches de l'appareil (F1-F4, C E Q S, flèches, Origine) ;
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
if (isNull _f || {(ctrlType _f) isNotEqualTo 2} || {!ctrlEnabled _f}) exitWith {
    // Hors saisie, le clavier pilote l'appareil comme ses touches : F1 / C contrôle rapide (A1),
    // F2 / E enroll (A2), F3 / Q QUERY, F4 / S SIGN, flèches gauche / droite << >>, Origine accueil.
    // Appel différé d'une image : rien n'est retiré de l'écran dans son propre gestionnaire.
    if (!_ctrl && {!_alt}) then {
        private _call = switch (true) do {
            case (_key in [0x3B, 0x2E]): { { ["start"] call comspec_overwatch_connect_fnc_seekQuick } };
            case (_key in [0x3C, 0x12]): { { ["start"] call comspec_overwatch_connect_fnc_seekEnroll } };
            case (_key in [0x3D, 0x10]): { { ["query"] call comspec_overwatch_connect_fnc_seekQuick } };
            case (_key in [0x3E, 0x1F]): { { ["send"] call comspec_overwatch_connect_fnc_seekSend } };
            case (_key isEqualTo 203): { { [-1, true] call comspec_overwatch_connect_fnc_sseTerminalPage } };
            case (_key isEqualTo 205): { { [1, true] call comspec_overwatch_connect_fnc_sseTerminalPage } };
            case (_key isEqualTo 199): { { [0] call comspec_overwatch_connect_fnc_sseTerminalPage } };
            default { {} };
        };
        if (_call isNotEqualTo {}) then { [_call] call CBA_fnc_execNextFrame; };
    };
    true
};
// Effacer, tabulation, Entrée, Entrée du pavé, début, gauche, droite, fin, suppr.
if (_key in [14, 15, 28, 156, 199, 203, 205, 207, 211]) exitWith { false };
// Haut / bas : zone de texte sur plusieurs lignes seulement.
if (_key in [200, 208] && {((ctrlStyle _f) mod 32) >= 16}) exitWith { false };
if (_ctrl && {_key in [30, 44, 45, 46, 47]}) exitWith { false };
true
