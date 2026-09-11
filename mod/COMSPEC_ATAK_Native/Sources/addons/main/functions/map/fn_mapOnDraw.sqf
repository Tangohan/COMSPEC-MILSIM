params ["_map"];
if (isNull _map) exitWith {};

private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap];
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
private _selected = (_state getOrDefault ["selectedEntity",createHashMap]) getOrDefault ["id",""];
private _labels = profileNamespace getVariable ["COMSPEC_ATAK_Labels",true];
private _scale = ctrlMapScale _map;

{
    private _entity = _y;
    private _pos = _entity getOrDefault ["position",[]];
    if ((count _pos) < 2) then { continue };
    ([_entity] call comspec_atak_native_fnc_symbology) params ["_icon","_color"];
    private _freshness = _entity getOrDefault ["freshness","LIVE"];
    if (_freshness isEqualTo "STALE") then { _color set [3,0.65]; };
    if (_freshness in ["LOST","OFFLINE"]) then { _color set [3,0.3]; };
    private _size = if (_x isEqualTo _selected) then {30} else {22};
    _map drawIcon [
        _icon,_color,_pos,_size,_size,_entity getOrDefault ["heading",0],
        if (_labels && {_scale < 0.25}) then {_entity getOrDefault ["callsign",""]} else {""},
        1,0.035,"RobotoCondensedBold","right"
    ];
    if (_x isEqualTo _selected) then {
        _map drawEllipse [_pos,18,18,0,[0.36,0.78,0.42,0.9],""];
    };
} forEach (_data getOrDefault ["units",createHashMap]);

{
    private _marker = _y;
    private _pos = _marker getOrDefault ["position",[]];
    if ((count _pos) < 2) then { continue };
    private _shape = toUpper (_marker getOrDefault ["shape","ICON"]);
    private _size = _marker getOrDefault ["size",[1,1]];
    switch _shape do {
        case "RECTANGLE": {
            _map drawRectangle [_pos,_size select 0,_size select 1,_marker getOrDefault ["dir",0],[0.36,0.78,0.42,0.65],""];
        };
        case "ELLIPSE": {
            _map drawEllipse [_pos,_size select 0,_size select 1,_marker getOrDefault ["dir",0],[0.36,0.78,0.42,0.65],""];
        };
        default {
            private _type = _marker getOrDefault ["type","mil_dot"];
            private _icon = getText (configFile >> "CfgMarkers" >> _type >> "icon");
            if (_icon isEqualTo "") then { _icon = "\A3\ui_f\data\map\markers\military\dot_CA.paa"; };
            _map drawIcon [_icon,[0.36,0.78,0.42,_marker getOrDefault ["alpha",1]],_pos,20,20,_marker getOrDefault ["dir",0],_marker getOrDefault ["text",""],1,0.032,"RobotoCondensed","right"];
        };
    };
} forEach (_data getOrDefault ["markers",createHashMap]);

{
    if ((count _x) >= 2) then {
        _map drawLine [_x select 0,_x select 1,[0.36,0.78,0.42,0.8]];
        _map drawArrow [_x select 0,_x select 1,[0.36,0.78,0.42,0.9]];
    };
} forEach (_data getOrDefault ["routes",[]]);

{
    if ((count _x) >= 3) then { _map drawPolygon [_x,[0.95,0.67,0.20,0.18]]; };
} forEach (_data getOrDefault ["zones",[]]);
