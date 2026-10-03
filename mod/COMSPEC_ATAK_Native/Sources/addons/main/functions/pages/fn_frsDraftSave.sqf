/* Garde la saisie de la fiche FRS (avant un redessin de la page) : seuls les champs affichés sont relus. */
private _form = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
if (uiNamespace getVariable ["COMSPEC_ATAK_FrsClearing", false]) exitWith {};
private _draft = uiNamespace getVariable ["COMSPEC_ATAK_FrsDraft", createHashMap];
{ if (_x in _form && {!isNull (_form get _x)}) then { _draft set [_x, [_x] call comspec_atak_native_fnc_formValue]; }; } forEach ["kind", "urgency", "source", "date", "place", "grid", "body", "case"];
uiNamespace setVariable ["COMSPEC_ATAK_FrsDraft", _draft];
