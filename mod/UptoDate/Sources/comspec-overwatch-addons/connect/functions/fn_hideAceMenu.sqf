/*
    Ferme le menu ACE s’il est ouvert (clic / molette rendus au jeu).
    Indispensable à l’embarquement dans un cockpit Hatchet.
*/
if (!hasInterface) exitWith { false };
if (isNil "ace_interact_menu_fnc_hideMenu") exitWith { false };
[] call ace_interact_menu_fnc_hideMenu;
true
