params ["_records"];

if !(_records isEqualType []) exitWith {false};

profileNamespace setVariable ["Iceman_PhotoLibrary_records", _records];
saveProfileNamespace;
true
