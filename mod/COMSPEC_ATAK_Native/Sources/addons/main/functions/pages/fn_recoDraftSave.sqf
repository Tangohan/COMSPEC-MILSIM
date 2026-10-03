/* Garde la saisie de la note de reco (avant un redessin de la page). */
private _form = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
if !("confidence" in _form) exitWith {};
if (uiNamespace getVariable ["COMSPEC_ATAK_RecoClearing", false]) exitWith {};
private _draft = uiNamespace getVariable ["COMSPEC_ATAK_RecoDraft", createHashMap];
{ _draft set [_x, [_x] call comspec_atak_native_fnc_formValue]; } forEach ["tag", "confidence", "text"];
uiNamespace setVariable ["COMSPEC_ATAK_RecoDraft", _draft];
