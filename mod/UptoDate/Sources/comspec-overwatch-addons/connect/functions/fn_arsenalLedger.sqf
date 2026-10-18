/*
    Registre des tenues déjà synchronisées avec le site, sur cet ordinateur (profileNamespace) :
    clé (nom en minuscules) -> [sha du site, sha local] au moment du dernier envoi ou import.
    Sert à dire ce qui a changé depuis : ici (sha local différent), sur le site (sha du site différent), ou que la
    tenue a été retirée du site (clé connue, plus de tenue sur le site).
      ["get", nom]                  -> [shaSite, shaLocal] ou []
      ["set", nom, shaSite, shaLocal]
      ["del", nom]
      ["map"]                       -> hashmap clé -> [shaSite, shaLocal]
*/
params [["_mode", "get", [""]], ["_name", "", [""]], ["_site", "", [""]], ["_local", "", [""]]];
private _rows = profileNamespace getVariable ["COMSPEC_ArsenalSyncLedger", []];
if !(_rows isEqualType []) then { _rows = []; };
private _key = toLower trim _name;
switch (_mode) do {
    case "map": { createHashMapFromArray (_rows select { (_x isEqualType []) && {(count _x) >= 3} } apply { [_x select 0, [_x select 1, _x select 2]] }) };
    case "get": { private _i = _rows findIf { (_x param [0, ""]) isEqualTo _key }; if (_i < 0) then { [] } else { [(_rows select _i) select 1, (_rows select _i) select 2] } };
    case "set": {
        if (_key isEqualTo "") exitWith {};
        private _i = _rows findIf { (_x param [0, ""]) isEqualTo _key };
        private _new = [_key, _site, _local];
        if (_i >= 0 && {(_rows select _i) isEqualTo _new}) exitWith {};
        if (_i >= 0) then { _rows set [_i, _new]; } else { _rows pushBack _new; };
        profileNamespace setVariable ["COMSPEC_ArsenalSyncLedger", _rows];
    };
    case "del": {
        private _i = _rows findIf { (_x param [0, ""]) isEqualTo _key };
        if (_i < 0) exitWith {};
        _rows deleteAt _i;
        profileNamespace setVariable ["COMSPEC_ArsenalSyncLedger", _rows];
    };
};
