/* Garde la saisie de l'app Feux (avant un redessin ou une action). */
private _form = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
private _f = [] call comspec_atak_native_fnc_firesState;
if (_f getOrDefault ["clearing", false]) exitWith {};
{ if (_x in _form) then { _f set [_x, [_x] call comspec_atak_native_fnc_formValue]; }; } forEach ["n1","n2","n3","n4","n5","n6","n7","n8","n9","nr","f1","f2","f3","f4","f5","gun","gunGrid","tgtGrid","az","dist","ammo","rounds"];
