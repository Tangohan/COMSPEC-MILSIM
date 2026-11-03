/*
    Questions de légende (SERVEUR). « D'où venez-vous ? », « Pour qui travaillez-vous ? », « Raison du déplacement ? »…
    ["ask", [demandeur, joueur, nombre, contexte]] -> nombre de questions posées
        une IA (checkpoint, contrôle IA) interroge un joueur : les questions partent à son poste (fn_idClient
        « question »), il répond par un choix (réponses tirées de sa vraie légende + leurres) dans l'app « Ma couverture »
        ou par son menu ACE personnel › Mes papiers › Répondre ; délai : réglage « Délai pour répondre ».
    ["answer", [joueur, qid, index]]   réponse reçue (fn_idServer « answer ») ; juste, fausse ou hors délai
    ["expire", [qid…]]                 échéance : une question sans réponse compte comme une réponse évasive
    ["pending", [joueur]] -> nombre de questions sans réponse
    ["aiAnswer", [ia, joueur, clé]] -> [phrase, indice]   un joueur interroge un PNJ : réponse selon la qualité de sa couverture
    Registre : questions  qid -> [netId, options, bonne, demandeur, contexte, échéance, clé, question]
    Évènement CBA serveur : comspec_sse_idAnswered [joueur, demandeur, juste, clé, contexte, évasif]
*/
params [["_op", "", [""]], ["_a", []]];
if (!isServer) exitWith { nil };
private _reg = ["init"] call comspec_sse_fnc_idRegistry;
private _qs = _reg getOrDefault ["questions", createHashMap];
_reg set ["questions", _qs];
private _send = {
    params ["_u", "_op2", "_args"];
    if (isNull _u || {!isPlayer _u}) exitWith {};
    if (local _u && {hasInterface}) then { [_op2, _args] call comspec_sse_fnc_idClient; } else { [_op2, _args] remoteExecCall ["comspec_sse_fnc_idClient", owner _u]; };
};
private _close = {
    params ["_qid", "_ok", "_evasive"];
    private _q = _qs getOrDefault [_qid, []];
    if (_q isEqualTo []) exitWith {};
    _qs deleteAt _qid;
    _q params ["_net", "", "", "_asker", "_ctx", "", "_k"];
    private _u = objectFromNetId _net;
    if (isNull _u) exitWith {};
    ["answer", [_u, _ok]] call comspec_sse_fnc_idCover;
    ["comspec_sse_idAnswered", [_u, _asker, _ok, _k, _ctx, _evasive]] call CBA_fnc_localEvent;
    [_u, "questionEnd", [_qid, _ok, _evasive]] call _send;
};

switch (_op) do {
    case "ask": {
        _a params [["_asker", objNull], ["_u", objNull], ["_n", 2], ["_ctx", ""]];
        if (isNull _u || {!isPlayer _u} || {!(missionNamespace getVariable ["comspec_sse_id_questions", true])}) exitWith { 0 };
        private _bank = ["questions", [_u, _n]] call comspec_sse_fnc_idLegend;
        if (_bank isEqualTo []) exitWith { 0 };
        private _delay = missionNamespace getVariable ["comspec_sse_id_questionTime", 20];
        private _end = time + _delay + 10 * ((count _bank) - 1);
        private _out = [];
        private _ids = [];
        {
            _x params ["_k", "_text", "_good", "_dec"];
            private _opts = ([_good] + _dec) call BIS_fnc_arrayShuffle;
            private _seq = (_reg getOrDefault ["qseq", 0]) + 1;
            _reg set ["qseq", _seq];
            private _qid = format ["Q%1", _seq];
            _qs set [_qid, [netId _u, _opts, _opts find _good, _asker, _ctx, _end, _k, _text]];
            _out pushBack [_qid, _text, _opts];
            _ids pushBack _qid;
        } forEach _bank;
        [_u, "question", [[name _asker, "?"] select (isNull _asker), _out, _delay + 10 * ((count _bank) - 1)]] call _send;
        [{ ["expire", _this] call comspec_sse_fnc_idQuestion; }, _ids, _end - time + 0.5] call CBA_fnc_waitAndExecute;
        count _out
    };
    case "answer": {
        _a params [["_u", objNull], ["_qid", ""], ["_i", -1]];
        private _q = _qs getOrDefault [_qid, []];
        if (_q isEqualTo [] || {(_q select 0) isNotEqualTo (netId _u)}) exitWith { false };
        // Hors délai : la personne a tardé, réponse évasive.
        if (time > (_q select 5)) exitWith { [_qid, false, true] call _close; true };
        [_qid, _i isEqualTo (_q select 2), false] call _close;
        true
    };
    case "expire": { { [_x, false, true] call _close; } forEach _a; true };
    case "pending": {
        _a params [["_u", objNull]];
        private _n = netId _u;
        { (_x select 0) isEqualTo _n } count (values _qs)
    };
    case "aiAnswer": {
        _a params [["_t", objNull], ["_me", objNull], ["_k", "origin"]];
        if (isNull _t || {!alive _t}) exitWith { ["", ""] };
        private _st = ["state", [_t]] call comspec_sse_fnc_idAiFsm;
        if (_st in ["fuit", "agressif", "renforts"]) exitWith { ["(Il ne vous répond pas.)", ""] };
        if (_st isEqualTo "refuse") exitWith { [selectRandom ["Je n'ai rien à vous dire.", "(Il hausse les épaules sans répondre.)"], ""] };
        (["aiAnswer", [_t, _k]] call comspec_sse_fnc_idLegend) params ["_txt", "_ok"];
        // Réponse qui ne colle pas : la personne sent qu'elle s'est trahie, la tension monte (automate des IA réalistes).
        if (!_ok) then { _t setVariable ["comspec_sse_idAsks", (_t getVariable ["comspec_sse_idAsks", 1]) + 1]; };
        [_txt, ["", "il hésite"] select (_st isEqualTo "nerveux" || {!_ok && {(random 1) < 0.5}})]
    };
    default { nil };
}
