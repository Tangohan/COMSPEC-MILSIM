/*
    Libellé français pour l’état du certificat terminal (UI joueur).
    Params: [_status, _expiresAt]
*/
params [
    ["_status", "", [""]],
    ["_expiresAt", "", [""]]
];

private _s = toLower (trim _status);
private _label = switch (_s) do {
    case "active";
    case "issued": { "Certificat actif" };
    case "expired": { "Certificat expiré — reconnectez Athena" };
    case "revoked": { "Certificat révoqué — contactez votre admin" };
    case "missing": { "Certificat non délivré" };
    case "pending": { "Certificat en cours de délivrance" };
    default {
        if (_s isEqualTo "") then { "Certificat inconnu" } else { format ["Certificat : %1", _s] };
    };
};

if (_expiresAt isNotEqualTo "" && {_s in ["active", "issued"]}) then {
    format ["%1 (valide jusqu’au %2)", _label, _expiresAt]
} else {
    _label
};
