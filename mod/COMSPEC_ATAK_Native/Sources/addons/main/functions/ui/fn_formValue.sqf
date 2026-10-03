/* Valeur d'un champ de formulaire : texte (edit / memo) ou donnée de la ligne choisie (combo). */
params ["_key", ["_default", ""]];
private _c = (uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap]) getOrDefault [_key, controlNull];
if (isNull _c) exitWith { _default };
private _secret = _c getVariable ["secretVar", ""];
if (_secret isNotEqualTo "") exitWith { uiNamespace getVariable [_secret, ""] };
if ((ctrlType _c) isEqualTo 4) exitWith { _c lbData (lbCurSel _c) };
ctrlText _c
