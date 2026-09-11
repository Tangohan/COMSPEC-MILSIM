/*
    Ouvre la connexion Athena.
    Sur ATAK Enhanced : formulaire natif dans le téléphone (pas de dialog / HTML).
    Sinon : écran Connexion classique.
*/
if (!hasInterface) exitWith {};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_authFocus"
    && {missionNamespace getVariable ["comspec_overwatch_atak_ui_only", true]}
) exitWith {
    [] call comspec_overwatch_atak_athena_fnc_athena_authFocus;
};

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
