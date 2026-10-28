/*
    Handler serveur des opérations SSE.
    [_op, _unit, _target, _args] call comspec_sse_fnc_serverHandleOp
*/
params [
    ["_op", "", [""]],
    ["_unit", objNull, [objNull]],
    ["_target", objNull, [objNull]],
    ["_args", [], [[]]]
];

if (!isServer) exitWith { false };
if (isNull _target) exitWith { false };

// Contrôles : l'acteur doit être sur place (ou Zeus) ; la génération est réservée à Zeus / au serveur.
private _isZeus = !isNull _unit && {!isNull getAssignedCuratorLogic _unit};
private _near = !isNull _unit && {alive _unit} && {(_unit distance _target) < 30};
if (!isNull _unit && {!_isZeus} && {!_near}) exitWith {
    [format ["serverOp %1 refusée : %2 trop loin de %3", _op, name _unit, _target], "WARN"] call comspec_sse_fnc_log;
    false
};
if ((toLower _op) isEqualTo "generate" && {!isNull _unit} && {!_isZeus}) exitWith {
    [format ["serverOp generate refusée : %1 n'est pas Zeus", name _unit], "WARN"] call comspec_sse_fnc_log;
    false
};

[format ["serverOp %1 by %2 on %3", _op, name _unit, _target]] call comspec_sse_fnc_log;

private _result = true;

switch (toLower _op) do {
    case "setstate": {
        _args params [["_state", "DISCOVERED"]];
        [_target, _state, true] call comspec_sse_fnc_setState;
    };
    case "generate": {
        _args params [["_profile", "RANDOM"], ["_complexity", "STANDARD"]];
        if (!isNil "comspec_sse_fnc_generateData") then {
            [_target, _profile, _complexity, "ZEUS"] call comspec_sse_fnc_generateData;
        };
    };
    case "collect": {
        _args params [["_quality", 50], ["_entry", createHashMap]];
        // Garde serveur : deux joueurs qui collectent en même temps, ou une collecte répétée, ne font qu'une ligne.
        // Drapeau « collected » plutôt que l'état seul : une pièce TRANSMITTED (ATAK) avant collecte reste collectable.
        private _d = [_target] call comspec_sse_fnc_getData;
        if (!isNil "_d" && {([_d, "collected", false] call comspec_sse_fnc_getPair) isEqualTo true
            || {([_d, "state", ""] call comspec_sse_fnc_getPair) isEqualTo "COLLECTED"}}) exitWith {
            [format ["serverOp collect ignorée : %1 déjà collecté", _target], "WARN"] call comspec_sse_fnc_log;
            _result = false;
        };
        // Auteur de la collecte (lu par le client avant sachet, notification et journal : fn_doCollect).
        private _byUid = if (isNull _unit) then { "" } else { [getPlayerUID _unit, netId _unit] select ((getPlayerUID _unit) isEqualTo "") };
        if (isNil "_d") then {
            ["append", _target, ["chainOfCustody", [_entry]]] call comspec_sse_fnc_recordOp;
            [_target, "COLLECTED", true] call comspec_sse_fnc_setState;
            _d = [_target] call comspec_sse_fnc_getData;
            if (!isNil "_d") then {
                _d = [_d, "collected", true] call comspec_sse_fnc_setPair;
                [_target, [_d, "collectedBy", _byUid] call comspec_sse_fnc_setPair, true] call comspec_sse_fnc_setData;
            };
        } else {
            // Une seule écriture publique (chaîne, état, drapeaux) : trois commits diffusaient la fiche entière trois fois.
            _d = +_d;
            private _sections = [_d, "sections", createHashMap] call comspec_sse_fnc_getPair;
            if !(_sections isEqualType createHashMap) then { _sections = createHashMap; };
            private _chain = _sections getOrDefault ["chainOfCustody", _sections getOrDefault ["chainofcustody", []]];
            _chain = if (_chain isEqualType []) then { +_chain } else { [] };
            _chain pushBack _entry;
            _sections deleteAt "chainofcustody";
            _sections set ["chainOfCustody", _chain];
            _d = [_d, "sections", _sections] call comspec_sse_fnc_setPair;
            // Ordre monotone (comme setState) : COLLECTED n'écrase pas TRANSMITTED, le drapeau marque la collecte.
            private _order = ["UNTOUCHED", "DISCOVERED", "SEARCHED", "PARTIALLY_EXPLOITED", "EXPLOITED", "COLLECTED", "TRANSMITTED"];
            private _curState = [_d, "state", "UNTOUCHED"] call comspec_sse_fnc_getPair;
            if (!(_curState isEqualType "")) then { _curState = "UNTOUCHED"; };
            if ((_order find (toUpper _curState)) <= (_order find "COLLECTED")) then {
                _d = [_d, "state", "COLLECTED"] call comspec_sse_fnc_setPair;
            };
            _d = [_d, "searched", true] call comspec_sse_fnc_setPair;
            _d = [_d, "exploited", true] call comspec_sse_fnc_setPair;
            _d = [_d, "collected", true] call comspec_sse_fnc_setPair;
            _d = [_d, "collectedBy", _byUid] call comspec_sse_fnc_setPair;
            [_target, _d, true] call comspec_sse_fnc_setData;
        };
        // Chaîne de possession ouverte ici, après la garde : un client refusé n'y ajoute plus de ligne COLLECTE.
        if (!isNil "comspec_sse_fnc_custody") then {
            ["open", [_target, _unit, format ["collecte, qualité %1 %%", _quality]]] call comspec_sse_fnc_custody;
        };
    };
    case "markexploited": {
        [_target, "EXPLOITED", true] call comspec_sse_fnc_setState;
    };
    default {
        [format ["serverOp inconnue: %1", _op], "WARN"] call comspec_sse_fnc_log;
    };
};

// Résultat envoyé au seul demandeur (et non plus à toutes les machines), avec la vraie valeur (collecte refusée : false).
if (!isNull _unit) then {
    ["comspec_sse_opResult", [_op, netId _target, _result], _unit] call CBA_fnc_targetEvent;
};
true
