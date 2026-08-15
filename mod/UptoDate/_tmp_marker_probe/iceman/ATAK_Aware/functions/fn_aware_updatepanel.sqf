private _controls = call Iceman_fnc_aware_findControls;
private _mode = call Iceman_fnc_aware_getMode;

private _status = _controls getOrDefault ["9201", controlNull];
private _individual = _controls getOrDefault ["9210", controlNull];
private _standard = _controls getOrDefault ["9211", controlNull];
private _group = _controls getOrDefault ["9212", controlNull];
private _detail = _controls getOrDefault ["9220", controlNull];
private _summary = _controls getOrDefault ["9230", controlNull];

private _friendlyGroups = allGroups select {side _x in (call cTab_fnc_getPlayerSides)};
private _friendlyUnits = allUnits select {
    alive _x
    && {side group _x in (call cTab_fnc_getPlayerSides)}
    && {isPlayer _x || {[_x, missionNamespace getVariable ["ctab_core_personnelDevices", []]] call cTab_fnc_checkGear}}
};

private _label = switch (_mode) do {
    case "individual": {"INDIVIDUAL"};
    default {"STANDARD"};
};

if (!isNull _status) then {
    _status ctrlSetStructuredText parseText format [
        "<t align='center'>Detail: <t color='#b8e8ef'>%1</t></t>",
        _label
    ];
};

{
    _x params ["_ctrl", "_ctrlMode"];
    if (!isNull _ctrl) then {
        private _active = _mode == _ctrlMode;
        _ctrl ctrlSetBackgroundColor ([[0.08,0.12,0.14,0.88], [0.10,0.42,0.50,0.95]] select _active);
    };
} forEach [
    [_individual, "individual"],
    [_standard, "default"]
];

if (!isNull _group) then {
    _group ctrlShow false;
};

if (!isNull _detail) then {
    private _lines = switch (_mode) do {
        case "individual": {
            [
                "<t color='#b8e8ef'>Individual detail</t>",
                "Friendly players are shown as named directional markers.",
                "Each marker follows the player's current position and facing.",
                "Useful for close coordination and clearing work."
            ]
        };
        default {
            [
                "<t color='#b8e8ef'>Standard detail</t>",
                "cTab/BCE friendly marker behavior is restored.",
                "Your group is shown with normal member detail.",
                "Other friendly groups remain grouped."
            ]
        };
    };
    _detail ctrlSetStructuredText parseText (_lines joinString "<br/>");
};

if (!isNull _summary) then {
    _summary ctrlSetStructuredText parseText format [
        "<t color='#d8e6e8'>Friendly units:</t> %1<br/><t color='#d8e6e8'>Friendly groups:</t> %2<br/><t color='#7fe4b1'>Mini follow:</t> ON | <t color='#d8e6e8'>Profile:</t> SAVED",
        count _friendlyUnits,
        count _friendlyGroups
    ];
};

true
