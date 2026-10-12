/* Crée un contrôle de page (dans la zone de contenu par défaut) et le suit pour la suppression. */
params ["_class", "_pos", ["_text", ""], ["_inGroup", true]];
disableSerialization;
private _d = ([] call comspec_atak_native_fnc_display);
private _c = if (_inGroup) then { _d ctrlCreate [_class, -1, _d displayCtrl 88531] } else { _d ctrlCreate [_class, -1] };
_c ctrlSetPosition _pos;
if (_text isNotEqualTo "") then { _c ctrlSetText _text; };
_c ctrlCommit 0;
private _list = uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []];
_list pushBack _c;
// Incrustations recréées sans rendu de page (pixels morts, fissures, effets) : les contrôles déjà supprimés sont retirés
// de la liste de temps en temps, sinon elle grossit tant que la page reste affichée (carte en HUD).
if ((count _list) > 600) then { _list = _list select { !isNull _x }; };
uiNamespace setVariable ["COMSPEC_ATAK_PageControls", _list];
_c
