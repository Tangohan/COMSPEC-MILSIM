/*
    IA réalistes face au contrôle d'identité (SERVEUR) : machine à états CBA (CBA_statemachine_fnc_create, addState,
    addTransition). La liste ne contient que les IA engagées dans un contrôle (interpellées par un joueur, un checkpoint
    IA ou le Zeus) : la machine traite UNE unité par image, les autres IA ne coûtent rien. Chaque unité ne recalcule
    son contexte qu'une fois par seconde (fn_idAiBrain), les ordres partent là où elle est locale (comspec_sse_idAiDo).

    États :  calme · interpelle · coopere · nerveux · ment · refuse · fuit · agressif · rendu · renforts
      calme      ->  interpelle                         (un contrôleur l'aborde)
      interpelle ->  coopere | ment | nerveux | refuse | fuit | agressif | rendu | renforts | calme
      coopere    ->  nerveux | refuse | fuit | agressif | rendu | renforts | calme
      ment       ->  nerveux | refuse | fuit | agressif | rendu | renforts | calme
      nerveux    ->  coopere | ment | refuse | fuit | agressif | rendu | renforts | calme
      refuse     ->  coopere | ment | nerveux | fuit | agressif | rendu | renforts | calme
      fuit       ->  rendu | agressif | calme           agressif -> rendu | fuit | calme
      renforts   ->  agressif | fuit | refuse | rendu   rendu    -> calme | fuit
    Une transition suit le souhait calculé par fn_idAiBrain (comspec_sse_idWant) ; le Zeus impose un état à la main.

    ["init"]                        crée l'automate (XEH_postInit serveur, une fois)
    ["engage", [ia, contrôleur]]    l'IA est abordée : elle entre dans l'automate (ou change de contrôleur)
    ["ask", [ia, contrôleur]] -> [état, délai]   « Papiers, s'il vous plaît » : décision immédiate (fn_idAiAnswer)
    ["force", [ia, état]]           Zeus : impose un état (ordres réels exécutés)
    ["bust", [ia, contrôleur]]      un faux vient d'être découvert sur ses papiers : la tension monte
    ["release", [ia]]               fin du contrôle : retour au calme (laissé partir)
    ["state", [ia]] -> état ("" hors automate)          ["states"] -> liste des états
*/
params [["_op", "", [""]], ["_a", []]];
if (!isServer) exitWith { "" };
private _states = ["calme", "interpelle", "coopere", "nerveux", "ment", "refuse", "fuit", "agressif", "rendu", "renforts"];
if (_op isEqualTo "states") exitWith { _states };
private _sm = missionNamespace getVariable ["comspec_sse_idFsm", locationNull];

if (_op isEqualTo "init") exitWith {
    if (!isNull _sm) exitWith { _sm };
    if (isNil "CBA_statemachine_fnc_create") exitWith { locationNull };
    comspec_sse_idFsmList = [];
    // Liste relue à chaque tour complet : unités vivantes encore engagées (les autres sortent, coût nul).
    _sm = [{
        comspec_sse_idFsmList = comspec_sse_idFsmList select { !isNull _x && {alive _x} && {!(_x getVariable ["comspec_sse_idDone", false])} };
        +comspec_sse_idFsmList
    }, true] call CBA_statemachine_fnc_create;
    {
        private _name = _x;
        [_sm,
            { [_this, _thisState] call comspec_sse_fnc_idAiBrain },
            compile format ["[_this, '%1'] call comspec_sse_fnc_idAiEnter", _name],
            {},
            _name] call CBA_statemachine_fnc_addState;
    } forEach _states;
    {
        _x params ["_from", "_tos"];
        {
            [_sm, _from, _x,
                compile format ["(_this getVariable ['comspec_sse_idWant', '']) isEqualTo '%1'", _x],
                { _this setVariable ["comspec_sse_idWant", ""]; },
                format ["%1>%2", _from, _x]] call CBA_statemachine_fnc_addTransition;
        } forEach _tos;
    } forEach [
        ["calme", ["interpelle"]],
        ["interpelle", ["coopere", "ment", "nerveux", "refuse", "fuit", "agressif", "rendu", "renforts", "calme"]],
        ["coopere", ["nerveux", "refuse", "fuit", "agressif", "rendu", "renforts", "calme"]],
        ["ment", ["nerveux", "refuse", "fuit", "agressif", "rendu", "renforts", "calme"]],
        ["nerveux", ["coopere", "ment", "refuse", "fuit", "agressif", "rendu", "renforts", "calme"]],
        ["refuse", ["coopere", "ment", "nerveux", "fuit", "agressif", "rendu", "renforts", "calme"]],
        ["fuit", ["rendu", "agressif", "calme"]],
        ["agressif", ["rendu", "fuit", "calme"]],
        ["renforts", ["agressif", "fuit", "refuse", "rendu"]],
        ["rendu", ["calme", "fuit"]]
    ];
    missionNamespace setVariable ["comspec_sse_idFsm", _sm];
    _sm
};
if (isNull _sm) then { _sm = ["init"] call comspec_sse_fnc_idAiFsm; };
if (isNull _sm) exitWith { "" };

_a params [["_u", objNull, [objNull]]];
if (isNull _u || {isPlayer _u} || {!(_u isKindOf "CAManBase")}) exitWith { "" };
private _cur = { if (_u in comspec_sse_idFsmList) then { [_u, _sm] call CBA_statemachine_fnc_getCurrentState } else { "" } };
private _go = {
    params ["_to"];
    private _from = call _cur;
    if (_from isEqualTo "") then { _from = "calme"; };
    if (_from isEqualTo _to) exitWith {};
    _u setVariable ["comspec_sse_idWant", ""];
    [_u, _sm, _from, _to, {}, "COMSPEC"] call CBA_statemachine_fnc_manualTransition;
};
private _engage = {
    params ["_c"];
    _u setVariable ["comspec_sse_idDone", false];
    if (!isNull _c) then { _u setVariable ["comspec_sse_idCtrl", _c]; _u setVariable ["comspec_sse_idSeen", time]; };
    if !(_u in comspec_sse_idFsmList) then {
        comspec_sse_idFsmList pushBack _u;
        _u setVariable ["comspec_sse_idTension", nil];
        _u setVariable ["comspec_sse_idProfile", nil];
        _u setVariable ["comspec_sse_idCalled", false];
        _u setVariable ["comspec_sse_idBust", 0];
    };
};

switch (_op) do {
    case "state": { call _cur };
    case "engage": {
        _a params ["", ["_c", objNull, [objNull]]];
        if !(missionNamespace getVariable ["comspec_sse_id_aiFsm", true]) exitWith {};
        [_c] call _engage;
        if ((call _cur) in ["", "calme"]) then { "interpelle" call _go; };
    };
    case "ask": {
        _a params ["", ["_c", objNull, [objNull]]];
        [_c] call _engage;
        _u setVariable ["comspec_sse_idAsks", (_u getVariable ["comspec_sse_idAsks", 0]) + 1];
        private _st = call _cur;
        if (_st in ["", "calme"]) then { "interpelle" call _go; _st = "interpelle"; };
        // Décision tout de suite (pas d'attente d'un tour de l'automate) : le contrôleur attend sa réponse.
        private _want = ["decide", [_u, _st, true]] call comspec_sse_fnc_idAiBrain;
        if (_want isEqualTo "" || {_want isEqualTo "calme"}) then { _want = _st; };
        if (_want isEqualTo "interpelle") then { _want = "coopere"; };
        // Comportement choisi (profil Athena, Zeus, Eden) : il décide à la place du contexte.
        private _imposed = _u getVariable ["COMSPEC_ID_Behaviour", "auto"];
        if (_imposed in ["coopere", "nerveux", "ment", "refuse", "fuit", "agressif", "rendu"]) then { _want = _imposed; };
        _want call _go;
        // Hésitation avant de répondre : elle se voit (nervosité, mensonge préparé ou non).
        private _real = (missionNamespace getVariable ["comspec_sse_id_difficulty", 1]) >= 2;
        [_want, switch (_want) do {
            case "nerveux": { 2 + random 1.5 };
            case "ment": { [1.2, 0.4] select _real };
            case "refuse"; case "renforts": { 1 };
            case "coopere": { 0.5 };
            default { 0 };
        }]
    };
    case "force": {
        _a params ["", ["_to", "", [""]]];
        if !(_to in _states) exitWith { false };
        // « Calme » : laisser partir (hors de l'automate si elle y était, rien sinon).
        if (_to isEqualTo "calme") exitWith {
            if (_u in comspec_sse_idFsmList) then { _u setVariable ["comspec_sse_idCtrl", objNull]; "calme" call _go; _u setVariable ["comspec_sse_idDone", true]; };
            true
        };
        [objNull] call _engage;
        _u setVariable ["comspec_sse_idSeen", time];
        if (isNull (_u getVariable ["comspec_sse_idCtrl", objNull])) then {
            private _p = allPlayers select { alive _x } apply { [_x distance _u, _x] };
            _p sort true;
            if (_p isNotEqualTo []) then { _u setVariable ["comspec_sse_idCtrl", (_p select 0) select 1]; };
        };
        _u setVariable ["comspec_sse_idForced", time];
        _to call _go;
        true
    };
    case "bust": {
        _a params ["", ["_c", objNull, [objNull]]];
        if !(_u in comspec_sse_idFsmList) exitWith {};
        _u setVariable ["comspec_sse_idBust", 30];
        _u setVariable ["comspec_sse_idNext", 0];
    };
    case "release": {
        if !(_u in comspec_sse_idFsmList) exitWith {};
        _u setVariable ["comspec_sse_idCtrl", objNull];
        "calme" call _go;
        _u setVariable ["comspec_sse_idDone", true];
    };
};
