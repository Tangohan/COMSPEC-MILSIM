/*
    Après un « Oui » au service Athéna : ouvre une fois la fenêtre Connexion Athena si aucune session n'est ouverte.
*/
if (!hasInterface || {isNull findDisplay 46}) exitWith {};
if !(uiNamespace getVariable ["COMSPEC_AthenaOfferLink", false]) exitWith {};
if (([] call comspec_overwatch_connect_fnc_athenaMode) isNotEqualTo "on") exitWith {};
uiNamespace setVariable ["COMSPEC_AthenaOfferLink", false];
if ([] call comspec_overwatch_connect_fnc_isReady) exitWith {};
["INFO", "Athena", "Service Athéna choisi sans session : ouverture de Connexion Athena"] call comspec_overwatch_connect_fnc_log;
[] call comspec_overwatch_connect_fnc_accountLinkShow;
