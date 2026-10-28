/*
    True si l'arsenal (ACE ou arsenal virtuel BIS) est ouvert chez ce joueur.
    Les envois qui suivent le loadout attendent sa fermeture : chaque clic dans l'arsenal change le loadout.
*/
!isNull (uiNamespace getVariable ["ace_arsenal_display", displayNull])
    || {!isNull (uiNamespace getVariable ["BIS_fnc_arsenal_display", displayNull])}
