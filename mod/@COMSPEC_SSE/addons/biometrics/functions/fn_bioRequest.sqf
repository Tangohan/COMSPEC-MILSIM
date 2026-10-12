/*
    Envoie une demande au moteur biométrique (serveur) et rappelle _callback avec
    le résultat (HashMap) quand il revient. En solo / hôte, appel direct.

    [_op, _payload, _callback, _context] call comspec_sse_fnc_bioRequest
      _op  "search" | "enroll" | "latent" | "dna"
      le rappel reçoit [_résultat, _context] (le code SQF ne capture pas les locales)
*/
params [["_op", "search", [""]], ["_payload", createHashMap, [createHashMap]], ["_callback", {}, [{}]], ["_context", []]];

private _cbs = uiNamespace getVariable ["comspec_sse_bioCallbacks", createHashMap];
private _n = (uiNamespace getVariable ["comspec_sse_bioReqCounter", 0]) + 1;
uiNamespace setVariable ["comspec_sse_bioReqCounter", _n];
private _id = format ["%1-%2", clientOwner, _n];
_cbs set [_id, [_callback, _context]];
uiNamespace setVariable ["comspec_sse_bioCallbacks", _cbs];

private _packed = [_payload] call comspec_sse_fnc_bioPack;
if (isServer) exitWith {
    [_op, _packed, _id, player] call comspec_sse_fnc_bioServerHandle;
    _id
};
["comspec_sse_bioReq", [_op, _packed, _id, player]] call CBA_fnc_serverEvent;
_id
