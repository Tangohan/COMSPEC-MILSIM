/*
    Interrogation d'identité depuis un terminal (SEEK II, actions ACE) : construit la
    sonde à partir des relevés et l'envoie au serveur.

    Hors liaison : base locale du terminal. En liaison (COMSPEC Link) : ABIS complet
    et avis de recherche Athena.

    Params: [_target, _relevés, _declaredName, _callback, _context]
      _relevés HashMap : nfiqs [10], eyes [2], face (qualité ou -1)
    Le rappel reçoit [résultat de comspec_sse_fnc_bioSearch, _context].
*/
params [
    ["_target", objNull, [objNull]],
    ["_captures", createHashMap, [createHashMap]],
    ["_declared", "", [""]],
    ["_callback", {}, [{}]],
    ["_context", []]
];

private _link = toLower (str (missionNamespace getVariable ["COMSPEC_LinkState", "offline"]));
private _online = _link isEqualTo "linked" || {missionNamespace getVariable ["comspec_sse_bioForceOnline", false]};

private _probe = createHashMapFromArray [
    ["unit", _target],
    ["key", [_target] call comspec_sse_fnc_bioPersonKey],
    ["declaredName", _declared],
    ["scope", ["local", "abis"] select _online],
    ["nfiqs", _captures getOrDefault ["nfiqs", []]],
    ["eyes", _captures getOrDefault ["eyes", []]],
    ["face", _captures getOrDefault ["face", -1]],
    ["attempt", _captures getOrDefault ["attempt", 1]]
];
if (_online) then { _probe set ["wanted", [] call comspec_sse_fnc_bioWantedCache]; };

["search", _probe, _callback, _context] call comspec_sse_fnc_bioRequest
