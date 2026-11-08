/*
    Graphe unique et borné du réseau maillé (lot 2, docs/technical/reseau-mesh-mpu5.md, sections 3 et 6) : l'écran, les données
    et la voix lisent le même graphe. Rien ne tourne tant que la liaison réaliste (comspec_atak_native_mpu_real) est décochée.

    Où il est calculé (décision 4, réglage comspec_atak_native_mesh_server, coché par défaut) :
      - serveur : graphe de référence, une seule vérité. Le constructeur part d'un script planifié au plus une fois toutes les
        2 s ; aucun gestionnaire par image, seule une passe en cours rend la main d'image en image entre ses tranches.
        Diffusion : la seule variable publique COMSPEC_ATAK_MeshRef, sur changement, au plus toutes les 2 s.
      - client : réglage décoché, ou serveur sans le mod (COMSPEC_ATAK_MeshSrvOn absente) : chaque client construit le graphe
        de son camp, même code, rien n'est diffusé (COMSPEC_ATAK_MeshLocal).
    Coût jamais mesuré à ce jour : le tableau de performance et l'export (fn_devMesh) servent à trancher.

    Constructeur ("pass") : au plus 64 nœuds par camp (passerelles d'abord), élagage par distance, canal et paire de passerelles,
    bilans en cache 30 s, tranches de 150 bilans, une seule passe à la fois (abandonnée après 20 s). Intervalle : 4 s, 8 s au-delà de
    40 nœuds, 2 s quand un nœud change. Hystérésis : un lien ne tombe qu'au deuxième constat et revient tout de suite ; un nœud
    disparu retire ses liens sans attendre.
    Format diffusé : [numéro, [[camp, nœuds, liens, total des nœuds trouvés]...]] ; nœuds à plat par 5 : antenne, porteur, type x 100 +
    canal, + 1000 en écoute seule (type : 0 joueur, 1 radio posée, 2 passerelle ; canal 0 pour une passerelle), portée (10 m), débit max (100 kbit/s) ;
    liens à plat par 3 : A + 64 x B, marge (2 dB, -1 si gardé par l'hystérésis), débit (100 kbit/s).

    Lecture ("view", client) : aucun bilan radio. Plus large chemin sous plafond de sauts (comspec_atak_native_mesh_hops, 6 par défaut,
    décision 3), résultat dans COMSPEC_ATAK_MpuMesh pour fn_mpuLink, fn_mpuVoice et fn_waveRelayAction "scan".

      "serverBuilt" -> vrai si le graphe vient du serveur          "maxHops" -> plafond de sauts réglé
      "serverTick"  (serveur, chaque seconde)                      "every"   -> s avant la prochaine lecture (0 : le graphe a changé)
      "pass" [camps] (script planifié)                             "view" [réseau, antenne, débit max] (script planifié) -> HashMap
      "graph" [camp] -> [nœuds, voisins, liens, total] ou []
      "peer" [unité] -> [sauts, marge, niveau] si mon graphe récent la joint (messages A vers B, lot 10), sinon []
      Sans effet, testés par fn_devMeshTest :
      "solve" [voisins, sources, autorisés, plafond, débit max, courbe, ne relaie pas] -> [débits, sauts, vers, marges, sources]
      "curve" -> part du débit gardée selon les sauts (décision 12)
      "gravity" [marge, sauts, plafond] -> niveau 0 à 3            "hyst" [lien bon, marge, débit, état précédent] -> [garder, état, marge, débit]
*/
params [["_act", ""], ["_arg", []]];
switch (_act) do {
    case "serverBuilt": {
        ((missionNamespace getVariable ["comspec_atak_native_mesh_server", true]) isEqualTo true) && {!isNil "COMSPEC_ATAK_MeshSrvOn"}
    };
    case "log": {
        // Journal de réseau (serveur, 200 lignes) : [heure, genre, texte].
        if (!isServer) exitWith {};
        _arg params [["_kind", ""], ["_text", ""]];
        private _l = missionNamespace getVariable ["COMSPEC_ATAK_MeshLog", []];
        _l pushBack [round ([time, serverTime] select isMultiplayer), _kind, _text];
        if ((count _l) > 200) then { _l deleteAt 0; };
        missionNamespace setVariable ["COMSPEC_ATAK_MeshLog", _l];
    };
    case "peer": {
        _arg params [["_u", objNull]];
        if (isNull _u || {!((missionNamespace getVariable ["comspec_atak_native_mesh_p2p", true]) isEqualTo true)} || {!(["active"] call comspec_atak_native_fnc_mpuLink)}) exitWith { [] };
        private _m = uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap];
        if (diag_tickTime - (_m getOrDefault ["at", -100]) > 20) exitWith { [] };
        (_m getOrDefault ["voice", createHashMap]) getOrDefault [netId _u, []]
    };
    case "maxHops": {
        private _h = missionNamespace getVariable ["comspec_atak_native_mesh_hops", 6];
        if (_h isEqualType 0 && {finite _h}) then { ((round _h) max 1) min 16 } else { 6 }
    };
    case "gravity": {
        _arg params [["_mg", 40], ["_hops", -1], ["_maxH", 6]];
        switch (true) do {
            case (_hops < 0 || {_hops > _maxH}): { 3 };
            case (_mg < 4): { 2 };
            case (_mg < 9 || {_hops >= _maxH - 1}): { 1 };
            default { 0 };
        }
    };
    case "hyst": {
        _arg params [["_ok", false], ["_m", 0], ["_kb", 0], ["_old", []]];
        if (_ok) exitWith { [true, [0, _m, _kb], _m, _kb] };
        if (_old isEqualTo [] || {(_old select 0) >= 1}) exitWith { [false, [], 0, 0] };
        [true, [1, _old select 1, _old select 2], -1, _old select 2]
    };
    case "curve": {
        // Part du débit gardée selon les sauts (index = sauts). Décision 12 : « moitié puis tiers » en Milsim, x 0,8 par saut relayé sinon.
        private _c = missionNamespace getVariable ["comspec_atak_native_mesh_hop_curve", 0];
        private _real = if (_c isEqualTo 0) then { (["profile"] call comspec_atak_native_fnc_netCond) isEqualTo 3 } else { _c isEqualTo 1 };
        if (_real) exitWith { [1, 1, 0.5, 0.33, 0.33, 0.33, 0.33, 0.33, 0.33, 0.33, 0.33, 0.33, 0.33, 0.33, 0.33, 0.33, 0.33] };
        [1, 1, 0.8, 0.64, 0.512, 0.41, 0.328, 0.262, 0.21, 0.168, 0.134, 0.107, 0.086, 0.069, 0.055, 0.044, 0.035]
    };
    case "solve": {
        // Plus large chemin à sauts bornés, couche par couche : débit du chemin = plus petit lien x part gardée selon les sauts. On garde par
        // couche le meilleur plus petit lien ; un état ni plus court ni plus large qu'un état déjà vu ne peut plus rien améliorer.
        _arg params ["_adj", "_sources", "_allowed", "_maxH", "_maxK", ["_curve", [1, 1, 0.8, 0.64, 0.512, 0.41, 0.328, 0.262, 0.21, 0.168, 0.134, 0.107, 0.086, 0.069, 0.055, 0.044, 0.035]], ["_noRelay", []]];
        private _n = count _adj;
        private _bw = [];
        _bw resize _n;
        _bw = _bw apply { -1 };
        private _hop = +_bw;
        private _via = +_bw;
        private _src = +_bw;
        private _seen = +_bw;
        private _mg = _bw apply { 40 };
        private _front = [];
        {
            _bw set [_x, _maxK];
            _seen set [_x, _maxK];
            _hop set [_x, 0];
            _src set [_x, _x];
            _front pushBack [_x, _maxK, 40, _x];
        } forEach _sources;
        for "_h" from 1 to _maxH do {
            if (_front isEqualTo []) then { break };
            private _layer = _bw apply { -1 };
            private _entry = _bw apply { [] };
            {
                _x params ["_u", "_ub", "_um", "_us"];
                {
                    _x params ["_v", "_m", "_kb"];
                    if !(_allowed select _v) then { continue };
                    private _c = _ub min _kb;
                    if (_c > (_layer select _v)) then {
                        _layer set [_v, _c];
                        _entry set [_v, [_v, _c, _um min _m, _us, _u]];
                    };
                } forEach (_adj select _u);
            } forEach _front;
            _front = [];
            private _k = _curve param [_h, 0.03];
            {
                if (_x isEqualTo []) then { continue };
                _x params ["_v", "_c", "_m", "_s", "_u"];
                if (_c <= (_seen select _v)) then { continue };
                _seen set [_v, _c];
                // Écoute seule (lot 11) : joignable, mais ne relaie pas.
                if !(_noRelay param [_v, false]) then { _front pushBack [_v, _c, _m, _s]; };
                if ((_c * _k) > (_bw select _v)) then {
                    _bw set [_v, _c * _k];
                    _hop set [_v, _h];
                    _via set [_v, _u];
                    _mg set [_v, _m];
                    _src set [_v, _s];
                };
            } forEach _entry;
        };
        [_bw, _hop, _via, _mg, _src]
    };
    case "graph": {
        _arg params [["_side", ""]];
        private _var = ["COMSPEC_ATAK_MeshLocal", "COMSPEC_ATAK_MeshRef"] select (["serverBuilt"] call comspec_atak_native_fnc_meshGraph);
        private _whole = missionNamespace getVariable [_var, []];
        if !(_whole isEqualType []) then { _whole = []; };
        _whole params [["_seq", -1, [0]], ["_ref", [], [[]]]];
        private _dec = uiNamespace getVariable ["COMSPEC_ATAK_MeshDec", []];
        if ((_dec param [0, -2]) isEqualTo _seq && {(_dec param [1, ""]) isEqualTo _side} && {(_dec param [2, ""]) isEqualTo _var}) exitWith { _dec select 3 };
        private _i = _ref findIf { (_x isEqualType []) && {(_x param [0, ""]) isEqualTo _side} };
        private _out = [];
        if (_i >= 0) then {
            (_ref select _i) params ["", ["_nf", [], [[]]], ["_lf", [], [[]]], ["_total", 0, [0]]];
            // Tailles : 64 nœuds et 2 016 liens au plus, comme le constructeur. Au-delà, ou type faux : graphe ignoré.
            if ((count _nf) > 320 || {(count _lf) > 6048} || {(_lf findIf { !(_x isEqualType 0) }) >= 0}) then {
                _nf = [];
                _lf = [];
            };
            private _nodes = [];
            for "_j" from 0 to ((count _nf) - 5) step 5 do {
                if (!((_nf select _j) isEqualType objNull) || {!((_nf select (_j + 1)) isEqualType objNull)} || {!((_nf select [_j + 2, 3]) isEqualTypeAll 0)}) then { _nf = []; _lf = []; _nodes = []; break };
                private _tc = _nf select (_j + 2);
                private _type = (floor (_tc / 100)) mod 10;
                if !(_type in [0, 1, 2]) then { _nf = []; _lf = []; _nodes = []; break };
                // [antenne, porteur, type, canal, portée m, débit max kbit/s, ne relaie pas]
                _nodes pushBack [_nf select _j, _nf select (_j + 1), _type, _tc mod 100, (_nf select (_j + 3)) * 10, (_nf select (_j + 4)) * 100, _tc >= 1000];
            };
            private _adj = _nodes apply { [] };
            private _links = [];
            for "_j" from 0 to ((count _lf) - 3) step 3 do {
                private _p = _lf select _j;
                private _a = _p mod 64;
                private _b = floor (_p / 64);
                private _m = _lf select (_j + 1);
                private _kb = (_lf select (_j + 2)) * 100;
                if (_a < 0 || {_b < 0} || {_a >= (count _nodes)} || {_b >= (count _nodes)} || {!finite _m} || {!finite _kb}) then { continue };
                _links pushBack [_a, _b, _m, _kb];
                (_adj select _a) pushBack [_b, _m, _kb];
                (_adj select _b) pushBack [_a, _m, _kb];
            };
            _out = [_nodes, _adj, _links, _total];
        };
        uiNamespace setVariable ["COMSPEC_ATAK_MeshDec", [_seq, _side, _var, _out]];
        _out
    };
    case "serverTick": {
        if (!isServer) exitWith {};
        private _pub = missionNamespace getVariable ["COMSPEC_ATAK_MeshRef", []];
        if !((["active"] call comspec_atak_native_fnc_mpuLink) && {(missionNamespace getVariable ["comspec_atak_native_mesh_server", true]) isEqualTo true}) exitWith {
            // Réglage décoché en cours de partie : les clients ne gardent pas un graphe périmé.
            if ((_pub param [1, []]) isNotEqualTo []) then { missionNamespace setVariable ["COMSPEC_ATAK_MeshRef", [(_pub select 0) + 1, []], true]; };
        };
        private _now = diag_tickTime;
        if (_now - (missionNamespace getVariable ["COMSPEC_ATAK_MeshBusyAt", -100]) < 20) exitWith {};
        // Signature des nœuds : le graphe est à refaire dès qu'un nœud apparaît, tombe, change de canal ou de puissance.
        private _sim = [[], missionNamespace getVariable ["COMSPEC_ATAK_MeshSimNodes", []]] select (["on"] call comspec_atak_native_fnc_devMesh);
        private _sig = ((allPlayers + _sim) select { alive _x }) apply { [_x, _x getVariable ["COMSPEC_ATAK_MeshSim", str side group _x], ["node", _x] call comspec_atak_native_fnc_meshRead] };
        _sig = _sig select { (_x select 2) isNotEqualTo [] };
        private _dirty = _sig isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_MeshSig", []]);
        if (_sig isEqualTo [] && {(_pub param [1, []]) isEqualTo []}) exitWith { missionNamespace setVariable ["COMSPEC_ATAK_MeshSig", []]; };
        private _since = _now - (missionNamespace getVariable ["COMSPEC_ATAK_MeshLastAt", -100]);
        if !((_dirty && {_since >= 2}) || {_since >= ([4, 8] select ((count _sig) > 40))}) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_MeshSig", _sig];
        missionNamespace setVariable ["COMSPEC_ATAK_MeshBusyAt", _now];
        missionNamespace setVariable ["COMSPEC_ATAK_MeshLastAt", _now];
        private _sides = (_sig apply { _x select 1 }) arrayIntersect (_sig apply { _x select 1 });
        [_sides] spawn {
            params ["_list"];
            ["pass", [_list]] call comspec_atak_native_fnc_meshGraph;
        };
    };
    case "every": {
        private _n = (uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap]) getOrDefault ["count", 0];
        private _normal = [4, 8] select (_n > 40);
        if (["serverBuilt"] call comspec_atak_native_fnc_meshGraph) exitWith {
            [_normal, 0] select (((missionNamespace getVariable ["COMSPEC_ATAK_MeshRef", []]) param [0, -1]) isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_MeshViewSeq", -2]))
        };
        private _side = str side group player;
        private _sig = ((allPlayers + (["units", [_side]] call comspec_atak_native_fnc_devMeshSim)) select { alive _x && {(_x getVariable ["COMSPEC_ATAK_MeshSim", str side group _x]) isEqualTo _side} }) apply { [_x, ["node", _x] call comspec_atak_native_fnc_meshRead] };
        _sig = _sig select { (_x select 1) isNotEqualTo [] };
        if (_sig isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_MeshSigLocal", []])) exitWith {
            uiNamespace setVariable ["COMSPEC_ATAK_MeshSigLocal", _sig];
            0
        };
        _normal
    };
    case "pass": {
        _arg params [["_sides", []]];
        if (!canSuspend) exitWith { [_sides] spawn { params ["_list"]; ["pass", [_list]] call comspec_atak_native_fnc_meshGraph; }; };
        private _t0 = diag_tickTime;
        private _id = _t0 + random 1;
        missionNamespace setVariable ["COMSPEC_ATAK_MeshPass", _id];
        missionNamespace setVariable ["COMSPEC_ATAK_MeshBusyAt", _t0];
        missionNamespace setVariable ["COMSPEC_ATAK_MeshLastAt", _t0];
        private _maxNodes = 64;
        private _wxForce = missionNamespace getVariable ["COMSPEC_ATAK_MeshWxForce", 0];
        if (!(_wxForce isEqualType 0) || {!finite _wxForce}) then { _wxForce = 0; };
        private _sliceTests = 150;
        private _maxK = (missionNamespace getVariable ["comspec_atak_native_mpu5_mbps", 20]) * 1000;
        private _server = ["serverBuilt"] call comspec_atak_native_fnc_meshGraph;
        private _devOn = ["on"] call comspec_atak_native_fnc_devMesh;
        private _tNow = [time, serverTime] select isMultiplayer;
        // Compteurs : bilans calculés, en cache, paires élaguées, tranches, pire tranche s, temps de travail s, début de tranche, bilans de la tranche.
        private _ct = [0, 0, 0, 1, 0, 0, _t0, 0];
        private _linkN = 0;
        private _nodeN = 0;
        private _ref = [];
        private _aborted = false;
        private _yield = {
            params ["_c", "_passId"];
            private _dt = diag_tickTime - (_c select 6);
            ["maillage constructeur (tranche)", _c select 6] call comspec_atak_native_fnc_perfAcc;
            _c set [4, (_c select 4) max _dt];
            _c set [5, (_c select 5) + _dt];
            _c set [3, (_c select 3) + 1];
            uiSleep 0;
            _c set [6, diag_tickTime];
            _c set [7, 0];
            (missionNamespace getVariable ["COMSPEC_ATAK_MeshPass", -1]) isEqualTo _passId
        };
        // Hauteur d'antenne : posture en Milsim seulement (décision 15), sinon hauteur debout au moins. Radio posée : mât éventuel (lot 9).
        private _milsim = (["profile"] call comspec_atak_native_fnc_netCond) isEqualTo 3;
        private _antOf = {
            params ["_o", "_posture"];
            if (_o isKindOf "CAManBase") exitWith {
                private _e = (eyePos _o) vectorAdd [0, 0, 0.4];
                if (_posture) exitWith { _e };
                [_e select 0, _e select 1, (_e select 2) max (((getPosASL _o) select 2) + 2)]
            };
            private _mast = _o getVariable ["COMSPEC_ATAK_MeshMast", 0];
            if !(_mast isEqualType 0) then { _mast = 0; };
            (getPosASL _o) vectorAdd [0, 0, 0.8 + ((_mast max 0) min 15)]
        };
        // Pertes propres à la paire, hors cache : Milsim seulement. Couché derrière un obstacle proche (réglage mesh_posture) ; porteur entre
        // son antenne et le correspondant (réglage mesh_body_mask, décoché). Valeurs de jeu, non tirées d'une fiche.
        private _bodyOn = _milsim && {(missionNamespace getVariable ["comspec_atak_native_mesh_body_mask", false]) isEqualTo true};
        private _proneOn = _milsim && {(missionNamespace getVariable ["comspec_atak_native_mesh_posture", true]) isEqualTo true};
        private _pairLoss = {
            params ["_o", "_from", "_to", "_prone", "_body"];
            if !(_o isKindOf "CAManBase") exitWith { 0 };
            private _l = 0;
            if (_body && {abs ((_o getRelDir _to) - 180) < 60}) then { _l = _l + 3; };
            if (_prone && {(stance _o) isEqualTo "PRONE"}) then {
                private _d = (_from distance _to) max 1;
                private _near = _from vectorAdd ((_to vectorDiff _from) vectorMultiply ((8 / _d) min 1));
                if ((lineIntersectsSurfaces [_from, _near, _o, objNull, true, 1, "GEOM", "NONE"]) isNotEqualTo []) then { _l = _l + 12; };
            };
            _l
        };
        private _jamAt = {
            params ["_p", "_jams", "_chan"];
            private _f = 1;
            {
                _x params [["_jp", [0, 0, 0]], ["_jr", 500]];
                // Brouillage GPS seul, ou d'un autre canal (lot 11) : sans effet sur ce nœud.
                private _jc = _x param [7, 0];
                if ((_x param [8, ""]) isEqualTo "gps" || {_jc > 0 && {_jc isNotEqualTo _chan}}) then { continue };
                if (_jp isEqualType objNull) then { _jp = getPosATL _jp; };
                private _d = _p distance2D _jp;
                private _js = (([_x param [6, 1], 1] select !((_x param [6, 1]) isEqualType 0)) max 0) min 1;
                if (_d < _jr) then { _f = _f * (1 - _js * (1 - (_d / _jr) ^ 2)); };
            } forEach _jams;
            _f
        };
        // Marge d'une paire : bilan radio en cache 30 s par paire de positions arrondies (15 m).
        private _marginOf = {
            params ["_pa", "_pb", "_r", "_cache", "_c"];
            private _ka = [round ((_pa select 0) / 15), round ((_pa select 1) / 15), round (_pa select 2)];
            private _kb = [round ((_pb select 0) / 15), round ((_pb select 1) / 15), round (_pb select 2)];
            private _k = str ([[_kb, _ka, round _r], [_ka, _kb, round _r]] select ((_ka select 0) < (_kb select 0) || {(_ka select 0) isEqualTo (_kb select 0) && {(_ka select 1) <= (_kb select 1)}}));
            private _hit = _cache getOrDefault [_k, []];
            if ((count _hit) isEqualTo 2 && {diag_tickTime - (_hit select 0) < 30}) exitWith {
                _c set [1, (_c select 1) + 1];
                _hit select 1
            };
            _c set [0, (_c select 0) + 1];
            _c set [7, (_c select 7) + 1];
            private _m = ([_pa, _pb, _r, "coarse"] call comspec_atak_native_fnc_radioLink) select 0;
            _cache set [_k, [diag_tickTime, _m]];
            _m
        };
        private _cache = missionNamespace getVariable ["COMSPEC_ATAK_MeshLos", createHashMap];
        // 64 nœuds font 2 016 paires : la remise à zéro ne doit jamais tomber au milieu d'une passe normale.
        if ((count _cache) > 4096) then { _cache = createHashMap; };
        missionNamespace setVariable ["COMSPEC_ATAK_MeshLos", _cache];
        private _hystAll = missionNamespace getVariable ["COMSPEC_ATAK_MeshHyst", createHashMap];
        private _log = missionNamespace getVariable ["COMSPEC_ATAK_MeshLinkLog", []];
        {
            private _side = _x;
            // Clé de réseau compromise (lot 7) : son détenteur d'un autre camp reste dans ce graphe jusqu'à la nouvelle époque.
            private _compNk = ["compromisedNk", [_side]] call comspec_atak_native_fnc_meshKeys;
            // Nœuds : [antenne, porteur, type, canal, portée, débit max, position]. Passerelles d'abord.
            private _found = [];
            {
                _x params ["_o", "_ap", "", "_k", "_r", "_mb", "_ok"];
                if (_ok && {_k isNotEqualTo "mpu5"}) then { _found pushBack [_o, objNull, 2, 0, _r, (_mb * 1000) min _maxK, _ap]; };
            } forEach (["list", [_side]] call comspec_atak_native_fnc_relayNet);
            {
                (["node", _x] call comspec_atak_native_fnc_meshRead) params [["_key", ""], ["_r", 0], ["_o", objNull], ["_wid", 1], ["_nk", -2], ["_activity", 5]];
                if (_key isEqualTo "") then { continue };
                // Clé de réseau absente ou périmée : hors du graphe (lot 6, sans effet réglage décoché).
                if !(["authorized", [_side, _nk]] call comspec_atak_native_fnc_meshKeys) then { continue };
                private _wf = [0.5, 1, 1.5] select _wid;
                _found pushBack [_o, _x, [1, 0] select (_o isKindOf "CAManBase"), parseNumber (_key select [1]), _r, _maxK * _wf, [_o, _milsim] call _antOf, _activity <= 0];
            } forEach ((allPlayers select { alive _x && {str side group _x isEqualTo _side || {_compNk >= 0 && {((["node", _x] call comspec_atak_native_fnc_meshRead) param [4, -2]) isEqualTo _compNk}}} }) + (["units", [_side]] call comspec_atak_native_fnc_devMeshSim));
            private _total = count _found;
            private _nodes = _found select [0, _maxNodes];
            private _n = count _nodes;
            _nodeN = _nodeN + _n;
            private _jams = (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) select { ((_x param [4, 1e9]) >= _tNow) && {!((_x param [5, false]) && {(_x param [3, ""]) isEqualTo _side})} };
            private _jf = _nodes apply { [_x select 6, _jams, _x select 3] call _jamAt };
            // Hystérésis : état de la passe précédente, retrouvé par objet (les index changent d'une passe à l'autre).
            (_hystAll getOrDefault [_side, [[], createHashMap]]) params ["_oldObjs", "_oldState"];
            private _oldIdx = _nodes apply { _oldObjs find (_x select 0) };
            private _state = createHashMap;
            private _lf = [];
            for "_i" from 0 to (_n - 2) do {
                private _a = _nodes select _i;
                for "_j" from (_i + 1) to (_n - 1) do {
                    private _b = _nodes select _j;
                    if (((_a select 2) isEqualTo 2) && {(_b select 2) isEqualTo 2}) then { continue };
                    if (((_a select 3) isNotEqualTo (_b select 3)) && {(_a select 2) isNotEqualTo 2} && {(_b select 2) isNotEqualTo 2}) then { continue };
                    private _r = (_a select 4) min (_b select 4);
                    private _m = -99;
                    private _kb = 0;
                    private _ok = false;
                    if (((_a select 6) distance (_b select 6)) > _r) then {
                        _ct set [2, (_ct select 2) + 1];
                    } else {
                        _m = [_a select 6, _b select 6, _r, _cache, _ct] call _marginOf;
                        // Météo radio forcée par Zeus (fn_meshTools).
                        _m = _m - _wxForce;
                        if (_bodyOn || {_proneOn}) then {
                            _m = _m - ([_a select 0, _a select 6, _b select 6, _proneOn, _bodyOn] call _pairLoss) - ([_b select 0, _b select 6, _a select 6, _proneOn, _bodyOn] call _pairLoss);
                        };
                        private _jam = (_jf select _i) min (_jf select _j);
                        if (_m >= 0 && {_jam >= 0.1}) then {
                            private _f = [0.06, 0.12, 0.25, 0.4, 0.55, 0.7, 0.85, 1] select (((({ _m >= _x } count [0, 2, 4, 6, 9, 12, 16, 20]) - 1) max 0) min 7);
                            _kb = (((_a select 5) min (_b select 5)) * _f * _jam);
                            _ok = true;
                        };
                    };
                    private _ia = _oldIdx select _i;
                    private _ib = _oldIdx select _j;
                    private _oldKey = [(_ia min _ib) + 64 * (_ia max _ib), -1] select (_ia < 0 || {_ib < 0});
                    private _old = _oldState getOrDefault [_oldKey, []];
                    if (_ok || {_old isNotEqualTo []}) then {
                        (["hyst", [_ok, _m, _kb, _old]] call comspec_atak_native_fnc_meshGraph) params ["_keep", "_st", "_lm", "_lk"];
                        if (_devOn && {(_old param [0, -1]) isNotEqualTo (_st param [0, -1])}) then {
                            _log pushBack [round _tNow, _a select 0, _b select 0, switch (_st param [0, -1]) do { case 0: { "revenu" }; case 1: { "premier constat" }; default { "tombé" }; }, _m];
                            if ((count _log) > 40) then { _log deleteAt 0; };
                        };
                        if (_keep) then {
                            _state set [_i + 64 * _j, _st];
                            _lf append [_i + 64 * _j, [2 * round (_lm / 2), -1] select (_lm < 0), round (_lk / 100)];
                        };
                    };
                    // Paires en cache comptées aussi : une passe sans aucun bilan à calculer reste découpée.
                    _ct set [7, (_ct select 7) + 0.25];
                    if ((_ct select 7) >= _sliceTests) then {
                        if !([_ct, _id] call _yield) then { _aborted = true; };
                    };
                    if (_aborted) then { break };
                };
                if (_aborted) then { break };
            };
            if (_aborted) then { break };
            _hystAll set [_side, [_nodes apply { _x select 0 }, _state]];
            // Temps d'antenne partagé (lot 9, réglage mesh_airtime) : les voisins d'un même canal se partagent l'émission. La voix n'en dépend pas au-dessus de ses seuils.
            if ((missionNamespace getVariable ["comspec_atak_native_mesh_airtime", true]) isEqualTo true) then {
                private _deg = _nodes apply { 0 };
                for "_q" from 0 to ((count _lf) - 3) step 3 do {
                    private _pk = _lf select _q;
                    _deg set [_pk mod 64, (_deg select (_pk mod 64)) + 1];
                    _deg set [floor (_pk / 64), (_deg select (floor (_pk / 64))) + 1];
                };
                for "_q" from 0 to ((count _lf) - 3) step 3 do {
                    private _pk = _lf select _q;
                    private _busy = (_deg select (_pk mod 64)) max (_deg select (floor (_pk / 64)));
                    _lf set [_q + 2, (round ((_lf select (_q + 2)) / (1 + 0.2 * ((_busy - 1) max 0)))) max 1];
                };
            };
            _linkN = _linkN + (count _lf) / 3;
            private _nf = [];
            { _nf append [_x select 0, _x select 1, ([0, 1000] select (_x param [7, false])) + 100 * (_x select 2) + (_x select 3), round ((_x select 4) / 10), round ((_x select 5) / 100)]; } forEach _nodes;
            _ref pushBack [_side, _nf, _lf, _total];
        } forEach _sides;
        if (_aborted) exitWith { false };
        missionNamespace setVariable ["COMSPEC_ATAK_MeshHyst", _hystAll];
        // Journal de réseau : changement du nombre de nœuds ou de liens (reformation, partition).
        (missionNamespace getVariable ["COMSPEC_ATAK_MeshLogLast", [-1, -1]]) params ["_ln", "_ll"];
        if (_ln isNotEqualTo _nodeN) then { ["log", ["graphe", format ["%1 nœuds, %2 liens (avant : %3, %4)", _nodeN, _linkN, _ln, _ll]]] call comspec_atak_native_fnc_meshGraph; };
        missionNamespace setVariable ["COMSPEC_ATAK_MeshLogLast", [_nodeN, _linkN]];
        if (_devOn) then { missionNamespace setVariable ["COMSPEC_ATAK_MeshLinkLog", _log]; };
        private _lastDt = diag_tickTime - (_ct select 6);
        ["maillage constructeur (tranche)", _ct select 6] call comspec_atak_native_fnc_perfAcc;
        _ct set [4, (_ct select 4) max _lastDt];
        _ct set [5, (_ct select 5) + _lastDt];
        // Diffusion sur changement seulement, au plus toutes les 2 s.
        private _pubs = missionNamespace getVariable ["COMSPEC_ATAK_MeshPubs", 0];
        if (isServer && {_server}) then {
            (missionNamespace getVariable ["COMSPEC_ATAK_MeshRef", []]) params [["_seq", 0], ["_prev", []]];
            if (_ref isNotEqualTo _prev) then {
                private _wait = 2 - (diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_MeshPubAt", -100]));
                if (_wait > 0) then { uiSleep _wait; };
                if ((missionNamespace getVariable ["COMSPEC_ATAK_MeshPass", -1]) isEqualTo _id) then {
                    missionNamespace setVariable ["COMSPEC_ATAK_MeshRef", [_seq + 1, _ref], true];
                    missionNamespace setVariable ["COMSPEC_ATAK_MeshPubAt", diag_tickTime];
                    _pubs = _pubs + 1;
                    missionNamespace setVariable ["COMSPEC_ATAK_MeshPubs", _pubs];
                };
            };
        } else {
            private _seq = (missionNamespace getVariable ["COMSPEC_ATAK_MeshLocal", []]) param [0, 0];
            missionNamespace setVariable ["COMSPEC_ATAK_MeshLocal", [_seq + 1, _ref]];
        };
        missionNamespace setVariable ["COMSPEC_ATAK_MeshStats", createHashMapFromArray [
            ["at", diag_tickTime], ["ms", (diag_tickTime - _t0) * 1000], ["workMs", (_ct select 5) * 1000], ["slices", _ct select 3], ["sliceMaxMs", (_ct select 4) * 1000],
            ["nodes", _nodeN], ["links", _linkN], ["tests", _ct select 0], ["hits", _ct select 1], ["pruned", _ct select 2], ["pubs", _pubs], ["sides", count _sides],
            ["where", ["client", "serveur"] select (isServer && {_server})], ["fps", round diag_fps], ["size", [0, count str _ref] select _devOn]
        ]];
        missionNamespace setVariable ["COMSPEC_ATAK_MeshBusyAt", -100];
        true
    };
    case "view": {
        _arg params ["_key", "_ant", "_maxK"];
        private _t0 = diag_tickTime;
        private _maxH = ["maxHops"] call comspec_atak_native_fnc_meshGraph;
        private _sideV = side group player;
        private _side = str _sideV;
        private _seqVar = ["COMSPEC_ATAK_MeshLocal", "COMSPEC_ATAK_MeshRef"] select (["serverBuilt"] call comspec_atak_native_fnc_meshGraph);
        uiNamespace setVariable ["COMSPEC_ATAK_MeshViewSeq", (missionNamespace getVariable [_seqVar, []]) param [0, -1]];
        private _dec = ["graph", [_side]] call comspec_atak_native_fnc_meshGraph;
        private _res = createHashMapFromArray [["key", _key], ["at", diag_tickTime], ["mode", ""], ["ok", false], ["reach", createHashMap], ["vkey", ""],
            ["kbps", 0], ["hops", -1], ["gw", ""], ["via", ""], ["lat", 0], ["loss", 100], ["why", "connexion au maillage en cours"], ["nodes", []], ["count", 0], ["level", 3], ["voice", createHashMap]];
        if (_dec isEqualTo []) exitWith { _res };
        _dec params ["_nodes", "_adj", "_links", "_total"];
        private _chan = parseNumber (_key select [1]);
        private _me = _nodes findIf { (_x select 0) isEqualTo _ant };
        if (_me < 0 && {_total > (count _nodes)}) then { _res set ["why", format ["maillage saturé : %1 nœuds pour %2 places", _total, count _nodes]]; };
        if (_me < 0 || {((_nodes select _me) select 3) isNotEqualTo _chan}) exitWith { _res };
        // Sous-graphe de mon réseau : les nœuds de mon canal et les passerelles, présentes sur tous les canaux.
        private _allowed = _nodes apply { !isNull (_x select 0) && {((_x select 2) isEqualTo 2) || {(_x select 3) isEqualTo _chan}} };
        private _gws = [];
        { if ((_x select 2) isEqualTo 2 && {!isNull (_x select 0)}) then { _gws pushBack _forEachIndex; }; } forEach _nodes;
        private _mode = "relay";
        private _lead = leader group player;
        if (_gws isEqualTo []) then {
            private _li = _nodes findIf { (_x select 1) isEqualTo _lead && {(_x select 3) isEqualTo _chan} };
            if (_lead isNotEqualTo player && {_li >= 0}) then { _gws = [_li]; _mode = "lead"; } else { _gws = [_me]; _mode = "self"; };
        };
        // Depuis les passerelles : ce que voient mes données et l'écran. Depuis moi : qui je joins (voix, liste de l'app Réseau).
        // En mode relais, tous les canaux depuis les passerelles : la voix par le cœur de réseau joint aussi les autres canaux, et un
        // chemin ne gagne rien à passer par une deuxième passerelle, déjà source. Pour mon canal, le résultat est le même.
        private _allowedG = if (_mode isEqualTo "relay") then { _nodes apply { !isNull (_x select 0) } } else { _allowed };
        private _curve = ["curve"] call comspec_atak_native_fnc_meshGraph;
        // Canal large : une radio peut dépasser le débit de base (lot 9), borné par la capacité de chaque nœud.
        (["solve", [_adj, _gws, _allowedG, _maxH, _maxK * 1.5, _curve, _nodes apply { _x select 6 }]] call comspec_atak_native_fnc_meshGraph) params ["_bwG", "_hopG", "_viaG", "_mgG", "_srcG"];
        (["solve", [_adj, [_me], _allowed, _maxH, _maxK * 1.5, _curve, _nodes apply { _x select 6 }]] call comspec_atak_native_fnc_meshGraph) params ["_bwM", "_hopM", "", "_mgM"];
        // Noms et position réelle des passerelles : liste locale des relais, déjà en cache.
        private _relays = ["list"] call comspec_atak_native_fnc_relayNet;
        private _nameOf = {
            params ["_node", "_isMe", "_list"];
            _node params ["_o", "_owner", "_type"];
            if (_type isEqualTo 2) exitWith {
                private _e = _list findIf { (_x select 0) isEqualTo _o };
                if (_e >= 0) then { (_list select _e) select 2 } else { "Relais ATAK" }
            };
            if (_isMe) exitWith { "Ma radio" };
            private _cs = [_owner, true] call comspec_atak_native_fnc_unitCallsign;
            if (_type isEqualTo 1) then { format ["MPU5 posée (%1)", _cs] } else { _cs }
        };
        private _posOf = {
            params ["_node", "_list"];
            _node params ["_o", "", "_type"];
            if (_type isEqualTo 2) exitWith {
                private _e = _list findIf { (_x select 0) isEqualTo _o };
                if (_e >= 0) then { (_list select _e) select 1 } else { (getPosASL _o) vectorAdd [0, 0, 4] }
            };
            if (_o isKindOf "CAManBase") then { (eyePos _o) vectorAdd [0, 0, 0.4] } else { (getPosASL _o) vectorAdd [0, 0, 0.8] }
        };
        private _names = [];
        private _poss = [];
        {
            _names pushBack (if (_allowed select _forEachIndex) then { [_x, _forEachIndex isEqualTo _me, _relays] call _nameOf } else { "" });
            _poss pushBack ([_x, _relays] call _posOf);
        } forEach _nodes;
        private _h = _hopG select _me;
        private _ok = _h >= 0;
        private _reach = createHashMap;
        {
            if (_forEachIndex isNotEqualTo _me && {!isNull (_x select 1)} && {(_hopM select _forEachIndex) >= 0}) then { _reach set [netId (_x select 1), _hopM select _forEachIndex]; };
        } forEach _nodes;
        // Voix : par porteur, le meilleur du chemin direct et du passage par le cœur de réseau, sous le plafond de sauts.
        // [sauts, marge du pire lien, niveau de gravité].
        private _voice = createHashMap;
        {
            private _owner = _x select 1;
            if (_forEachIndex isEqualTo _me || {isNull _owner}) then { continue };
            private _hd = _hopM select _forEachIndex;
            private _md = _mgM select _forEachIndex;
            private _hc = -1;
            private _mc = 40;
            if (_ok && {_mode isNotEqualTo "self"} && {(_hopG select _forEachIndex) >= 0}) then {
                _hc = _h + (_hopG select _forEachIndex);
                _mc = (_mgG select _me) min (_mgG select _forEachIndex);
            };
            // Priorité voix (lot 9) : la saturation ralentit d'abord les données ; la voix ne se dégrade qu'en dessous de ces seuils.
            private _pathKb = if (_hd >= 0) then { _bwM select _forEachIndex } else { (_bwG select _me) min (_bwG select _forEachIndex) };
            private _pick = switch (true) do {
                case (_hd < 0 && {_hc < 0 || {_hc > _maxH}}): { [] };
                case (_hd < 0): { [_hc, _mc] };
                case (_hc < 0 || {_hc > _maxH} || {_hd <= _hc}): { [_hd, _md] };
                default { [_hc, _mc] };
            };
            if (_pick isEqualTo []) then { continue };
            private _lvl = ["gravity", [_pick select 1, _pick select 0, _maxH]] call comspec_atak_native_fnc_meshGraph;
            _lvl = _lvl max (switch (true) do { case (_pathKb < 64): { 2 }; case (_pathKb < 200): { 1 }; default { 0 }; });
            _voice set [netId _owner, [_pick select 0, _pick select 1, _lvl]];
        } forEach _nodes;
        _res set ["voice", _voice];
        // Part relayée (lot 9, batterie) : nœuds dont le chemin vers la passerelle passe d'abord par moi.
        _res set ["relayed", { _x isEqualTo _me } count _viaG];
        _res set ["mode", _mode];
        _res set ["ok", _ok];
        _res set ["reach", _reach];
        _res set ["vkey", [switch (_mode) do {
            case "relay": { "R" + _side };
            case "lead": { "L" + netId _lead };
            default { ["", "L" + netId player] select (_lead isEqualTo player) };
        }, ""] select !_ok];
        private _meG = _srcG select _me;
        private _self = _meG isEqualTo _me;
        if (_ok) then {
            private _mgMin = (_mgG select _me) max 0;
            _res set ["kbps", round ((_bwG select _me) min ((_nodes select _me) select 5))];
            _res set ["hops", _h];
            _res set ["gw", if (_self) then { "maillage autonome" } else { _names select _meG }];
            _res set ["via", if (_self) then { "" } else { _names select (_viaG select _me) }];
            _res set ["lat", round ((missionNamespace getVariable ["comspec_atak_native_mpu_hop_ms", 15]) * (_h max 1) + 5 + 60 * (1 - ((_mgMin / 20) min 1)))];
            _res set ["loss", [round ((40 * exp (-_mgMin / 5)) min 60), 0] select (_h isEqualTo 0)];
            _res set ["why", ""];
        } else {
            _res set ["why", [format ["hors du maillage de l'équipe (%1 injoignable, au plus %2 sauts)", _names select (_gws select 0), _maxH],
                format ["aucune passerelle du maillage à portée, au plus %1 sauts (relais, pylônes, MPU5 du canal)", _maxH]] select (_mode isEqualTo "relay")];
        };
        _res set ["level", ["gravity", [_mgG select _me, _h, _maxH]] call comspec_atak_native_fnc_meshGraph];
        // Liste de l'app Réseau : [nom, type, sauts depuis moi (-1 hors maillage), distance m, débit kbit/s], les plus proches d'abord.
        private _myPos = _poss select _me;
        private _list = [];
        {
            if (_forEachIndex isEqualTo _me || {!(_allowed select _forEachIndex)}) then { continue };
            private _hm = _hopM select _forEachIndex;
            _list pushBack [[_hm, 99] select (_hm < 0), round (_myPos distance (_poss select _forEachIndex)), _names select _forEachIndex, ["node", "posed", "gw"] select (_x select 2), _hm, round ((_bwM select _forEachIndex) max 0)];
        } forEach _nodes;
        _list sort true;
        _res set ["nodes", (_list select [0, 12]) apply { [_x select 2, _x select 3, _x select 4, _x select 1, _x select 5] }];
        _res set ["count", {_x} count _allowed];
        // Écran (fn_waveRelayAction "scan", carte) : mêmes données, moi en premier, sauts jusqu'à la passerelle.
        private _order = [_me];
        { if (_x && {_forEachIndex isNotEqualTo _me}) then { _order pushBack _forEachIndex; }; } forEach _allowed;
        private _rank = _nodes apply { -1 };
        { _rank set [_x, _forEachIndex]; } forEach _order;
        private _scrLinks = [];
        {
            _x params ["_a", "_b", "_m"];
            if ((_rank select _a) >= 0 && {(_rank select _b) >= 0}) then { _scrLinks pushBack [_rank select _a, _rank select _b, ((round (100 * ((_m / 20) min 1))) max 1) min 100]; };
        } forEach _links;
        private _meGw = [_gws select 0, _meG] select _ok;
        private _gwNode = _nodes select _meGw;
        private _gwLabel = if ((_gwNode select 2) isEqualTo 2) then {
            private _e = _relays findIf { (_x select 0) isEqualTo (_gwNode select 0) };
            private _gk = if (_e >= 0) then { (_relays select _e) select 3 } else { "relay" };
            [format ["%1 %2", ["Passerelle", "PC mobile"] select (_gk isEqualTo "cp"), _names select _meGw], _names select _meGw] select (_gk isEqualTo "relay")
        } else {
            format ["Chef de groupe (%1)", _names select _meGw]
        };
        _res set ["screen", createHashMapFromArray [
            ["nodes", _order apply {
                private _nd = _nodes select _x;
                [_nd select 0, _poss select _x, [_names select _x, [_nd select 1, true] call comspec_atak_native_fnc_unitCallsign] select (_x isEqualTo _me), ["node", "gw"] select ((_nd select 2) isEqualTo 2), _nd select 4]
            }],
            ["links", _scrLinks],
            ["hops", _order apply { _hopG select _x }],
            ["levels", _order apply { ["gravity", [_mgG select _x, _hopG select _x, _maxH]] call comspec_atak_native_fnc_meshGraph }],
            ["me", 0], ["gw", _rank select _meGw], ["gwLabel", _gwLabel], ["range", missionNamespace getVariable ["COMSPEC_ATAK_MeshRange", 3000]]
        ]];
        // Graphe complet pour les outils, en mode développeur seulement (fn_devMesh).
        if (["on"] call comspec_atak_native_fnc_devMesh) then {
            private _devNodes = [];
            {
                _x params ["_o", "", "_type", "_c", "_r"];
                private _nm = _names select _forEachIndex;
                if (_nm isEqualTo "") then { _nm = format ["Canal %1, %2", _c, [_x select 1, true] call comspec_atak_native_fnc_unitCallsign]; };
                _devNodes pushBack [_o, _poss select _forEachIndex, _nm, ["node", "posed", "gw"] select _type, _c, _r, _hopG select _forEachIndex, (_bwG select _forEachIndex) max 0];
            } forEach _nodes;
            _res set ["dev", [_devNodes, _links]];
        };
        ["maillage lecture", _t0] call comspec_atak_native_fnc_perfAcc;
        _res
    };
};
