/*
    Met la pièce sous scellé : consomme un sachet de preuve de l'inventaire du joueur, marque la fiche et inscrit le
    scellé au registre. Refusé sans sachet. L'action DONNAIT auparavant un sachet : source infinie, même sur une
    pièce déjà scellée.
    [_entity, _player] call comspec_sse_fnc_bagEvidence
*/
params [
    ["_entity", objNull, [objNull]],
    ["_player", player, [objNull]]
];

if (isNull _entity) exitWith { false };
if !("COMSPEC_SSE_EvidenceBag" in (items _player)) exitWith {
    ["Sachet de preuve SSE requis pour mettre la pièce sous scellé.", "WARNING"] call comspec_sse_fnc_notify;
    false
};
private _state = [_entity, "evidenceState"] call comspec_sse_fnc_getSection;
if (!isNil "_state" && {_state isEqualType createHashMap} && {_state getOrDefault ["bagged", false]}) exitWith {
    [format ["Pièce déjà sous scellé (%1).", _state getOrDefault ["evidenceLabel", "?"]], "WARNING"] call comspec_sse_fnc_notify;
    false
};

// Le sachet est consommé (l'unité du joueur est locale ici).
_player removeItem "COMSPEC_SSE_EvidenceBag";

[_entity] call comspec_sse_fnc_ensureGenerated;
private _label = [_entity] call comspec_sse_fnc_makeEvidenceLabel;
// Section evidenceState (nom canonique), fusionnée par le serveur : une section « sections » imbriquée était créée.
["merge", _entity, ["evidenceState", createHashMapFromArray [
    ["bagged", true],
    ["evidenceLabel", _label],
    ["baggedBy", name _player],
    ["baggedAt", time]
]]] call comspec_sse_fnc_recordOp;

[_entity, _player, "bag", _label] call comspec_sse_fnc_registerActionHistory;
["SSE_RecordCollected", [_entity, _label]] call comspec_sse_fnc_emitEvent;
// Registre des scellés : numéro de scellé et ligne au journal de la pièce (le serveur répond).
if (!isNil "comspec_sse_fnc_custody") exitWith { ["seal", [_entity, _player, ""]] call comspec_sse_fnc_custody; true };
[format ["Preuve mise sous scellé\n%1", _label], "SUCCESS"] call comspec_sse_fnc_notify;
true
