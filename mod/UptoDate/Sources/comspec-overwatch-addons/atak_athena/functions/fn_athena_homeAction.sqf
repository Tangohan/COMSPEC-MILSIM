/*
    Bouton bandeau Athena : ouvrir le formulaire, Entrer (canal poste), ou actualiser si déjà lié.
*/
if (!hasInterface) exitWith {};

private _linked = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
if (!(_linked isEqualType true)) then { _linked = false; };
private _athenaName = trim (str (missionNamespace getVariable ["comspec_profile_name", ""]));
if (_athenaName isEqualTo "" || {(toLower _athenaName) in ["<null>", "any", "nil"]}) then { _athenaName = ""; };
private _armaName = if (!isNull player) then { trim (name player) } else { "" };
if (_athenaName isNotEqualTo "" && {_armaName isNotEqualTo ""} && {(toLower _athenaName) isEqualTo (toLower _armaName)}) then {
    _athenaName = "";
};
private _allOk = _linked && {_athenaName isNotEqualTo ""};
private _authState = missionNamespace getVariable ["comspec_overwatch_auth_state", ""];
private _ready = (_authState isEqualTo "READY") || {_linked};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openAtakApp") then {
    ["Athena"] call comspec_overwatch_atak_athena_fnc_athena_openAtakApp;
};

// Déjà connecté : rafraîchir la fiche, ne pas relancer Entrer.
if (_allOk) exitWith {
    [] call comspec_overwatch_atak_athena_fnc_athena_applyHomeLayout;
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};

// Compte trouvé / en liaison : le bandeau dit « Entrer » → ouvrir le canal poste.
if (_ready) exitWith {
    ["enter"] call comspec_overwatch_atak_athena_fnc_athena_authAction;
};

[] call comspec_overwatch_atak_athena_fnc_athena_authFocus;
