/* Garde la saisie de la fiche FRS (avant un redessin de la page). */
private _form = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
if !("body" in _form && {"urgency" in _form}) exitWith {};
if (uiNamespace getVariable ["COMSPEC_ATAK_FrsClearing", false]) exitWith {};
private _draft = uiNamespace getVariable ["COMSPEC_ATAK_FrsDraft", createHashMap];
{ _draft set [_x, [_x] call comspec_atak_native_fnc_formValue]; } forEach ["kind", "urgency", "source", "date", "place", "grid", "body", "case"];
uiNamespace setVariable ["COMSPEC_ATAK_FrsDraft", _draft];
