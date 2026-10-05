/*
    Rôle tenu en ce moment, pour les temps par rôle. Params : [unité]. Renvoie [clé, libellé].
    1. Poste d'équipage : pilote, copilote / mitrailleur de bord (aéronef), conducteur ou équipage (véhicule).
    2. Rôle d'équipe de feu choisi dans le téléphone (app Groupe, onglet ÉQUIPES).
    3. Spécialité Arma (infirmier, sapeur, démineur, opérateur drone), sinon le rôle du slot (ex. « Fusilier »).
*/
params [["_u", player]];
if (isNull _u) exitWith { ["", ""] };
private _v = objectParent _u;
private _crewKey = "";
if (!isNull _v) then {
    private _air = _v isKindOf "Air";
    _crewKey = switch (true) do {
        case (driver _v isEqualTo _u): { ["DRV", "PIL"] select _air };
        case (gunner _v isEqualTo _u || {commander _v isEqualTo _u} || {((assignedVehicleRole _u) param [0, ""]) isEqualTo "Turret"}): { ["CREW", "CPL"] select _air };
        default { "" };
    };
};
if (_crewKey isNotEqualTo "") exitWith {
    [_crewKey, createHashMapFromArray [["PIL", "Pilote"], ["CPL", "Copilote / mitrailleur de bord"], ["DRV", "Conducteur"], ["CREW", "Équipage véhicule"]] get _crewKey]
};
private _r = _u getVariable ["COMSPEC_FTRole", ""];
if (_r isNotEqualTo "") exitWith {
    private _cat = [] call comspec_atak_native_fnc_ftCatalog;
    [_r, (((_cat get "roles") select { (_x select 0) isEqualTo _r }) param [0, ["", _r]]) select 1]
};
switch (true) do {
    case (_u getUnitTrait "Medic"): { ["MED", "Auxiliaire sanitaire"] };
    case (_u getUnitTrait "explosiveSpecialist"): { ["EOD", "Démineur (EOD)"] };
    case (_u getUnitTrait "Engineer"): { ["ENG", "Sapeur"] };
    case (_u getUnitTrait "UAVHacker"): { ["DRN", "Télépilote drone"] };
    default {
        private _n = roleDescription _u;
        if (_n isEqualTo "") then { _n = getText (configOf _u >> "displayName"); };
        _n = (_n splitString "@") param [0, _n];
        [format ["SLOT:%1", (toUpper _n) select [0, 40]], _n select [0, 60]]
    };
}
