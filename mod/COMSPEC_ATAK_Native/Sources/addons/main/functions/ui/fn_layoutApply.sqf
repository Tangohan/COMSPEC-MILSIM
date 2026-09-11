disableSerialization; private _d=findDisplay 88500; if (isNull _d) exitWith {false}; private _l=[] call comspec_atak_native_fnc_layoutGet;
private _map=_d displayCtrl 88530; _map ctrlSetPosition [_l get "centerX",_l get "bodyY",_l get "centerW",_l get "bodyH"]; _map ctrlCommit 0;
private _content=_d displayCtrl 88531; _content ctrlSetPosition [_l get "centerX",_l get "bodyY",_l get "centerW",_l get "bodyH"]; _content ctrlCommit 0; true
