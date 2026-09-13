/*
    Ferme le menu contextuel de la carte terrain (clic droit) s'il est ouvert.
*/
{
    if (!isNull _x) then { ctrlDelete _x; };
} forEach (uiNamespace getVariable ["COMSPEC_MapContextMenuCtrls", []]);
uiNamespace setVariable ["COMSPEC_MapContextMenuCtrls", []];
