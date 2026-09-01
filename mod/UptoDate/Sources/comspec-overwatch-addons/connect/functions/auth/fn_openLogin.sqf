/*
    Ouvre la connexion Athena (e-mail / code / Steam).
    Uniquement sur action joueur — jamais au démarrage de mission.
    Sur le téléphone ATAK : createDisplay pour ne pas fermer cTab.
*/
if (!hasInterface) exitWith {};
if (!isNull (uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull])) exitWith {};

private _parent = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
private _ok = false;
if (!isNull _parent) then {
    private _child = _parent createDisplay "COMSPEC_AthenaAuth_Dialog";
    _ok = !isNull _child;
};
if (!_ok) then {
    _ok = createDialog "COMSPEC_AthenaAuth_Dialog";
};
if (_ok) then {
    [] call comspec_overwatch_connect_fnc_pollAuth;
};
