params ["_domain","_value",["_revision",-1]];
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap];
private _revs = _data getOrDefault ["revisions",createHashMap];
if (_revision < 0) then { _revision = (_revs getOrDefault [_domain,0]) + 1; };
if ((_revs getOrDefault [_domain,-1]) isEqualTo _revision) exitWith { false };
_data set [_domain,_value]; _revs set [_domain,_revision];
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
(_state getOrDefault ["dirty",createHashMap]) set [_domain,true];
true
