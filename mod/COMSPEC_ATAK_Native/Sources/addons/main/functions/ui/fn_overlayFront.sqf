/*
    Remet les effets d'écran (fêlures, filtre de nuit, écran éteint, animations d'alimentation) devant l'app.
    Arma dessine le contrôle focalisé, et le groupe qui le contient (zone de contenu), devant tous les autres :
    après un clic dans une app, les fêlures passaient derrière. On rend le focus au bouton invisible hors écran
    (même principe que fn_mapUnfocus), sauf pendant une saisie de texte ou une liste déroulante ouverte.
*/
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { false };
if !((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false]) exitWith { false };
private _sink = _d displayCtrl 88543;
private _f = focusedCtrl _d;
if (isNull _sink || {isNull _f} || {_f isEqualTo _sink}) exitWith { false };
// 2 : champ de saisie ; 4 : liste déroulante ; 44 : liste déroulante étendue.
if ((ctrlType _f) in [2, 4, 44]) exitWith { false };
ctrlSetFocus _sink;
true
