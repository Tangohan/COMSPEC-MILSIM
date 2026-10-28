/*
    Après un « Oui » au service Athéna : ouvre une fois la fenêtre Connexion Athena si aucune session n'est ouverte.
    Avec l'ATAK natif : connexion au compte par code (fn_athenaLink), la page athena.ttrd.fr/lier s'ouvre
    avec le code déjà rempli ; plus de clé de communauté ni d'identifiant à coller.
*/
if (!hasInterface || {isNull findDisplay 46}) exitWith {};
if !(uiNamespace getVariable ["COMSPEC_AthenaOfferLink", false]) exitWith {};
if (([] call comspec_overwatch_connect_fnc_athenaMode) isNotEqualTo "on") exitWith {};
uiNamespace setVariable ["COMSPEC_AthenaOfferLink", false];
if ([] call comspec_overwatch_connect_fnc_isReady) exitWith {};
["INFO", "Athena", "Service Athéna choisi sans session : ouverture de Connexion Athena"] call comspec_overwatch_connect_fnc_log;
if (!isNil "comspec_atak_native_fnc_athenaLink") exitWith {
    ["offer", true] call comspec_atak_native_fnc_athenaLink;
};
[] call comspec_overwatch_connect_fnc_accountLinkShow;
