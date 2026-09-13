/*
    Cycle de vie ordre C2 : Émis/Reçu → Confirmé → En cours → Échec/Annulé.
    Params: [_currentStatus, _newStatus]
    Returns: bool
*/
params ["_current", "_new"];

_current = toUpper (trim _current);
_new = toUpper (trim _new);

if (_current isEqualTo _new) exitWith { true };
if (_current isEqualTo "CANCELLED") exitWith { false };

if (_new isEqualTo "CANCELLED") exitWith {
    !(_current in ["CANCELLED", "FAILED"])
};

switch (_new) do {
    case "ACK": { _current in ["PENDING", "DELIVERED"] };
    case "EXEC": { _current isEqualTo "ACK" };
    case "FAILED": { _current in ["PENDING", "DELIVERED", "ACK", "EXEC"] };
    case "DELIVERED": { _current isEqualTo "PENDING" };
    default { false };
};
