/*
    Affiche une alerte Windows (MessageBox) expliquant comment lier le compte Athena.
    - Une fois par session Arma (sauf si « Ne plus afficher »)
    - Ignoré si le compte est déjà lié
    - Désactivable via CBA (« Rappel Windows ») ou bouton Non dans l’alerte
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_athena_link_help", true])) exitWith {};

// Réactivation CBA : lever l’ancien « Ne plus afficher » du profil
if (profileNamespace getVariable ["comspec_overwatch_hide_athena_link_help", false]) then {
    profileNamespace setVariable ["comspec_overwatch_hide_athena_link_help", false];
    saveProfileNamespace;
};

// Déjà affiché cette session
if (missionNamespace getVariable ["COMSPEC_AthenaLinkHelpShown", false]) exitWith {};

// Compte déjà lié → silence
private _linkState = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
if (_linkState isEqualTo "linked") exitWith {};

private _key = missionNamespace getVariable ["comspec_overwatch_api_key", ""];
if (!(_key isEqualType "")) then { _key = ""; };
_key = trim _key;
if ((count _key) >= 16) exitWith {};

missionNamespace setVariable ["COMSPEC_AthenaLinkHelpShown", true, false];

private _raw = ["COMSPECExtension" callExtension ["ShowAthenaLinkHelp", []]] call comspec_overwatch_connect_fnc_extResult;
private _parts = _raw splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };
private _action = if (count _parts >= 2) then { _parts select 1 } else { "" };

if (_prefix isEqualTo "OK" && {_action isEqualTo "dont_show"}) then {
    profileNamespace setVariable ["comspec_overwatch_hide_athena_link_help", true];
    saveProfileNamespace;
    // Persiste aussi dans CBA pour que le joueur puisse le réactiver dans Options
    ["comspec_overwatch_athena_link_help", false, 0, "client", true] call CBA_fnc_setSetting;
    ["[Athena] Aide liaison désactivée (Ne plus afficher)."] call comspec_overwatch_connect_fnc_appendLinkLog;
};
