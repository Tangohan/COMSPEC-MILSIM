/* Crée un contrôle de page (dans la zone de contenu par défaut) et le suit pour la suppression. */
params ["_class", "_pos", ["_text", ""], ["_inGroup", true]];
disableSerialization;
private _d = findDisplay 88500;
private _c = if (_inGroup) then { _d ctrlCreate [_class, -1, _d displayCtrl 88531] } else { _d ctrlCreate [_class, -1] };
_c ctrlSetPosition _pos;
if (_text isNotEqualTo "") then { _c ctrlSetText _text; };
_c ctrlCommit 0;
private _list = uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []];
_list pushBack _c;
uiNamespace setVariable ["COMSPEC_ATAK_PageControls", _list];
_c
