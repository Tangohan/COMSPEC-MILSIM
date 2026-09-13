/*
    Plus tard : ferme sans confirmation (réaffichage possible au prochain lancement).
*/
if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_NDA_Display", displayNull];
if (!isNull _display) then {
    _display closeDisplay 2;
} else {
    closeDialog 0;
};
