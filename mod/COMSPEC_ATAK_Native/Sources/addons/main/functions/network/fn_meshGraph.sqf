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
    Ajouts 4.2.1, lus seulement s'ils sont là (un serveur plus ancien ne les envoie pas) :
      causes à plat par 2 : A + 64 x B, cause du lien de marge inférieure à 9 dB (codes "causeTxt") ;
      liens manqués à plat par 3 : A + 64 x B (A : le nœud qui manque B), cause, marge (2 dB) ; 3 au plus par nœud, les plus proches de passer.
    Bâtiments (réglage mesh_buildings, 50 % par défaut) : un seul rayon par paire de moins de 3 km, en cache avec le bilan (30 s).
    Reconvergence (réglage mesh_reconverge_s, 3 s par défaut, côté client) : un nœud de ma route vers la passerelle disparaît du graphe
    partagé, la nouvelle route n'est prise qu'après ce délai (ni données ni rallonge voix pendant ce temps).

    Lecture ("view", client) : aucun bilan radio. Plus large chemin sous plafond de sauts (comspec_atak_native_mesh_hops, 6 par défaut,
    décision 3), résultat dans COMSPEC_ATAK_MpuMesh pour fn_mpuLink, fn_mpuVoice et fn_waveRelayAction "scan".

      "serverBuilt" -> vrai si le graphe vient du serveur          "maxHops" -> plafond de sauts réglé
      "serverTick"  (serveur, chaque seconde)                      "every"   -> s avant la prochaine lecture (0 : le graphe a changé)
      "pass" [camps] (script planifié)                             "view" [réseau, antenne, débit max] (script planifié) -> HashMap
      "graph" [camp] -> [nœuds, voisins, liens, total] ou []
      "peer" [unité] -> [sauts, marge, niveau] si mon graphe récent la joint (messages A vers B, lot 10), sinon []
      "weak" [antenne] -> [sauts, marge, niveau, nom A, nom B, marge du lien] : chemin vers ce nœud et son maillon faible (ping, fn_meshTools), sinon []
      "jamAt" [position, brouilleurs, canal] -> part du signal gardée, 0 à 1
      Sans effet, testés par fn_devMeshTest :
      "solve" [voisins, sources, autorisés, plafond, débit max, courbe, ne relaie pas] -> [débits, sauts, vers, marges, sources]
      "chain" [voisins, vers, nœud] -> liens du chemin, du nœud jusqu'à sa source : [[A, B, marge]...]
      "weakest" [liens] -> le lien de plus faible marge, ou []
      "curve" -> part du débit gardée selon les sauts (décision 12)
      "gravity" [marge, sauts, plafond] -> niveau 0 à 3            "hyst" [lien bon, marge, débit, état précédent] -> [garder, état, marge, débit]
      "lossCode" [[cause, dB]...] (fn_radioLink) -> code de la plus forte perte au-delà de 3 dB, 0 sinon
      "causeTxt" [code] -> cause en clair ; "causeFix" [code, distance à gagner m] -> conseil au joueur
*/
params [["_act", ""], ["_arg", []]];
switch (_act) do {
    case "lossCode": {
        private _best = 3;
        private _c = 0;
        {
            _x params [["_n", ""], ["_db", 0]];
            if (_n isEqualType "" && {_db isEqualType 0} && {_db > _best}) then {
                _best = _db;
                _c = switch (true) do {
                    case ((_n find "Relief") >= 0 || {(_n find "Fresnel") >= 0}): { 2 };
                    case ((_n find "Bâtiments") >= 0): { 3 };
                    case ((_n find "Feuillage") >= 0): { 8 };
                    default { 4 };
                };
            };
        } forEach _arg;
        _c
    };
    case "causeTxt": {
        // 1 distance, 2 relief, 3 bâtiments, 4 météo, 5 brouillage, 6 posture du porteur, 7 autre canal, 8 feuillage.
        ["la distance", "la distance", "le relief", "les bâtiments", "la météo", "un brouillage", "la posture du porteur", "un autre canal", "le feuillage"] param [_arg param [0, 1], "la distance"]
    };
    case "causeFix": {
        _arg params [["_c", 1], ["_gain", 0]];
        switch (_c) do {
            case 2: { "Montez sur un point haut, ou posez un relais sur la crête : une antenne plus haute passe au-dessus du relief." };
            case 3: { "Sortez des rues, ou posez un relais sur un toit : il voit par-dessus les bâtiments." };
            case 4: { "Pluie forte ou orage : rapprochez les radios, ou posez un relais de plus." };
            case 5: { "Éloignez-vous du brouilleur, ou changez de canal (app Wave Relay)." };
            case 6: { "Relevez-vous, ou sortez de derrière l'obstacle : couché, l'antenne est trop basse." };
            case 7: { "Mettez votre MPU5 sur le canal de l'équipe (app Wave Relay)." };
            case 8: { "Sortez du sous-bois, ou posez le relais en lisière." };
            default {
                if (_gain >= 50) then {
                    format ["Rapprochez-vous d'environ %1, ou posez un relais à mi-chemin.", [format ["%1 m", 50 * round (_gain / 50)], format ["%1 km", (_gain / 1000) toFixed 1]] select (_gain >= 1000)]
                } else { "Rapprochez-vous, ou posez un relais à mi-chemin." }
            };
        }
    };
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
    case "chain": {
        _arg params ["_adj", "_via", "_from"];
        private _out = [];
        private _v = _from;
        // 17 au plus : plafond de sauts réglable jusqu'à 16. « vers » vient de couches différentes : jamais de boucle sans fin.
        for "_step" from 1 to 17 do {
            private _u = _via param [_v, -1];
            if (_u < 0) exitWith {};
            private _li = (_adj select _u) findIf { (_x select 0) isEqualTo _v };
            if (_li < 0) exitWith { _out = []; };
            _out pushBack [_u, _v, ((_adj select _u) select _li) select 1];
            _v = _u;
        };
        _out
    };
    case "weakest": {
        private _best = [];
        { if (_best isEqualTo [] || {(_x select 2) < (_best select 2)}) then { _best = _x; }; } forEach (_arg param [0, []]);
        _best
    };
    case "weak": {
        _arg params [["_o", objNull]];
        private _m = uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap];
        if (isNull _o || {diag_tickTime - (_m getOrDefault ["at", -100]) > 20}) exitWith { [] };
        (_m getOrDefault ["path", []]) params [["_nodes", []], ["_adj", []], ["_names", []], ["_me", -1], ["_viaM", []], ["_hopM", []], ["_mgM", []], ["_viaG", []], ["_hopG", []]];
        private _t = _nodes findIf { (_x select 0) isEqualTo _o };
        if (_t < 0 || {_t isEqualTo _me}) exitWith { [] };
        private _hd = _hopM select _t;
        private _owner = (_nodes select _t) select 1;
        // Chemin retenu pour la voix et les messages quand le nœud a un porteur ; sinon (relais, passerelle) le chemin direct.
        private _peer = if (isNull _owner) then { [] } else { (_m getOrDefault ["voice", createHashMap]) getOrDefault [netId _owner, []] };
        if (_peer isEqualTo [] && {_hd < 0}) exitWith { [] };
        if (_peer isEqualTo []) then { _peer = [_hd, _mgM select _t, ["gravity", [_mgM select _t, _hd, ["maxHops"] call comspec_atak_native_fnc_meshGraph]] call comspec_atak_native_fnc_meshGraph]; };
        // Sauts différents du chemin direct : passage par le cœur de réseau, moi vers la passerelle puis passerelle vers lui.
        private _chain = if (_hd >= 0 && {(_peer select 0) isEqualTo _hd}) then {
            ["chain", [_adj, _viaM, _t]] call comspec_atak_native_fnc_meshGraph
        } else {
            (["chain", [_adj, _viaG, _me]] call comspec_atak_native_fnc_meshGraph) + (["chain", [_adj, _viaG, _t]] call comspec_atak_native_fnc_meshGraph)
        };
        private _w = ["weakest", [_chain]] call comspec_atak_native_fnc_meshGraph;
        private _nameOf = {
            params ["_i"];
            private _n = _names param [_i, ""];
            if (_n isNotEqualTo "") exitWith { _n };
            format ["Canal %1, %2", (_nodes select _i) select 3, [(_nodes select _i) select 1, true] call comspec_atak_native_fnc_unitCallsign]
        };
        if (_w isEqualTo []) exitWith { _peer + ["", "", _peer select 1] };
        _peer + [[_w select 0] call _nameOf, [_w select 1] call _nameOf, _w select 2]
    };
    case "jamAt": {
        _arg call {
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
            _f max 0
        }
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
            (_ref select _i) params ["", ["_nf", [], [[]]], ["_lf", [], [[]]], ["_total", 0, [0]], ["_cf", [], [[]]], ["_mf", [], [[]]]];
            // Tailles : 64 nœuds et 2 016 liens au plus, comme le constructeur. Au-delà, ou type faux : graphe ignoré.
            if ((count _nf) > 320 || {(count _lf) > 6048} || {(_lf findIf { !(_x isEqualType 0) }) >= 0}) then {
                _nf = [];
                _lf = [];
            };
            // Causes et liens manqués (4.2.1) : simple explication, ignorée si la taille ou le type est faux.
            if ((count _cf) > 4032 || {(_cf findIf { !(_x isEqualType 0) || {!finite _x} }) >= 0}) then { _cf = []; };
            if ((count _mf) > 576 || {(_mf findIf { !(_x isEqualType 0) || {!finite _x} }) >= 0}) then { _mf = []; };
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
            private _causes = createHashMap;
            for "_j" from 0 to ((count _cf) - 2) step 2 do { _causes set [_cf select _j, ((round (_cf select (_j + 1))) max 0) min 8]; };
            private _links = [];
            for "_j" from 0 to ((count _lf) - 3) step 3 do {
                private _p = _lf select _j;
                private _a = _p mod 64;
                private _b = floor (_p / 64);
                private _m = _lf select (_j + 1);
                private _kb = (_lf select (_j + 2)) * 100;
                if (_a < 0 || {_b < 0} || {_a >= (count _nodes)} || {_b >= (count _nodes)} || {!finite _m} || {!finite _kb}) then { continue };
                // 5e élément : cause du lien faible (0 : rien à signaler).
                _links pushBack [_a, _b, _m, _kb, _causes getOrDefault [_p, 0]];
                (_adj select _a) pushBack [_b, _m, _kb];
                (_adj select _b) pushBack [_a, _m, _kb];
            };
            // Liens manqués par nœud : [[marge, autre nœud, cause]...], les plus proches de passer d'abord.
            private _miss = _nodes apply { [] };
            for "_j" from 0 to ((count _mf) - 3) step 3 do {
                private _p = _mf select _j;
                private _a = _p mod 64;
                private _b = floor (_p / 64);
                if (_p < 0 || {_a >= (count _nodes)} || {_b >= (count _nodes)} || {_a isEqualTo _b}) then { continue };
                (_miss select _a) pushBack [_mf select (_j + 2), _b, ((round (_mf select (_j + 1))) max 0) min 8];
            };
            _out = [_nodes, _adj, _links, _total, _causes, _miss];
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
        // Reconvergence en cours dans la dernière lecture : relue dès la fin du délai, pas 4 s plus tard.
        if (((uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap]) getOrDefault ["reconv", 0]) > 0) then {
            _normal = _normal min ((((uiNamespace getVariable ["COMSPEC_ATAK_MeshReconv", [-1, ""]]) param [0, -1]) - diag_tickTime) max 0);
        };
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
        private _jamAt = { ["jamAt", _this] call comspec_atak_native_fnc_meshGraph };
        // Bâtiments entre deux radios du maillage (4.2.1, réglage mesh_buildings) : le profil grossier ne voit que le relief. Un seul rayon par
        // paire de moins de 3 km, calculé avec le bilan et gardé avec lui ; mêmes pertes que fn_radioLink "full" (12 dB par bâtiment,
        // x 0,6 en Réaliste, 30 dB au plus), multipliées par le réglage. Bâtiments à moins de 15 m d'une antenne : abri ou support, non comptés.
        private _bldK = missionNamespace getVariable ["comspec_atak_native_mesh_buildings", 50];
        if (!(_bldK isEqualType 0) || {!finite _bldK}) then { _bldK = 50; };
        _bldK = ((_bldK max 0) min 100) / 100;
        private _realLvl = ["realism"] call comspec_atak_native_fnc_netCond;
        if !(_realLvl isEqualType 0) then { _realLvl = 1; };
        private _bldOn = _bldK > 0 && {_realLvl > 0};
        private _bldEnv = [0.6, 1] select (_realLvl >= 2);
        // Marge d'une paire et cause de la plus forte perte : bilan radio en cache 30 s par paire de positions arrondies (15 m).
        private _marginOf = {
            // _ka, _kb : clés de position des deux nœuds ([x / 15, y / 15, z, texte]), calculées une fois par nœud et par passe
            // ("_posKey") ; avant, six arrondis, deux tableaux et un str imbriqué par paire, 2 016 fois par passe à 64 nœuds.
            params ["_pa", "_pb", "_r", "_cache", "_c", "_ka", "_kb"];
            private _k = (if ((_ka select 0) < (_kb select 0) || {(_ka select 0) isEqualTo (_kb select 0) && {(_ka select 1) <= (_kb select 1)}}) then { (_ka select 3) + (_kb select 3) } else { (_kb select 3) + (_ka select 3) }) + _kTail + str (round _r);
            private _hit = _cache getOrDefault [_k, []];
            if ((count _hit) >= 2 && {diag_tickTime - (_hit select 0) < 30}) exitWith {
                _c set [1, (_c select 1) + 1];
                [_hit select 1, _hit param [2, 1]]
            };
            _c set [0, (_c select 0) + 1];
            _c set [7, (_c select 7) + 1];
            ([_pa, _pb, _r, "coarse"] call comspec_atak_native_fnc_radioLink) params ["_m", "_losses", "_d"];
            if (_bldOn && {_d <= 3000} && {_m > -30}) then {
                private _blds = [];
                {
                    private _o = _x select 2;
                    if (isNull _o || {_o in _blds} || {typeOf _o isEqualTo ""} || {_o isKindOf "AllVehicles"}) then { continue };
                    private _ip = _x select 0;
                    if ((_ip distance _pa) < 15 || {(_ip distance _pb) < 15}) then { continue };
                    _blds pushBack _o;
                } forEach (lineIntersectsSurfaces [_pa, _pb, objNull, objNull, true, 8, "GEOM", "NONE"]);
                if (_blds isNotEqualTo []) then {
                    private _bl = (((count _blds) * 12 * _bldEnv) min 30) * _bldK;
                    _m = _m - _bl;
                    _losses pushBack ["Bâtiments sur le trajet", _bl];
                };
                // Rayon compté comme un bilan de plus : la tranche reste bornée.
                _c set [7, (_c select 7) + 1];
            };
            // Rien au-delà de 3 dB : la marge n'est limitée que par la distance.
            private _code = ["lossCode", _losses] call comspec_atak_native_fnc_meshGraph;
            if (_code isEqualTo 0) then { _code = 1; };
            _cache set [_k, [diag_tickTime, _m, _code]];
            [_m, _code]
        };
        private _kTail = format ["b%1r", round (_bldK * 100)];
        private _posKey = {
            params ["_p"];
            private _kx = round ((_p select 0) / 15);
            private _ky = round ((_p select 1) / 15);
            private _kz = round (_p select 2);
            [_kx, _ky, _kz, format ["%1,%2,%3;", _kx, _ky, _kz]]
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
                // Objet obligatoire manquant (fn_netRequire) : hors du graphe, quoi que le client publie.
                if (["blocked", [_x]] call comspec_atak_native_fnc_netRequire) then { continue };
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
            private _pk = _nodes apply { [_x select 6] call _posKey };
            // Hystérésis : état de la passe précédente, retrouvé par objet (les index changent d'une passe à l'autre).
            (_hystAll getOrDefault [_side, [[], createHashMap]]) params ["_oldObjs", "_oldState"];
            private _oldIdx = _nodes apply { _oldObjs find (_x select 0) };
            private _state = createHashMap;
            private _lf = [];
            // Explication (4.2.1) : cause des liens faibles, et par nœud les 3 liens manqués les plus proches de passer. Aucun bilan de plus.
            private _cf = [];
            private _miss = _nodes apply { [] };
            private _missAdd = {
                params ["_p", "_q", "_mm", "_cc"];
                {
                    _x params ["_s", "_t"];
                    private _l = _miss select _s;
                    _l pushBack [_mm, _t, _cc];
                    if ((count _l) > 3) then { _l sort false; _l resize 3; };
                } forEach [[_p, _q], [_q, _p]];
            };
            for "_i" from 0 to (_n - 2) do {
                private _a = _nodes select _i;
                for "_j" from (_i + 1) to (_n - 1) do {
                    private _b = _nodes select _j;
                    if (((_a select 2) isEqualTo 2) && {(_b select 2) isEqualTo 2}) then { continue };
                    if (((_a select 3) isNotEqualTo (_b select 3)) && {(_a select 2) isNotEqualTo 2} && {(_b select 2) isNotEqualTo 2}) then {
                        // Autre canal à portée : l'erreur la plus simple à corriger, gardée comme lien manqué (marge 0).
                        if (((_a select 6) distance (_b select 6)) <= ((_a select 4) min (_b select 4))) then { [_i, _j, 0, 7] call _missAdd; };
                        continue
                    };
                    private _r = (_a select 4) min (_b select 4);
                    private _m = -99;
                    private _kb = 0;
                    private _ok = false;
                    private _code = 1;
                    private _dAB = (_a select 6) distance (_b select 6);
                    if (_dAB > _r) then {
                        _ct set [2, (_ct select 2) + 1];
                        // Trop loin : marge en espace libre seulement (aucun calcul de relief), jusqu'à 3 fois la portée.
                        if (_dAB < 3 * _r) then { [_i, _j, 20 * log ((_r max 1) / _dAB), 1] call _missAdd; };
                    } else {
                        ([_a select 6, _b select 6, _r, _cache, _ct, _pk select _i, _pk select _j] call _marginOf) params ["_m0", "_c0"];
                        _code = _c0;
                        // Météo radio forcée par Zeus (fn_meshTools).
                        _m = _m0 - _wxForce;
                        if (_wxForce >= 3 && {_m < 9} && {_m0 >= 9 || {_m < 0 && {_m0 >= 0}}}) then { _code = 4; };
                        if (_bodyOn || {_proneOn}) then {
                            private _pl = ([_a select 0, _a select 6, _b select 6, _proneOn, _bodyOn] call _pairLoss) + ([_b select 0, _b select 6, _a select 6, _proneOn, _bodyOn] call _pairLoss);
                            _m = _m - _pl;
                            if (_pl >= 6 && {_m < 9}) then { _code = 6; };
                        };
                        private _jam = (_jf select _i) min (_jf select _j);
                        if (_m >= 0 && {_jam >= 0.1}) then {
                            private _f = [0.06, 0.12, 0.25, 0.4, 0.55, 0.7, 0.85, 1] select (((({ _m >= _x } count [0, 2, 4, 6, 9, 12, 16, 20]) - 1) max 0) min 7);
                            _kb = (((_a select 5) min (_b select 5)) * _f * _jam);
                            _ok = true;
                            if (_jam < 0.7) then { _code = 5; };
                        } else {
                            if (_m >= 0) then { _code = 5; };
                            [_i, _j, _m min 0, _code] call _missAdd;
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
                            // Lien dégradé, limite ou gardé par l'hystérésis : sa cause voyage avec lui.
                            if (_lm < 9) then { _cf append [_i + 64 * _j, _code]; };
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
            // Liens manqués, marge arrondie à 2 dB comme les liens : le graphe ne se rediffuse pas à chaque pas d'un joueur.
            private _mf = [];
            {
                private _s = _forEachIndex;
                { _x params ["_mm", "_t", "_cc"]; if (_mm > -40) then { _mf append [_s + 64 * _t, _cc, 2 * round (_mm / 2)]; }; } forEach _x;
            } forEach _miss;
            _ref pushBack [_side, _nf, _lf, _total, _cf, _mf];
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
        _dec params ["_nodes", "_adj", "_links", "_total", ["_causes", createHashMap], ["_miss", []]];
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
        // Relais exigé (fn_relayNet "gateOn", coché par défaut) : sans passerelle en service dans le graphe, aucun réseau. Ni maillage d'équipe
        // autour du chef de groupe, ni maillage autonome : la raison exacte vient de fn_relayNet "gate" (aucun relais, détruit, éteint, brouillé).
        if (_gws isEqualTo [] && {["gateOn"] call comspec_atak_native_fnc_relayNet}) exitWith {
            private _gate = ["gate", ["", true]] call comspec_atak_native_fnc_relayNet;
            _res set ["why", [_gate select 2, "Aucun relais en service à portée"] select ((_gate select 2) isEqualTo "")];
            _res set ["fix", _gate select 3];
            _res set ["gate", _gate select 1];
            _res
        };
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
        (["solve", [_adj, [_me], _allowed, _maxH, _maxK * 1.5, _curve, _nodes apply { _x select 6 }]] call comspec_atak_native_fnc_meshGraph) params ["_bwM", "_hopM", "_viaM", "_mgM"];
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
        // Pour le ping et la prévision (fn_meshTools), à la demande : de simples références, rien n'est recalculé ni diffusé.
        _res set ["path", [_nodes, _adj, _names, _me, _viaM, _hopM, _mgM, _viaG, _hopG, _poss]];
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
        // Pourquoi ça ne passe pas (4.2.1) : lu dans le graphe partagé (causes et liens manqués du constructeur), aucun bilan radio ici.
        //   "cause" texte, "fix" conseil, "miss" [antenne A, antenne B, étiquette de carte, cause] : le lien qui relierait mon îlot au maillage.
        private _nameAny = {
            params ["_i"];
            private _nm = _names param [_i, ""];
            if (_nm isNotEqualTo "") exitWith { _nm };
            [_nodes select _i, _i isEqualTo _me, _relays] call _nameOf
        };
        private _distTxt = { params ["_d"]; if (_d >= 1000) then { format ["%1 km", (_d / 1000) toFixed 1] } else { format ["%1 m", round _d] } };
        private _cause = "";
        private _fix = "";
        private _missOut = [];
        if (!_ok && {_mode isNotEqualTo "self"}) then {
            // Mon îlot : les nœuds que je joins (moi compris). Le lien manqué vers un nœud déjà relié à la passerelle passe devant.
            private _pick = [];
            {
                private _ia = _forEachIndex;
                if (_ia isNotEqualTo _me && {(_hopM select _ia) < 0}) then { continue };
                {
                    _x params ["_mm", "_jb", "_cc"];
                    if (_jb >= (count _nodes) || {_jb isEqualTo _me} || {(_hopM select _jb) >= 0} || {isNull ((_nodes select _jb) select 0)}) then { continue };
                    private _score = _mm + ([0, 100] select ((_hopG select _jb) >= 0));
                    if (_pick isEqualTo [] || {_score > (_pick select 4)}) then { _pick = [_mm, _ia, _jb, _cc, _score]; };
                } forEach (_miss param [_ia, []]);
            } forEach _nodes;
            if (_pick isNotEqualTo []) then {
                _pick params ["_mm", "_ia", "_jb", "_cc"];
                private _d = (_poss select _ia) distance (_poss select _jb);
                private _na = [_ia] call _nameAny;
                private _nb = [_jb] call _nameAny;
                if (_cc isEqualTo 7) then {
                    _cause = format ["%1 est à %2, mais sur le canal %3 et %4 sur le canal %5", _nb, [_d] call _distTxt, (_nodes select _jb) select 3, _na, (_nodes select _ia) select 3];
                } else {
                    _cause = format ["lien %1 › %2 (%3) coupé par %4", _na, _nb, [_d] call _distTxt, ["causeTxt", [_cc]] call comspec_atak_native_fnc_meshGraph];
                    if (_mm < 0 && {_cc isNotEqualTo 5}) then { _cause = _cause + format [" : il manque %1 dB", round (-_mm)]; };
                };
                // Distance : en espace libre, -6 dB quand la distance double ; ce qu'il faut gagner pour revenir à 0 dB.
                _fix = ["causeFix", [_cc, [0, _d * (1 - 10 ^ ((_mm min 0) / 20))] select (_cc isEqualTo 1)]] call comspec_atak_native_fnc_meshGraph;
                private _lbl = [format ["lien coupé (%1)", ["causeTxt", [_cc]] call comspec_atak_native_fnc_meshGraph], "autre canal"] select (_cc isEqualTo 7);
                _missOut = [(_nodes select _ia) select 0, (_nodes select _jb) select 0, _lbl, _cc];
                _res set ["why", format ["hors du maillage : %1", _cause]];
            };
        };
        if (_ok && {(_res get "level") >= 1}) then {
            // Chemin bon mais fragile : le maillon le plus faible vers la passerelle, et ce qui le limite.
            private _w = ["weakest", [["chain", [_adj, _viaG, _me]] call comspec_atak_native_fnc_meshGraph]] call comspec_atak_native_fnc_meshGraph;
            if (_w isNotEqualTo [] && {(_w select 2) < 9}) then {
                _w params ["_wa", "_wb", "_wm"];
                private _wc = _causes getOrDefault [(_wa min _wb) + 64 * (_wa max _wb), 1];
                _cause = format ["maillon faible %1 › %2 : marge %3 dB, limitée par %4", [_wa] call _nameAny, [_wb] call _nameAny, (round _wm) max 0, ["causeTxt", [_wc]] call comspec_atak_native_fnc_meshGraph];
                _fix = ["causeFix", [_wc, 0]] call comspec_atak_native_fnc_meshGraph;
            } else {
                if (_h >= _maxH - 1) then {
                    _cause = format ["%1 sauts pour %2 au plus : chaque saut coûte du débit et du délai", _h, _maxH];
                    _fix = "Posez un relais plus près, ou rapprochez-vous de la passerelle.";
                };
            };
        };
        _res set ["cause", _cause];
        _res set ["fix", _fix];
        _res set ["miss", _missOut];
        // Reconvergence (4.2.1, réglage mesh_reconverge_s) : un nœud de ma route vers la passerelle a quitté le graphe (relais détruit, ramassé,
        // batterie vide, porteur tombé) ; comme un vrai maillage, la nouvelle route ne sert qu'après quelques secondes. Rien n'est diffusé.
        private _rcS = missionNamespace getVariable ["comspec_atak_native_mesh_reconverge_s", 3];
        if (!(_rcS isEqualType 0) || {!finite _rcS}) then { _rcS = 3; };
        _rcS = (_rcS max 0) min 15;
        private _route = [];
        if (_ok) then { { _route pushBack [(_nodes select (_x select 0)) select 0, [_x select 0] call _nameAny]; } forEach (["chain", [_adj, _viaG, _me]] call comspec_atak_native_fnc_meshGraph); };
        (uiNamespace getVariable ["COMSPEC_ATAK_MeshRoute", [false, [], ""]]) params [["_wasOk", false], ["_oldRoute", []], ["_oldKey", ""]];
        if (_rcS > 0 && {_wasOk} && {_oldKey isEqualTo _key}) then {
            private _present = _nodes apply { _x select 0 };
            private _lost = _oldRoute findIf { isNull (_x select 0) || {!alive (_x select 0)} || {!((_x select 0) in _present)} };
            if (_lost >= 0) then { uiNamespace setVariable ["COMSPEC_ATAK_MeshReconv", [diag_tickTime + _rcS, (_oldRoute select _lost) select 1]]; };
        };
        uiNamespace setVariable ["COMSPEC_ATAK_MeshRoute", [_ok, _route, _key]];
        (uiNamespace getVariable ["COMSPEC_ATAK_MeshReconv", [-1, ""]]) params [["_rcUntil", -1], ["_rcName", ""]];
        private _rcLeft = _rcUntil - diag_tickTime;
        _res set ["reconv", 0];
        if (_rcLeft > 0 && {_rcS > 0}) then {
            _res set ["reconv", _rcLeft];
            if (_ok) then {
                _res set ["ok", false];
                _res set ["kbps", 0];
                _res set ["vkey", ""];
                _res set ["voice", createHashMap];
                _res set ["level", 3];
                _res set ["why", format ["reconvergence du maillage : %1 perdu, nouvelle route dans %2 s", _rcName, ceil _rcLeft]];
                _res set ["cause", format ["%1 a quitté le maillage : les radios recalculent leurs routes", _rcName]];
                _res set ["fix", "Patientez quelques secondes : le réseau repasse par un autre chemin s'il en existe un."];
            };
        };
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
            _x params ["_a", "_b", "_m", "", ["_lc", 0]];
            // 4e élément (4.2.1) : cause du lien faible (codes "causeTxt", 0 si rien à signaler).
            if ((_rank select _a) >= 0 && {(_rank select _b) >= 0}) then { _scrLinks pushBack [_rank select _a, _rank select _b, ((round (100 * ((_m / 20) min 1))) max 1) min 100, _lc]; };
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
