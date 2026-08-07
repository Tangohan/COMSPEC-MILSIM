private _disp = findDisplay 93500;
if (isNull _disp) exitWith { false };

private _isZeus = !isNull (getAssignedCuratorLogic player)
    || {player getVariable ["comspec_sse_zeusUi", false]}
    || {!isMultiplayer};

if (!_isZeus) exitWith {
    (_disp displayCtrl 93510) ctrlSetStructuredText parseText "<t color='#f88'>Accès réservé au Zeus.</t>";
    (_disp displayCtrl 93511) ctrlSetStructuredText parseText "";
    lbClear (_disp displayCtrl 93512);
    false
};

private _rec = [] call comspec_sse_fnc_uiGetRecord;
if (isNull _rec) then {
    private _c = cursorObject;
    if (!isNull _c) then {
        [_c] call comspec_sse_fnc_uiSetRecord;
        _rec = _c;
    };
};

private _knownTxt = "<t color='#8f8'>CONNU JOUEURS</t><br/>(aucun record)";
private _truthTxt = "<t color='#f84'>VÉRITÉ COMPLÈTE</t><br/>(aucun record)";
private _rows = [];

if (!isNull _rec && {!isNil "comspec_sse_fnc_getPlayerKnownView"}) then {
    private _view = [_rec] call comspec_sse_fnc_getPlayerKnownView;
    private _known = _view getOrDefault ["known", []];
    private _truth = _view getOrDefault ["truth", []];

    private _knownLines = [];
    {
        if (_x isEqualType createHashMap) then {
            _knownLines pushBack (_x getOrDefault ["text", ""]);
        } else {
            _knownLines pushBack (str _x);
        };
    } forEach (_known select [0, 6]);

    private _truthLines = [];
    {
        if (_x isEqualType createHashMap) then {
            _truthLines pushBack (_x getOrDefault ["text", ""]);
        } else {
            _truthLines pushBack (str _x);
        };
    } forEach (_truth select [0, 8]);

    _knownTxt = format [
        "<t color='#8f8'>CONNU JOUEURS</t><br/>Niveau: %1<br/>Éléments: %2<br/>%3",
        _view getOrDefault ["level", "?"],
        count _known,
        _knownLines joinString "<br/>"
    ];
    _truthTxt = format [
        "<t color='#f84'>VÉRITÉ COMPLÈTE</t><br/>Éléments: %1<br/>%2",
        count _truth,
        _truthLines joinString "<br/>"
    ];
};

private _site = if (!isNil "comspec_sse_fnc_listSiteEntities") then {
    [player, 80] call comspec_sse_fnc_listSiteEntities
} else {
    []
};

{
    _rows pushBack format [
        "%1 | %2 | lvl %3 | %4",
        _x getOrDefault ["uid", "?"],
        _x getOrDefault ["type", "?"],
        _x getOrDefault ["level", "?"],
        _x getOrDefault ["label", ""]
    ];
} forEach _site;

(_disp displayCtrl 93510) ctrlSetStructuredText parseText _knownTxt;
(_disp displayCtrl 93511) ctrlSetStructuredText parseText _truthTxt;
private _lb = _disp displayCtrl 93512;
lbClear _lb;
{ _lb lbAdd _x; } forEach _rows;
if (_rows isEqualTo []) then { _lb lbAdd "(aucune entité SSE proche)"; };
true
