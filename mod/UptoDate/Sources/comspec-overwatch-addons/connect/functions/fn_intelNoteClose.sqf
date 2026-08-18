/*
    Ferme le rédacteur de fiche en conservant le brouillon.
*/
if (!hasInterface) exitWith {};

// Dernière lecture des champs encore visibles, puis brouillon.
["capture"] call comspec_overwatch_connect_fnc_intelNoteCache;
[false] call comspec_overwatch_connect_fnc_intelNoteSaveDraft;

private _disp = uiNamespace getVariable ["COMSPEC_IntelNote_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9982; };

if (!isNull _disp) then {
    _disp closeDisplay 2;
} else {
    closeDialog 0;
};
