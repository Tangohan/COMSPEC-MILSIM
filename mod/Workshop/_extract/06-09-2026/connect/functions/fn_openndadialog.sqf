/*
    Overlay Arma de la note bêta (menu principal en priorité).
    Retour : true si un écran est ouvert.
*/
if (!hasInterface) exitWith { false };
if (!isNull (uiNamespace getVariable ["COMSPEC_NDA_Display", displayNull])) exitWith { true };

private _parent = findDisplay 0;
if (isNull _parent) then { _parent = findDisplay 49; };
if (isNull _parent) then { _parent = findDisplay 12; };
if (isNull _parent) then { _parent = findDisplay 46; };
if (isNull _parent) exitWith { false };

private _child = _parent createDisplay "COMSPEC_NDA_Dialog";
if (!isNull _child) exitWith { true };

createDialog "COMSPEC_NDA_Dialog";
!isNull (uiNamespace getVariable ["COMSPEC_NDA_Display", displayNull])
