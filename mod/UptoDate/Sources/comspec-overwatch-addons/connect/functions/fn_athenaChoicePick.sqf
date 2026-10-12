/*
    Bouton OUI / NON de l'écran du choix Athena : enregistre, applique, ferme.
    Oui en mission sans session : la fenêtre Connexion Athena s'ouvre pour lier le compte.
    Oui au menu principal : la session est restaurée tout de suite ; sinon la liaison est proposée en mission.
    Params : ["on" | "off"]
*/
params [["_mode", ""]];
private _display = uiNamespace getVariable ["COMSPEC_AthenaChoice_Display", displayNull];
if !([_mode] call comspec_overwatch_connect_fnc_athenaApply) exitWith {};
if (!isNull _display) then { _display closeDisplay 1; };

if (_mode isEqualTo "on") then {
    uiNamespace setVariable ["COMSPEC_AthenaOfferLink", true];
    if (!isNull findDisplay 46) then {
        // Laisser la session se restaurer (Steam / clé du profil) avant de proposer la liaison.
        [{ [] call comspec_overwatch_connect_fnc_isReady }, {
            [] call comspec_overwatch_connect_fnc_athenaOfferLink;
        }, [], 12, {
            [] call comspec_overwatch_connect_fnc_athenaOfferLink;
        }] call CBA_fnc_waitUntilAndExecute;
    };
};
