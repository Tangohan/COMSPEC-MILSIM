/*
    Ajoute une entrée preuve BII dans chainOfCustody / documents SSE.
    [_personEntity, _evidenceEntry] call comspec_sse_fnc_biiImportEvidenceEntry
*/
params [
    ["_entity", objNull, [objNull]],
    ["_entry", [], [[]]]
];

if (isNull _entity || {_entry isEqualTo []}) exitWith { false };

private _evId = _entry param [0, ""];
private _displayName = _entry param [1, "Preuve"];
private _className = _entry param [2, ""];
private _grid = _entry param [3, ""];
private _collector = _entry param [5, ""];
private _dtg = _entry param [6, ""];
private _lead = _entry param [7, ""];
private _priority = _entry param [8, "Normal"];
private _linkedName = _entry param [9, ""];

private _coc = [_entity, "chainOfCustody"] call comspec_sse_fnc_getSection;
if (isNil "_coc" || {!(_coc isEqualType [])}) then { _coc = []; };

private _already = false;
{
    if ((_x isEqualType createHashMap) && {(_x getOrDefault ["biiEvidenceId", ""]) isEqualTo _evId}) then {
        _already = true;
    };
} forEach _coc;
if (_already) exitWith { true };

private _row = createHashMapFromArray [
    ["biiEvidenceId", _evId],
    ["label", _displayName],
    ["className", _className],
    ["grid", _grid],
    ["collectedBy", _collector],
    ["collectedAt", _dtg],
    ["lead", _lead],
    ["priority", _priority],
    ["linkedName", _linkedName],
    ["source", "BII"]
];
_coc pushBack _row;
[_entity, "chainOfCustody", _coc, true] call comspec_sse_fnc_setSection;

if (_lead isNotEqualTo "" || {_linkedName isNotEqualTo ""}) then {
    private _docs = [_entity, "documents"] call comspec_sse_fnc_getSection;
    if (isNil "_docs" || {!(_docs isEqualType [])}) then { _docs = []; };
    _docs pushBack createHashMapFromArray [
        ["uid", _evId],
        ["title", _displayName],
        ["summary", [_lead, _linkedName] select (_lead isEqualTo "")],
        ["grid", _grid],
        ["source", "BII"]
    ];
    [_entity, "documents", _docs, true] call comspec_sse_fnc_setSection;
};

true
