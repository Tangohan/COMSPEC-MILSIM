// Signature (4e paramètre, facultatif) : fournie par l'appelant pour une grosse table (unités, marqueurs), au lieu de
// convertir toute la valeur en texte (str) à chaque écriture.
params ["_domain","_value",["_revision",-1],["_signature",""]];
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap];
private _revs = _data getOrDefault ["revisions",createHashMap];
private _signatures = _data getOrDefault ["signatures",createHashMap];
if (_signature isEqualTo "") then { _signature = str _value; };
if ((_signatures getOrDefault [_domain,""]) isEqualTo _signature) exitWith { false };
if (_revision < 0) then { _revision = (_revs getOrDefault [_domain,0]) + 1; };
if ((_revs getOrDefault [_domain,-1]) isEqualTo _revision) exitWith { false };
_data set [_domain,_value]; _revs set [_domain,_revision];
_signatures set [_domain,_signature];
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
(_state getOrDefault ["dirty",createHashMap]) set [_domain,true];
true
