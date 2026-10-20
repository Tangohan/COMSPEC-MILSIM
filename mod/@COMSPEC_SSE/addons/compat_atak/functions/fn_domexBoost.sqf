/*
    Valise DOMEX de terrain (COMSPEC_SSE_DomexCase) posée à moins de 10 m d'un support : l'extraction
    passe par son lecteur rapide et son poste de forçage (téléchargement et forçage deux fois plus courts).
    Appliqué une seule fois par support, juste avant le branchement du DL-7 depuis l'app SSE terrain (serveur).
    [_obj] call comspec_sse_fnc_domexBoost
*/
params [["_obj", objNull, [objNull]]];
if (isNull _obj) exitWith { false };
if (!isServer) exitWith { [_obj] remoteExecCall ["comspec_sse_fnc_domexBoost", 2]; true };
if (_obj getVariable ["comspec_sse_domexBoosted", false]) exitWith { false };
if ((nearestObjects [_obj, ["COMSPEC_SSE_DomexCase"], 10]) isEqualTo []) exitWith { false };

private _cfg = createHashMapFromArray (_obj getVariable ["COMSPEC_ATAK_Data", []]);
if (count _cfg isEqualTo 0) exitWith { false };
_obj setVariable ["comspec_sse_domexBoosted", true, true];
[_obj, [
    ["duration", ((_cfg getOrDefault ["duration", 45]) / 2) max 10],
    ["crackTime", ((_cfg getOrDefault ["crackTime", 90]) / 2) max 20]
], true] call comspec_atak_native_fnc_dataSetup;
true
