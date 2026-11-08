/*
    « Papiers, s'il vous plaît » reçu par un joueur, et sa réponse (menu ACE personnel « Mes papiers »).
    ["request", [contrôleur]]   demande reçue : notification ATAK (vibreur, sans son en mode discrétion), 60 s
    ["pending"]                 -> [[contrôleur, fin]…] demandes encore valables
    ["target"]                  -> à qui présenter : demande la plus récente à moins de 4 m, sinon la personne visée
    ["present", [classe, "present" | "give"]]   tendre (jeton de 120 s) ou remettre l'objet, 1 s
    ["refuse"]                  refuser : chaque demandeur est prévenu
*/
params [["_op", "", [""]], ["_a", []]];
private _pend = (missionNamespace getVariable ["comspec_sse_idPending", []]) select { !isNull (_x select 0) && {(_x select 1) > time} };
missionNamespace setVariable ["comspec_sse_idPending", _pend];
switch (_op) do {
    case "request": {
        _a params [["_c", objNull]];
        if (isNull _c) exitWith {};
        _pend = _pend select { (_x select 0) isNotEqualTo _c };
        _pend pushBack [_c, time + 60];
        missionNamespace setVariable ["comspec_sse_idPending", _pend];
        ["Vos papiers, s'il vous plaît.", "WARNING", [_c, objNull] select (isPlayer _c), format [("%1 vous demande vos papiers. Menu ACE personnel › Mes papiers : présenter ou refuser." call comspec_sse_fnc_tr), name _c]] call comspec_sse_fnc_toast;
        // Alerte discrète : la coque vibre ; le son suit le réglage discrétion de l'ATAK (COMSPEC_ATAK_Silent).
        if (!isNil "comspec_atak_native_fnc_vibrate") then { [] call comspec_atak_native_fnc_vibrate; };
    };
    case "pending": { _pend };
    case "target": {
        private _near = _pend select { ((_x select 0) distance player) <= 4 };
        if (_near isNotEqualTo []) exitWith { (_near select -1) select 0 };
        private _c = cursorObject;
        if (isNull _c || {!(_c isKindOf "CAManBase")}) then { _c = cursorTarget; };
        if (isNull _c || {!(_c isKindOf "CAManBase")} || {_c isEqualTo player} || {(player distance _c) > 3}) exitWith { objNull };
        _c
    };
    case "present": {
        _a params [["_cls", ""], ["_mode", "present"]];
        private _to = ["target"] call comspec_sse_fnc_idPresentPrompt;
        if (isNull _to) exitWith { ["Personne à qui présenter vos papiers : regardez-la, à moins de 3 m.", "WARNING", "", "IDCHECK"] call comspec_sse_fnc_notify; };
        ["send", ["begin", [player, "present", player]]] call comspec_sse_fnc_idClient;
        [1, ["Vous tendez votre document…", "Vous remettez votre document…"] select (_mode isEqualTo "give"), {
            params ["_cls", "_to", "_mode"];
            ["send", ["present", [player, _cls, _to, _mode]]] call comspec_sse_fnc_idClient;
            missionNamespace setVariable ["comspec_sse_idPending", (missionNamespace getVariable ["comspec_sse_idPending", []]) select { (_x select 0) isNotEqualTo _to }];
        }, [_cls, _to, _mode], _to] call comspec_sse_fnc_progressAction;
    };
    case "refuse": {
        ["send", ["refuse", [player]]] call comspec_sse_fnc_idClient;
        missionNamespace setVariable ["comspec_sse_idPending", []];
        ["Vous refusez de présenter vos papiers.", "INFO", "", "IDCHECK"] call comspec_sse_fnc_notify;
    };
};
