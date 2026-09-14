/*
    Vrai si Messagerie (canaux) est l’écran ATAK courant.
    Inclut l’ancienne app Groups IceMan, redirigée vers Messagerie.
*/
if (!hasInterface) exitWith { false };

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Comms_group", controlNull];
if (!isNull _group && {ctrlShown _group}) exitWith { true };

private _page = toLower ((["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings) param [0, ""]);
if (_page isEqualTo "") exitWith { false };

_page in [
    "atakcomms",
    "comspec_atak_comms",
    "atak_comms",
    "comms",
    "messagerie",
    "group"
]
