/*
    Indique si le joueur local peut triager les alertes médicales.
    Médecin ACE / trait Medic / chef de groupe / rôle contenant « medic » ou « médecin ».
*/
if (!hasInterface) exitWith { false };
if (isNull player) exitWith { false };

if ((player getVariable ["ace_medical_medicClass", 0]) > 0) exitWith { true };
if (player getUnitTrait "Medic") exitWith { true };
if (leader (group player) isEqualTo player) exitWith { true };

private _role = toLower (roleDescription player);
if (_role find "medic" >= 0 || {_role find "médecin" >= 0} || {_role find "medecin" >= 0} || {_role find "corpsman" >= 0}) exitWith { true };

private _unitRole = toLower (missionNamespace getVariable ["COMSPEC_UnitRole", ""]);
if (_unitRole find "medic" >= 0 || {_unitRole find "médecin" >= 0} || {_unitRole find "medecin" >= 0}) exitWith { true };

false
