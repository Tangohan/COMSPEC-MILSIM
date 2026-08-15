private _records = profileNamespace getVariable ["Iceman_PhotoLibrary_records", []];
if !(_records isEqualType []) exitWith {[]};

_records select {
    _x isEqualType []
    && {count _x >= 14}
    && {(_x param [0, ""]) isEqualType ""}
}
