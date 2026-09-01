/*
    Rejoue les transmissions mises en attente pendant une coupure.

    ## Temporisation

    On ne réessaie pas à chaque appel. Une liaison qui vient de revenir est souvent
    instable : marteler l'extension au retour produit une salve d'échecs qui
    remplit le journal et fait croire à une panne. Chaque entrée porte son nombre
    de tentatives, et l'attente double à chaque échec.

    ## Péremption

    Une entrée trop vieille n'est pas rejouée. Un compte rendu de contact
    transmis quarante minutes après les faits ne renseigne plus le poste de
    commandement — il le trompe, parce qu'il arrive avec l'horodatage de sa
    réception et se lit comme une information fraîche. Les entrées périmées sont
    écartées **en le disant** : l'opérateur doit savoir que son message n'est pas
    parti, sinon il croit l'avoir envoyé.

    Params: [_force] — true : ignore la temporisation (rétablissement manuel)
    Returns: Number — transmissions passées
*/
params [["_force", false, [false]]];

if (!hasInterface) exitWith { 0 };

private _queue = profileNamespace getVariable ["COMSPEC_Outbox", []];
if (!(_queue isEqualType [])) then { _queue = []; };
_queue = _queue select {
    !((toLower (_x param [0, ""])) in ["syncwardrobe", "syncwardrobesbatch"])
};
if (_queue isEqualTo []) exitWith {
    profileNamespace setVariable ["COMSPEC_Outbox", []];
    0
};

// Rien ne sert d'essayer si la liaison n'est pas revenue.
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { 0 };
private _link = [false] call comspec_overwatch_connect_fnc_canTransmit;
if (!(_link getOrDefault ["can_transmit", true])) exitWith { 0 };

// Temporisation globale entre deux salves.
private _nextAt = missionNamespace getVariable ["COMSPEC_OutboxNextAt", 0];
if (!_force && { time < _nextAt }) exitWith { 0 };

private _maxAge = missionNamespace getVariable ["COMSPEC_OutboxMaxAge", 1800];
if (!(_maxAge isEqualType 0) || { _maxAge < 60 }) then { _maxAge = 1800; };
private _maxTries = missionNamespace getVariable ["COMSPEC_OutboxMaxTries", 5];
if (!(_maxTries isEqualType 0) || { _maxTries < 1 }) then { _maxTries = 5; };

// L'âge se mesure en horloge murale ; la temporisation, elle, reste en temps de
// mission — c'est une cadence de session, pas une durée à conserver.
private _now = [] call comspec_overwatch_connect_fnc_wallClockSeconds;

private _remaining = [];
private _sent = 0;
private _expired = 0;
private _failed = 0;

{
    _x params ["_cmd", "_args", "_label", "_linkCat", "_queuedAt", "_tries"];

    private _age = _now - _queuedAt;

    switch (true) do {
        // Entrée écrite par une version antérieure, horodatée en temps de mission :
        // elle est inexploitable et son âge ne veut rien dire. On l'écarte plutôt
        // que de risquer de rejouer une demande d'une opération précédente.
        case (_queuedAt < 1000000000 || { _age < 0 }): {
            _expired = _expired + 1;
            [
                "WARN",
                "Outbox",
                format ["Horodatage inexploitable, entrée écartée : %1", _label]
            ] call comspec_overwatch_connect_fnc_log;
        };
        case (_age > _maxAge): {
            _expired = _expired + 1;
            [
                "WARN",
                "Outbox",
                format ["Périmée après %1 s, non transmise : %2", round _age, _label]
            ] call comspec_overwatch_connect_fnc_log;
        };
        case (_tries >= _maxTries): {
            _expired = _expired + 1;
            [
                "WARN",
                "Outbox",
                format ["Abandonnée après %1 tentatives : %2", _tries, _label]
            ] call comspec_overwatch_connect_fnc_log;
        };
        default {
            // On repasse par le chemin normal, sans remise en file : sinon un
            // échec réinjecterait indéfiniment la même entrée.
            private _res = [_cmd, _args, _label, false, true, _linkCat, false]
                call comspec_overwatch_connect_fnc_callExtLogged;

            if (_res param [0, false]) then {
                _sent = _sent + 1;
            } else {
                _failed = _failed + 1;
                _remaining pushBack [_cmd, _args, _label, _linkCat, _queuedAt, _tries + 1];
            };
        };
    };
} forEach _queue;

profileNamespace setVariable ["COMSPEC_Outbox", _remaining];
saveProfileNamespace;

// Attente croissante tant qu'il reste des échecs.
if (_remaining isEqualTo []) then {
    missionNamespace setVariable ["COMSPEC_OutboxNextAt", 0];
} else {
    private _base = missionNamespace getVariable ["COMSPEC_OutboxRetryBase", 15];
    private _worst = 0;
    { _worst = _worst max (_x param [5, 0]); } forEach _remaining;
    missionNamespace setVariable ["COMSPEC_OutboxNextAt", time + (_base * (2 ^ (_worst min 5)))];
};

if (_failed > 0) then {
    [
        "WARN",
        "Outbox",
        format ["%1 transmission(s) ont échoué et restent en attente (nouvelle tentative différée)", _failed]
    ] call comspec_overwatch_connect_fnc_log;
};

if (_sent > 0) then {
    [
        format ["Liaison rétablie — %1 transmission(s) en attente envoyée(s).", _sent],
        "tactical",
        "info"
    ] call comspec_overwatch_connect_fnc_announce;
};

if (_expired > 0) then {
    [
        format [
            "%1 transmission(s) non parvenue(s) et abandonnée(s). À refaire si l'information vaut toujours.",
            _expired
        ],
        "tactical",
        "warn"
    ] call comspec_overwatch_connect_fnc_announce;
};

_sent
