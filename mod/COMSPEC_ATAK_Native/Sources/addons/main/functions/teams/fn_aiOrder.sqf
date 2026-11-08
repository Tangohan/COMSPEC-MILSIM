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
      "why"    [groupe ou grpNull]      -> "" si le joueur peut ordonner à ce groupe, sinon le motif à lui afficher
      "netId"  groupe                   -> identifiant réseau du groupe ("" si aucun), aussi en solo
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
// Suivi d'un ordre de bout en bout dans le RPT (réglage CBA « Niveau de journal » d'Overwatch sur Détaillé), sinon muet.
private _dbg = { if ((missionNamespace getVariable ["comspec_overwatch_log_level", 3]) >= 4) then { ["DEBUG", "AIORDER", _this] call comspec_atak_native_fnc_log; }; };
// Identifiants réseau : BIS_fnc_netId prend le groupe nu et BIS_fnc_groupFromNetId la chaîne nue. Les appeler avec
// [groupe] / [chaîne] échouait sans bruit (contrôle de type de BIS) : le serveur ne retrouvait jamais le groupe.
private _gid = { if (_this isEqualType grpNull && {!isNull _this}) then { _this call BIS_fnc_netId } else { "" } };
private _gOf = { if (_this isEqualType "" && {_this isNotEqualTo ""}) then { _this call BIS_fnc_groupFromNetId } else { grpNull } };
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
    case "netId": { _arg call _gid };
    case "why": {
        private _g = _arg;
        if !(_g isEqualType grpNull) then { _g = grpNull; };
        if (!hasInterface || {isNull player} || {!alive player}) exitWith { "Aucun joueur" };
        // Serveur sans le mod, ou d'une version d'avant les ordres aux IA : la demande partirait dans le vide.
        if (!isServer && {(missionNamespace getVariable ["COMSPEC_ATAK_SrvAiOrdV", 0]) < 1}) exitWith {
            ["Le serveur n'a pas le mod ATAK natif : ordres aux IA impossibles",
             "Le serveur a une version plus ancienne du mod ATAK : ordres aux IA impossibles tant qu'il n'est pas mis à jour"] select (missionNamespace getVariable ["COMSPEC_ATAK_PlanSrvOn", false])
        };
        if !(["can", player] call comspec_atak_native_fnc_aiOrder) exitWith { "Seul un chef de groupe, un commandant, le poste TOC ou Zeus peut donner un ordre aux IA" };
        if (isNull _g) exitWith { "Aucun groupe IA de votre camp ne peut recevoir d'ordre" };
        if (({alive _x} count (units _g)) isEqualTo 0) exitWith { "Ce groupe IA n'existe plus" };
        if ((str side _g) isNotEqualTo (str side group player)) exitWith { "Ce groupe n'est pas de votre camp" };
        if (({isPlayer _x} count (units _g)) > 0) exitWith { "Ce groupe compte un joueur : envoyez-lui un ordre ou un message" };
        if (_g getVariable ["COMSPEC_ATAK_NoOrders", false]) exitWith { "Ce groupe est protégé par la mission : il ne prend pas d'ordre" };
        if (unitIsUAV (vehicle leader _g)) exitWith { "Un drone se commande depuis l'app Drone" };
        ""
    };
    // Réponse d'un groupe IA : toast d'Arma (CfgNotifications, config.cpp), rien dans le centre de notifications du téléphone.
    case "toast": {
        _arg params [["_t", ""], ["_type", "INFO"]];
        if (!hasInterface || {_t isEqualTo ""}) exitWith {};
        [["COMSPEC_ATAK_AiReply", "COMSPEC_ATAK_AiReplyWarn"] select (_type in ["WARNING", "ERROR"]), [((_t) call comspec_atak_native_fnc_tr) regexReplace ["[<>&]", " "]]] call BIS_fnc_showNotification;
    };
    case "send": {
        _arg params [["_g", grpNull], ["_k", ""], ["_pos", []], ["_toc", false]];
        if !(_g isEqualType grpNull) then { _g = grpNull; };
        // PC du poste ouvert : ligne d'état du PC ; sinon toast d'Arma (jamais les notifications du téléphone).
        private _say = {
            params ["_t", ["_type", "WARNING"]];
            if (!isNull (uiNamespace getVariable ["COMSPEC_ATAK_TocLapDisp", displayNull])) then { ["status", _t] call comspec_atak_native_fnc_tocLaptop; } else { ["toast", [_t, _type]] call comspec_atak_native_fnc_aiOrder; };
            false
        };
        private _why = ["why", _g] call comspec_atak_native_fnc_aiOrder;
        if (_why isNotEqualTo "") exitWith { format ["refus local : %1 (%2 · %3)", _why, groupId _g, _k] call _dbg; [_why] call _say };
        private _ki = _kinds findIf { (_x select 0) isEqualTo _k };
        if (_ki < 0) exitWith { format ["ordre inconnu %1", _k] call _dbg; false };
        if (((_kinds select _ki) select 2) && {(count _pos) < 2}) exitWith { ["Indiquez d'abord le point de l'ordre : clic droit sur la carte, ou grille de l'objectif"] call _say };
        private _issuer = if (_toc) then { format ["TOC · %1", ["callsign"] call comspec_atak_native_fnc_tocPost] } else {
            ([[player, true] call comspec_atak_native_fnc_unitCallsign, name player] select { _x isNotEqualTo "" }) param [0, name player]
        };
        if ((count _pos) >= 2) then { _pos = [_pos select 0, _pos select 1]; } else { _pos = []; };
        private _nid = _g call _gid;
        private _id = format ["AI-%1-%2", getPlayerUID player, floor (serverTime * 10)];
        private _ev = [player, _nid, _k, _pos, _id, _issuer, _toc];
        format ["%1 demandé : %2 → %3 (%4), point %5, depuis %6", _id, _k, groupId _g, _nid, _pos, ["le téléphone", "le PC du poste"] select _toc] call _dbg;
        // PC du poste : réseau filaire, envoi direct ; téléphone : soumis au signal comme les autres ordres (fn_netSend).
        private _how = "SENT";
        if (_toc) then { ["comspec_atak_native_aiOrdSrv", _ev] call CBA_fnc_serverEvent; } else {
            _how = [{ ["comspec_atak_native_aiOrdSrv", _this] call CBA_fnc_serverEvent; }, _ev, "Ordre IA", 1] call comspec_atak_native_fnc_netSend;
        };
        format ["%1 : envoi %2", _id, _how] call _dbg;
        // Sans réseau, fn_netSend a déjà prévenu (« Pas de réseau : Ordre IA en attente ») : l'ordre part au retour du signal.
        if (_how isEqualTo "QUEUED") exitWith { true };
        // Attente de la réponse du serveur : sans nouvelle au bout de 30 s (envoi perdu, serveur muet), le joueur le sait.
        private _pend = missionNamespace getVariable ["COMSPEC_ATAK_AiOrdPend", createHashMap];
        _pend set [_id, [groupId _g, [_k] call _kLabel]];
        missionNamespace setVariable ["COMSPEC_ATAK_AiOrdPend", _pend];
        [{
            params ["_id"];
            private _pend = missionNamespace getVariable ["COMSPEC_ATAK_AiOrdPend", createHashMap];
            if !(_id in _pend) exitWith {};
            (_pend deleteAt _id) params ["_gn", "_kl"];
            // Remis en file par fn_netSend (3 pertes de suite) : il repartira, pas d'alerte en double.
            if (((missionNamespace getVariable ["COMSPEC_ATAK_NetQueue", []]) findIf { ((_x param [1, []]) param [4, ""]) isEqualTo _id }) >= 0) exitWith {};
            if ((missionNamespace getVariable ["comspec_overwatch_log_level", 3]) >= 4) then { ["DEBUG", "AIORDER", format ["%1 : aucune réponse du serveur après 30 s", _id]] call comspec_atak_native_fnc_log; };
            private _t = format ["%1 · %2 : pas de réponse du serveur. Réessayez ; si ça recommence, le serveur n'a sans doute pas la même version du mod ATAK", _gn, _kl];
            if (!isNull (uiNamespace getVariable ["COMSPEC_ATAK_TocLapDisp", displayNull])) then { ["status", _t] call comspec_atak_native_fnc_tocLaptop; };
            ["toast", [_t, "WARNING"]] call comspec_atak_native_fnc_aiOrder;
        }, [_id], 30] call comspec_atak_native_fnc_waitAndExec;
        [format ["%1 · %2 : ordre envoyé", groupId _g, [_k] call _kLabel], "INFO"] call _say;
        true
    };
    case "srv": {
        if (!isServer) exitWith {};
        _arg params [["_u", objNull], ["_nid", ""], ["_k", ""], ["_pos", []], ["_id", ""], ["_by", ""], ["_toc", false]];
        if !(_u isEqualType objNull) exitWith {};
        private _g = _nid call _gOf;
        private _sd = str side group _u;
        private _no = {
            params ["_t"];
            format ["%1 refusé par le serveur : %2", _id, _t] call _dbg;
            if (!isNull _u) then { ["comspec_atak_native_aiOrdState", ["", [["text", _t], ["id", _id]], _sd], _u] call CBA_fnc_targetEvent; };
        };
        format ["%1 reçu par le serveur : %2 → %3 (%4) de %5", _id, _k, [groupId _g, "groupe introuvable"] select (isNull _g), _nid, [name _u, "?"] select (isNull _u)] call _dbg;
        if (isNull _u || {!alive _u} || {!isPlayer _u}) exitWith { format ["%1 ignoré : donneur absent ou mort", _id] call _dbg; };
        if (isNull _g || {({alive _x} count (units _g)) isEqualTo 0}) exitWith { ["Ce groupe IA n'existe plus"] call _no; };
        if ((str side _g) isNotEqualTo _sd) exitWith { ["Ce groupe n'est pas de votre camp"] call _no; };
        if (({isPlayer _x} count (units _g)) > 0) exitWith { ["Ce groupe compte un joueur : envoyez-lui un ordre ou un message"] call _no; };
        if (_g getVariable ["COMSPEC_ATAK_NoOrders", false]) exitWith { ["Ce groupe est protégé par la mission : il ne prend pas d'ordre"] call _no; };
        if !(["can", _u] call comspec_atak_native_fnc_aiOrder) exitWith { ["Seul un chef de groupe, un commandant, le poste TOC ou Zeus peut donner un ordre aux IA"] call _no; };
        if ((_kinds findIf { (_x select 0) isEqualTo _k }) < 0) exitWith { ["Ordre inconnu du serveur : versions du mod différentes"] call _no; };
        if !(_pos isEqualTypeArray [0, 0]) then { _pos = []; };
        _by = (_by regexReplace ["[<>&]", ""]) select [0, 40];
        if !(_k in _post) then {
            ["report", [_nid, _sd, [["id", _id], ["kind", _k], ["state", "NEW"], ["text", "transmis"], ["pos", _pos], ["by", _by], ["uid", getPlayerUID _u], ["grp", groupId _g], ["at", serverTime]]]] call comspec_atak_native_fnc_aiOrder;
        };
        format ["%1 accepté, exécuté %2", _id, ["sur le serveur", format ["sur la machine %1 (client headless ou joueur)", groupOwner _g]] select !(local _g)] call _dbg;
        ["comspec_atak_native_aiOrdExec", [_nid, _k, _pos, _id, _u], leader _g] call CBA_fnc_targetEvent;
    };
    case "report": {
        _arg params ["_nid", "_sd", "_pairs"];
        ["comspec_atak_native_sideEvent", ["comspec_atak_native_aiOrdState", [_nid, _pairs, _sd], _sd]] call CBA_fnc_serverEvent;
    };
    case "exec": {
        _arg params [["_nid", ""], ["_k", ""], ["_pos", []], ["_id", ""], ["_by", objNull]];
        private _g = _nid call _gOf;
        // Groupe disparu, ou passé sur une autre machine entre la demande et l'exécution : le donneur le sait.
        if (isNull _g || {!local _g}) exitWith {
            format ["%1 non exécuté ici : %2", _id, ["groupe passé sur une autre machine", "groupe introuvable"] select (isNull _g)] call _dbg;
            if (!isNull _by) then {
                ["comspec_atak_native_aiOrdState", ["", [["text", ["Le groupe vient de changer de machine : renvoyez l'ordre", "Ce groupe IA n'existe plus"] select (isNull _g)], ["id", _id]], str side group _by], _by] call CBA_fnc_targetEvent;
            };
        };
        private _sd = str side _g;
        private _rep = { params ["_st", "_txt"]; format ["%1 %2 : %3", _id, _st, _txt] call _dbg; ["report", [_nid, _sd, [["id", _id], ["state", _st], ["text", _txt]]]] call comspec_atak_native_fnc_aiOrder; };
        private _ld = leader _g;
        private _units = (units _g) select { alive _x };
        format ["%1 exécuté par %2 (%3 IA) : %4", _id, groupId _g, count _units, _k] call _dbg;
        if (_k in _post) exitWith {
            private _f = _post get _k;
            switch (_f) do {
                case "beh": { _g setBehaviour _k; };
                case "spd": { _g setSpeedMode _k; };
                default { _g setFormation _k; };
            };
            ["report", [_nid, _sd, [[_f, _k], ["ack", [_k] call _kLabel], ["ackUid", getPlayerUID _by], ["pid", _id]]]] call comspec_atak_native_fnc_aiOrder;
        };
        // Groupe figé par la mission ou par Eden : simulation dynamique (gelé loin des joueurs) et IA sans déplacement
        // (« PATH » / « MOVE » coupés, garnisons). Un ordre de déplacement les libère, sinon le groupe restait sur place.
        if (_k in ["MOVE", "PATROL", "ATTACK", "WITHDRAW", "HOLD", "FOLLOW", "BOARD"]) then {
            if (dynamicSimulationEnabled _g) then {
                if (isServer) then { _g enableDynamicSimulation false; } else { [_g, false] remoteExecCall ["enableDynamicSimulation", 2]; };
                format ["%1 : simulation dynamique coupée pour le groupe", _id] call _dbg;
            };
            { if (!(_x checkAIFeature "PATH") || {!(_x checkAIFeature "MOVE")}) then { _x enableAI "PATH"; _x enableAI "MOVE"; }; } forEach _units;
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
        { if !((_x select 0) in ["ack", "ackUid", "pid"]) then { _e set [_x select 0, _x select 1]; }; } forEach _pairs;
        _all set [_nid, _e];
        // Groupes disparus : plus rien à rejouer.
        { if (isNull (_x call _gOf)) then { _all deleteAt _x; }; } forEach (keys _all);
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
            ["toast", [_t, _type]] call comspec_atak_native_fnc_aiOrder;
        };
        private _in = createHashMapFromArray _pairs;
        // Réponse du serveur reçue : plus d'alerte « pas de réponse » pour cet ordre (fn_aiOrder "send").
        private _pend = missionNamespace getVariable ["COMSPEC_ATAK_AiOrdPend", createHashMap];
        private _pid = _in getOrDefault ["pid", _in getOrDefault ["id", ""]];
        if (_pid in _pend) then { _pend deleteAt _pid; format ["%1 : réponse reçue (%2)", _pid, _in getOrDefault ["state", _in getOrDefault ["text", "posture"]]] call _dbg; };
        // Demande refusée par le serveur : seulement le motif, au donneur.
        if (_nid isEqualTo "") exitWith { [_in getOrDefault ["text", ""], "WARNING"] call _tell; };
        private _all = missionNamespace getVariable ["COMSPEC_ATAK_AiOrds", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_AiOrds", _all];
        private _e = _all getOrDefault [_nid, createHashMap];
        private _was = [_e getOrDefault ["id", ""], _e getOrDefault ["state", ""]];
        { if !(_x in ["ack", "ackUid", "pid"]) then { _e set [_x, _y]; }; } forEach _in;
        if (isNull (_e getOrDefault ["g", grpNull])) then { _e set ["g", _nid call _gOf]; };
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
