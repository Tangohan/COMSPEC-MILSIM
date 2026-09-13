/*
    Envoie l’inscription accès anticipé vers Athena (RegisterBeta).
    Collecte : Steam / UID, nom, build Arma, version mod — l’IP est capturée côté serveur.
*/
if (!hasInterface) exitWith { false };

private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];
if (!(_url isEqualType "")) then { _url = ""; };
_url = trim _url;
if (_url isEqualTo "") then {
    _url = trim (profileNamespace getVariable ["comspec_overwatch_saved_api_url", ""]);
};
if (_url isEqualTo "") then {
    _url = "https://athena.ttrd.fr/public";
};

private _steamUid = "";
if (!isNull player) then {
    _steamUid = getPlayerUID player;
};
if (!(_steamUid isEqualType "")) then { _steamUid = ""; };
_steamUid = trim _steamUid;
if ((count _steamUid) < 15) then {
    _steamUid = trim (profileNamespace getVariable ["comspec_overwatch_saved_steam_uid", ""]);
};

private _playerUid = _steamUid;
private _playerName = profileName;
if (!isNull player) then {
    private _n = name player;
    if (_n isEqualType "" && {!(trim _n isEqualTo "")}) then { _playerName = _n; };
};
if (!(_playerName isEqualType "")) then { _playerName = ""; };

private _modVersion = [] call comspec_overwatch_connect_fnc_getModVersion;

private _armaBuild = "";
private _armaBranch = "";
private _pv = productVersion;
if (_pv isEqualType [] && {count _pv >= 4}) then {
    _armaBuild = str (_pv select 3);
    if (count _pv >= 5) then {
        private _branch = _pv select 4;
        if (_branch isEqualType "") then { _armaBranch = _branch; };
    };
};

private _extVersion = "1.17";
private _extRaw = ["COMSPECExtension" callExtension ["GetExtensionVersion", []]] call comspec_overwatch_connect_fnc_extResult;
private _extParts = _extRaw splitString "|";
if ((count _extParts) >= 2 && {(_extParts select 0) isEqualTo "OK"}) then {
    private _label = _extParts select 1;
    private _bits = _label splitString " ";
    if ((count _bits) >= 2) then { _extVersion = _bits select 1; };
};

private _raw = [
    "COMSPECExtension" callExtension [
        "RegisterBeta",
        [_url, _steamUid, _playerUid, _playerName, _modVersion, _armaBuild, _armaBranch, _extVersion, "1"]
    ]
] call comspec_overwatch_connect_fnc_extResult;

private _parts = _raw splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };

if (_prefix isEqualTo "OK") then {
    profileNamespace setVariable ["comspec_overwatch_beta_registered", true];
    if ((count _steamUid) >= 15) then {
        profileNamespace setVariable ["comspec_overwatch_saved_steam_uid", _steamUid];
        profileNamespace setVariable ["comspec_overwatch_beta_has_steam", true];
    };
    saveProfileNamespace;
    true
} else {
    false
};
