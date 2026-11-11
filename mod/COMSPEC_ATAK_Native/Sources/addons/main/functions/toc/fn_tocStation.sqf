/*
    Postes nommés de la salle TOC (feuille de route TOC, lot 2, n° 13) : chaque PC de TOC (fn_tocPost) est un poste avec
    un rôle (chef de quart, opérations, renseignement, feux, logistique, transmissions) ; le serveur sait qui le tient.
    Tenir le poste « chef de quart » d'une salle donne le rôle « chef de poste » dans cette salle (fn_tocAccess).
    Sans Athena ni téléphone. L'état qui fait foi vit dans localNamespace du serveur ; missionNamespace
    COMSPEC_ATAK_TocStations en est la copie publique, pour l'affichage :
      [[netId du PC, salle, camp, rôle du poste, nom du poste, nom du tenant, UID du tenant, heure ZULU de prise,
        serverTime de prise, UID de celui à qui le poste est gardé, jusqu'à (serverTime)]...]
    Variable publique du PC : COMSPEC_ATAK_TocStation = [rôle du poste, nom, imposé] (posée par le serveur ; « imposé » :
    le poste garde ce rôle, sinon celui qui s'assoit choisit).
    Modes :
      ["kinds"]                               table des rôles de poste (ci-dessous)
      ["list", camp]                          postes tenus ou réglés du camp ("" : tous)
      ["of", unité]                           poste tenu par ce joueur, ou []
      ["holder", PC]                          poste de ce PC, ou []
      ["chiefOf", [unité, salle]]             vrai si ce joueur tient le poste « chef de quart » de la salle
      ["chiefOk", unité]                      vrai si ce joueur a le grade demandé pour être chef de quart
      ["text", camp]                          tableau des postes en texte (export)
    Demandes (client -> serveur, l'émetteur fait foi) :
      ["set", [PC, rôle du poste, nom, imposé]]   droit « salles »
      ["take", [PC, rôle du poste]]               droit « tenir un poste », à moins de 6 m du PC
      ["leave"]                                   quitte son poste
      ["kick", PC]                                libère le poste d'un autre (chef de poste, administrateur Zeus)
    Serveur : ["init"], ["assign", [PC, unité, rôle]] (relève signée), ["lost", [UID, nom]] (déconnexion : poste gardé
      5 minutes à son tenant), ["dump"] / ["load", données] (fn_tocPersist : rôle et nom de chaque PC, pas le tenant)
    Client : ["menu"] actions ACE sur soi « Poste TOC » (prendre, quitter, relève, verrou, journal, diagnostic)
*/
params [["_mode", "list"], ["_a", []]];
private _acc = comspec_atak_native_fnc_tocAccess;
// ---- TABLE _kinds (JSON) : [rôle du poste, libellé, donne le rôle « chef de poste », affichage conseillé de la console].
private _kinds = [
["CDQ", "Chef de quart", true, "SALLE"], ["OPS", "Opérations", false, "SITUATION"], ["RENS", "Renseignement", false, "SITUATION"],
["FEUX", "Feux", false, "CONDUITE"], ["LOG", "Logistique", false, "CONDUITE"], ["TRANS", "Transmissions", false, "SITUATION"]
];
// ---- FIN
// ---- TABLE _ranks (JSON) : grades du jeu, du plus bas au plus haut (réglage « Grade minimal du chef de quart »).
private _ranks = ["PRIVATE", "CORPORAL", "SERGEANT", "LIEUTENANT", "CAPTAIN", "MAJOR", "COLONEL"];
// ---- FIN
private _inside = !isRemoteExecuted || {!isNil "_tocTrusted"};
private _all = {
    private _l = if (isServer) then { localNamespace getVariable ["COMSPEC_ATAK_TocStationsSrv", []] } else { missionNamespace getVariable ["COMSPEC_ATAK_TocStations", []] };
    _l select { _x isEqualType [] && {(count _x) >= 11} }
};
private _publish = {
    params ["_l"];
    localNamespace setVariable ["COMSPEC_ATAK_TocStationsSrv", _l];
    missionNamespace setVariable ["COMSPEC_ATAK_TocStations", +_l, true];
};
private _kindOf = { params ["_k"]; (_kinds select { (_x select 0) isEqualTo _k }) param [0, []] };
private _online = { params ["_uid"]; _uid isNotEqualTo "" && {(allPlayers findIf { (getPlayerUID _x) isEqualTo _uid }) >= 0} };
private _sideOfPc = {
    params ["_pc"];
    private _sd = toUpper trim (_pc getVariable ["COMSPEC_ATAK_TocSide", ""]);
    if (_sd isEqualTo "") then { _sd = (["get", ["of", _pc] call comspec_atak_native_fnc_tocHall] call comspec_atak_native_fnc_tocHall) param [2, ""]; };
    _sd
};
private _chiefOk = {
    params ["_u"];
    if (isNull _u) exitWith { false };
    if ((["level", [_u, ""]] call _acc) >= 2) exitWith { true };
    private _min = (round (missionNamespace getVariable ["comspec_atak_native_toc_chief_rank", 0])) max 0 min 6;
    (_ranks find (toUpper rank _u)) >= _min
};
// Poste rendu libre : tenant retiré, enregistrement gardé seulement si le PC a un rôle réglé.
private _free = {
    params ["_rec", ["_keepFor", ""]];
    _rec set [5, ""]; _rec set [6, ""]; _rec set [7, ""]; _rec set [8, 0];
    _rec set [9, _keepFor]; _rec set [10, [0, serverTime + 300] select (_keepFor isNotEqualTo "")];
    _rec
};
private _event = {
    params ["_rec", "_what"];
    ["comspec_atak_toc_stationChanged", [_rec select 0, _rec select 1, _rec select 2, _rec select 3, _rec select 5, _what]] call CBA_fnc_globalEvent;
};
switch (_mode) do {
    case "kinds": { +_kinds };
    case "list": { (call _all) select { _a isEqualTo "" || {(_x select 2) in ["", _a]} } };
    case "of": {
        private _uid = getPlayerUID _a;
        ((call _all) select { _uid isNotEqualTo "" && {(_x select 6) isEqualTo _uid} }) param [0, []]
    };
    case "holder": { private _id = netId _a; ((call _all) select { (_x select 0) isEqualTo _id }) param [0, []] };
    case "chiefOf": {
        _a params [["_u", objNull], ["_hall", ""]];
        private _uid = getPlayerUID _u;
        if (_uid isEqualTo "") exitWith { false };
        // Poste « chef de quart » de cette salle ; pour un objet sans salle, celui de n'importe quelle salle du camp.
        ((call _all) findIf { (_x select 6) isEqualTo _uid && {((([_x select 3] call _kindOf) param [2, false]))} && {_hall isEqualTo "" || {(_x select 1) isEqualTo _hall}} }) >= 0
    };
    case "chiefOk": { [_a] call _chiefOk };
    case "text": {
        private _T = { _this call comspec_atak_native_fnc_tr };
        private _rows = ((call _all) select { _a isEqualTo "" || {(_x select 2) in ["", _a]} }) apply {
            format ["%1  %2  %3  %4", [_x select 4, ([_x select 3] call _kindOf) param [1, _x select 3]] select ((_x select 4) isEqualTo ""),
                (([_x select 3] call _kindOf) param [1, _x select 3]) call _T,
                [_x select 5, "libre" call _T] select ((_x select 5) isEqualTo ""),
                ["", (format [("depuis %1Z" call _T), _x select 7])] select ((_x select 7) isNotEqualTo "")]
        };
        ([("POSTES DU TOC" call _T)] + _rows) joinString endl
    };
    case "init": {
        if (!isServer || {missionNamespace getVariable ["COMSPEC_ATAK_TocStationInit", false]}) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_TocStationInit", true];
        [call _all] call _publish;
        addMissionEventHandler ["PlayerDisconnected", { params ["", "_uid", "_name"]; ["lost", [_uid, _name]] call comspec_atak_native_fnc_tocStation; }];
    };
    case "set": {
        _a params [["_pc", objNull], ["_kind", ""], ["_name", ""], ["_fixed", true], ["_claimed", objNull]];
        if (!isServer) exitWith { ["set", [_pc, _kind, _name, _fixed, player]] remoteExecCall ["comspec_atak_native_fnc_tocStation", 2]; true };
        if !(_pc isEqualType objNull && {!isNull _pc} && {_kind isEqualType ""} && {_name isEqualType ""} && {_fixed isEqualType true}) exitWith { false };
        _kind = toUpper _kind;
        if (_kind isNotEqualTo "" && {([_kind] call _kindOf) isEqualTo []}) exitWith { false };
        private _hall = ["of", _pc] call comspec_atak_native_fnc_tocHall;
        _name = (trim _name) select [0, 32];
        if !(["check", [_claimed, "rooms", _hall, "station.set", format ["%1 %2", _kind, _name], [_pc] call _sideOfPc]] call _acc) exitWith { false };
        _pc setVariable ["COMSPEC_ATAK_TocStation", [_kind, _name, _fixed && {_kind isNotEqualTo ""}], true];
        private _l = +(call _all);
        private _i = _l findIf { (_x select 0) isEqualTo (netId _pc) };
        if (_i < 0) then { _l pushBack [netId _pc, _hall, [_pc] call _sideOfPc, _kind, _name, "", "", "", 0, "", 0]; } else {
            (_l select _i) set [1, _hall]; (_l select _i) set [4, _name];
            if (_kind isNotEqualTo "") then { (_l select _i) set [3, _kind]; };
        };
        [_l] call _publish;
        ["save"] call comspec_atak_native_fnc_tocPersist;
        true
    };
    case "take": {
        _a params [["_pc", objNull], ["_kind", ""], ["_claimed", objNull]];
        if (!isServer) exitWith { ["take", [_pc, _kind, player]] remoteExecCall ["comspec_atak_native_fnc_tocStation", 2]; true };
        if !(_pc isEqualType objNull && {!isNull _pc} && {_kind isEqualType ""}) exitWith { false };
        (["actor", _claimed] call _acc) params ["_u", "_tr"];
        if (isNull _u) exitWith { false };
        private _hall = ["of", _pc] call comspec_atak_native_fnc_tocHall;
        (_pc getVariable ["COMSPEC_ATAK_TocStation", []]) params [["_fk", ""], ["_fn2", ""], ["_fixed", false]];
        _kind = toUpper _kind;
        if (_fixed || {_kind isEqualTo ""}) then { _kind = _fk; };
        if (([_kind] call _kindOf) isEqualTo []) then { _kind = "OPS"; };
        private _uid = getPlayerUID _u;
        private _l = +(call _all);
        private _i = _l findIf { (_x select 0) isEqualTo (netId _pc) };
        private _rec = if (_i >= 0) then { _l select _i } else { [netId _pc, _hall, [_pc] call _sideOfPc, _kind, _fn2, "", "", "", 0, "", 0] };
        private _lvl = ["level", [_u, _hall, _tr, _rec select 2]] call _acc;
        private _chief = ([_kind] call _kindOf) param [2, false];
        // Raison d'un refus, dite au joueur dans sa console et écrite au journal.
        private _no = switch (true) do {
            case ((_u distance _pc) > 6): { "Refusé : approchez-vous du PC du poste" };
            case (_chief && {!([_u] call _chiefOk)}): { "Refusé : votre grade ne permet pas de tenir le poste de chef de quart" };
            case ((_rec select 6) isNotEqualTo "" && {(_rec select 6) isNotEqualTo _uid} && {[_rec select 6] call _online} && {_lvl < 2}): { "Refusé : ce poste est tenu, demandez la relève" };
            case ((_rec select 9) isNotEqualTo "" && {(_rec select 9) isNotEqualTo _uid} && {serverTime < (_rec select 10)} && {_lvl < 2}): { "Refusé : ce poste est gardé à son opérateur pendant 5 minutes" };
            case (_chief && {(_l findIf { (_x select 1) isEqualTo _hall && {(_x select 0) isNotEqualTo (netId _pc)} && {(([_x select 3] call _kindOf) param [2, false])} && {[_x select 6] call _online} && {(_x select 6) isNotEqualTo _uid} }) >= 0} && {_lvl < 3}): { "Refusé : cette salle a déjà un chef de quart, demandez la relève" };
            default { "" };
        };
        if (_no isNotEqualTo "") exitWith {
            private _tocTrusted = true;
            ["add", [_rec select 2, _hall, _u, "station.take", _kind, false]] call comspec_atak_native_fnc_tocJournal;
            ["feedback", [_u, false, _no, []]] call _acc;
            false
        };
        if !(["check", [_u, "station", _hall, "station.take", format ["%1 %2", _kind, _rec select 4], _rec select 2]] call _acc) exitWith { false };
        // Un joueur ne tient qu'un poste : l'ancien est libéré.
        { if ((_x select 6) isEqualTo _uid) then { [_x] call _free; }; } forEach _l;
        _rec set [1, _hall]; _rec set [3, _kind]; _rec set [5, (["nameOf", _u] call comspec_atak_native_fnc_tocAccess)]; _rec set [6, _uid];
        _rec set [7, ["zulu"] call comspec_atak_native_fnc_tocRoom]; _rec set [8, serverTime]; _rec set [9, ""]; _rec set [10, 0];
        if (_i < 0) then { _l pushBack _rec; };
        [_l] call _publish;
        ["feedback", [_u, true, "Poste pris : %1", [([_kind] call _kindOf) param [1, _kind]]]] call _acc;
        [_rec, "take"] call _event;
        true
    };
    case "leave";
    case "kick": {
        private _pc = if (_mode isEqualTo "kick") then { _a param [0, objNull] } else { objNull };
        private _claimed = if (_mode isEqualTo "kick") then { _a param [1, objNull] } else { _a param [0, objNull] };
        if (!isServer) exitWith { [_mode, [[player], [_pc, player]] select (_mode isEqualTo "kick")] remoteExecCall ["comspec_atak_native_fnc_tocStation", 2]; true };
        (["actor", _claimed] call _acc) params ["_u"];
        private _l = +(call _all);
        private _i = if (_mode isEqualTo "kick") then {
            if !(_pc isEqualType objNull) then { _pc = objNull; };
            _l findIf { (_x select 0) isEqualTo (netId _pc) && {(_x select 6) isNotEqualTo ""} }
        } else { private _uid = getPlayerUID _u; _l findIf { _uid isNotEqualTo "" && {(_x select 6) isEqualTo _uid} } };
        if (_i < 0) exitWith { false };
        private _rec = _l select _i;
        if (_mode isEqualTo "kick" && {!(["check", [_u, "lock", _rec select 1, "station.kick", format ["%1 %2", _rec select 3, _rec select 5], _rec select 2]] call _acc)}) exitWith { false };
        if (_mode isEqualTo "leave") then {
            private _tocTrusted = true;
            ["add", [_rec select 2, _rec select 1, _u, "station.leave", _rec select 3, true]] call comspec_atak_native_fnc_tocJournal;
        };
        [_rec] call _free;
        [_l] call _publish;
        ["feedback", [_u, true, "Poste libéré", []]] call _acc;
        [_rec, _mode] call _event;
        true
    };
    case "assign": {
        // Relève signée (fn_tocHandover) : le poste passe à l'entrant, sans autre contrôle (déjà fait par la relève).
        if (!isServer || {!_inside}) exitWith { false };
        _a params [["_pc", objNull], ["_u", objNull]];
        if (isNull _pc || {isNull _u}) exitWith { false };
        private _uid = getPlayerUID _u;
        private _l = +(call _all);
        { if ((_x select 6) isEqualTo _uid) then { [_x] call _free; }; } forEach _l;
        private _i = _l findIf { (_x select 0) isEqualTo (netId _pc) };
        if (_i < 0) exitWith { false };
        private _rec = _l select _i;
        _rec set [5, (["nameOf", _u] call comspec_atak_native_fnc_tocAccess)]; _rec set [6, _uid]; _rec set [7, ["zulu"] call comspec_atak_native_fnc_tocRoom]; _rec set [8, serverTime]; _rec set [9, ""]; _rec set [10, 0];
        [_l] call _publish;
        [_rec, "handover"] call _event;
        true
    };
    case "lost": {
        if (!isServer || {!_inside}) exitWith { false };
        _a params [["_uid", ""], ["_name", ""]];
        if (_uid isEqualTo "") exitWith { false };
        private _tocTrusted = true;
        // Verrous du joueur parti : levés (un verrou oublié ne bloque pas la salle).
        ["unlockBy", [_uid, "déconnexion"]] call _acc;
        private _l = +(call _all);
        private _mine = _l select { (_x select 6) isEqualTo _uid };
        if (_mine isEqualTo []) exitWith { false };
        {
            ["add", [_x select 2, _x select 1, _name, "station.lost", _x select 3, true]] call comspec_atak_native_fnc_tocJournal;
            [_x, _uid] call _free;
            [_x, "lost"] call _event;
        } forEach _mine;
        [_l] call _publish;
        true
    };
    case "dump": {
        if (!isServer || {!_inside}) exitWith { [] };
        // Rôle et nom de chaque PC réglé, retrouvé à la partie suivante par sa classe et sa place (un netId ne survit pas).
        private _out = [];
        {
            private _pc = objectFromNetId (_x select 0);
            private _cfg = _pc getVariable ["COMSPEC_ATAK_TocStation", []];
            if (!isNull _pc && {_cfg isNotEqualTo []}) then { _out pushBack [typeOf _pc, getPosASL _pc, _cfg]; };
        } forEach (call _all);
        _out
    };
    case "load": {
        if (!isServer || {!_inside} || {!(_a isEqualType [])}) exitWith { false };
        private _l = +(call _all);
        {
            _x params [["_cls", ""], ["_pos", [0, 0, 0]], ["_cfg", []]];
            if !(_cls isEqualType "" && {_pos isEqualType []} && {(count _pos) >= 3} && {_cfg isEqualType []} && {(count _cfg) >= 3}) then { continue };
            private _pc = ((nearestObjects [ASLToAGL _pos, [_cls], 1.5]) select { alive _x }) param [0, objNull];
            if (isNull _pc || {(_l findIf { (_x select 0) isEqualTo (netId _pc) }) >= 0}) then { continue };
            _cfg params [["_kind", ""], ["_name", ""], ["_fixed", false]];
            if !(_kind isEqualType "" && {_name isEqualType ""} && {_fixed isEqualType true}) then { continue };
            _pc setVariable ["COMSPEC_ATAK_TocStation", [_kind, _name select [0, 32], _fixed], true];
            _l pushBack [netId _pc, ["of", _pc] call comspec_atak_native_fnc_tocHall, [_pc] call _sideOfPc, _kind, _name select [0, 32], "", "", "", 0, "", 0];
        } forEach _a;
        [_l] call _publish;
        true
    };
    case "menu": {
        // Actions ACE sur soi « Poste TOC » : tout ce lot s'emploie sans console (les consoles y ajoutent leurs boutons).
        if (!hasInterface || {isNil "ace_interact_menu_fnc_createAction"} || {missionNamespace getVariable ["COMSPEC_ATAK_TocStationMenu", false]}) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_TocStationMenu", true];
        private _root = ["COMSPEC_ATAK_TocDesk", "Poste TOC", "", {}, {
            !isNull (["near"] call comspec_atak_native_fnc_tocPost) || {(["of", _player] call comspec_atak_native_fnc_tocStation) isNotEqualTo []} || {["can", [_player, "diag", ""]] call comspec_atak_native_fnc_tocAccess}
        }, {
            params ["_target", "_player"];
            private _out = [];
            private _pc = ["near"] call comspec_atak_native_fnc_tocPost;
            private _hall = if (isNull _pc) then { "" } else { ["of", _pc] call comspec_atak_native_fnc_tocHall };
            private _mine = ["of", _player] call comspec_atak_native_fnc_tocStation;
            private _add = {
                params ["_id", "_txt", "_code", ["_arg", []]];
                _out pushBack [["COMSPEC_ATAK_TocDesk_" + _id, _txt, "", _code, { true }, {}, _arg] call comspec_atak_native_fnc_aceAction, [], _target];
            };
            if (!isNull _pc) then {
                (_pc getVariable ["COMSPEC_ATAK_TocStation", []]) params [["_fk", ""], "", ["_fixed", false]];
                {
                    _x params ["_k", "_lbl"];
                    if ((!_fixed || {_k isEqualTo _fk}) && {(_mine param [0, ""]) isNotEqualTo (netId _pc) || {(_mine param [3, ""]) isNotEqualTo _k}}) then {
                        ["Take" + _k, format [("Prendre le poste : %1" call comspec_atak_native_fnc_tr), _lbl call comspec_atak_native_fnc_tr], { ["take", [(_this select 2) select 0, (_this select 2) select 1]] call comspec_atak_native_fnc_tocStation; }, [_pc, _k]] call _add;
                    };
                } forEach (["kinds"] call comspec_atak_native_fnc_tocStation);
                private _ho = ["pending", _pc] call comspec_atak_native_fnc_tocHandover;
                if (_ho isNotEqualTo [] && {(_ho param [7, ""]) isNotEqualTo ((["nameOf", _player] call comspec_atak_native_fnc_tocAccess))}) then {
                    ["Sign", format [("Signer la relève de %1" call comspec_atak_native_fnc_tr), _ho param [7, "?"]], { ["sign", [(_this select 2) select 0]] call comspec_atak_native_fnc_tocHandover; }, [_pc]] call _add;
                };
            };
            if (_mine isNotEqualTo []) then {
                ["Leave", "Quitter mon poste", { ["leave"] call comspec_atak_native_fnc_tocStation; }] call _add;
                ["Offer", "Proposer la relève de mon poste", { ["offer", [objNull, ""]] call comspec_atak_native_fnc_tocHandover; }] call _add;
                ["Relief", "Lire les relèves du camp", { ["open"] call comspec_atak_native_fnc_tocHandover; }] call _add;
            };
            if (_hall isNotEqualTo "" && {["can", [_player, "lock", _hall]] call comspec_atak_native_fnc_tocAccess}) then {
                private _lk = (["locked", _hall] call comspec_atak_native_fnc_tocAccess) select 0;
                ["Lock", ["Verrouiller la salle", "Déverrouiller la salle"] select _lk, { ["lock", [(_this select 2) select 0, (_this select 2) select 1, ""]] call comspec_atak_native_fnc_tocAccess; }, [_hall, !_lk]] call _add;
            };
            if (["can", [_player, "journal", ""]] call comspec_atak_native_fnc_tocAccess) then {
                ["Journal", "Journal des actions du TOC", { ["journal"] call comspec_atak_native_fnc_tocDiag; }] call _add;
            };
            if (["can", [_player, "diag", ""]] call comspec_atak_native_fnc_tocAccess) then {
                ["Diag", "Exporter le diagnostic du TOC", { ["run"] call comspec_atak_native_fnc_tocDiag; }] call _add;
            };
            _out
        }] call comspec_atak_native_fnc_aceAction;
        ["CAManBase", 1, ["ACE_SelfActions"], _root, true] call ace_interact_menu_fnc_addActionToClass;
    };
    default { false };
};
