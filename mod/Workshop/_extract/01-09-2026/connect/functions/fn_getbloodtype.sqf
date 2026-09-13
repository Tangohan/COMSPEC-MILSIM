/*
    Groupe sanguin ACE / plaque d’identité du joueur local (pour le bilan médical Athena).
    Retour : "O+", "A-", … ou "" si indisponible.
*/
if (!hasInterface) exitWith { "" };

private _unit = player;
if (isNull _unit) exitWith { "" };

private _bt = "";
if (!isNil "ace_dogtags_fnc_getDogtagData") then {
    private _data = [_unit] call ace_dogtags_fnc_getDogtagData;
    if (_data isEqualType [] && {count _data >= 3}) then {
        _bt = _data select 2;
    };
};
if (_bt isEqualTo "") then {
    private _idx = _unit getVariable ["ace_medical_bloodType", -1];
    if (_idx isEqualType 0) then {
        _bt = switch (_idx) do {
            case 0: { "O-" };
            case 1: { "O+" };
            case 2: { "A-" };
            case 3: { "A+" };
            case 4: { "B-" };
            case 5: { "B+" };
            case 6: { "AB-" };
            case 7: { "AB+" };
            default { "" };
        };
    };
};
if (!(_bt isEqualType "")) then { _bt = str _bt; };
_bt = trim _bt;
_bt
