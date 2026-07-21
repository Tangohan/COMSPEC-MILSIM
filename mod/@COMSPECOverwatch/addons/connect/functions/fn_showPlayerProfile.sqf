/*
    Récupère le profil site du joueur (nom, callsign, photo), télécharge/cache la photo et met à
    jour le bloc "Profil" du hub (idc 9114 photo, 9115 nom). Best effort — silencieux en cas
    d'échec (compte non lié, portail injoignable...), ne doit jamais gêner le reste du hub.
    Appelé en spawn (réseau) depuis l'onLoad du hub.
*/
if (!hasInterface) exitWith {};

private _info = [] call comspec_overwatch_connect_fnc_getPlayerAvatarInfo;
if (count _info < 3) exitWith {};
_info params ["_displayName", "_callsign", "_avatarUrl"];

private _label = if (_callsign != "") then { _callsign } else { _displayName };
if (_label == "") exitWith {};

private _localPath = "";
if (_avatarUrl != "") then {
    private _cacheKey = "avatar_" + (getPlayerUID player);
    private _raw = ["COMSPECExtension" callExtension ["DownloadBriefingSlideImage", [_avatarUrl, _cacheKey]]] call comspec_overwatch_connect_fnc_extResult;
    private _parts = _raw splitString "|";
    private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };
    if (_prefix == "OK" && {count _parts >= 2}) then { _localPath = _parts select 1; };
};

private _hub = uiNamespace getVariable ["COMSPEC_Hub_Display", displayNull];
if (isNull _hub) then { _hub = findDisplay 9969; };
if (isNull _hub) exitWith {};

private _picCtrl = _hub displayCtrl 9114;
if (!isNull _picCtrl && {_localPath != ""}) then { _picCtrl ctrlSetText _localPath; };

private _nameCtrl = _hub displayCtrl 9115;
if (!isNull _nameCtrl) then {
    _nameCtrl ctrlSetStructuredText parseText format ["<t size='0.6' color='#d0dce8'>%1</t>", _label];
};
