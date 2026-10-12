/* Garde la saisie de la note de reco (avant un redessin de la page) ; type et fiabilité sont déjà dans le brouillon. */
private _form = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
if !("recoText" in _form) exitWith {};
if (uiNamespace getVariable ["COMSPEC_ATAK_RecoClearing", false]) exitWith {};
private _draft = uiNamespace getVariable ["COMSPEC_ATAK_RecoDraft", createHashMap];
_draft set ["grid", ["recoGrid"] call comspec_atak_native_fnc_formValue];
_draft set ["text", ["recoText"] call comspec_atak_native_fnc_formValue];
uiNamespace setVariable ["COMSPEC_ATAK_RecoDraft", _draft];
