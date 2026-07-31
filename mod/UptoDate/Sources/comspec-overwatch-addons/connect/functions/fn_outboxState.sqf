/*
    État du tampon hors ligne, pour l'affichage.

    Un tampon invisible ne vaut guère mieux qu'une perte : l'opérateur doit savoir
    qu'il a des transmissions en attente, sinon il repart en croyant avoir rendu
    compte. C'est ce que cette fonction sert au terminal et au hub.

    Params: ["get"]   → HashMap : count, oldest_age, labels
            ["clear"] → vide le tampon (abandon assumé, tracé)

    Returns: HashMap
*/
params [["_mode", "get", [""]]];

private _out = createHashMap;
_out set ["count", 0];
_out set ["oldest_age", 0];
_out set ["labels", []];

if (!hasInterface) exitWith { _out };

private _queue = profileNamespace getVariable ["COMSPEC_Outbox", []];
if (!(_queue isEqualType [])) then { _queue = []; };

if ((toLower _mode) isEqualTo "clear") exitWith {
    private _n = count _queue;
    profileNamespace setVariable ["COMSPEC_Outbox", []];
    saveProfileNamespace;
    if (_n > 0) then {
        [
            "WARN",
            "Outbox",
            format ["Tampon vidé à la demande — %1 transmission(s) abandonnée(s)", _n]
        ] call comspec_overwatch_connect_fnc_log;
        [
            format ["%1 transmission(s) en attente abandonnée(s). Elles ne partiront pas.", _n],
            "tactical",
            "warn"
        ] call comspec_overwatch_connect_fnc_announce;
    };
    _out
};

private _labels = [];
private _oldest = 0;
{
    _labels pushBack (_x param [2, "?"]);
    private _age = time - (_x param [4, time]);
    if (_age > _oldest) then { _oldest = _age; };
} forEach _queue;

_out set ["count", count _queue];
_out set ["oldest_age", round _oldest];
_out set ["labels", _labels];

_out
