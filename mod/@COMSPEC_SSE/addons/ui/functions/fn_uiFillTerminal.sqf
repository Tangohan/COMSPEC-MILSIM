/*
    Remplit le Terminal SSE terrain.
*/
private _disp = findDisplay 93200;
if (isNull _disp) exitWith { false };

private _rec = [] call comspec_sse_fnc_uiGetRecord;
private _nav = "<t size='0.85' color='#8f8'>TERMINAL</t>  ·  Digital · SEEK · Site · Graph · Preuves · Mission";
(_disp displayCtrl 93210) ctrlSetStructuredText parseText _nav;

private _sum = "";
private _detail = "";
private _entries = [];

if (isNull _rec) then {
    _sum = "<t color='#8f8'>Aucun record ciblé</t><br/>Placez le viseur sur une entité SSE ou ouvrez depuis une interaction.<br/><br/>Le terminal affiche aussi le journal mission et les transmissions.";
    private _journal = if (!isNil "comspec_sse_fnc_getJournal") then { [] call comspec_sse_fnc_getJournal } else { [] };
    {
        if (_x isEqualType createHashMap) then {
            _entries pushBack format ["%1 | %2", _x getOrDefault ["action", "?"], _x getOrDefault ["detail", ""]];
        };
    } forEach (_journal select [0, (count _journal) min 20]);
    _detail = format ["<t color='#8f8'>Journal local</t><br/>%1 entrée(s)", count _journal];
} else {
    [_rec] call comspec_sse_fnc_ensureGenerated;
    private _data = [_rec] call comspec_sse_fnc_getData;
    private _uid = [_data, "uid", "?"] call BIS_fnc_getFromPairs;
    private _type = [_data, "type", "?"] call BIS_fnc_getFromPairs;
    private _lvl = if (!isNil "comspec_sse_fnc_getExploitationLevel") then { [_rec] call comspec_sse_fnc_getExploitationLevel } else {"?"};
    private _label = if (!isNil "comspec_sse_fnc_makeEvidenceLabel") then { [_rec] call comspec_sse_fnc_makeEvidenceLabel } else {_uid};
    private _id = [_rec, "identity"] call comspec_sse_fnc_getSection;
    private _name = if (_id isEqualType createHashMap) then { _id getOrDefault ["name", ""] } else {""};
    private _alias = if (_id isEqualType createHashMap) then { _id getOrDefault ["alias", ""] } else {""};
    private _access = [_rec, "sections"] call comspec_sse_fnc_getSection;
    private _acc = if (_access isEqualType createHashMap) then { (_access getOrDefault ["accessState", createHashMap]) getOrDefault ["state", "OPEN"] } else {"?"};
    private _estate = if (_access isEqualType createHashMap) then { (_access getOrDefault ["evidenceState", createHashMap]) getOrDefault ["state", "INTACT"] } else {"?"};

    _sum = format [
        "<t color='#8f8'>RECORD SSE</t><br/>UID: %1<br/>Type: %2<br/>Niveau: %3<br/>Identité: %4<br/>Alias: %5<br/>Accès: %6<br/>État: %7<br/>Label: %8",
        _uid, _type, _lvl, _name, _alias, _acc, _estate, _label
    ];

    private _devices = [_rec, "digitalDevices"] call comspec_sse_fnc_getSection;
    if (_devices isEqualType []) then {
        { _entries pushBack format ["DEVICE | %1", if (_x isEqualType createHashMap) then {_x getOrDefault ["deviceType", "DEV"]} else {"?"}]; } forEach _devices;
    };
    private _docs = [_rec, "documents"] call comspec_sse_fnc_getSection;
    if (_docs isEqualType []) then {
        { _entries pushBack format ["DOC | %1", if (_x isEqualType createHashMap) then {_x getOrDefault ["title", "Doc"]} else {"?"}]; } forEach _docs;
    };
    private _intel = if (!isNil "comspec_sse_fnc_getRevealedIntel") then { [_rec] call comspec_sse_fnc_getRevealedIntel } else { [] };
    { _entries pushBack format ["INTEL | %1", if (_x isEqualType createHashMap) then {_x getOrDefault ["text", ""]} else {"?"}]; } forEach (_intel select [0, 8]);

    private _tx = "LOCAL";
    if (!isNil "comspec_sse_fnc_isOnline") then {
        _tx = if ([] call comspec_sse_fnc_isOnline) then {"ONLINE"} else {"OFFLINE QUEUE"};
    };
    _detail = format ["<t color='#8f8'>Transmission</t><br/>Statut: %1<br/>Intel révélé: %2<br/>Classe: %3", _tx, count _intel, typeOf _rec];
};

(_disp displayCtrl 93211) ctrlSetStructuredText parseText _sum;
(_disp displayCtrl 93213) ctrlSetStructuredText parseText _detail;
private _lb = _disp displayCtrl 93212;
lbClear _lb;
{ _lb lbAdd _x; } forEach _entries;
if (_entries isEqualTo []) then { _lb lbAdd "(aucun élément listé)"; };
true
