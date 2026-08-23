/*
    Remplace narrativement l'objet par une preuve « bagged » (conserve record).
    [_entity, _player] call comspec_sse_fnc_bagEvidence
*/
params [
    ["_entity", objNull, [objNull]],
    ["_player", player, [objNull]]
];

if (isNull _entity) exitWith { false };
if !([_player, "evidence_bag"] call comspec_sse_fnc_hasEquipment) exitWith {
    hint "Sachet de preuve requis.";
    false
};

[_entity] call comspec_sse_fnc_ensureGenerated;
private _label = [_entity] call comspec_sse_fnc_makeEvidenceLabel;
private _sec = [_entity, "sections"] call comspec_sse_fnc_getSection;
if (isNil "_sec" || {!(_sec isEqualType createHashMap)}) then { _sec = createHashMap; };
_sec set ["bagged", true];
_sec set ["evidenceLabel", _label];
_sec set ["baggedBy", name _player];
_sec set ["baggedAt", time];
[_entity, "sections", _sec, true] call comspec_sse_fnc_setSection;

if (_player canAdd "COMSPEC_SSE_EvidenceBag") then {
    _player addItem "COMSPEC_SSE_EvidenceBag";
};

[_entity, _player, "bag", _label] call comspec_sse_fnc_registerActionHistory;
["SSE_RecordCollected", [_entity, _label]] call comspec_sse_fnc_emitEvent;
hint format ["Preuve mise sous scellé\n%1", _label];
true
