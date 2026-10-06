/*
    Équipes de feu : modifications appliquées par le serveur, seul à écrire l'état partagé
    (deux téléphones qui modifient la même équipe au même instant ne s'écrasent pas).
    Params : [unité qui agit, opération, arguments]
      "create" [nom, couleur, icône, description]      crée une équipe dans le groupe de l'unité ; le créateur la rejoint en chef d'équipe
      "edit"   [id, nom, couleur, icône, description]  chef de groupe ou chef de cette équipe
      "delete" [id]                                    idem ; les membres repassent sans équipe
      "lock"   [id]                                    équipe fermée / ouverte aux arrivées
      "join"   [id]                                    rejoindre (refusé si fermée, sauf pour le chef de groupe)
      "leave"  []                                      quitter son équipe
      "assign" [netId de l'unité, id ou ""]            placer un membre du groupe dans une équipe (chef de groupe, ou chef de l'équipe visée)
      "role"   [netId de l'unité, clé de rôle]         soi-même, chef de groupe ou chef de son équipe
      "roles"  [[[clé, libellé, abrégé, clé d'icône, origine]...]]  rôles d'Athena et créés en jeu (fn_ftRoleSync)
      "roleNew" [libellé, clé d'icône]                 crée un rôle (clé C_<NOM>) et le donne à son auteur
      "restore" [id d'équipe, clé de rôle]             réapparition : l'unité retrouve son équipe et son rôle
    Rôle de repli (perte du rôle de chef d'équipe) : le rôle mémorisé du joueur (COMSPEC_FTRolePref), sinon fusilier.
    Rôles ajoutés : missionNamespace COMSPEC_ATAK_FtExtraRoles et COMSPEC_ATAK_FtRolesRev (publics, lus par fn_ftCatalog).
    Écrit : groupe COMSPEC_FireTeams et COMSPEC_FTRev (public), unités COMSPEC_FT et COMSPEC_FTRole (public).
    Les cinq couleurs d'Arma sont aussi appliquées à l'équipe du jeu (assignTeam).
*/
params [["_by", objNull], ["_op", ""], ["_args", []]];
if (!isServer || {isNull _by}) exitWith { false };
private _g = group _by;
if (isNull _g) exitWith { false };
private _cat = [] call comspec_atak_native_fnc_ftCatalog;
private _teams = +(_g getVariable ["COMSPEC_FireTeams", []]);
private _players = (units _g) select { isPlayer _x };
// Chef de groupe : le chef Arma, ou le premier joueur quand le chef est une IA.
private _isGL = (leader _g) isEqualTo _by || {(count units _g) isEqualTo 1} || {!isPlayer leader _g && {(_players param [0, objNull]) isEqualTo _by}};
private _idx = { params ["_tid"]; _teams findIf { (_x select 0) isEqualTo _tid } };
private _isTL = { params ["_tid"]; _tid isNotEqualTo "" && {(_by getVariable ["COMSPEC_FT", ""]) isEqualTo _tid} && {(_by getVariable ["COMSPEC_FTRole", ""]) isEqualTo "CDE"} };
private _say = { params ["_msg", ["_to", _by]]; ["comspec_atak_native_groupNotice", [_msg], _to] call CBA_fnc_targetEvent; };
private _clean = { params ["_t", "_max"]; if !(_t isEqualType "") then { _t = str _t; }; (trim _t) select [0, _max] };
private _validColor = { params ["_k"]; ((_cat get "colors") findIf { (_x select 0) isEqualTo _k }) >= 0 };
private _validIcon = { params ["_k"]; ((_cat get "icons") findIf { (_x select 0) isEqualTo _k }) >= 0 };
private _validIconRole = { params ["_k"]; _k isEqualType "" && {((_cat get "roleIcons") findIf { (_x select 0) isEqualTo _k }) >= 0} };
// Ajoute ou met à jour des rôles d'Athena / créés en jeu ([clé, libellé, abrégé, clé d'icône, origine, auteur]) ; vrai si la liste change.
private _addRoles = {
    params ["_list"];
    private _ex = +(missionNamespace getVariable ["COMSPEC_ATAK_FtExtraRoles", []]);
    private _before = str _ex;
    {
        private _k = _x select 0;
        private _i = _ex findIf { (_x select 0) isEqualTo _k };
        if (_i >= 0) then { _ex set [_i, _x]; } else { if ((count _ex) < 60) then { _ex pushBack _x; }; };
    } forEach _list;
    if ((str _ex) isEqualTo _before) exitWith { false };
    missionNamespace setVariable ["COMSPEC_ATAK_FtExtraRoles", _ex, true];
    missionNamespace setVariable ["COMSPEC_ATAK_FtRolesRev", (missionNamespace getVariable ["COMSPEC_ATAK_FtRolesRev", 0]) + 1, true];
    true
};
private _armaTeam = {
    params ["_tid"];
    private _i = [_tid] call _idx;
    if (_i < 0) exitWith { "MAIN" };
    private _ck = (_teams select _i) select 2;
    (((_cat get "colors") select { (_x select 0) isEqualTo _ck }) param [0, ["", "", "", [], "MAIN"]]) select 4
};
private _fallback = {
    params ["_u"];
    private _p = _u getVariable ["COMSPEC_FTRolePref", ""];
    [_p, "FUS"] select (_p isEqualTo "" || {_p isEqualTo "CDE"} || {((_cat get "roles") findIf { (_x select 0) isEqualTo _p }) < 0})
};
private _setTeam = {
    params ["_u", "_tid"];
    _u setVariable ["COMSPEC_FT", _tid, true];
    // Rôle de chef d'équipe : seulement dans une équipe.
    if (_tid isEqualTo "" && {(_u getVariable ["COMSPEC_FTRole", ""]) isEqualTo "CDE"}) then { _u setVariable ["COMSPEC_FTRole", [_u] call _fallback, true]; };
    [_u, [_tid] call _armaTeam] remoteExecCall ["assignTeam", 0];
};
private _setRole = {
    params ["_u", "_r"];
    private _tid = _u getVariable ["COMSPEC_FT", ""];
    if (_r isEqualTo "CDE") then {
        if (_tid isEqualTo "") exitWith { _r = [_u] call _fallback; };
        // Un seul chef par équipe : l'ancien reprend son rôle mémorisé (fusilier par défaut).
        { if (_x isNotEqualTo _u && {(_x getVariable ["COMSPEC_FT", ""]) isEqualTo _tid} && {(_x getVariable ["COMSPEC_FTRole", ""]) isEqualTo "CDE"}) then { _x setVariable ["COMSPEC_FTRole", [_x] call _fallback, true]; }; } forEach units _g;
    };
    _u setVariable ["COMSPEC_FTRole", _r, true];
};
private _hasLead = { params ["_tid"]; ((units _g) findIf { (_x getVariable ["COMSPEC_FT", ""]) isEqualTo _tid && {(_x getVariable ["COMSPEC_FTRole", ""]) isEqualTo "CDE"} }) >= 0 };
private _changed = false;
private _msg = "";

switch (_op) do {
    case "create": {
        _args params [["_name", ""], ["_ck", "RED"], ["_ik", "INF"], ["_desc", ""]];
        _name = [_name, 24] call _clean;
        if (_name isEqualTo "") exitWith { ["Donnez un nom à l'équipe."] call _say; };
        if ((count _teams) >= 8) exitWith { ["Huit équipes au plus par groupe."] call _say; };
        if ((_teams findIf { (toLower (_x select 1)) isEqualTo toLower _name }) >= 0) exitWith { [format ["L'équipe %1 existe déjà dans le groupe.", _name]] call _say; };
        if !([_ck] call _validColor) then { _ck = "RED"; };
        if !([_ik] call _validIcon) then { _ik = "INF"; };
        private _seq = (_g getVariable ["COMSPEC_FTSeq", 0]) + 1;
        _g setVariable ["COMSPEC_FTSeq", _seq];
        private _tid = format ["FT%1", _seq];
        _teams pushBack [_tid, _name, _ck, _ik, [_desc, 160] call _clean, false];
        _g setVariable ["COMSPEC_FireTeams", _teams, true];
        [_by, _tid] call _setTeam;
        [_by, "CDE"] call _setRole;
        _changed = true;
        _msg = format ["%1 crée l'équipe %2", name _by, _name];
    };
    case "edit": {
        _args params [["_tid", ""], ["_name", ""], ["_ck", ""], ["_ik", ""], ["_desc", ""]];
        private _i = [_tid] call _idx;
        if (_i < 0) exitWith {};
        if !(_isGL || {[_tid] call _isTL}) exitWith { ["Seuls le chef de groupe et le chef de cette équipe peuvent la modifier."] call _say; };
        private _t = _teams select _i;
        _name = [_name, 24] call _clean;
        if (_name isNotEqualTo "" && {(_teams findIf { (_x select 0) isNotEqualTo _tid && {(toLower (_x select 1)) isEqualTo toLower _name} }) < 0}) then { _t set [1, _name]; };
        if ([_ck] call _validColor) then { _t set [2, _ck]; };
        if ([_ik] call _validIcon) then { _t set [3, _ik]; };
        _t set [4, [_desc, 160] call _clean];
        _g setVariable ["COMSPEC_FireTeams", _teams, true];
        // Nouvelle couleur : équipe Arma des membres à jour.
        private _at = [_tid] call _armaTeam;
        { if ((_x getVariable ["COMSPEC_FT", ""]) isEqualTo _tid) then { [_x, _at] remoteExecCall ["assignTeam", 0]; }; } forEach units _g;
        _changed = true;
        _msg = format ["Équipe %1 modifiée par %2", _t select 1, name _by];
    };
    case "delete": {
        _args params [["_tid", ""]];
        private _i = [_tid] call _idx;
        if (_i < 0) exitWith {};
        if !(_isGL || {[_tid] call _isTL}) exitWith { ["Seuls le chef de groupe et le chef de cette équipe peuvent la dissoudre."] call _say; };
        private _name = (_teams select _i) select 1;
        _teams deleteAt _i;
        _g setVariable ["COMSPEC_FireTeams", _teams, true];
        { if ((_x getVariable ["COMSPEC_FT", ""]) isEqualTo _tid) then { [_x, ""] call _setTeam; }; } forEach units _g;
        _changed = true;
        _msg = format ["Équipe %1 dissoute par %2", _name, name _by];
    };
    case "lock": {
        _args params [["_tid", ""]];
        private _i = [_tid] call _idx;
        if (_i < 0 || {!(_isGL || {[_tid] call _isTL})}) exitWith {};
        private _t = _teams select _i;
        _t set [5, !(_t param [5, false])];
        _g setVariable ["COMSPEC_FireTeams", _teams, true];
        _changed = true;
        _msg = format ["Équipe %1 %2", _t select 1, ["ouverte", "fermée"] select (_t select 5)];
    };
    case "join": {
        _args params [["_tid", ""]];
        private _i = [_tid] call _idx;
        if (_i < 0) exitWith {};
        private _t = _teams select _i;
        if ((_t param [5, false]) && {!_isGL}) exitWith { [format ["L'équipe %1 est fermée : demandez à son chef de vous y placer.", _t select 1]] call _say; };
        if ((_by getVariable ["COMSPEC_FT", ""]) isEqualTo _tid) exitWith {};
        if ((_by getVariable ["COMSPEC_FTRole", ""]) isEqualTo "CDE") then { _by setVariable ["COMSPEC_FTRole", [_by] call _fallback, true]; };
        [_by, _tid] call _setTeam;
        // Équipe sans chef : le premier arrivé le devient.
        if !([_tid] call _hasLead) then { [_by, "CDE"] call _setRole; };
        _changed = true;
        _msg = format ["%1 rejoint l'équipe %2", name _by, _t select 1];
    };
    case "leave": {
        private _tid = _by getVariable ["COMSPEC_FT", ""];
        if (_tid isEqualTo "") exitWith {};
        private _i = [_tid] call _idx;
        [_by, ""] call _setTeam;
        _changed = true;
        if (_i >= 0) then { _msg = format ["%1 quitte l'équipe %2", name _by, (_teams select _i) select 1]; };
    };
    case "assign": {
        _args params [["_nid", ""], ["_tid", ""]];
        private _u = objectFromNetId _nid;
        if (isNull _u || {group _u isNotEqualTo _g}) exitWith {};
        if (_tid isNotEqualTo "" && {([_tid] call _idx) < 0}) exitWith {};
        private _cur = _u getVariable ["COMSPEC_FT", ""];
        private _ok = _isGL || {_u isEqualTo _by && {_tid isEqualTo ""}} || {[_tid] call _isTL} || {_tid isEqualTo "" && {[_cur] call _isTL}};
        if !(_ok) exitWith { ["Seuls le chef de groupe et les chefs d'équipe peuvent placer un membre."] call _say; };
        if (_cur isEqualTo _tid) exitWith {};
        if ((_u getVariable ["COMSPEC_FTRole", ""]) isEqualTo "CDE") then { _u setVariable ["COMSPEC_FTRole", [_u] call _fallback, true]; };
        [_u, _tid] call _setTeam;
        if (_tid isNotEqualTo "" && {!([_tid] call _hasLead)}) then { [_u, "CDE"] call _setRole; };
        _changed = true;
        _msg = if (_tid isEqualTo "") then { format ["%1 retiré de son équipe par %2", name _u, name _by] } else { format ["%1 placé dans l'équipe %2 par %3", name _u, (_teams select ([_tid] call _idx)) select 1, name _by] };
    };
    case "role": {
        _args params [["_nid", ""], ["_r", ""]];
        private _u = objectFromNetId _nid;
        if (isNull _u || {group _u isNotEqualTo _g}) exitWith {};
        if (_r isNotEqualTo "" && {((_cat get "roles") findIf { (_x select 0) isEqualTo _r }) < 0}) exitWith { [format ["Rôle %1 inconnu du serveur : rechargez les rôles (Groupe > ÉQUIPES) ou mettez le mod à jour.", _r]] call _say; };
        private _tid = _u getVariable ["COMSPEC_FT", ""];
        private _ok = _isGL || {[_tid] call _isTL} || {_u isEqualTo _by && {_r isNotEqualTo "CDE" || {!([_tid] call _hasLead)}}};
        if !(_ok) exitWith { ["Seuls le chef de groupe et le chef d'équipe nomment un chef d'équipe."] call _say; };
        [_u, _r] call _setRole;
        _changed = true;
        private _rl = ((_cat get "roles") select { (_x select 0) isEqualTo (_u getVariable ["COMSPEC_FTRole", ""]) }) param [0, ["", "sans rôle"]];
        _msg = format ["%1 : %2", name _u, _rl select 1];
    };
    case "roles": {
        _args params [["_list", []]];
        if !(_list isEqualType []) exitWith {};
        private _add = _list select { _x isEqualType [] && {(_x param [0, ""]) isEqualType ""} && {(_x param [0, ""]) regexMatch "^(A[0-9]{1,9}|C_[A-Z0-9]{1,13})$"} };
        _add = _add apply {
            _x params ["_k", ["_l", ""], ["_ab", ""], ["_ik", "FUS"], ["_o", ""]];
            [_k, [_l, 40] call _clean, toUpper ([_ab, 5] call _clean), [_ik, "FUS"] select !([_ik] call _validIconRole), ["CUSTOM", "ATHENA"] select ((_k select [0, 1]) isEqualTo "A")]
        };
        _changed = [_add select { (_x select 1) isNotEqualTo "" }] call _addRoles;
    };
    case "roleNew": {
        _args params [["_label", ""], ["_ik", "FUS"]];
        _label = [_label, 32] call _clean;
        if ((count _label) < 2) exitWith { ["Donnez un nom au rôle (2 lettres au moins)."] call _say; };
        if !([_ik] call _validIconRole) then { _ik = "FUS"; };
        private _roles = _cat get "roles";
        // Même nom qu'un rôle existant : on le réutilise.
        private _same = _roles findIf { (toLower (_x select 1)) isEqualTo toLower _label };
        private _key = "";
        if (_same >= 0) then {
            _key = (_roles select _same) select 0;
        } else {
            private _slug = toString ((toArray toUpper _label) select { (_x >= 48 && {_x <= 57}) || {_x >= 65 && {_x <= 90}} });
            if (_slug isEqualTo "") then { _slug = format ["R%1", count (missionNamespace getVariable ["COMSPEC_ATAK_FtExtraRoles", []])]; };
            _key = "C_" + (_slug select [0, 12]);
            private _n = 2;
            while { (_roles findIf { (_x select 0) isEqualTo _key }) >= 0 && {_n < 10} } do { _key = format ["C_%1%2", _slug select [0, 12], _n]; _n = _n + 1; };
            private _words = (_label splitString " -'") select { !((toLower _x) in ["de", "du", "des", "la", "le", "les", "d", "l", "et"]) };
            private _ab = if ((count _words) > 1) then { toUpper ((_words apply { _x select [0, 1] }) joinString "") } else { toUpper (_label select [0, 3]) };
            [[[_key, _label, _ab select [0, 4], _ik, "CUSTOM", getPlayerUID _by]]] call _addRoles;
        };
        [_by, _key] call _setRole;
        _by setVariable ["COMSPEC_FTRolePref", _key, true];
        ["comspec_atak_native_ftRemember", [_key], _by] call CBA_fnc_targetEvent;
        _changed = true;
        _msg = format ["%1 crée le rôle %2", name _by, _label];
    };
    case "restore": {
        _args params [["_tid", ""], ["_r", ""]];
        if (_tid isNotEqualTo "" && {([_tid] call _idx) >= 0} && {(_by getVariable ["COMSPEC_FT", ""]) isEqualTo ""}) then {
            [_by, _tid] call _setTeam;
            _changed = true;
        };
        if (_r isEqualTo "" || {(_by getVariable ["COMSPEC_FTRole", ""]) isNotEqualTo ""} || {((_cat get "roles") findIf { (_x select 0) isEqualTo _r }) < 0}) exitWith {};
        private _tidNow = _by getVariable ["COMSPEC_FT", ""];
        if (_r isEqualTo "CDE" && {_tidNow isEqualTo "" || {[_tidNow] call _hasLead}}) then { _r = [_by] call _fallback; };
        [_by, _r] call _setRole;
        _changed = true;
    };
};
if (_changed) then {
    _g setVariable ["COMSPEC_FTRev", (_g getVariable ["COMSPEC_FTRev", 0]) + 1, true];
    missionNamespace setVariable ["COMSPEC_ATAK_SquadRev", (missionNamespace getVariable ["COMSPEC_ATAK_SquadRev", 0]) + 1, true];
    // Tout le camp redessine (Groupe, BFT, Inter-team) ; seul le groupe reçoit l'annonce.
    ["comspec_atak_native_ftChanged", [_g, _msg], allPlayers select { side group _x isEqualTo side _g }] call CBA_fnc_targetEvent;
};
_changed
