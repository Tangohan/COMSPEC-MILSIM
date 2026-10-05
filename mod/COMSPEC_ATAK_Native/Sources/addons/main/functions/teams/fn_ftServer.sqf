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
private _armaTeam = {
    params ["_tid"];
    private _i = [_tid] call _idx;
    if (_i < 0) exitWith { "MAIN" };
    private _ck = (_teams select _i) select 2;
    (((_cat get "colors") select { (_x select 0) isEqualTo _ck }) param [0, ["", "", "", [], "MAIN"]]) select 4
};
private _setTeam = {
    params ["_u", "_tid"];
    _u setVariable ["COMSPEC_FT", _tid, true];
    // Rôle de chef d'équipe : seulement dans une équipe.
    if (_tid isEqualTo "" && {(_u getVariable ["COMSPEC_FTRole", ""]) isEqualTo "CDE"}) then { _u setVariable ["COMSPEC_FTRole", "FUS", true]; };
    [_u, [_tid] call _armaTeam] remoteExecCall ["assignTeam", 0];
};
private _setRole = {
    params ["_u", "_r"];
    private _tid = _u getVariable ["COMSPEC_FT", ""];
    if (_r isEqualTo "CDE") then {
        if (_tid isEqualTo "") exitWith { _r = "FUS"; };
        // Un seul chef par équipe : l'ancien redevient fusilier.
        { if (_x isNotEqualTo _u && {(_x getVariable ["COMSPEC_FT", ""]) isEqualTo _tid} && {(_x getVariable ["COMSPEC_FTRole", ""]) isEqualTo "CDE"}) then { _x setVariable ["COMSPEC_FTRole", "FUS", true]; }; } forEach units _g;
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
        if ((_by getVariable ["COMSPEC_FTRole", ""]) isEqualTo "CDE") then { _by setVariable ["COMSPEC_FTRole", "FUS", true]; };
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
        if ((_u getVariable ["COMSPEC_FTRole", ""]) isEqualTo "CDE") then { _u setVariable ["COMSPEC_FTRole", "FUS", true]; };
        [_u, _tid] call _setTeam;
        if (_tid isNotEqualTo "" && {!([_tid] call _hasLead)}) then { [_u, "CDE"] call _setRole; };
        _changed = true;
        _msg = if (_tid isEqualTo "") then { format ["%1 retiré de son équipe par %2", name _u, name _by] } else { format ["%1 placé dans l'équipe %2 par %3", name _u, (_teams select ([_tid] call _idx)) select 1, name _by] };
    };
    case "role": {
        _args params [["_nid", ""], ["_r", ""]];
        private _u = objectFromNetId _nid;
        if (isNull _u || {group _u isNotEqualTo _g}) exitWith {};
        if (_r isNotEqualTo "" && {((_cat get "roles") findIf { (_x select 0) isEqualTo _r }) < 0}) exitWith {};
        private _tid = _u getVariable ["COMSPEC_FT", ""];
        private _ok = _isGL || {[_tid] call _isTL} || {_u isEqualTo _by && {_r isNotEqualTo "CDE" || {!([_tid] call _hasLead)}}};
        if !(_ok) exitWith { ["Seuls le chef de groupe et le chef d'équipe nomment un chef d'équipe."] call _say; };
        [_u, _r] call _setRole;
        _changed = true;
        private _rl = ((_cat get "roles") select { (_x select 0) isEqualTo (_u getVariable ["COMSPEC_FTRole", ""]) }) param [0, ["", "sans rôle"]];
        _msg = format ["%1 : %2", name _u, _rl select 1];
    };
};
if (_changed) then {
    _g setVariable ["COMSPEC_FTRev", (_g getVariable ["COMSPEC_FTRev", 0]) + 1, true];
    missionNamespace setVariable ["COMSPEC_ATAK_SquadRev", (missionNamespace getVariable ["COMSPEC_ATAK_SquadRev", 0]) + 1, true];
    // Tout le camp redessine (Groupe, BFT, Inter-team) ; seul le groupe reçoit l'annonce.
    ["comspec_atak_native_ftChanged", [_g, _msg], allPlayers select { side group _x isEqualTo side _g }] call CBA_fnc_targetEvent;
};
_changed
