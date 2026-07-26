/*
    Demande le profil site (nom, callsign, photo, unité, identifiant ATAK, activité, MID) du joueur
    courant à la plateforme (extension native, fonction GetPlayerAvatarInfo), identifié par son
    SteamUID + l'identifiant de communauté obtenu lors de la liaison de compte (RedeemGameLink).

    Retourne : [displayName, callsign, avatarUrl, unitName, atakId, playtimeHours, lastSeenAt, militaryId, errorCode]
*/
if (!hasInterface) exitWith { [] };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { [] };

private _steamUid = getPlayerUID player;
if (_steamUid isEqualTo "") exitWith { [] };

private _tenantId = missionNamespace getVariable ["comspec_overwatch_tenant_id", ""];
private _raw = ["COMSPECExtension" callExtension ["GetPlayerAvatarInfo", [_steamUid, _tenantId]]] call comspec_overwatch_connect_fnc_extResult;
private _parts = _raw splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };

if (_prefix != "OK") exitWith {
    private _err = if (count _parts >= 2) then { _parts select 1 } else { "empty" };
    if (_err isEqualTo "not_found") then { _err = "not_linked"; };
    diag_log format ["[COMSPEC] Échec GetPlayerAvatarInfo : %1", _raw];
    ["", "", "", "", "", "", "", "", _err]
};

private _payload = if (count _parts >= 2) then { _parts select 1 } else { "" };
private _cols = _payload splitString (toString [9]);
if (count _cols < 3) exitWith { ["", "", "", "", "", "", "", "", "invalid_response"] };

[
    _cols select 0,
    _cols select 1,
    _cols select 2,
    if (count _cols >= 4) then { _cols select 3 } else { "" },
    if (count _cols >= 5) then { _cols select 4 } else { "" },
    if (count _cols >= 6) then { _cols select 5 } else { "" },
    if (count _cols >= 7) then { _cols select 6 } else { "" },
    if (count _cols >= 8) then { _cols select 7 } else { "" },
    ""
]
