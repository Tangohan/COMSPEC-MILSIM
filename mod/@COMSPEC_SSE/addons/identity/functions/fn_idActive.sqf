/*
    Identité active d'un joueur qui porte ses deux identités, réelle et couverture (SERVEUR) : celle qu'il présente.
    L'autre reste dans ses affaires, cachée : la fouille la trouve (« papiers à plusieurs noms »).
    Rôle d'un papier (doc « role ») : « cover » si son identité Athena est une couverture, un alias ou un faux, « real »
    sinon (posé par fn_idModuleGive « athenaSpec ») ; papier sans rôle (Zeus, Eden, IA) : compté avec l'identité active.
    ["get", [unité]]          -> "real" | "cover"   choix du joueur, sinon couverture si une fiche de couverture Athena
                                 la lui attribue (paquet, briefs) ou s'il ne porte que des papiers de couverture
    ["set", [unité, rôle]]    -> Boolean   change d'identité (seulement s'il a des papiers de ce rôle)
    ["roles", [unité]]        -> [actif, [[classe, rôle]…], les deux ?]   papiers portés, pour son poste
    ["push", [unité]]         envoie « roles » au poste du joueur (menu ACE « Mes papiers », Ma couverture)
    ["of", [document]]        -> rôle du document ("" sans rôle)
    Registre : activeRole  Steam ID (ou clé biométrique) -> rôle choisi.
*/
params [["_op", "", [""]], ["_a", []]];
if (!isServer) exitWith { nil };
_a params [["_u", objNull], ["_x1", ""]];
private _reg = ["init"] call comspec_sse_fnc_idRegistry;
private _act = _reg getOrDefault ["activeRole", createHashMap];
_reg set ["activeRole", _act];
if (_op isEqualTo "of") exitWith { if (_u isEqualType createHashMap) then { _u getOrDefault ["role", ""] } else { "" } };
if !(_u isEqualType objNull) exitWith { nil };
if (isNull _u) exitWith { ["real", nil] select (_op isEqualTo "roles") };
private _who = getPlayerUID _u;
if (_who isEqualTo "") then { _who = [_u] call comspec_sse_fnc_bioPersonKey; };
private _docs = (["unitDocs", [_u]] call comspec_sse_fnc_idRegistry) apply { ["doc", [_x]] call comspec_sse_fnc_idRegistry };
private _roles = _docs apply { _x getOrDefault ["role", ""] };
private _both = ("real" in _roles) && {"cover" in _roles};
private _default = {
    private _brief = (_reg getOrDefault ["briefs", createHashMap]) getOrDefault [getPlayerUID _u, createHashMap];
    private _bc = ((_brief getOrDefault ["identity_id", ""]) isNotEqualTo "") || {(_brief getOrDefault ["cover_name", ""]) isNotEqualTo ""};
    if (_bc || {("cover" in _roles) && {!("real" in _roles)}}) exitWith { "cover" };
    "real"
};
private _get = {
    private _r = _act getOrDefault [_who, ""];
    if !(_r in ["real", "cover"]) then { _r = call _default; };
    // Choix d'un rôle dont il n'a plus aucun papier : retour au défaut.
    if (_roles isNotEqualTo [] && {!(_r in _roles)} && {("real" in _roles) || {"cover" in _roles}}) then { _r = call _default; };
    _r
};
switch (_op) do {
    case "get": { call _get };
    case "set": {
        if !(_x1 in ["real", "cover"]) exitWith { false };
        if !(_x1 in _roles) exitWith { false };
        _act set [_who, _x1];
        ["reset", [_u]] call comspec_sse_fnc_idLegend;
        true
    };
    case "roles": {
        private _active = call _get;
        private _out = [];
        { if ((_x getOrDefault ["cls", ""]) isNotEqualTo "") then { _out pushBack [_x get "cls", _x getOrDefault ["role", ""]]; }; } forEach _docs;
        [_active, _out, _both]
    };
    case "push": {
        if (!isPlayer _u) exitWith { false };
        private _r = ["roles", [_u]] call comspec_sse_fnc_idActive;
        if (local _u && {hasInterface}) then { ["roles", _r] call comspec_sse_fnc_idClient; } else { ["roles", _r] remoteExecCall ["comspec_sse_fnc_idClient", owner _u]; };
        true
    };
    default { nil };
}
