/*
    Indicatif tactique du joueur local.
    Ordre : mission → profil → nom joueur → groupe → Opérateur (jamais vide / Unknown).
*/
if (!hasInterface) exitWith { "Operateur" };

private _cs = missionNamespace getVariable ["COMSPEC_Callsign", ""];
if (!(_cs isEqualType "")) then { _cs = str _cs; };
_cs = trim _cs;
if (_cs isEqualTo "" || {(toLower _cs) in ["unknown", "inconnu"]}) then {
    _cs = trim (profileNamespace getVariable ["COMSPEC_Callsign", ""]);
};
if (_cs isEqualTo "" || {(toLower _cs) in ["unknown", "inconnu"]}) then {
    _cs = trim (name player);
};
if (_cs isEqualTo "") then {
    _cs = trim (groupId (group player));
};
if (_cs isEqualTo "" || {(toLower _cs) in ["unknown", "inconnu"]}) then {
    _cs = "Operateur";
};
_cs
