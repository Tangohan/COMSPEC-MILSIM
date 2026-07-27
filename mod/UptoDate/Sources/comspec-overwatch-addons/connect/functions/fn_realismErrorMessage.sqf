/*
    Message joueur français pour un échec terminal / certificat Athena.
    Params: [_code, _fallback]
*/
params [
    ["_code", "", [""]],
    ["_fallback", "Enregistrement du terminal impossible — réessayez plus tard.", [""]]
];

private _c = toLower (trim _code);
if (_c isEqualTo "") exitWith { _fallback };

switch (true) do {
    case (_c isEqualTo "not_connected"): {
        "Liaison Athena non établie — reliez votre compte puis réessayez."
    };
    case (_c isEqualTo "unauthorized"): {
        "Accès refusé — vérifiez que votre compte Athena est bien lié."
    };
    case (_c isEqualTo "pairing_invalid"): {
        "Code de connexion téléphone expiré ou invalide — générez-en un nouveau."
    };
    case (_c isEqualTo "pairing_disabled"): {
        "L’enregistrement automatique du terminal est désactivé par votre communauté."
    };
    case (_c isEqualTo "missing_terminal_uid" || {_c isEqualTo "missing_terminal_id"}): {
        "Terminal non identifié — relancez la mission ou reconnectez Athena."
    };
    case (_c find "timeout" >= 0): {
        "Délai dépassé — vérifiez votre connexion réseau."
    };
    case (_c find "network" >= 0): {
        "Impossible de joindre Athena — vérifiez votre réseau."
    };
    case (_c find "http_" == 0): {
        "Athena n’a pas pu traiter la demande — réessayez dans un instant."
    };
    default { _fallback };
};
