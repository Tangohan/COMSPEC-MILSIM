// Signature (4e paramètre, facultatif) : fournie par l'appelant pour une grosse table (unités, marqueurs), au lieu de
// convertir toute la valeur en texte (str) à chaque écriture.
params ["_domain","_value",["_revision",-1],["_signature",""]];
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap];
private _revs = _data getOrDefault ["revisions",createHashMap];
private _signatures = _data getOrDefault ["signatures",createHashMap];
// Sans signature : conversion en texte, mesurée au RPT (« magasin : empreinte par texte », perf lot 2) pour repérer un
// appelant qui passerait une grosse table sans signature (les unités, les marqueurs, les ordres et les pistes d'Athena en donnent une).
if (_signature isEqualTo "") then { private _tS = diag_tickTime; _signature = str _value; ["magasin : empreinte par texte", _tS, 4, -1, "magasin : empreinte par texte (" + _domain + ")"] call comspec_atak_native_fnc_perfAcc; };
if ((_signatures getOrDefault [_domain,""]) isEqualTo _signature) exitWith { false };
if (_revision < 0) then { _revision = (_revs getOrDefault [_domain,0]) + 1; };
if ((_revs getOrDefault [_domain,-1]) isEqualTo _revision) exitWith { false };
_data set [_domain,_value]; _revs set [_domain,_revision];
_signatures set [_domain,_signature];
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
(_state getOrDefault ["dirty",createHashMap]) set [_domain,true];
true
