/*
    Libellé humain d’un ordre C2.
    [_orderHashMap]  ou  [_type, _typeLabel]
    Types personnalisés (TYP_… / CUSTOM / TPL_…) ne tombent plus sur « Se déplacer ».
*/
params [
    ["_orderOrType", "MOVE"],
    ["_typeLabel", ""]
];

private _type = "MOVE";
private _custom = trim _typeLabel;

if (_orderOrType isEqualType createHashMap) then {
    _type = _orderOrType getOrDefault ["type", "MOVE"];
    if (_custom isEqualTo "") then {
        _custom = trim (_orderOrType getOrDefault ["typeLabel", ""]);
    };
} else {
    _type = _orderOrType;
};

if (_custom isNotEqualTo "") exitWith { _custom };

private _upper = toUpper (str _type);
switch (_upper) do {
    case "HOLD": { "Tenir la position" };
    case "RECON": { "Reconnaissance" };
    case "CAS": { "Appui aérien" };
    case "QRF": { "Force de réaction" };
    case "MOVE": { "Se déplacer" };
    case "FRAGO": { "Ordre fragmentaire" };
    case "CUSTOM": { "Ordre personnalisé" };
    case "VIBRATE": { "Faire vibrer le terminal" };
    case "NOTIFY": { "Notification terminal" };
    case "HELMET_SNAP": { "Photo casque" };
    case "HELMET_SNAP_HD": { "Photo casque HD" };
    case "HELMET_STREAM": { "Flux casque" };
    default {
        if (
            (_upper select [0, 4]) isEqualTo "TYP_"
            || {(_upper select [0, 4]) isEqualTo "TPL_"}
            || {(_upper select [0, 7]) isEqualTo "CUSTOM_"}
        ) then {
            "Ordre personnalisé"
        } else {
            "Se déplacer"
        };
    };
};
