/*
    App Reco : note de reconnaissance rapide (type, confiance, observation de 140 caractères),
    posée sous le regard du joueur ou sur sa position, avec repère d'équipe, envoyée à Athena (/api/recon/notes)
    par la fonction d'Overwatch (reconPushNote). Les dernières notes de la session sont listées dessous.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
if !([] call comspec_atak_native_fnc_bridge) exitWith {
    [[["title", "Reco"], ["text", "<t color='#8a9a93'>Les notes de reco partent vers Athena par COMSPEC Overwatch, qui n'est pas chargé sur ce serveur.</t>"]], [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
private _draft = uiNamespace getVariable ["COMSPEC_ATAK_RecoDraft", createHashMap];
private _hint = uiNamespace getVariable ["COMSPEC_ATAK_RecoHint", ["", false]];
// Coordonnées saisies à la main (grille 6, 8 ou 10 chiffres), ma position par défaut.
private _rows = [
    ["title", "Note de reco"],
    ["edit", "grid", "Coordonnées (grille, ex. 1518 1730)", _draft getOrDefault ["grid", [getPosASL player, 8] call comspec_atak_native_fnc_gridRef]],
    ["buttons", [["MA POSITION", { [] call comspec_atak_native_fnc_recoDraftSave; (uiNamespace getVariable ["COMSPEC_ATAK_RecoDraft", createHashMap]) set ["grid", [getPosASL player, 8] call comspec_atak_native_fnc_gridRef]; [{ ["RECO"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }]]],
    ["combo", "tag", "Type", [["Véhicule", "vehicle"], ["Groupe armé", "armed_group"], ["Position statique", "static"], ["Obstacle / mine", "mine"], ["Civil", "civilian"], ["Infrastructure", "infrastructure"], ["Autre", "other"]], _draft getOrDefault ["tag", "armed_group"]],
    ["combo", "confidence", "Confiance", [["Vu directement", "vu_direct"], ["Rapporté", "rapporte"]], _draft getOrDefault ["confidence", "vu_direct"]],
    ["edit", "text", "Observation (140 caractères)", _draft getOrDefault ["text", ""]],
    ["buttons", [["ENVOYER LA NOTE", { [{ [] call comspec_atak_native_fnc_recoSubmit; }] call CBA_fnc_execNextFrame; }, true]]]
];
if ((_hint select 0) isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='%1'>%2</t>", ["#7aa89a", "#e8b84a"] select (_hint select 1), _hint select 0]]; };
_rows pushBack ["title", "Dernières notes"];
private _log = missionNamespace getVariable ["COMSPEC_ReconNoteLog", []];
if ((count _log) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune note pendant cette session.</t>"]; };
{
    _x params [["_tag", ""], ["_text", ""], ["_grid", ""]];
    _rows pushBack ["text", format ["<t color='#5cc76b' font='RobotoCondensedBold'>%1</t>  <t color='#8a9a93'>%2</t><br/>%3", _tag, _grid, _text]];
} forEach _log;
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
