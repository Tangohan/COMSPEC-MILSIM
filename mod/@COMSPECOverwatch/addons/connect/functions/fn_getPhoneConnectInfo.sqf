/*
    Demande un nouveau pairing "connexion téléphone" (inspiré de cTab) à la plateforme (extension
    native, fonction GetPhoneConnectInfo) : token, code court lisible, URL de connexion, URL du
    QR code (image), date d'expiration.

    Variable mission optionnelle : comspec_overwatch_tenant_id (déploiement multi-communauté).

    Retourne : [token, code, connectUrl, qrImageUrl, expiresAt] ou [] en cas d'échec.
    En cas d'échec, stocke un libellé lisible dans COMSPEC_PhoneConnectLastError.
*/
if (!hasInterface) exitWith { [] };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { [] };

missionNamespace setVariable ["COMSPEC_PhoneConnectLastError", "", false];

private _tenantId = missionNamespace getVariable ["comspec_overwatch_tenant_id", ""];
private _raw = ["COMSPECExtension" callExtension ["GetPhoneConnectInfo", [_tenantId]]] call comspec_overwatch_connect_fnc_extResult;
private _parts = _raw splitString "|";
private _prefix = if (count _parts >= 1) then { _parts select 0 } else { "" };

if (_prefix != "OK") exitWith {
    private _code = if (count _parts >= 2) then { _parts select 1 } else { _raw };
    private _msg = switch (_code) do {
        case "not_connected": { "Liaison Athena non établie — vérifiez l’URL dans les options du mod, puis relancez la mission." };
        case "not_found": { "Adresse Athena incorrecte ou service indisponible (page introuvable). Utilisez https://athena.ttrd.fr/public sans slash final." };
        case "unauthorized": { "Accès refusé — renseignez la clé d’accès Athena dans les options du mod (fournie par votre admin)." };
        case "no_tenant": { "Communauté non indiquée côté serveur — contactez l’admin Athena (identifiant de communauté manquant)." };
        case "unavailable": { "Service temporairement indisponible — réessayez dans un instant." };
        case "timeout": { "Délai dépassé — vérifiez votre connexion réseau." };
        case "network": { "Impossible de joindre Athena — vérifiez l’URL et votre réseau." };
        case "invalid_response": { "Réponse inattendue d’Athena — contactez l’admin si le problème persiste." };
        default {
            if (_code find "http_" == 0) then {
                private _httpCode = _code select [5, (count _code) - 5];
                format ["Erreur serveur Athena (%1) — vérifiez l’URL et la clé d’accès.", _httpCode]
            } else {
                "Connexion téléphone indisponible pour le moment."
            }
        };
    };
    missionNamespace setVariable ["COMSPEC_PhoneConnectLastError", _msg, false];
    diag_log format ["[COMSPEC] Échec GetPhoneConnectInfo : %1 → %2", _raw, _msg];
    []
};

private _payload = if (count _parts >= 2) then { _parts select 1 } else { "" };
private _cols = _payload splitString "\t";
if (count _cols < 4) exitWith {
    missionNamespace setVariable ["COMSPEC_PhoneConnectLastError", "Réponse incomplète d’Athena.", false];
    []
};

[
    _cols select 0,
    _cols select 1,
    _cols select 2,
    _cols select 3,
    if (count _cols >= 5) then { _cols select 4 } else { "" }
]
