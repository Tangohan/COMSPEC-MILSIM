/*
    Message joueur français pour un échec extension ATAK (sans code brut).
    Params: [_detail, _fallback]
*/
params [
    ["_detail", "", [""]],
    ["_fallback", "Action impossible — vérifiez la liaison Athena.", [""]]
];

private _d = toLower (str _detail);
if (_d isEqualTo "" || {_d isEqualTo "0"} || {_d isEqualTo "success"}) exitWith { _fallback };

if (_d find "unauthorized" >= 0 || {_d find "401" >= 0} || {_d find "403" >= 0}) exitWith {
    "Liaison Athena refusée — vérifiez votre compte."
};
if (_d find "not_connected" >= 0 || {_d find "not connected" >= 0}) exitWith {
    "Pas de liaison Athena — connectez le mod puis réessayez."
};
if (_d find "timeout" >= 0) exitWith {
    "Délai dépassé — réessayez dans un instant."
};
if (_d find "network" >= 0) exitWith {
    "Réseau indisponible — vérifiez votre connexion."
};
if (_d find "migration" >= 0) exitWith {
    "Athena est en maintenance — réessayez plus tard."
};
if (_d find "store_failed" >= 0 || {_d find "http 500" >= 0} || {_d find "http 502" >= 0} || {_d find "http 503" >= 0}) exitWith {
    "Athena n’a pas pu enregistrer la demande — réessayez dans un instant."
};
if (_d find "payload empty" >= 0) exitWith {
    "Données incomplètes — réessayez."
};
if (_d find "vehicle_callsign" >= 0) exitWith {
    "Véhicule non identifié — réessayez depuis un véhicule valide."
};

_fallback
