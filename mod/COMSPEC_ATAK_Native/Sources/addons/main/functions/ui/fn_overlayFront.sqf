/*
    Remet les effets d'écran (fêlures, filtre de nuit, écran éteint, animations d'alimentation) devant l'app.
    Arma dessine le contrôle focalisé, et le groupe qui le contient (zone de contenu), devant tous les autres :
    après un clic dans une app, les fêlures passaient derrière. On rend le focus au bouton invisible hors écran
    (même principe que fn_mapUnfocus), sauf pendant une saisie de texte ou une liste déroulante ouverte.
    Params : [appui en cours (true : appelé à l'image qui suit l'enfoncement du bouton de la souris, fn_displayLoad)]
    Appui en cours : le moteur donne le focus au contrôle sous le doigt dès l'enfoncement ; jusqu'ici il n'était rendu
    qu'au relâchement, et pendant tout l'appui la page passait devant le reflet du verre, les coins, la saleté et les
    fêlures (clignotement à chaque clic). Rendu tout de suite, mais seulement pris à ce qui se clique sans se glisser
    (boutons, textes actifs, cases) : carte, listes, curseurs et groupes défilants gardent le focus jusqu'au relâchement.
*/
params [["_press", false]];
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { false };
if !((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false]) exitWith { false };
private _sink = _d displayCtrl 88543;
private _f = focusedCtrl _d;
if (isNull _sink || {isNull _f} || {_f isEqualTo _sink}) exitWith { false };
// 2 : champ de saisie ; 4 : liste déroulante ; 44 : liste déroulante étendue.
if ((ctrlType _f) in [2, 4, 44]) exitWith { false };
// 0 texte, 1 bouton, 6 barre d'outils, 7 cases, 11 texte actif, 13 texte structuré, 16 bouton à raccourci, 41 bouton X, 77 case.
if (_press && {!((ctrlType _f) in [0, 1, 6, 7, 11, 13, 16, 41, 77])}) exitWith { false };
ctrlSetFocus _sink;
true
