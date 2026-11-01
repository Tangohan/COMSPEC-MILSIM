/*
    Ordres réels aux groupes IA du camp, avec ou sans Athena (aucune requête au site) : depuis la carte du téléphone
    (clic droit > ORDRE AUX IA, fn_mapCtxMenu), le PC du poste TOC (onglet GROUPES > ORDRE IA, fn_tocLaptop) et les ordres
    locaux adressés à un groupe IA (fn_orderLocal "send" : DÉPLACER, TENIR, RECO, RENFORT).
    Parcours : le client demande au serveur (événement comspec_atak_native_aiOrdSrv), le serveur vérifie les droits et
    envoie l'ordre là où le groupe est local (serveur, client headless : événement CBA ciblé sur le chef) ; la machine du
    groupe pose les points de passage, suit l'exécution et renvoie l'état (comspec_atak_native_aiOrdState), relayé par le
    serveur aux seuls joueurs du camp (comspec_atak_native_sideEvent) et gardé pour les arrivants (COMSPEC_ATAK_SrvAiOrd).
    Droits : chef de groupe, joueur seul, rôle chef / commandant, opérateur d'un poste TOC ou Zeus, du même camp que le
    groupe ; jamais un groupe qui compte un joueur ; la mission peut protéger un groupe :
    _grp setVariable ["COMSPEC_ATAK_NoOrders", true, true].
      "kinds"                           -> [[clé, libellé, point requis]...]
      "groups" [camp, centre]           -> groupes IA commandables du camp, du plus proche au plus loin
      "label"  groupe                   -> « Alpha 1-1 · 4 IA · Hunter »
      "can"    unité                    -> l'unité peut donner un ordre aux IA (toute machine)
      "send"   [groupe, clé, [x, y] ou [], depuis le PC du poste] (client) -> true si la demande part
      "srv"    [joueur, netId, clé, position, id, émetteur, PC du poste] (serveur)
      "exec"   [netId, clé, position, id, donneur] (machine où le groupe est local)
      "report" [netId, camp, paires] (machine du groupe) état vers le serveur puis le camp
      "keep"   [netId, paires, camp] (serveur) mémoire pour les arrivants
      "recv"   [netId, paires, camp] (client) état reçu : carte, PC du poste, notification au donneur
      "draw"   [carte, taille du texte, police] ordres en cours sur la carte (téléphone et PC du poste)
    États : NEW (transmis), EXEC (en cours), DONE (terminé), FAIL (impossible), CANCEL (annulé).
*/
params [["_act", ""], ["_arg", []]];
// Carte (à chaque image) : traité avant de construire les tables ci-dessous.
if (_act isEqualTo "draw") exitWith {
    _arg params ["_map", ["_ts", 0.03], ["_font", "RobotoCondensedBold"]];
    private _all = missionNamespace getVariable ["COMSPEC_ATAK_AiOrds", createHashMap];
    if ((count _all) isEqualTo 0) exitWith {};
    private _cols = createHashMapFromArray [["NEW", [0.5, 0.75, 1, 1]], ["EXEC", [1, 0.8, 0.25, 1]], ["DONE", [0.36, 0.85, 0.42, 0.85]], ["FAIL", [0.95, 0.35, 0.3, 0.85]], ["CANCEL", [0.6, 0.62, 0.6, 0.7]]];
    {
        private _st = _y getOrDefault ["state", ""];
        private _g = _y getOrDefault ["g", grpNull];
        // Ordres en cours, et ceux finis depuis moins d'une minute.
        if ((_st in ["NEW", "EXEC"] || {(serverTime - (_y getOrDefault ["end", -1e9])) < 60}) && {!isNull _g} && {alive leader _g}) then {
            private _c = _cols getOrDefault [_st, [1, 1, 1, 1]];
            private _lp = getPosASLVisual leader _g;
            private _p = _y getOrDefault ["pos", []];
            _map drawIcon ["\A3\ui_f\data\map\markers\military\circle_CA.paa", _c, _lp, 20, 20, 0, "", 0];
            if ((count _p) >= 2) then {
                private _p3 = [_p select 0, _p select 1, 0];
                _map drawLine [_lp, _p3, _c];
                _map drawIcon ["\A3\ui_f\data\map\markers\military\objective_CA.paa", _c, _p3, 24, 24, 0, _y getOrDefault ["lbl", ""], 1, _ts, _font, "right"];
            } else {
                _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)", _c, _lp, 20, 20, 0, "   " + (_y getOrDefault ["lbl", ""]), 1, _ts, _font, "right"];
            };
        };
    } forEach _all;
};
private _kinds = [
    ["MOVE", "Aller au point", true], ["PATROL", "Patrouiller la zone", true], ["ATTACK", "Attaquer la zone", true],
    ["SUPPRESS", "Tir de suppression", true], ["WITHDRAW", "Se replier au point", true], ["HOLD", "Tenir la position", false],
    ["FOLLOW", "Me suivre", false], ["BOARD", "Embarquer", false], ["DISMOUNT", "Débarquer", false], ["CANCEL", "Annuler l'ordre", false],
    ["AWARE", "Posture prudente", false], ["COMBAT", "Posture de combat", false], ["STEALTH", "Posture furtive", false],
    ["LIMITED", "Vitesse lente", false], ["NORMAL", "Vitesse normale", false], ["FULL", "Vitesse rapide", false],
    ["COLUMN", "Formation en colonne", false], ["WEDGE", "Formation en coin", false], ["LINE", "Formation en ligne", false], ["FILE", "Formation en file", false]
];
// Posture, vitesse, formation : appliquées tout de suite, sans remplacer le déplacement en cours.
private _post = createHashMapFromArray [["AWARE", "beh"], ["COMBAT", "beh"], ["STEALTH", "beh"], ["LIMITED", "spd"], ["NORMAL", "spd"], ["FULL", "spd"],
    ["COLUMN", "frm"], ["WEDGE", "frm"], ["LINE", "frm"], ["FILE", "frm"]];
private _kLabel = { params ["_k"]; (_kinds param [(_kinds findIf { (_x select 0) isEqualTo _k }) max 0]) select 1 };
switch (_act) do {
    case "kinds": { _kinds };
    case "groups": {
        _arg params [["_sd", ""], ["_c", [0, 0]]];
        private _out = allGroups select {
            (str side _x) isEqualTo _sd && {({isPlayer _x} count (units _x)) isEqualTo 0} && {({alive _x} count (units _x)) > 0}
                && {!unitIsUAV (vehicle leader _x)} && {!(_x getVariable ["COMSPEC_ATAK_NoOrders", false])}
        };
        [_out, [_c], { (leader _x) distance2D _input0 }, "ASCEND"] call BIS_fnc_sortBy
    };
    case "label": {
        private _g = _arg;
        private _v = vehicle leader _g;
        format ["%1 · %2 IA%3", groupId _g, {alive _x} count (units _g), ["", " · " + getText (configOf _v >> "displayName")] select (_v isNotEqualTo leader _g)]
    };
    case "can": {
        private _u = _arg;
        if !(_u isEqualType objNull) then { _u = player; };
        if (isNull _u || {!alive _u}) exitWith { false };
        // Poste TOC : indicatif publié par fn_tocPost tant que l'opérateur est au PC (lisible aussi par le serveur).
        if ((_u getVariable ["COMSPEC_ATAK_TocOn", ""]) isNotEqualTo "" || {!isNull (getAssignedCuratorLogic _u)}) exitWith { true };
        private _g = group _u;
        (leader _g) isEqualTo _u || {({isPlayer _x} count (units _g)) <= 1}
            || {private _r = toLower (roleDescription _u); (_r find "chef") >= 0 || {(_r find "command") >= 0} || {(_r find "leader") >= 0}}
    };
    case "send": {
        _arg params [["_g", grpNull], ["_k", ""], ["_pos", []], ["_toc", false]];
        // PC du poste ouvert : ligne d'état du PC ; sinon notification du téléphone.
        private _say = {
            params ["_t", ["_type", "WARNING"]];
            if (!isNull (uiNamespace getVariable ["COMSPEC_ATAK_TocLapDisp", displayNull])) then { ["status", _t] call comspec_atak_native_fnc_tocLaptop; } else { [_type, _t, 3, 30] call comspec_atak_native_fnc_notify; };
            false
        };
        if (isNull _g) exitWith { ["Choisissez d'abord un groupe IA"] call _say };
        if !(["can", player] call comspec_atak_native_fnc_aiOrder) exitWith { ["Seul un chef ou le poste TOC peut donner un ordre aux IA"] call _say };
        private _ki = _kinds findIf { (_x select 0) isEqualTo _k };
        if (_ki < 0) exitWith { false };
        if (((_kinds select _ki) select 2) && {(count _pos) < 2}) exitWith { ["Choisissez d'abord le point sur la carte (clic droit)"] call _say };
        private _issuer = if (_toc) then { format ["TOC · %1", ["callsign"] call comspec_atak_native_fnc_tocPost] } else {
            ([[player, true] call comspec_atak_native_fnc_unitCallsign, name player] select { _x isNotEqualTo "" }) param [0, name player]
        };
        if ((count _pos) >= 2) then { _pos = [_pos select 0, _pos select 1]; } else { _pos = []; };
        private _ev = [player, [_g] call BIS_fnc_netId, _k, _pos,
            format ["AI-%1-%2", getPlayerUID player, floor (serverTime * 10)], _issuer, _toc];
        // PC du poste : réseau filaire, envoi direct ; téléphone : soumis au signal comme les autres ordres (fn_netSend).
        if (_toc) then { ["comspec_atak_native_aiOrdSrv", _ev] call CBA_fnc_serverEvent; } else {
            [{ ["comspec_atak_native_aiOrdSrv", _this] call CBA_fnc_serverEvent; }, _ev, "Ordre IA", 1] call comspec_atak_native_fnc_netSend;
        };
        [format ["%1 · %2 : ordre transmis", groupId _g, [_k] call _kLabel], "INFO"] call _say;
        true
    };
    case "srv": {
        if (!isServer) exitWith {};
        _arg params [["_u", objNull], ["_nid", ""], ["_k", ""], ["_pos", []], ["_id", ""], ["_by", ""], ["_toc", false]];
        private _g = [_nid] call BIS_fnc_groupFromNetId;
        private _sd = str side group _u;
        private _no = {
            params ["_t"];
            if (!isNull _u) then { ["comspec_atak_native_aiOrdState", ["", [["text", _t]], _sd], _u] call CBA_fnc_targetEvent; };
        };
        if (isNull _u || {!alive _u} || {!isPlayer _u}) exitWith {};
        if (isNull _g || {({alive _x} count (units _g)) isEqualTo 0}) exitWith { ["Ce groupe n'existe plus"] call _no; };
        if ((str side _g) isNotEqualTo _sd || {({isPlayer _x} count (units _g)) > 0} || {_g getVariable ["COMSPEC_ATAK_NoOrders", false]}) exitWith { ["Ce groupe ne prend pas d'ordre du téléphone"] call _no; };
        if !(["can", _u] call comspec_atak_native_fnc_aiOrder) exitWith { ["Seul un chef ou le poste TOC peut donner un ordre aux IA"] call _no; };
        if ((_kinds findIf { (_x select 0) isEqualTo _k }) < 0) exitWith {};
        if !(_pos isEqualTypeArray [0, 0]) then { _pos = []; };
        _by = (_by regexReplace ["[<>&]", ""]) select [0, 40];
        if !(_k in _post) then {
            ["report", [_nid, _sd, [["id", _id], ["kind", _k], ["state", "NEW"], ["text", "transmis"], ["pos", _pos], ["by", _by], ["uid", getPlayerUID _u], ["grp", groupId _g], ["at", serverTime]]]] call comspec_atak_native_fnc_aiOrder;
        };
        ["comspec_atak_native_aiOrdExec", [_nid, _k, _pos, _id, _u], leader _g] call CBA_fnc_targetEvent;
    };
    case "report": {
        _arg params ["_nid", "_sd", "_pairs"];
        ["comspec_atak_native_sideEvent", ["comspec_atak_native_aiOrdState", [_nid, _pairs, _sd], _sd]] call CBA_fnc_serverEvent;
    };
    case "exec": {
        _arg params [["_nid", ""], ["_k", ""], ["_pos", []], ["_id", ""], ["_by", objNull]];
        private _g = [_nid] call BIS_fnc_groupFromNetId;
        // shortcut: groupe passé sur une autre machine entre la demande et l'exécution, ordre perdu ; à relayer si les clients headless en font souvent.
        if (isNull _g || {!local _g}) exitWith {};
        private _sd = str side _g;
        private _rep = { params ["_st", "_txt"]; ["report", [_nid, _sd, [["id", _id], ["state", _st], ["text", _txt]]]] call comspec_atak_native_fnc_aiOrder; };
        private _ld = leader _g;
        private _units = (units _g) select { alive _x };
        if (_k in _post) exitWith {
            private _f = _post get _k;
            switch (_f) do {
                case "beh": { _g setBehaviour _k; };
                case "spd": { _g setSpeedMode _k; };
                default { _g setFormation _k; };
            };
            ["report", [_nid, _sd, [[_f, _k], ["ack", [_k] call _kLabel], ["ackUid", getPlayerUID _by]]]] call comspec_atak_native_fnc_aiOrder;
        };
        // Ordre précédent abandonné : points de passage effacés (du dernier au premier), arrêts et tirs levés.
        private _clear = {
            for "_i" from ((count (waypoints _g)) - 1) to 0 step -1 do { deleteWaypoint [_g, _i]; };
            _units doFollow _ld;
            { _x doWatch objNull; } forEach _units;
        };
        private _p3 = if ((count _pos) >= 2) then { [_pos select 0, _pos select 1, 0] } else { getPosATL _ld };
        private _wpAdd = {
            params ["_type", ["_at", _p3], ["_beh", "UNCHANGED"], ["_spd", "UNCHANGED"]];
            private _w = _g addWaypoint [_at, 0];
            _w setWaypointType _type;
            _w setWaypointBehaviour _beh;
            _w setWaypointSpeed _spd;
            _w setWaypointCompletionRadius 20;
            _w setWaypointDescription ([_k] call _kLabel);
            _w
        };
        private _veh = objNull;
        private _wp = [];
        private _fail = "";
        call _clear;
        switch (_k) do {
            case "MOVE": { _wp = ["MOVE"] call _wpAdd; };
            case "HOLD": { _wp = ["HOLD"] call _wpAdd; };
            case "WITHDRAW": { _g setCombatMode "YELLOW"; _wp = ["MOVE", _p3, "AWARE", "FULL"] call _wpAdd; };
            case "ATTACK": { _g setCombatMode "RED"; _wp = ["SAD", _p3, "COMBAT"] call _wpAdd; };
            case "PATROL": {
                // Quatre points autour de la zone, puis on recommence (CYCLE).
                private _first = [];
                {
                    private _w = ["MOVE", _p3 getPos [120, _x], "UNCHANGED", "LIMITED"] call _wpAdd;
                    if (_first isEqualTo []) then { _first = _w; };
                } forEach [0, 90, 180, 270];
                ["CYCLE", _p3 getPos [120, 0]] call _wpAdd;
                _wp = _first;
            };
            case "SUPPRESS": {
                _g setCombatMode "YELLOW";
                private _tgt = AGLToASL [_p3 select 0, _p3 select 1, 1.5];
                { _x doWatch _tgt; _x doSuppressiveFire _tgt; } forEach _units;
            };
            case "FOLLOW": {
                if (isNull _by || {!alive _by}) exitWith { _fail = "donneur d'ordre introuvable"; };
                _g move (getPosATL _by);
            };
            case "BOARD": {
                // Véhicule le plus proche du point (ou du groupe), en état de rouler, sans équipage d'un autre camp.
                private _c = [_p3, getPosATL _ld] select ((count _pos) < 2);
                private _seats = { params ["_v"]; (_v emptyPositions "Driver") + (_v emptyPositions "Gunner") + (_v emptyPositions "Commander") + (_v emptyPositions "Cargo") };
                private _cands = (nearestObjects [_c, ["Car", "Tank", "Helicopter", "Ship"], 150]) select {
                    alive _x && {canMove _x} && {(locked _x) < 2} && {!unitIsUAV _x} && {([_x] call _seats) > 0}
                        && {((crew _x) findIf { alive _x && {(side group _x) isNotEqualTo side _g} }) < 0}
                };
                if (_cands isEqualTo []) exitWith { _fail = "aucun véhicule libre à moins de 150 m"; };
                private _i = _cands findIf { ([_x] call _seats) >= (count _units) };
                _veh = _cands select (_i max 0);
                _g addVehicle _veh;
                _units allowGetIn true;
                _units orderGetIn true;
                _wp = ["GETIN", getPosATL _veh] call _wpAdd;
                _wp waypointAttachVehicle _veh;
            };
            case "DISMOUNT": {
                private _vs = [];
                { if ((vehicle _x) isNotEqualTo _x) then { _vs pushBackUnique (vehicle _x); }; } forEach _units;
                if (_vs isEqualTo []) exitWith { _fail = "le groupe est déjà à pied"; };
                { _g leaveVehicle _x; } forEach _vs;
                { unassignVehicle _x; } forEach _units;
                _units orderGetIn false;
            };
            case "CANCEL": {};
        };
        if (_wp isNotEqualTo []) then { _g setCurrentWaypoint _wp; };
        _g setVariable ["COMSPEC_ATAK_AiOrdId", _id];
        if (_fail isNotEqualTo "") exitWith { ["FAIL", _fail] call _rep; };
        if (_k isEqualTo "CANCEL") exitWith { ["CANCEL", "ordre annulé"] call _rep; };
        ["EXEC", ["en cours", "en patrouille"] select (_k isEqualTo "PATROL")] call _rep;
        // Suivi toutes les 3 s sur la machine du groupe (un script par ordre en cours, rien par image).
        [_g, _id, _k, _pos, _by, _wp, _veh, _rep, _nid, _sd] spawn {
            params ["_g", "_id", "_k", "_pos", "_by", "_wp", "_veh", "_rep", "_nid", "_sd"];
            private _t0 = time;
            private _last = getPosATL leader _g;
            private _stillAt = time;
            private _near = false;
            private _followAt = [];
            while { true } do {
                sleep 3;
                // Ordre remplacé, groupe disparu ou passé sur une autre machine : ce suivi s'arrête.
                if (isNull _g || {!local _g} || {(_g getVariable ["COMSPEC_ATAK_AiOrdId", ""]) isNotEqualTo _id}) exitWith {};
                private _units = (units _g) select { alive _x };
                if (_units isEqualTo []) exitWith { ["FAIL", "groupe détruit"] call _rep; };
                private _ld = leader _g;
                private _d = if ((count _pos) >= 2) then { _ld distance2D _pos } else { 0 };
                private _passed = _wp isNotEqualTo [] && {(currentWaypoint _g) > (_wp select 1)};
                private _out = switch (_k) do {
                    case "MOVE";
                    case "WITHDRAW": { ["", "arrivé au point"] select (_passed || {_d < 25}) };
                    case "HOLD": { ["", "en position"] select (_d < 30) };
                    case "ATTACK": { ["", "zone nettoyée"] select _passed };
                    case "SUPPRESS": { ["", "tir de suppression fini"] select (time - _t0 > 30) };
                    case "BOARD": {
                        private _in = { (vehicle _x) isEqualTo _veh } count _units;
                        if (isNull _veh || {!alive _veh}) then { "!véhicule détruit" } else {
                            [["", format ["%1 / %2 à bord", _in, count _units]] select (time - _t0 > 120), "tout le groupe à bord"] select (_in isEqualTo (count _units))
                        }
                    };
                    case "DISMOUNT": { [["", "débarqué en partie"] select (time - _t0 > 60), "groupe à pied"] select (({ (vehicle _x) isNotEqualTo _x } count _units) isEqualTo 0) };
                    case "FOLLOW": {
                        if (isNull _by || {!alive _by}) then { "!donneur d'ordre perdu" } else {
                            // Point de ralliement remis à jour quand le donneur s'est éloigné.
                            if ((_ld distance2D _by) > 35 && {_followAt isEqualTo [] || {(_by distance2D _followAt) > 15}}) then {
                                _followAt = getPosATL _by;
                                _g move _followAt;
                            };
                            if ((_ld distance2D _by) < 35 && {!_near}) then { _near = true; ["EXEC", "avec vous"] call _rep; };
                            if ((_ld distance2D _by) > 80) then { _near = false; };
                            ""
                        }
                    };
                    default { "" };
                };
                if ((_out select [0, 1]) isEqualTo "!") exitWith { ["FAIL", _out select [1]] call _rep; };
                if (_out isNotEqualTo "") exitWith { ["DONE", _out] call _rep; };
                // Bloqué : moins de 5 m en 2 min alors qu'il doit se déplacer (hors combat : un groupe au contact s'arrête).
                if ((_ld distance2D _last) > 5) then { _last = getPosATL _ld; _stillAt = time; };
                if (_k in ["MOVE", "WITHDRAW", "HOLD", "BOARD"] && {time - _stillAt > 120} && {(behaviour _ld) isNotEqualTo "COMBAT"}) exitWith {
                    ["FAIL", "bloqué, point inaccessible"] call _rep;
                };
            };
        };
    };
    case "keep": {
        if (!isServer) exitWith {};
        _arg params [["_nid", ""], ["_pairs", []], ["_sd", ""]];
        if (_nid isEqualTo "") exitWith {};
        private _all = COMSPEC_ATAK_SrvAiOrd getOrDefault [_sd, createHashMap];
        private _e = _all getOrDefault [_nid, createHashMap];
        { if !((_x select 0) in ["ack", "ackUid"]) then { _e set [_x select 0, _x select 1]; }; } forEach _pairs;
        _all set [_nid, _e];
        // Groupes disparus : plus rien à rejouer.
        { if (isNull ([_x] call BIS_fnc_groupFromNetId)) then { _all deleteAt _x; }; } forEach (keys _all);
        COMSPEC_ATAK_SrvAiOrd set [_sd, _all];
    };
    case "recv": {
        _arg params [["_nid", ""], ["_pairs", []], ["_sd", ""]];
        if (!hasInterface || {isNull player} || {_sd isNotEqualTo str side group player}) exitWith {};
        private _replay = missionNamespace getVariable ["COMSPEC_ATAK_Replaying", false];
        private _tell = {
            params ["_t", ["_type", "INFO"]];
            if (_replay) exitWith {};
            if (!isNull (uiNamespace getVariable ["COMSPEC_ATAK_TocLapDisp", displayNull])) then { ["status", _t] call comspec_atak_native_fnc_tocLaptop; };
            [_type, _t, 6, 50, "MAP"] call comspec_atak_native_fnc_notify;
        };
        // Demande refusée par le serveur : seulement le motif, au donneur.
        if (_nid isEqualTo "") exitWith { [(createHashMapFromArray _pairs) getOrDefault ["text", ""], "WARNING"] call _tell; };
        private _all = missionNamespace getVariable ["COMSPEC_ATAK_AiOrds", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_AiOrds", _all];
        private _e = _all getOrDefault [_nid, createHashMap];
        private _was = [_e getOrDefault ["id", ""], _e getOrDefault ["state", ""]];
        private _in = createHashMapFromArray _pairs;
        { if !(_x in ["ack", "ackUid"]) then { _e set [_x, _y]; }; } forEach _in;
        if (isNull (_e getOrDefault ["g", grpNull])) then { _e set ["g", [_nid] call BIS_fnc_groupFromNetId]; };
        private _g = _e get "g";
        private _gn = _e getOrDefault ["grp", groupId _g];
        private _st = _e getOrDefault ["state", ""];
        if (_st in ["DONE", "FAIL", "CANCEL"] && {(_was select 1) isNotEqualTo _st}) then { _e set ["end", serverTime]; };
        // Texte de la carte préparé ici (traduit une fois, pas à chaque image).
        private _stLbl = createHashMapFromArray [["NEW", "transmis"], ["EXEC", "en cours"], ["DONE", "terminé"], ["FAIL", "impossible"], ["CANCEL", "annulé"]];
        _e set ["lbl", format ["%1 · %2 · %3", _gn, ([_e getOrDefault ["kind", "MOVE"]] call _kLabel) call comspec_atak_native_fnc_tr, (_stLbl getOrDefault [_st, _st]) call comspec_atak_native_fnc_tr]];
        _all set [_nid, _e];
        if ((_in getOrDefault ["ackUid", ""]) isEqualTo getPlayerUID player) then {
            [format ["%1 : %2 appliquée", _gn, _in getOrDefault ["ack", ""]]] call _tell;
        };
        // Donneur d'ordre : en cours, terminé ou impossible (une fois par changement d'état).
        if ((_e getOrDefault ["uid", ""]) isEqualTo getPlayerUID player && {[_e getOrDefault ["id", ""], _st] isNotEqualTo _was} && {_st isNotEqualTo "NEW"}) then {
            private _kl = [_e getOrDefault ["kind", ""]] call _kLabel;
            private _tx = _e getOrDefault ["text", ""];
            switch (_st) do {
                case "EXEC": { [format ["%1 · %2 : %3", _gn, _kl, _tx]] call _tell; };
                case "DONE": { [format ["%1 · %2 terminé : %3", _gn, _kl, _tx], "SUCCESS"] call _tell; };
                case "FAIL": { [format ["%1 · %2 impossible : %3", _gn, _kl, _tx], "WARNING"] call _tell; };
                default { [format ["%1 : ordre annulé", _gn]] call _tell; };
            };
        };
    };
};
