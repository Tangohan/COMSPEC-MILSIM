/*
    Libellé humain d’un ordre C2.
    [_orderHashMap]  ou  [_type, _typeLabel]
    Types personnalisés (TYP_… / CUSTOM / TPL_…) ne tombent plus sur « Se déplacer ».

    Pas d’exitWith : appelé depuis des forEach TASK, un exitWith
    peut remonter au caller et laisser _typeLabel non assigné.
*/
params [
    ["_orderOrType", "MOVE"],
    ["_incomingLabel", ""]
];

private _type = "MOVE";
private _custom = trim _incomingLabel;

if (_orderOrType isEqualType createHashMap) then {
    _type = _orderOrType getOrDefault ["type", "MOVE"];
    if (_custom isEqualTo "") then {
        _custom = trim (_orderOrType getOrDefault ["typeLabel", ""]);
    };
} else {
    _type = _orderOrType;
};

if (_custom isNotEqualTo "") then {
    _custom
} else {
    private _upper = toUpper (str _type);
    private _labels = createHashMapFromArray [
        ["HOLD", "Tenir la position"],
        ["RECON", "Reconnaissance"],
        ["CAS", "Appui aérien"],
        ["QRF", "Force de réaction"],
        ["MOVE", "Se déplacer"],
        ["FRAGO", "Ordre fragmentaire"],
        ["CUSTOM", "Ordre personnalisé"],
        ["VIBRATE", "Faire vibrer le terminal"],
        ["NOTIFY", "Notification terminal"],
        ["HELMET_SNAP", "Photo casque"],
        ["HELMET_SNAP_HD", "Photo casque HD"],
        ["HELMET_STREAM", "Flux casque"],
        ["PHONE_GEOLOC", "Géolocalisation téléphone"],
        ["PHONE_GEOLOC_OFF", "Arrêt géolocalisation téléphone"]
    ];
    private _fallback = "Se déplacer";
    if (
        (_upper select [0, 4]) isEqualTo "TYP_"
        || {(_upper select [0, 4]) isEqualTo "TPL_"}
        || {(_upper select [0, 7]) isEqualTo "CUSTOM_"}
    ) then {
        _fallback = "Ordre personnalisé";
    };
    _labels getOrDefault [_upper, _fallback]
}
