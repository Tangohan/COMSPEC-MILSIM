/*
    Nouvel ordre Athena → pastille notification cTab si disponible.
*/
params [["_order", createHashMap]];

if (!hasInterface) exitWith {};
if (!(_order isEqualType createHashMap)) exitWith {};

if (isNil "cTab_fnc_addNotification") exitWith {};

private _type = _order getOrDefault ["type", "MOVE"];
private _typeLabel = trim (_order getOrDefault ["typeLabel", ""]);
if (_typeLabel isEqualTo "") then {
    _typeLabel = switch (toUpper _type) do {
        case "HOLD": { "Tenir la position" };
        case "RECON": { "Reconnaissance" };
        case "CAS": { "Appui aérien" };
        case "QRF": { "Force de réaction" };
        case "CUSTOM": { "Ordre personnalisé" };
        default { "Se déplacer" };
    };
};
private _issuer = _order getOrDefault ["issuer", "C2"];

["ATHENA", format ["Nouvel ordre — %1 (de %2)", _typeLabel, _issuer], 8] call cTab_fnc_addNotification;
if (!isNil "cTab_phoneVibrate") then {
    playSound "cTab_phoneVibrate";
};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
if (!isNull _group && {ctrlShown _group}) then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};
