/*
    Récupère le profil site du joueur (nom, callsign, photo, unité, identifiant ATAK),
    télécharge/cache la photo et met à jour un bloc "Profil" (photo + nom). Best effort —
    silencieux en cas d'échec (compte non lié, portail injoignable...), ne doit jamais gêner le
    reste du dialog. Appelé en spawn (réseau) depuis l'onLoad du hub ou de la vue tablette.

    Params (tous optionnels — défaut : bloc "Profil" du hub, idc 9114/9115) :
    [_display, _picIdc, _nameIdc]
*/
params [["_display", displayNull], ["_picIdc", 9114, [0]], ["_nameIdc", 9115, [0]]];
if (isNull _display) then {
    _display = uiNamespace getVariable ["COMSPEC_Hub_Display", displayNull];
    if (isNull _display) then { _display = findDisplay 9969; };
};
if (isNull _display) exitWith {};

private _info = [] call comspec_overwatch_connect_fnc_getPlayerAvatarInfo;
if (count _info < 3) exitWith {};
_info params [
    "_displayName", "_callsign", "_avatarUrl",
    ["_unitName", ""], ["_atakId", ""],
    ["_playtimeHours", ""], ["_lastSeenAt", ""]
];

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

if (isNull _display) exitWith {}; // le joueur a pu fermer le dialog pendant le téléchargement

private _picCtrl = _display displayCtrl _picIdc;
if (!isNull _picCtrl && {_localPath != ""}) then { _picCtrl ctrlSetText _localPath; };

private _nameCtrl = _display displayCtrl _nameIdc;
if (!isNull _nameCtrl) then {
    private _lines = [format ["<t size='0.62' color='#d0dce8'>%1</t>", _label]];
    if (_unitName != "") then {
        _lines pushBack format ["<t size='0.52' color='#8aa0b4'>%1</t>", _unitName];
    };
    if (_atakId != "") then {
        _lines pushBack format ["<t size='0.48' color='#5a9e88'>ID %1</t>", _atakId];
    };
    // Activité réelle si trackée côté serveur ; placeholder explicite sinon — jamais de valeur
    // inventée (voir AtakApiController::playerProfile).
    private _activityLabel = if (_playtimeHours != "") then {
        format ["%1h de jeu — vu le %2", _playtimeHours, if (_lastSeenAt != "") then { _lastSeenAt } else { "—" }]
    } else {
        "Activité non suivie"
    };
    _lines pushBack format ["<t size='0.42' color='#5a6c7e'>%1</t>", _activityLabel];
    _nameCtrl ctrlSetStructuredText parseText (_lines joinString "<br/>");
};
