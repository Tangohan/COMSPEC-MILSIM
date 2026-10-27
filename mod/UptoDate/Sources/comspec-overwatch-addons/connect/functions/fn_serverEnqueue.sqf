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
    // Garde-fou : trop d’entrées → exécution immédiate des plus anciennes (échéance la plus proche) jusqu’à 120.
    // serverFlushQueue n’exécute que les entrées échues : juste après un ajout, rien ne l’est (débounce).
    private _byDue = (keys _queue) apply { [(_queue getOrDefault [_x, [0]]) param [0, 0], _x] };
    _byDue sort true;
    {
        // getOrDefault : une clé déjà retirée (code ré-entrant) ne rend pas nil.
        private _oldKey = _x select 1;
        private _oldEntry = _queue getOrDefault [_oldKey, []];
        _queue deleteAt _oldKey;
        if ((_oldEntry isEqualType []) && {(count _oldEntry) >= 2}) then {
            private _oldCode = _oldEntry param [1, {}];
            private _oldArgs = _oldEntry param [2, []];
            if (_oldCode isEqualType {}) then { _oldArgs call _oldCode; };
        };
    } forEach (_byDue select [0, _n - 120]);
};

true
