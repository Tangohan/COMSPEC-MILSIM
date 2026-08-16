params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

if (!_activated || {!hasInterface}) exitWith { true };

private _targets = synchronizedObjects _logic;
if (count _targets == 0) then {
    private _attached = _logic getVariable ["bis_fnc_curatorAttachObject_object", objNull];
    if (!isNull _attached) then { _targets = [_attached]; };
};

{
    private _data = [_x] call comspec_sse_fnc_getData;
    if (isNil "_data" || {!(_data isEqualType [])}) then {
        hint format ["%1 — aucune donnée SSE", _x];
    } else {
        private _uid = [_data, "uid", "?"] call comspec_sse_fnc_getPair;
        private _type = [_data, "type", "?"] call comspec_sse_fnc_getPair;
        private _profile = [_data, "profile", "?"] call comspec_sse_fnc_getPair;
        private _state = [_data, "state", "?"] call comspec_sse_fnc_getPair;
        private _identity = [_x, "identity"] call comspec_sse_fnc_getSection;
        private _name = if (!isNil "_identity" && {_identity isEqualType createHashMap}) then {
            _identity getOrDefault ["name", "-"]
        } else { "-" };
        private _links = [_x] call comspec_sse_fnc_getLinks;
        hint format [
            "SSE DATA\nUID: %1\nType: %2\nProfil: %3\nÉtat: %4\nNom: %5\nLiens: %6\nSeed: %7",
            _uid, _type, _profile, _state, _name, count _links,
            [_data, "seed", -1] call comspec_sse_fnc_getPair
        ];
    };
} forEach _targets;

deleteVehicle _logic;
true
