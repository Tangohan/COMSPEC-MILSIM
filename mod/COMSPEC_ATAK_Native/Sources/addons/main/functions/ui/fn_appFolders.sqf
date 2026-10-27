/*
    Groupes d'apps créés par le joueur (profil COMSPEC_ATAK_AppFolders).
    Entrées : [id, nom, [pages…]] — max 12 groupes, 24 apps par groupe.
    Params : [action, …]
      "list"              → liste des groupes
      "get", id           → [id, nom, pages] ou []
      "create", nom       → id ("" si refus)
      "rename", id, nom   → bool
      "delete", id        → bool
      "add", id, page     → bool
      "remove", id, page  → bool
      "contains", page    → ids de groupes qui contiennent la page
*/
params [["_act", "list"], ["_a", ""], ["_b", ""]];

private _load = {
    private _f = profileNamespace getVariable ["COMSPEC_ATAK_AppFolders", []];
    if !(_f isEqualType []) then { _f = []; };
    // Nettoyage : structure [id, name, pages]
    private _out = [];
    {
        if (_x isEqualType [] && {(count _x) >= 3}) then {
            _x params [["_id", ""], ["_name", ""], ["_pages", []]];
            if (!(_pages isEqualType [])) then { _pages = []; };
            if (_id isNotEqualTo "" && {_name isNotEqualTo ""}) then {
                _out pushBack [_id, _name, _pages select { _x isEqualType "" && {_x isNotEqualTo ""} }];
            };
        };
    } forEach _f;
    _out
};
private _save = {
    params ["_list"];
    profileNamespace setVariable ["COMSPEC_ATAK_AppFolders", _list];
    [] call comspec_atak_native_fnc_saveProfile;
};

switch (_act) do {
    case "list": { call _load };
    case "get": {
        private _hit = [];
        { if ((_x select 0) isEqualTo _a) exitWith { _hit = _x; }; } forEach (call _load);
        _hit
    };
    case "create": {
        private _name = trim str _a;
        if (_name isEqualTo "") exitWith { "" };
        private _list = call _load;
        if ((count _list) >= 12) exitWith { "" };
        private _id = format ["f%1", round (diag_tickTime * 1000) + (floor random 999)];
        _list pushBack [_id, _name select [0, 24], []];
        [_list] call _save;
        _id
    };
    case "rename": {
        private _name = trim str _b;
        if (_name isEqualTo "") exitWith { false };
        private _list = call _load;
        private _ok = false;
        {
            if ((_x select 0) isEqualTo _a) exitWith {
                _x set [1, _name select [0, 24]];
                _ok = true;
            };
        } forEach _list;
        if (_ok) then { [_list] call _save; };
        _ok
    };
    case "delete": {
        private _list = (call _load) select { (_x select 0) isNotEqualTo _a };
        [_list] call _save;
        true
    };
    case "add": {
        private _page = toUpper str _b;
        if (_page isEqualTo "") exitWith { false };
        private _list = call _load;
        private _ok = false;
        {
            if ((_x select 0) isEqualTo _a) exitWith {
                private _pages = +(_x select 2);
                if !(_page in _pages) then {
                    if ((count _pages) >= 24) exitWith {};
                    _pages pushBack _page;
                    _x set [2, _pages];
                    _ok = true;
                } else {
                    _ok = true;
                };
            };
        } forEach _list;
        if (_ok) then { [_list] call _save; };
        _ok
    };
    case "remove": {
        private _page = toUpper str _b;
        private _list = call _load;
        private _ok = false;
        {
            if ((_x select 0) isEqualTo _a) exitWith {
                _x set [2, (_x select 2) - [_page]];
                _ok = true;
            };
        } forEach _list;
        if (_ok) then { [_list] call _save; };
        _ok
    };
    case "contains": {
        private _page = toUpper str _a;
        (call _load) select { _page in (_x select 2) } apply { _x select 0 }
    };
    default { [] };
}
