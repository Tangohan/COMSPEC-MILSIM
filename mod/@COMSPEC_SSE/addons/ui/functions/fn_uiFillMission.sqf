private _ctx = ["mission"] call comspec_sse_fnc_uiDisplayCtx;
_ctx params ["_disp", "_idcFilter", "_idcList"];
if (isNull _disp) exitWith { false };

private _filter = missionNamespace getVariable ["comspec_sse_uiMissionFilter", "ALL"];
(_disp displayCtrl _idcFilter) ctrlSetStructuredText parseText format [
    "<t size='0.85'>Filtre fusion: <t color='#8f8'>%1</t> · OBSERVED / REPORTED / ASSESSED / CONFIRMED</t>",
    _filter
];

private _rows = [];

// Intel révélé sur le record
private _rec = [] call comspec_sse_fnc_uiGetRecord;
if (!isNull _rec && {!isNil "comspec_sse_fnc_getRevealedIntel"}) then {
    {
        if (_x isEqualType createHashMap) then {
            private _kind = _x getOrDefault ["confidenceKind", "EXTRACTED"];
            private _state = _x getOrDefault ["discoveryState", "KNOWN"];
            // Map vers catégories mission
            private _cat = switch (_kind) do {
                case "OBSERVED": { "OBSERVED" };
                case "EXTRACTED": { "REPORTED" };
                case "PROBABLE": { "ASSESSED" };
                case "HYPOTHESIS": { "ASSESSED" };
                default { "REPORTED" };
            };
            if (_state == "CONFIRMED") then { _cat = "CONFIRMED"; };
            if (_filter == "ALL" || {_filter == _cat}) then {
                _rows pushBack format ["[%1|%2] V%3 C%4 — %5", _cat, _state, _x getOrDefault ["INTEL_VALUE",0], _x getOrDefault ["CONFIDENCE",0], _x getOrDefault ["text",""]];
            };
        };
    } forEach ([_rec] call comspec_sse_fnc_getRevealedIntel);
};

// Index découverte global
if (!isNil "comspec_sse_discoveryStates") then {
    {
        private _st = comspec_sse_discoveryStates get _x;
        if (_st isEqualType createHashMap) then {
            private _ds = _st getOrDefault ["discoveryState", "KNOWN"];
            private _cat = switch (_ds) do {
                case "CONFIRMED": { "CONFIRMED" };
                case "ASSESSED": { "ASSESSED" };
                case "DISPROVEN": { "ASSESSED" };
                default { "REPORTED" };
            };
            if (_filter == "ALL" || {_filter == _cat}) then {
                _rows pushBack format ["[%1|%2] %3", _cat, _ds, _x];
            };
        };
    } forEach (keys comspec_sse_discoveryStates);
};

if (!isNil "comspec_sse_fnc_deduplicateIntel") then {
    _rows = [_rows] call comspec_sse_fnc_deduplicateIntel;
};

private _lb = _disp displayCtrl _idcList;
lbClear _lb;
{ _lb lbAdd _x; } forEach (_rows select [0, 80]);
if (_rows isEqualTo []) then { _lb lbAdd "(aucune donnée fusionnée)"; };
true
