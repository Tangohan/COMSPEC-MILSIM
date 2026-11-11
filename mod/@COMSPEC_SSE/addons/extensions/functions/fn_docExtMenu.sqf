/*
    Menu Échap (RscDisplayInterrupt, RscDisplayMPInterrupt : Extended_DisplayLoad_EventHandlers de CBA) : bouton discret
    « SSE ROLEPLAY · Extensions » en haut à droite, sous le bouton « Réalisme et réseau » de l'ATAK natif (lui-même sous
    la barre COMSPEC d'Overwatch), aligné sur le même bord droit. En jeu seulement ; ouvre fn_docExtOpen.
    [display] call comspec_sse_fnc_docExtMenu
*/
params [["_display", displayNull, [displayNull]]];
if (!hasInterface || {isNull _display}) exitWith {};
disableSerialization;
// Réouverture du menu : le bouton précédent est retiré ici, jamais dans son propre gestionnaire.
private _old = _display displayCtrl 93940;
if (!isNull _old) then { ctrlDelete _old; };
private _w = 0.125 * safeZoneW;
private _h = 0.034 * safeZoneH;
private _bt = _display ctrlCreate ["ctrlButton", 93940];
_bt ctrlSetPosition [safeZoneX + safeZoneW * 0.988 - _w, safeZoneY + 0.108 * safeZoneH, _w, _h];
_bt ctrlSetText ("SSE ROLEPLAY · Extensions" call comspec_sse_fnc_tr);
// Police tirée de la hauteur du bouton : avec une interface NORMALE ou plus grande, la police par défaut déborde.
_bt ctrlSetFontHeight (_h * 0.6);
_bt ctrlSetTooltip ("Extensions de documents chargées : activer ou désactiver chacune (Zeus, administrateur)." call comspec_sse_fnc_tr);
_bt ctrlSetBackgroundColor [0.10, 0.12, 0.11, 0.92];
_bt ctrlSetTextColor [0.85, 0.9, 0.86, 1];
_bt ctrlAddEventHandler ["ButtonClick", {
    // Le menu Échap se ferme d'abord ; la fenêtre s'ouvre à l'image suivante, hors du gestionnaire du bouton.
    (ctrlParent (_this select 0)) closeDisplay 2;
    [{ [] call comspec_sse_fnc_docExtOpen; }, []] call CBA_fnc_execNextFrame;
}];
_bt ctrlCommit 0;
