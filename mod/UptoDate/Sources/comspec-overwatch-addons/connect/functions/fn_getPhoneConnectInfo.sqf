/*
    Demande un nouveau pairing "connexion téléphone" (inspiré de cTab) à la plateforme (extension
    native, fonction GetPhoneConnectInfo) : token, code court lisible, URL de connexion, URL du
    QR code (image), date d'expiration.

    Variable mission optionnelle : comspec_overwatch_tenant_id (déploiement multi-communauté).

    Retourne : [token, code, connectUrl, qrImageUrl, expiresAt] ou [] en cas d'échec.
    En cas d'échec, stocke un libellé lisible dans COMSPEC_PhoneConnectLastError.

    IMPORTANT SQF : splitString "\t" ne coupe PAS sur tabulation — "\t" = caractères \ et t
    (donc https://athena… → fragments dont "ps://a"). Utiliser toString [9] (comme [10] pour LF).
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
        case "not_connected": { "Liaison Athena non établie — liez votre compte (touche K → Compte Athena) ou vérifiez l’URL du mod, puis réessayez." };
        case "not_found": { "Adresse Athena incorrecte ou service indisponible (page introuvable). Utilisez https://athena.ttrd.fr/public sans slash final." };
        case "unauthorized": { "Accès refusé — liez votre compte Athena en jeu, ou renseignez la clé d’accès fournie par votre admin." };
        case "mod_steam_blocked": { "Accès au mod refusé pour cet identifiant Steam — contactez un administrateur de la communauté." };
        case "mod_ip_blocked": { "Accès au mod refusé depuis cette adresse réseau — contactez un administrateur de la communauté." };
        case "no_tenant": { "Communauté non reconnue — reliez votre compte Athena en jeu (code de liaison), puis réessayez « Connecter mon téléphone »." };
        case "not_enabled": { "Connexion téléphone pas encore activée sur le serveur — contactez un administrateur Athena." };
        case "unavailable": { "Connexion téléphone temporairement indisponible — réessayez dans un instant." };
        case "timeout": { "Délai dépassé — vérifiez votre connexion réseau." };
        case "network": { "Impossible de joindre Athena — vérifiez l’URL et votre réseau." };
        case "invalid_response": { "Réponse inattendue d’Athena — contactez l’admin si le problème persiste." };
        default {
            if (_code find "http_" == 0) then {
                private _httpCode = _code select [5, (count _code) - 5];
                format ["Erreur serveur Athena (%1) — vérifiez la liaison du compte et la clé d’accès.", _httpCode]
            } else {
                "Connexion téléphone indisponible — reliez votre compte Athena si ce n’est pas déjà fait, puis réessayez."
            }
        };
    };
    missionNamespace setVariable ["COMSPEC_PhoneConnectLastError", _msg, false];
    diag_log format ["[COMSPEC] Échec GetPhoneConnectInfo : %1 → %2", _raw, _msg];
    []
};

// Payload après le premier "OK|" : rejoindre le reste au cas où un champ contiendrait "|" (ne devrait pas).
private _payload = _raw select [3, (count _raw) - 3];
private _tab = toString [9];
private _cols = _payload splitString _tab;
if (count _cols < 4) exitWith {
    missionNamespace setVariable ["COMSPEC_PhoneConnectLastError", "Réponse incomplète d’Athena.", false];
    diag_log format ["[COMSPEC] GetPhoneConnectInfo payload incomplet (%1 cols) : %2", count _cols, _payload];
    []
};

private _token = _cols select 0;
private _shortCode = toUpper (_cols select 1);
private _connectUrl = _cols select 2;
private _qrImageUrl = _cols select 3;
private _expiresAt = if (count _cols >= 5) then { _cols select 4 } else { "" };

// Garde-fou : le code court doit rester alphanumérique (pas un fragment d’URL type ps://a).
private _codeOk = (count _shortCode >= 4) && (count _shortCode <= 12) && (_shortCode find "://" < 0) && (_shortCode find "/" < 0) && (_shortCode find "." < 0);
if (!_codeOk) exitWith {
    missionNamespace setVariable ["COMSPEC_PhoneConnectLastError", "Code de connexion invalide reçu — réessayez ou contactez un admin Athena.", false];
    diag_log format ["[COMSPEC] GetPhoneConnectInfo code suspect : %1 (raw cols=%2)", _shortCode, count _cols];
    []
};
if (_connectUrl isEqualTo "") exitWith {
    missionNamespace setVariable ["COMSPEC_PhoneConnectLastError", "Adresse mobile manquante dans la réponse Athena — réessayez.", false];
    diag_log "[COMSPEC] GetPhoneConnectInfo : connectUrl vide";
    []
};

[_token] spawn {
    params ["_pairingToken"];
    uiSleep 0.3;
    [_pairingToken, true] call comspec_overwatch_connect_fnc_syncAtakRealism;
};

[_token, _shortCode, _connectUrl, _qrImageUrl, _expiresAt]
