/*
    Côté joueur du contrôle d'identité : envoi au serveur et réponses du serveur (remoteExec, allowedTargets = 1).
    ["send", [op, args]]                        demande au serveur (fn_idServer ; appel direct en solo / hôte)
    Réponses : request [contrôleur] · token [porteur, docId, libellé, fin] · answer [cible, réponse, texte]
      docs [cible, [[poignée, classe, libellé]…]] · doc [document imprimé, N0, sujet, mien, clé] · blank [poignée]
      result [docId, niveau, résultat] · seized / returned [cible, classe…] · vehicle [véhicule, op, occupants]
      notify [texte, type] · zeus [texte, genre] · packFetch []
      say [nom, phrase, type, indice]   phrase courte d'une IA contrôlée (IA réalistes), traduite ici
    État local (jamais diffusé) : comspec_sse_idTokens (documents présentés), comspec_sse_idSearch (dernière fouille),
    comspec_sse_idSeized (pièces saisies, pour « Rendre »), comspec_sse_idPending (demandes reçues).
*/
params [["_op", "", [""]], ["_a", []]];
if (_op isEqualTo "send") exitWith {
    _a params [["_sop", ""], ["_sargs", []]];
    if (isServer) then { [_sop, _sargs] call comspec_sse_fnc_idServer; } else { [_sop, _sargs] remoteExecCall ["comspec_sse_fnc_idServer", 2]; };
    true
};
if (!hasInterface) exitWith {};
private _say = { params ["_t", ["_type", "INFO"]]; [_t, _type, "", "IDCHECK"] call comspec_sse_fnc_notify; };
private _tokens = missionNamespace getVariable ["comspec_sse_idTokens", createHashMap];
missionNamespace setVariable ["comspec_sse_idTokens", _tokens];

switch (_op) do {
    case "notify": { _a params [["_t", ""], ["_type", "INFO"]]; [_t, _type] call _say; };
    case "zeus": { _a params [["_t", ""], ["_k", "info"]]; [_t, _k] call comspec_sse_fnc_zeusNotify; };
    case "request": { _a params [["_c", objNull]]; ["request", [_c]] call comspec_sse_fnc_idPresentPrompt; };
    case "token": {
        _a params [["_holder", objNull], ["_id", ""], ["_label", ""], ["_end", 0]];
        // Fin de validité en temps serveur ramenée à l'horloge locale (time diffère d'un poste à l'autre).
        _tokens set [_id, [_holder, _label, time + ((missionNamespace getVariable ["comspec_sse_id_tokenTime", 120]) - 1)]];
        [format ["%1 vous présente : %2. Menu ACE › Contrôle d'identité › Contrôler l'identité.", [name _holder, "On"] select (isNull _holder), _label]] call _say;
    };
    case "say": {
        _a params [["_n", ""], ["_t", ""], ["_type", "INFO"], ["_hint", ""]];
        private _p = _t call comspec_sse_fnc_tr;
        private _line = if ((_t select [0, 1]) isEqualTo "(") then { format ["%1 %2", _n, _p] } else { format ["%1 : « %2 »", _n, _p] };
        if (_hint isNotEqualTo "") then { _line = _line + format [" [%1]", _hint call comspec_sse_fnc_tr]; };
        [_line, _type] call _say;
    };
    case "answer": {
        _a params [["_t", objNull], ["_ans", ""], ["_text", ""]];
        // Dernière réponse d'une personne contrôlée : reprise par les comptes rendus SALUTE / SPOTREP (fn_idReport).
        missionNamespace setVariable ["comspec_sse_idLastAnswer", [_t, _ans, _text, time]];
        [format ["%1 : %2", [name _t, "?"] select (isNull _t), _text call comspec_sse_fnc_tr], ["INFO", "WARNING"] select (_ans in ["refuse", "flee", "none"])] call _say;
    };
    case "docs": {
        _a params [["_t", objNull], ["_list", []]];
        missionNamespace setVariable ["comspec_sse_idSearch", [_t, _list, time]];
        {
            _x params ["_h", "", "_label"];
            if ((_h select [0, 2]) isEqualTo "ID") then { _tokens set [_h, [_t, _label, time + 110]]; };
        } forEach _list;
        if (_list isEqualTo []) exitWith { ["Fouille : aucun document trouvé."] call _say; };
        [format ["Fouille : %1 document(s). Menu ACE › Contrôle d'identité : examiner ou saisir.", count _list]] call _say;
    };
    case "blank": { ["Document vierge : aucun titulaire, rien d'imprimé.", "WARNING"] call _say; };
    case "doc": {
        _a params [["_pub", createHashMap], ["_res", createHashMap], ["_subj", objNull], ["_own", false], ["_key", ""]];
        if (!_own) then { ["start", [_pub, _subj, _key, _res]] call comspec_sse_fnc_idHistory; };
        [_pub, _own] call comspec_sse_fnc_idViewerOpen;
    };
    case "result": {
        _a params [["_id", ""], ["_lvl", 1], ["_res", createHashMap]];
        // Base en ligne : le joueur interroge Athena avec sa propre liaison (DLL), puis complète le résultat local.
        if (_lvl isEqualTo 3 && {["has"] call comspec_sse_fnc_idAthenaPack}) exitWith {
            [_id, _res] spawn {
                params ["_id", "_res"];
                private _raw = ["QueryIdDocument", _res getOrDefault ["query", []]] call comspec_sse_fnc_extensionCall;
                ["merge", [_res, _raw]] call comspec_sse_fnc_idAthenaPack;
                ["level", [_id, 3, _res]] call comspec_sse_fnc_idHistory;
            };
        };
        ["level", [_id, _lvl, _res]] call comspec_sse_fnc_idHistory;
    };
    case "seized": {
        _a params [["_t", objNull], ["_cls", ""]];
        private _s = missionNamespace getVariable ["comspec_sse_idSeized", createHashMap];
        _s set [_cls, netId _t];
        missionNamespace setVariable ["comspec_sse_idSeized", _s];
        [format ["Document saisi sur %1 : il est dans votre inventaire (chaîne de possession ouverte).", [name _t, "?"] select (isNull _t)], "SUCCESS"] call _say;
    };
    case "returned": {
        _a params [["_t", objNull], ["_cls", ""]];
        (missionNamespace getVariable ["comspec_sse_idSeized", createHashMap]) deleteAt _cls;
        [format ["Document rendu à %1.", [name _t, "?"] select (isNull _t)]] call _say;
    };
    case "vehicle": {
        _a params [["_v", objNull], ["_vop", "vehicle"], ["_out", []]];
        if (_out isEqualTo []) exitWith { ["Aucun occupant à contrôler."] call _say; };
        { _x params ["", "_n", "_ans", "_text"]; [format ["%1 : %2", _n, _text], ["INFO", "WARNING"] select (_ans in ["refuse", "flee", "none"])] call _say; } forEach _out;
    };
    case "packFetch": {
        [] spawn {
            private _raw = ["fetch"] call comspec_sse_fnc_idAthenaPack;
            if (_raw isNotEqualTo "") then { ["send", ["pack", [player, _raw]]] call comspec_sse_fnc_idClient; };
        };
    };
};
