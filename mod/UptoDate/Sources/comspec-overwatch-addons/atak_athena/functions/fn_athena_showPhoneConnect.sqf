/*
    Ouvre l'écran natif "Connexion téléphone" (code + QR) depuis l'app Athena d'ATAK Enhanced.
    Scanner le QR avec un vrai téléphone donne accès à l'ATAK Athena dédié. Dialog natif
    (COMSPEC_PhoneConnect_Dialog, pas de navigateur intégré / pas tablet.html) ouvert en enfant
    de cTab_Android_dlg — même schéma que athena_showLinkDialog.sqf : reste "dans" ATAK Enhanced,
    pas un système séparé.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if (!isNull (uiNamespace getVariable ["COMSPEC_PhoneConnect_Display", displayNull])) exitWith {};

private _parent = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _parent) then {
    _parent = findDisplay 46;
};

private _ok = false;
if (!isNull _parent) then {
    private _child = _parent createDisplay "COMSPEC_PhoneConnect_Dialog";
    _ok = !isNull _child;
} else {
    _ok = createDialog "COMSPEC_PhoneConnect_Dialog";
};

if (!_ok) then {
    ["COMSPEC_Warning", ["Impossible d'ouvrir Connexion téléphone."]] call comspec_overwatch_connect_fnc_showNotification;
};
