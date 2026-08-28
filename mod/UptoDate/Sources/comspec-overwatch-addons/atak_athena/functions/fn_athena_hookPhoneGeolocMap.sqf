/*
    Dessine les téléphones suivis sur la carte ATAK en jeu (cTab / IceMan).
*/
params [
    ["_ctrl", controlNull, [controlNull]]
];
if (isNull _ctrl) exitWith {};

private _icon = "\a3\ui_f\data\igui\cfg\simpletasks\types\radio_ca.paa";
private _color = [0.09, 0.75, 0.85, 1];
private _list = missionNamespace getVariable ["COMSPEC_PhoneTrackUnits", []];
if (!(_list isEqualType [])) exitWith {};

{
    if (!(_x isEqualType objNull) || {isNull _x} || {!alive _x}) then { continue };
    if (
        !isNil "comspec_overwatch_connect_fnc_isObjectFlag"
        && {!([_x, "COMSPEC_PhoneTrack"] call comspec_overwatch_connect_fnc_isObjectFlag)}
    ) then { continue };
    private _pos = getPosWorld _x;
    if ((abs (_pos select 0) < 1) && { abs (_pos select 1) < 1 }) then { continue };
    private _label = "";
    if (!isNil "comspec_overwatch_connect_fnc_phoneTrackCallsign") then {
        _label = [_x] call comspec_overwatch_connect_fnc_phoneTrackCallsign;
    };
    _ctrl drawIcon [
        _icon,
        _color,
        _pos,
        26,
        26,
        0,
        _label,
        1,
        0.03,
        "RobotoCondensedBold",
        "right"
    ];
} forEach _list;
