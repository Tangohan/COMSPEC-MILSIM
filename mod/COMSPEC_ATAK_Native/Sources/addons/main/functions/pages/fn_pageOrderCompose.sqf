/*
    Rédaction d'un ordre depuis le téléphone (app Tâches > NOUVEL ORDRE), pour le chef d'unité :
    type (déplacement, maintien, reco, appui aérien, renfort, FRAGO), priorité, destinataire,
    consigne (ou rubriques SMEAC du FRAGO) et grille. Envoi par Overwatch connect (issueOrder) :
    l'ordre part vers Athena et arrive dans l'app Tâches des destinataires.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _d = uiNamespace getVariable ["COMSPEC_ATAK_OrderDraft", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_OrderDraft", _d];
private _kind = _d getOrDefault ["kind", "MOVE"];
private _seg = {
    params ["_key", "_opts"];
    _opts apply { [_x select 0, compile format ["['set', '%1', '%2'] call comspec_atak_native_fnc_orderAction;", _key, _x select 1], ((_d getOrDefault [_key, (_opts select 0) select 1]) isEqualTo (_x select 1))] }
};
// Destinataires : mon groupe, les autres groupes de mon camp ayant des joueurs, tout le monde.
private _targets = [[format ["Mon groupe (%1)", groupId group player], format ["group|%1|%1", groupId group player]]];
{
    if (_x isNotEqualTo group player && {(units _x) findIf { isPlayer _x } >= 0}) then {
        _targets pushBack [groupId _x, format ["group|%1|%1", groupId _x]];
    };
} forEach (groups side group player);
_targets pushBack ["Tout le monde", "all||Tous"];
private _rows = [
    ["section", "Nouvel ordre", format ["Émis par %1", groupId group player]],
    ["segment", "Type", [["kind", [["DÉPLACER", "MOVE"], ["TENIR", "HOLD"], ["RECO", "RECON"]]] call _seg] select 0],
    ["segment", "", [["kind", [["APPUI AÉRIEN", "CAS"], ["RENFORT", "QRF"], ["FRAGO", "FRAGO"]]] call _seg] select 0],
    ["segment", "Priorité", [["prio", [["ROUTINE", "ROUTINE"], ["IMPORTANT", "IMPORTANT"], ["URGENT", "URGENT"], ["FLASH", "FLASH"]]] call _seg] select 0],
    ["combo", "target", "Destinataire", _targets apply { [_x select 0, _x select 1] }, _d getOrDefault ["target", (_targets select 0) select 1]],
    ["edit", "grid", "Grille de l'objectif (facultatif)", _d getOrDefault ["grid", ""]],
    ["buttons", [["MA POSITION", { ['grid'] call comspec_atak_native_fnc_orderAction; }]]]
];
if (_kind isEqualTo "FRAGO") then {
    _rows append [
        ["memo", "sit", "Situation", _d getOrDefault ["sit", ""], 2],
        ["memo", "mis", "Mission", _d getOrDefault ["mis", ""], 2],
        ["memo", "exe", "Exécution", _d getOrDefault ["exe", ""], 2],
        ["memo", "sup", "Soutien", _d getOrDefault ["sup", ""], 2],
        ["memo", "cmd", "Commandement", _d getOrDefault ["cmd", ""], 2]
    ];
} else {
    _rows pushBack ["memo", "text", "Consigne", _d getOrDefault ["text", ""], 3];
};
private _hint = uiNamespace getVariable ["COMSPEC_ATAK_OrderHint", ""];
if (_hint isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='#e8b84a'>%1</t>", _hint]]; };
_rows pushBack ["buttons", [["ANNULER", { ['cancel'] call comspec_atak_native_fnc_orderAction; }], ["ENVOYER L'ORDRE", { ['send'] call comspec_atak_native_fnc_orderAction; }, true]]];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
