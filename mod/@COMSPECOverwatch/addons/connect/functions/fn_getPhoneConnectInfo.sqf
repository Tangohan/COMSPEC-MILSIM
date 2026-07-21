/*
    Demande un nouveau pairing "connexion téléphone" (inspiré de cTab) à la plateforme (extension
    native, fonction GetPhoneConnectInfo) : token, code court lisible, URL de connexion, URL du
    QR code (image), date d'expiration.

    Variable mission optionnelle : comspec_overwatch_tenant_id (déploiement multi-communauté).

    Retourne : [token, code, connectUrl, qrImageUrl, expiresAt] ou [] en cas d'échec.
*/
if (!hasInterface) exitWith { [] };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { [] };

private _tenantId = missionNamespace getVariable ["comspec_overwatch_tenant_id", ""];
private _raw = ["COMSPECExtension" callExtension ["GetPhoneConnectInfo", [_tenantId]]] call comspec_overwatch_connect_fnc_extResult;
private _parts = _raw splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };

if (_prefix != "OK") exitWith {
    diag_log format ["[COMSPEC] Échec GetPhoneConnectInfo : %1", _raw];
    []
};

private _payload = if (count _parts >= 2) then { _parts select 1 } else { "" };
private _cols = _payload splitString "\t";
if (count _cols < 4) exitWith { [] };

[
    _cols select 0,
    _cols select 1,
    _cols select 2,
    _cols select 3,
    if (count _cols >= 5) then { _cols select 4 } else { "" }
]
