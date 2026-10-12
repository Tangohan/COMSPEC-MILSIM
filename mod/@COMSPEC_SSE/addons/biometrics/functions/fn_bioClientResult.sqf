/*
    Réception côté joueur d'une réponse du moteur biométrique.
    [_requestId, _packedResult] call comspec_sse_fnc_bioClientResult
*/
params [["_id", ""], ["_packed", []]];
private _cbs = uiNamespace getVariable ["comspec_sse_bioCallbacks", createHashMap];
private _entry = _cbs getOrDefault [_id, [{}, []]];
_cbs deleteAt _id;
_entry params [["_cb", {}], ["_ctx", []]];
private _res = [_packed] call comspec_sse_fnc_bioUnpack;
if (!(_res isEqualType createHashMap)) then { _res = createHashMap; };
[_res, _ctx] call _cb;
