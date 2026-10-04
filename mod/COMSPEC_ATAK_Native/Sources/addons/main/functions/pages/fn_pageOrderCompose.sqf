/*
    Rédaction d'un ordre depuis le téléphone (app Tâches > NOUVEL ORDRE), pour le chef d'unité :
    type (déplacement, maintien, reco, DEM-SSE, appui aérien, renfort, FRAGO), priorité, destinataire,
    consigne (ou rubriques SMEAC du FRAGO) et grille. Envoi par COMSPEC Link (issueOrder) :
    l'ordre part vers Athena et arrive dans l'app Tâches des destinataires.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _d = uiNamespace getVariable ["COMSPEC_ATAK_OrderDraft", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_OrderDraft", _d];
private _kind = _d getOrDefault ["kind", "MOVE"];
private _seg = {
    // Défaut explicite : le type est réparti sur plusieurs lignes, sans quoi chaque ligne allumait sa première case.
    params ["_key", "_opts", ["_def", ""]];
    if (_def isEqualTo "") then { _def = (_opts select 0) select 1; };
    _opts apply { [_x select 0, compile format ["['set', '%1', '%2'] call comspec_atak_native_fnc_orderAction;", _key, _x select 1], ((_d getOrDefault [_key, _def]) isEqualTo (_x select 1))] }
};
// Destinataires : mon groupe (celui d'Athena), les autres groupes de mon camp ayant des joueurs, tout le monde.
private _mine = [player] call comspec_atak_native_fnc_unitGroup;
private _targets = [[format ["Mon groupe (%1)", _mine], format ["group|%1|%1", _mine]]];
{
    private _g = [_x] call comspec_atak_native_fnc_unitGroup;
    if (_g isNotEqualTo _mine && {(_targets findIf { (_x select 1) isEqualTo format ["group|%1|%1", _g] }) < 0}) then {
        _targets pushBack [_g, format ["group|%1|%1", _g]];
    };
} forEach ((allPlayers - [player]) select { side group _x isEqualTo side group player });
_targets pushBack ["Tout le monde", "all||Tous"];
private _rows = [
    ["section", "Nouvel ordre", format ["Émis par %1", ([[player, true] call comspec_atak_native_fnc_unitCallsign, _mine] select { _x isNotEqualTo "" }) joinString " · "]],
    ["segment", "Type", ["kind", [["DÉPLACER", "MOVE"], ["TENIR", "HOLD"], ["RECO", "RECON"], ["DEM-SSE", "DEMSSE"]], "MOVE"] call _seg],
    ["segment", "", ["kind", [["APPUI AÉRIEN", "CAS"], ["RENFORT", "QRF"], ["FRAGO", "FRAGO"]], "MOVE"] call _seg],
    ["segment", "Priorité", ["prio", [["ROUTINE", "ROUTINE"], ["IMPORTANT", "IMPORTANT"], ["URGENT", "URGENT"], ["FLASH", "FLASH"]]] call _seg],
    ["combo", "target", "Destinataire", _targets apply { [_x select 0, _x select 1] }, _d getOrDefault ["target", (_targets select 0) select 1]],
    ["edit", "grid", "Grille de l'objectif (facultatif)", _d getOrDefault ["grid", ""]],
    ["buttons", [["MA POSITION", { ['grid'] call comspec_atak_native_fnc_orderAction; }]]]
];
if (_kind isEqualTo "DEMSSE") then {
    // Demande d'exploitation de site : ce qu'il faut fouiller, quoi collecter en priorité, la menace, le dossier SSE de rattachement.
    private _case = if (!isNil "comspec_overwatch_connect_fnc_sseActiveCase") then { ["get"] call comspec_overwatch_connect_fnc_sseActiveCase } else { "" };
    _rows append [
        ["section", "Demande d'exploitation de site", "Ce que l'équipe SSE doit fouiller et rapporter"],
        ["segment", "Nature du site", ["sseSite", [["BÂTIMENT", "BATIMENT"], ["VÉHICULE", "VEHICULE"], ["PERSONNE", "PERSONNE"], ["CACHE", "CACHE"]]] call _seg],
        ["segment", "Collecte prioritaire", ["sseWant", [["DOCUMENTS", "DOCUMENTS"], ["NUMÉRIQUE", "NUMERIQUE"], ["BIOMÉTRIE", "BIOMETRIE"], ["ARMEMENT", "ARMEMENT"]]] call _seg],
        ["segment", "Menace sur site", ["sseThreat", [["AUCUNE CONNUE", "AUCUNE"], ["IED SUSPECTÉ", "IED"], ["HOSTILES PROCHES", "HOSTILES"]]] call _seg],
        ["edit", "sseCase", "Dossier SSE (référence du poste)", _d getOrDefault ["sseCase", _case]],
        ["memo", "text", "Consigne (délai, points d'attention)", _d getOrDefault ["text", ""], 3]
    ];
} else { if (_kind isEqualTo "FRAGO") then {
    _rows append [
        ["memo", "sit", "Situation", _d getOrDefault ["sit", ""], 2],
        ["memo", "mis", "Mission", _d getOrDefault ["mis", ""], 2],
        ["memo", "exe", "Exécution", _d getOrDefault ["exe", ""], 2],
        ["memo", "sup", "Soutien", _d getOrDefault ["sup", ""], 2],
        ["memo", "cmd", "Commandement", _d getOrDefault ["cmd", ""], 2]
    ];
} else {
    _rows pushBack ["memo", "text", "Consigne", _d getOrDefault ["text", ""], 3];
}; };
private _hint = uiNamespace getVariable ["COMSPEC_ATAK_OrderHint", ""];
if (_hint isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='#e8b84a'>%1</t>", _hint]]; };
_rows pushBack ["buttons", [["ANNULER", { ['cancel'] call comspec_atak_native_fnc_orderAction; }], ["ENVOYER L'ORDRE", { ['send'] call comspec_atak_native_fnc_orderAction; }, true]]];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
