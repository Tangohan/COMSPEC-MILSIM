/*
    Rôle affiché d'un opérateur (effectifs, étiquettes, remontée Athena atak_units.role).
    Règle unique de l'ATAK natif (comspec_atak_native_fnc_displayRole) : rôle choisi dans l'app Profil, sinon fonction
    de la communauté, sinon rôle du slot, sinon rien. Le rôle du slot part aussi à part (role_description, « déclaré en jeu »).
*/
params [["_unit", objNull, [objNull]]];
if (isNull _unit) exitWith { "" };
if (!isNil "comspec_atak_native_fnc_displayRole") exitWith { [_unit] call comspec_atak_native_fnc_displayRole };
// Sans ATAK natif : fonction de la communauté (joueur local), sinon description du slot.
private _fn = if (_unit isEqualTo player) then { missionNamespace getVariable ["comspec_profile_function", ""] } else { "" };
if (!(_fn isEqualType "")) then { _fn = ""; };
if ((trim _fn) isNotEqualTo "") exitWith { trim _fn };
private _raw = roleDescription _unit;
if (!(_raw isEqualType "")) then { _raw = ""; };
trim ((_raw splitString "@") param [0, ""])
