private _disp = findDisplay 93400;
if (isNull _disp) exitWith { false };

private _items = [];
private _store = [];

// Preuves du record courant + historiques d'actions bag/collect
private _rec = [] call comspec_sse_fnc_uiGetRecord;
if (!isNull _rec) then {
    private _sec = [_rec, "sections"] call comspec_sse_fnc_getSection;
    private _label = if (!isNil "comspec_sse_fnc_makeEvidenceLabel") then { [_rec] call comspec_sse_fnc_makeEvidenceLabel } else {"SSE"};
    private _coc = if (_sec isEqualType createHashMap) then { _sec getOrDefault ["chainOfCustody", []] } else { [] };
    private _estate = if (_sec isEqualType createHashMap) then { (_sec getOrDefault ["evidenceState", createHashMap]) getOrDefault ["state", "INTACT"] } else {"?"};
    private _bagged = if (_sec isEqualType createHashMap) then { _sec getOrDefault ["bagged", false] } else { false };
    private _entry = createHashMapFromArray [
        ["id", _label],
        ["entity", _rec],
        ["collector", if (_sec isEqualType createHashMap) then {_sec getOrDefault ["baggedBy", name player]} else {name player}],
        ["time", if (_sec isEqualType createHashMap) then {_sec getOrDefault ["baggedAt", time]} else {time}],
        ["pos", getPosATL _rec],
        ["state", _estate],
        ["bagged", _bagged],
        ["quality", 70],
        ["transfers", count _coc]
    ];
    _store pushBack _entry;
    _items pushBack format ["%1 | %2 | %3", _label, _estate, if (_bagged) then {"SCELLÉ"} else {"SUR SITE"}];
};

private _hist = if (!isNil "comspec_sse_fnc_getActionHistory") then { [] call comspec_sse_fnc_getActionHistory } else { [] };
{
    if (_x isEqualType createHashMap && {(_x getOrDefault ["action", ""]) in ["bag", "collect", "collect_media"]}) then {
        _items pushBack format ["HIST | %1 | %2 | %3", _x getOrDefault ["player","?"], _x getOrDefault ["action","?"], _x getOrDefault ["detail","?"]];
    };
} forEach _hist;

missionNamespace setVariable ["comspec_sse_uiEvidenceStore", _store];
private _lb = _disp displayCtrl 93410;
lbClear _lb;
{ _lb lbAdd _x; } forEach _items;
if (_items isEqualTo []) then { _lb lbAdd "(aucune preuve)"; };

private _detail = "<t color='#8f8'>CHAIN OF CUSTODY</t><br/>Sélectionnez une preuve.<br/>Champs: ID, collecteur, heure, position, état, qualité, transferts.";
if (count _store > 0) then {
    private _e = _store select 0;
    _detail = format [
        "<t color='#8f8'>PREUVE</t><br/>ID: %1<br/>Collecteur: %2<br/>Heure: %3<br/>Pos: %4<br/>État: %5<br/>Qualité: %6<br/>Transferts: %7<br/>Scellé: %8",
        _e getOrDefault ["id", "?"],
        _e getOrDefault ["collector", "?"],
        _e getOrDefault ["time", 0],
        mapGridPosition (_e getOrDefault ["pos", [0,0,0]]),
        _e getOrDefault ["state", "?"],
        _e getOrDefault ["quality", 0],
        _e getOrDefault ["transfers", 0],
        _e getOrDefault ["bagged", false]
    ];
};
(_disp displayCtrl 93411) ctrlSetStructuredText parseText _detail;
true
