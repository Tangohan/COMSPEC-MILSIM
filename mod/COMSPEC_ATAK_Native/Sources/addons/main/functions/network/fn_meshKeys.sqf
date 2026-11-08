/*
    Clés du maillage (lots 6 et 7, réglage serveur comspec_atak_native_mesh_keys, décoché par défaut). MÉCANIQUE DE JEU : une clé est une étiquette et
    un numéro d'époque (K-ALPHA, époque 3). Aucune cryptographie, aucun secret, rien de réel n'est protégé.
    Serveur : registre COMSPEC_ATAK_MeshKeyReg, table publique COMSPEC_ATAK_MeshEpochs = [[id, nom, époque, compromise, camp, est clé de réseau,
    heure du dernier changement]...], 16 lignes au plus, diffusée sur changement seulement. Une clé de réseau par camp, créée à la première demande.
    Client : COMSPEC_ATAK_MeshKeysMine, HashMap id -> époque, reçue par événement ciblé. Publié : un petit entier, id x 1000 + époque, dans COMSPEC_ATAK_MpuNode.
    Clé de réseau absente ou périmée : hors du graphe des autres (fn_meshGraph), portée radio normale. Clé de groupe absente ou périmée :
    message « [CHIFFRÉ · nom] », voix sans rallonge (décision 2). Après un changement de clé, 30 s pour renouveler ; ensuite un nœud isolé reste périmé
    jusqu'à son retour près d'un allié autorisé, ou jusqu'au chargeur de clés.
    Compromission (décision 1) : automatique quand un joueur d'un autre camp ramasse une MPU5 ou un chargeur (réglage mesh_compromise_auto), ou sur ordre.
    Tant que l'époque n'est pas renouvelée, sa clé de réseau reste valable pour lui : il relaie dans le graphe de l'autre camp.
    Limite : il ne lit pas ce graphe pour lui-même. Code TFAR (décision 17, réglage mesh_tfar_code, décoché) : ne s'entendent que les radios de même code, À CONFIRMER EN JEU.
      "on" ; "init" (serveur) ; "tick" (client, 2 s)
      "define" [nom, camp, réseau] (serveur) -> id ; "request" [auto] ; "zeroize" ; "rekey" [id] ; "compromise" [id] ; "taken" [objet d'où vient l'appareil]
      "nodeKey" -> entier à publier ; "authorized" [camp, entier] -> vrai si ce nœud est admis ; "compromisedNk" [camp] -> entier ou -9
      "state" [id] -> "valide" | "à renouveler" | "absente" | "compromise" ; "rows" -> lignes de l'app Wave Relay ; "unknown" -> appareils non autorisés à portée
      "stamp" [groupe] -> [id, époque] ou [] ; "open" [étiquette, texte] -> texte ou [CHIFFRÉ · nom] ; "voiceOk" -> vrai si ma clé couvre le groupe de ma radio
*/
params [["_act", ""], ["_arg", []], ["_caller", objNull]];
private _on = (missionNamespace getVariable ["comspec_atak_native_mesh_keys", false]) isEqualTo true;
private _rowOf = {
    params ["_id"];
    private _i = (missionNamespace getVariable ["COMSPEC_ATAK_MeshEpochs", []]) findIf { (_x param [0, -1]) isEqualTo _id };
    if (_i < 0) exitWith { [] };
    (missionNamespace getVariable "COMSPEC_ATAK_MeshEpochs") select _i
};
private _rowByName = {
    params ["_name"];
    private _i = (missionNamespace getVariable ["COMSPEC_ATAK_MeshEpochs", []]) findIf { (_x param [1, ""]) isEqualTo _name };
    if (_i < 0) exitWith { [] };
    (missionNamespace getVariable "COMSPEC_ATAK_MeshEpochs") select _i
};
private _netRow = {
    params ["_side"];
    private _i = (missionNamespace getVariable ["COMSPEC_ATAK_MeshEpochs", []]) findIf { (_x param [4, ""]) isEqualTo _side && {_x param [5, false]} };
    if (_i < 0) exitWith { [] };
    (missionNamespace getVariable "COMSPEC_ATAK_MeshEpochs") select _i
};
private _say = {
    params ["_type", "_msg"];
    [_type, _msg, 6, 40] call comspec_atak_native_fnc_notify;
};
switch (_act) do {
    case "on": { _on };
    // ---- Lecture : sans le réglage, tout est admis et rien n'est chiffré.
    case "nodeKey": {
        if (!_on) exitWith { -2 };
        private _row = [str side group player] call _netRow;
        if (_row isEqualTo []) exitWith { -1 };
        // Clé compromise récupérée sur l'autre camp : on publie celle-là.
        private _mine = missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysMine", createHashMap];
        private _foreign = (keys _mine) select { private _r = [_x] call _rowOf; _r isNotEqualTo [] && {_r select 5} && {(_r select 4) isNotEqualTo str side group player} && {_r select 3} };
        if ((_row select 0) in _mine) exitWith { (_row select 0) * 1000 + (_mine get (_row select 0)) };
        if (_foreign isNotEqualTo []) exitWith { (_foreign select 0) * 1000 + (_mine get (_foreign select 0)) };
        -1
    };
    case "authorized": {
        if (!_on) exitWith { true };
        _arg params [["_side", ""], ["_nk", -1]];
        private _row = [_side] call _netRow;
        // Registre pas encore prêt pour ce camp : personne n'est exclu.
        if (_row isEqualTo []) exitWith { true };
        private _t = (_row select 0) * 1000;
        private _now = [time, serverTime] select isMultiplayer;
        _nk isEqualTo (_t + (_row select 2)) || {_nk isEqualTo (_t + (_row select 2) - 1) && {_now - (_row param [6, -1000]) < 30}}
    };
    case "compromisedNk": {
        if (!_on) exitWith { -9 };
        private _row = [_arg param [0, ""]] call _netRow;
        if (_row isEqualTo [] || {!(_row select 3)}) exitWith { -9 };
        (_row select 0) * 1000 + (_row select 2)
    };
    case "state": {
        private _row = [_arg param [0, -1]] call _rowOf;
        if (_row isEqualTo []) exitWith { "absente" };
        private _mine = missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysMine", createHashMap];
        if !((_row select 0) in _mine) exitWith { "absente" };
        if (_row select 3) exitWith { "compromise" };
        ["à renouveler", "valide"] select ((_mine get (_row select 0)) isEqualTo (_row select 2))
    };
    case "stamp": {
        if (!_on) exitWith { [] };
        private _kn = ["keyOf", [_arg param [0, 0]]] call comspec_atak_native_fnc_meshGroups;
        if (_kn isEqualTo "") exitWith { [] };
        private _row = [_kn] call _rowByName;
        if (_row isEqualTo []) exitWith { [] };
        [_row select 0, _row select 2]
    };
    case "open": {
        _arg params [["_kk", []], ["_text", ""]];
        if (!_on || {!(_kk isEqualType [])} || {(count _kk) isNotEqualTo 2}) exitWith { _text };
        private _mine = missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysMine", createHashMap];
        if ((_mine getOrDefault [_kk select 0, -1]) isEqualTo (_kk select 1)) exitWith { _text };
        format ["[CHIFFRÉ · %1]", ([_kk select 0] call _rowOf) param [1, "?"]]
    };
    case "voiceOk": {
        if (!_on) exitWith { true };
        (missionNamespace getVariable ["COMSPEC_ATAK_VoiceMe", []]) params [["_me", []]];
        if (_me isEqualTo []) exitWith { false };
        private _f = if ((_me select 0) isEqualTo "acre") then { (["freq", [_me select 1]] call comspec_atak_native_fnc_mpu5) param [0, 0] } else { _me select 2 };
        private _kk = ["stamp", [["ofFreq", [_f]] call comspec_atak_native_fnc_meshGroups]] call comspec_atak_native_fnc_meshKeys;
        if (_kk isEqualTo []) exitWith { true };
        ((missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysMine", createHashMap]) getOrDefault [_kk select 0, -1]) isEqualTo (_kk select 1)
    };
    // ---- Client
    case "tick": {
        if (!hasInterface || {!_on}) exitWith {};
        // Porteur d'une clé : le corps garde son camp, pour la compromission (fn_meshKeys "taken").
        if ((player getVariable ["COMSPEC_ATAK_MeshOwnerSide", ""]) isNotEqualTo str side group player) then { player setVariable ["COMSPEC_ATAK_MeshOwnerSide", str side group player, true]; };
        if !(missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysTake", false]) then {
            missionNamespace setVariable ["COMSPEC_ATAK_MeshKeysTake", true];
            ["comspec_atak_native_meshKeyGive", {
                params [["_id", -1], ["_epoch", 0]];
                private _mine = missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysMine", createHashMap];
                _mine set [_id, _epoch];
                missionNamespace setVariable ["COMSPEC_ATAK_MeshKeysMine", _mine];
                uiNamespace setVariable ["COMSPEC_ATAK_MpuMeshAt", -100];
            }] call CBA_fnc_addEventHandler;
            ["comspec_atak_native_meshKeyAlert", { params [["_msg", ""]]; ["WARNING", _msg, 8, 50] call comspec_atak_native_fnc_notify; }] call CBA_fnc_addEventHandler;
            player addEventHandler ["Take", { params ["", "_from", "_item"]; ["taken", [_from, _item]] call comspec_atak_native_fnc_meshKeys; }];
            player addEventHandler ["Respawn", { params ["_new"]; _new addEventHandler ["Take", { params ["", "_from", "_item"]; ["taken", [_from, _item]] call comspec_atak_native_fnc_meshKeys; }]; }];
        };
        private _mine = missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysMine", createHashMap];
        private _auto = (missionNamespace getVariable ["comspec_atak_native_mesh_keys_auto", true]) isEqualTo true;
        private _at = uiNamespace getVariable ["COMSPEC_ATAK_MeshKeysAskAt", -100];
        if (diag_tickTime - _at < 10) exitWith {};
        // Clé périmée ou absente : demande au serveur, qui juge. Sans chargement automatique, rien tant que la clé n'a pas été chargée une fois.
        private _stale = (missionNamespace getVariable ["COMSPEC_ATAK_MeshEpochs", []]) select { private _e = _mine getOrDefault [_x select 0, -1]; _e >= 0 && {_e isNotEqualTo (_x select 2)} };
        if ((_auto && {(count _mine) isEqualTo 0} && {!(uiNamespace getVariable ["COMSPEC_ATAK_MeshZeroized", false])}) || {_stale isNotEqualTo []}) then {
            uiNamespace setVariable ["COMSPEC_ATAK_MeshKeysAskAt", diag_tickTime];
            ["comspec_atak_native_meshKeysSrv", [player, "request", [false]]] call CBA_fnc_serverEvent;
        };
        // Code TFAR (décision 17) : radio pontée sur la clé de son groupe ; valeur d'origine rendue sinon.
        // Retire la voix directe sans la clé : décoché par défaut, coupé par le coupe-circuit, valeur d'origine rendue par "tfRestore".
        if ((missionNamespace getVariable ["comspec_atak_native_mesh_tfar_code", false]) isEqualTo true && {["voice"] call comspec_atak_native_fnc_radioGuard} && {["has", ["TFAR_fnc_setSwRadioCode"]] call comspec_atak_native_fnc_radioGuard}) then {
            (missionNamespace getVariable ["COMSPEC_ATAK_VoiceMe", []]) params [["_me", []]];
            private _want = "";
            if (_me isNotEqualTo [] && {(_me select 0) in ["sw", "lr"]}) then {
                private _kk = ["stamp", [["ofFreq", [_me select 2]] call comspec_atak_native_fnc_meshGroups]] call comspec_atak_native_fnc_meshKeys;
                if (_kk isNotEqualTo [] && {(_mine getOrDefault [_kk select 0, -1]) isEqualTo (_kk select 1)}) then { _want = format ["%1-%2", ([_kk select 0] call _rowOf) param [1, ""], _kk select 1]; };
            };
            (uiNamespace getVariable ["COMSPEC_ATAK_MeshTfCode", []]) params [["_radio", ""], ["_code", ""], ["_orig", ""]];
            if (_code isNotEqualTo _want) then {
                private _isLr = (_me param [0, ""]) isEqualTo "lr";
                private _target = if (_want isEqualTo "") then { _radio } else { if (_isLr) then { ["api", ["TFAR_fnc_activeLrRadio", [], []]] call comspec_atak_native_fnc_radioGuard } else { _me select 1 } };
                if (_want isNotEqualTo "" && {_code isEqualTo ""}) then {
                    _orig = ["api", [["TFAR_fnc_getSwRadioCode", "TFAR_fnc_getLrRadioCode"] select _isLr, _target, ""]] call comspec_atak_native_fnc_radioGuard;
                };
                private _set = if (_target isEqualType "") then { "TFAR_fnc_setSwRadioCode" } else { "TFAR_fnc_setLrRadioCode" };
                ["api", [_set, [_target, [_want, _orig] select (_want isEqualTo "")], false]] call comspec_atak_native_fnc_radioGuard;
                uiNamespace setVariable ["COMSPEC_ATAK_MeshTfCode", [[[], _target] select (_want isNotEqualTo ""), _want, _orig]];
            };
        };
    };
    case "tfRestore": {
        // Code de la radio rendu tel qu'il était (fn_mpuVoice "release").
        (uiNamespace getVariable ["COMSPEC_ATAK_MeshTfCode", []]) params [["_radio", ""], ["_code", ""], ["_orig", ""]];
        if (_code isEqualTo "") exitWith {};
        private _set = if (_radio isEqualType "") then { "TFAR_fnc_setSwRadioCode" } else { "TFAR_fnc_setLrRadioCode" };
        ["api", [_set, [_radio, _orig], false]] call comspec_atak_native_fnc_radioGuard;
        uiNamespace setVariable ["COMSPEC_ATAK_MeshTfCode", []];
    };
    case "request": {
        // Client : demande de chargement (chargeur de clés, poste de commandement). Le serveur vérifie.
        if (isNull _caller) exitWith {
            uiNamespace setVariable ["COMSPEC_ATAK_MeshZeroized", false];
            ["comspec_atak_native_meshKeysSrv", [player, "request", [true]]] call CBA_fnc_serverEvent;
        };
        if (!isServer || {!_on}) exitWith {};
        private _manual = _arg param [0, false];
        private _sideStr = str side group _caller;
        // Une clé de réseau par camp, créée à la première demande.
        if (([_sideStr] call _netRow) isEqualTo []) then { ["define", [format ["K-%1", _sideStr], _sideStr, true]] call comspec_atak_native_fnc_meshKeys; };
        private _hasLoader = "COMSPEC_ATAK_KeyLoader" in (items _caller);
        private _toc = (_caller getVariable ["COMSPEC_ATAK_TocOn", ""]) isNotEqualTo "";
        private _auto = (missionNamespace getVariable ["comspec_atak_native_mesh_keys_auto", true]) isEqualTo true;
        private _row = [_sideStr] call _netRow;
        private _nkOk = {
            params ["_who", "_rowN"];
            (["node", _who] call comspec_atak_native_fnc_meshRead) param [4, -1]
        };
        // Retour d'un nœud périmé : un allié à jour, à portée de MPU5.
        private _r = missionNamespace getVariable ["comspec_atak_native_mpu5_range", 6000];
        private _nearOk = (allPlayers findIf {
            _x isNotEqualTo _caller && {alive _x} && {str side group _x isEqualTo _sideStr} && {(_x distance _caller) < _r} && {([_x, _row] call _nkOk) isEqualTo ((_row select 0) * 1000 + (_row select 2))}
        }) >= 0;
        private _graceOk = (([time, serverTime] select isMultiplayer) - (_row param [6, -1000])) < 30;
        private _mine = ["node", _caller] call comspec_atak_native_fnc_meshRead;
        if (!(_hasLoader || {_toc} || {_auto && {(_mine param [4, -1]) < 0}} || {_nearOk} || {_graceOk})) exitWith {};
        if (_manual && {!(_hasLoader || {_toc} || {_auto})}) exitWith { ["comspec_atak_native_meshKeyAlert", ["Il faut un chargeur de clés ou le poste de commandement"], _caller] call CBA_fnc_targetEvent; };
        {
            _x params ["_id", "", "_ep", "", "_s"];
            if (_s isEqualTo _sideStr) then { ["comspec_atak_native_meshKeyGive", [_id, _ep], _caller] call CBA_fnc_targetEvent; };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_MeshEpochs", []]);
    };
    case "zeroize": {
        missionNamespace setVariable ["COMSPEC_ATAK_MeshKeysMine", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_MeshZeroized", true];
        uiNamespace setVariable ["COMSPEC_ATAK_MpuMeshAt", -100];
        ["WARNING", "Clés effacées : hors du maillage jusqu'au prochain chargement"] call _say;
        [{ ["WAVERELAY"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    };
    case "taken": {
        // Client : j'ai pris un appareil. Un corps ou une radio d'un autre camp : compromission (décision 1, désactivable).
        _arg params [["_from", objNull], ["_item", ""]];
        if (!_on || {!((missionNamespace getVariable ["comspec_atak_native_mesh_compromise_auto", true]) isEqualTo true)}) exitWith {};
        private _l = toLower _item;
        if ((_l find "mpu5") < 0 && {_l isNotEqualTo "comspec_atak_keyloader"}) exitWith {};
        private _owner = _from getVariable ["COMSPEC_ATAK_MeshOwnerSide", ""];
        if (_owner isEqualTo "" || {_owner isEqualTo str side group player}) exitWith {};
        ["comspec_atak_native_meshKeysSrv", [player, "compromiseSide", [_owner]]] call CBA_fnc_serverEvent;
    };
    // ---- Serveur
    case "init": {
        if (!isServer) exitWith {};
        ["comspec_atak_native_meshKeysSrv", {
            params [["_who", objNull], ["_a", ""], ["_xa", []]];
            if (isNull _who || {!(_a in ["request", "rekey", "compromise", "compromiseSide"])}) exitWith {};
            [_a, _xa, _who] call comspec_atak_native_fnc_meshKeys;
        }] call CBA_fnc_addEventHandler;
    };
    case "define": {
        if (!isServer) exitWith { -1 };
        _arg params [["_name", ""], ["_side", ""], ["_isNet", false]];
        private _rows = missionNamespace getVariable ["COMSPEC_ATAK_MeshEpochs", []];
        private _have = [_name] call _rowByName;
        if (_have isNotEqualTo []) exitWith { _have select 0 };
        if (_name isEqualTo "" || {(count _rows) >= 16}) exitWith { diag_log format ["[COMSPEC ATAK NATIVE][WARN][MESH] Clé refusée (16 au plus, nom requis) : %1", _name]; -1 };
        private _id = (count _rows) + 1;
        _rows pushBack [_id, _name select [0, 24], 1, false, _side, _isNet, [time, serverTime] select isMultiplayer];
        missionNamespace setVariable ["COMSPEC_ATAK_MeshEpochs", _rows, true];
        ["log", ["key", format ["Clé définie : %1", _name]]] call comspec_atak_native_fnc_meshGraph;
        _id
    };
    case "rekey": {
        if (isNull _caller) exitWith { ["comspec_atak_native_meshKeysSrv", [player, "rekey", _arg]] call CBA_fnc_serverEvent; };
        if (!isServer || {!_on}) exitWith {};
        // Zeus, admin ou poste de commandement.
        if !([_caller] call comspec_atak_native_fnc_adminAllowed || {(_caller getVariable ["COMSPEC_ATAK_TocOn", ""]) isNotEqualTo ""} || {_caller getVariable ["COMSPEC_ATAK_MeshServerCall", false]}) exitWith {};
        private _row = [_arg param [0, -1]] call _rowOf;
        if (_row isEqualTo []) exitWith {};
        _row set [2, (_row select 2) + 1];
        _row set [3, false];
        _row set [6, [time, serverTime] select isMultiplayer];
        missionNamespace setVariable ["COMSPEC_ATAK_MeshEpochs", missionNamespace getVariable "COMSPEC_ATAK_MeshEpochs", true];
        ["log", ["key", format ["Changement de clé : %1, époque %2", _row select 1, _row select 2]]] call comspec_atak_native_fnc_meshGraph;
        ["comspec_atak_native_meshKeyAlert", [format ["Clé %1 : nouvelle époque (%2)", _row select 1, _row select 2]], (_row select 4) call { private _s = _this; allPlayers select { str side group _x isEqualTo _s } }] call CBA_fnc_targetEvent;
    };
    case "compromise": {
        if (isNull _caller) exitWith { ["comspec_atak_native_meshKeysSrv", [player, "compromise", _arg]] call CBA_fnc_serverEvent; };
        if (!isServer || {!_on}) exitWith {};
        if !([_caller] call comspec_atak_native_fnc_adminAllowed || {(_caller getVariable ["COMSPEC_ATAK_TocOn", ""]) isNotEqualTo ""}) exitWith {};
        private _row = [_arg param [0, -1]] call _rowOf;
        if (_row isEqualTo []) exitWith {};
        _row set [3, true];
        missionNamespace setVariable ["COMSPEC_ATAK_MeshEpochs", missionNamespace getVariable "COMSPEC_ATAK_MeshEpochs", true];
        ["log", ["key", format ["Clé compromise : %1", _row select 1]]] call comspec_atak_native_fnc_meshGraph;
        ["comspec_atak_native_meshKeyAlert", [format ["Clé %1 compromise : changez-la", _row select 1]], (_row select 4) call { private _s = _this; allPlayers select { str side group _x isEqualTo _s } }] call CBA_fnc_targetEvent;
    };
    case "compromiseSide": {
        if (!isServer || {!_on}) exitWith {};
        private _owner = _arg param [0, ""];
        if !((missionNamespace getVariable ["comspec_atak_native_mesh_compromise_auto", true]) isEqualTo true) exitWith {};
        // Contrôle côté serveur : l'appelant est bien d'un autre camp.
        if (_owner isEqualTo "" || {_owner isEqualTo str side group _caller}) exitWith {};
        private _row = [_owner] call _netRow;
        if (_row isEqualTo []) exitWith {};
        _row set [3, true];
        missionNamespace setVariable ["COMSPEC_ATAK_MeshEpochs", missionNamespace getVariable "COMSPEC_ATAK_MeshEpochs", true];
        ["comspec_atak_native_meshKeyGive", [_row select 0, _row select 2], _caller] call CBA_fnc_targetEvent;
        ["log", ["key", format ["Clé compromise (appareil ramassé) : %1", _row select 1]]] call comspec_atak_native_fnc_meshGraph;
        ["comspec_atak_native_meshKeyAlert", [format ["Clé %1 compromise : un appareil a été ramassé. Changez-la", _row select 1]], allPlayers select { str side group _x isEqualTo _owner }] call CBA_fnc_targetEvent;
    };
    case "rows": {
        if (!_on) exitWith { [] };
        private _side = str side group player;
        private _rows = [["section", "Clés (mécanique de jeu)", "Aucune sécurité réelle"]];
        private _isAdm = [player] call comspec_atak_native_fnc_adminAllowed || {(player getVariable ["COMSPEC_ATAK_TocOn", ""]) isNotEqualTo ""};
        {
            _x params ["_id", "_name", "_ep", "_cmp", "_s"];
            if (_s isNotEqualTo _side) then { continue };
            _rows pushBack ["info", _name, format ["époque %1 · %2", _ep, ["state", [_id]] call comspec_atak_native_fnc_meshKeys]];
            if (_isAdm) then {
                _rows pushBack ["buttons", [
                    ["NOUVELLE ÉPOQUE", compile format ["['rekey', [%1]] call comspec_atak_native_fnc_meshKeys;", _id]],
                    ["COMPROMISE", compile format ["['compromise', [%1]] call comspec_atak_native_fnc_meshKeys;", _id]]
                ]];
            };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_MeshEpochs", []]);
        _rows pushBack ["buttons", [["CHARGER LES CLÉS", { ["request", [true]] call comspec_atak_native_fnc_meshKeys; }, true], ["ZÉROÏSER", { ["zeroize"] call comspec_atak_native_fnc_meshKeys; }, "red"]]];
        if (_isAdm) then {
            private _unk = ["unknown"] call comspec_atak_native_fnc_meshKeys;
            _rows pushBack ["info", "Appareils non autorisés à portée", [_unk joinString ", ", "aucun"] select (_unk isEqualTo [])];
        };
        _rows
    };
    case "unknown": {
        if (!_on) exitWith { [] };
        private _side = str side group player;
        private _r = missionNamespace getVariable ["comspec_atak_native_mpu5_range", 6000];
        ((allPlayers select { _x isNotEqualTo player && {alive _x} && {(_x distance player) < _r} }) select {
            private _n = ["node", _x] call comspec_atak_native_fnc_meshRead;
            _n isNotEqualTo [] && {!(["authorized", [_side, _n param [4, -1]]] call comspec_atak_native_fnc_meshKeys)}
        }) apply { private _h = player getDir _x; format ["appareil inconnu, %1°", round _h] }
    };
};
