/*
    Ouvre l’écran Connexion Athena (code et/ou Steam + barre de transmission).
    Sur le téléphone ATAK : createDisplay pour ne pas fermer cTab.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if (!isNull (uiNamespace getVariable ["COMSPEC_AccountLink_Display", displayNull])) exitWith {};

private _parent = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _parent) then {
    _parent = findDisplay 46;
};

private _ok = false;
if (!isNull _parent) then {
    private _child = _parent createDisplay "COMSPEC_AccountLink_Dialog";
    _ok = !isNull _child;
} else {
    _ok = createDialog "COMSPEC_AccountLink_Dialog";
};

if (!_ok) then {
    ["COMSPEC_Warning", ["Impossible d’ouvrir Connexion Athena."]] call comspec_overwatch_connect_fnc_showNotification;
};
