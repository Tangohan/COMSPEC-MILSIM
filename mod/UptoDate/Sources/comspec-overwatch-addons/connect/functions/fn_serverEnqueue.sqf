/*
    File d’événements serveur (anti-spam).
    Params: [_key, _code, _debounceSec, _args]
      _key         : identifiant unique (même clé = remplace l’entrée précédente)
      _code        : code à exécuter au flush
      _debounceSec : délai avant exécution (défaut 0.6)
      _args        : arguments passés au code
*/
params [
    ["_key", "", [""]],
    ["_code", {}, [{}]],
    ["_debounceSec", 0.6, [0]],
    ["_args", [], [[]]]
];

if (!isServer) exitWith { false };
if (_key isEqualTo "") exitWith { false };
if (_debounceSec < 0) then { _debounceSec = 0; };

private _queue = missionNamespace getVariable ["COMSPEC_ServerQueue", createHashMap];
if (!(_queue isEqualType createHashMap)) then { _queue = createHashMap; };

private _due = diag_tickTime + _debounceSec;
_queue set [_key, [_due, _code, _args]];
missionNamespace setVariable ["COMSPEC_ServerQueue", _queue, false];

private _n = count (keys _queue);
if (_n > 120) then {
    // Garde-fou : trop d’entrées → flush immédiat des plus anciennes
    [] call comspec_overwatch_connect_fnc_serverFlushQueue;
};

true
