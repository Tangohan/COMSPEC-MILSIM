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
    private _self = _entity getOrDefault ["self",false];
    private _size = if (_x isEqualTo _selected || _self) then {26} else {20};
    if (_self) then { _color = [0.30,0.70,1,1]; };
    _map drawIcon [
        _icon,_color,_pos,_size,_size,_entity getOrDefault ["heading",0],
        if (_labels && {_scale < 0.25}) then {_entity getOrDefault ["callsign",""]} else {""},
        1,0.026,"RobotoCondensedBold","right"
    ];
    if (_x isEqualTo _selected) then {
        _map drawEllipse [_pos,18,18,0,[0.36,0.78,0.42,0.9],""];
    };
} forEach (_data getOrDefault ["units",createHashMap]);

{
    private _marker = _y;
    if (_marker getOrDefault ["local",false]) then { continue };
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
            private _rgba = getArray (configFile >> "CfgMarkerColors" >> (_marker getOrDefault ["color","ColorGreen"]) >> "color");
            _rgba = _rgba apply { if (_x isEqualType "") then { call compile _x } else { _x } };
            if ((count _rgba) < 4) then { _rgba = [0.36,0.78,0.42,1]; };
            _rgba set [3,(_rgba select 3) * (_marker getOrDefault ["alpha",1])];
            _map drawIcon [_icon,_rgba,_pos,20,20,_marker getOrDefault ["dir",0],if (_labels) then {_marker getOrDefault ["text",""]} else {""},1,0.024,"RobotoCondensed","right"];
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

private _amber = [0.95,0.67,0.20,1];
private _yellow = [1,0.92,0.15,0.95];
private _dot = "\A3\ui_f\data\map\markers\military\dot_CA.paa";
private _cursor = _state getOrDefault ["cursorPos",[]];
private _dashed = {
    params ["_a","_b","_c"];
    private _n = ((round ((_a distance2D _b) / (_scale * 300))) max 4) min 60;
    for "_i" from 0 to (_n - 1) step 2 do {
        _map drawLine [_a vectorAdd ((_b vectorDiff _a) vectorMultiply (_i / _n)), _a vectorAdd ((_b vectorDiff _a) vectorMultiply ((_i + 1) / _n)), _c];
    };
};

// Trait jaune joueur -> curseur (outil Distance, en main)
if ((_state getOrDefault ["interactive",false]) && {_state getOrDefault ["mapDistance",true]} && {(count _cursor) >= 2} && {(_state getOrDefault ["mapMode","SELECT"]) in ["SELECT","MARKER"]}) then {
    private _me = getPosASL player;
    private _c = [_cursor select 0,_cursor select 1,0];
    [[_me select 0,_me select 1,0],_c,_yellow] call _dashed;
    _map drawIcon [_dot,_yellow,_c,10,10,0,format ["%1 m  %2°",round (player distance2D _c),round (player getDir _c)],2,0.03,"RobotoCondensedBold","right"];
};

// Mesure A -> B (ou A -> curseur tant que B n'est pas posé)
private _measure = +(_state getOrDefault ["mapMeasure",[]]);
if ((count _measure) isEqualTo 1 && {(count _cursor) >= 2}) then { _measure pushBack [_cursor select 0,_cursor select 1,0]; };
if ((count _measure) >= 2) then {
    _measure params ["_a","_b"];
    _map drawLine [_a,_b,_amber];
    _map drawIcon [_dot,_amber,_a,14,14,0,"A",2,0.03,"RobotoCondensedBold","right"];
    _map drawIcon [_dot,_amber,_b,14,14,0,format ["%1 m  %2°",round (_a distance2D _b),round (_a getDir _b)],2,0.03,"RobotoCondensedBold","right"];
};

// Bâtiments numérotés
{
    _x params ["_b","_label"];
    if (isNull _b) then { continue };
    (boundingBoxReal _b) params ["_p1","_p2"];
    _map drawRectangle [getPosASL _b,((_p2 select 0) - (_p1 select 0)) / 2,((_p2 select 1) - (_p1 select 1)) / 2,getDir _b,[0.95,0.67,0.20,0.9],""];
    _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)",[1,1,1,1],getPosASL _b,0,0,0,_label,2,0.032,"RobotoCondensedBold","center"];
} forEach (_state getOrDefault ["mapHouses",[]]);

// Hauteurs relevées
{
    _x params ["_p","_alt","_delta"];
    _map drawIcon ["\A3\ui_f\data\map\markers\military\triangle_CA.paa",[0.85,0.85,0.85,1],_p,14,14,0,format ["%1 m (%2%3)",round _alt,["","+"] select (_delta >= 0),round _delta],2,0.03,"RobotoCondensedBold","right"];
} forEach (_state getOrDefault ["mapHeights",[]]);

// Zones plates
{
    _map drawEllipse [_x,12,12,0,[0.36,0.78,0.42,0.9],""];
    _map drawIcon [_dot,[0.36,0.78,0.42,1],_x,10,10,0,format ["LZ %1",_forEachIndex + 1],2,0.03,"RobotoCondensedBold","right"];
} forEach (_state getOrDefault ["mapFlat",[]]);

// Ligne de vue
private _los = _state getOrDefault ["mapLos",[]];
if ((count _los) >= 3) then {
    _los params ["_from","_to","_block"];
    if ((count _block) > 0) then {
        _map drawLine [_from,ASLToAGL _block,[0.36,0.78,0.42,1]];
        _map drawLine [ASLToAGL _block,_to,[0.90,0.25,0.22,1]];
        _map drawIcon [_dot,[0.90,0.25,0.22,1],ASLToAGL _block,12,12,0,"BLOQUÉ",2,0.03,"RobotoCondensedBold","right"];
    } else {
        _map drawLine [_from,_to,[0.36,0.78,0.42,1]];
        _map drawIcon [_dot,[0.36,0.78,0.42,1],_to,12,12,0,"VUE OK",2,0.03,"RobotoCondensedBold","right"];
    };
};

// Marqueur sélectionné
private _selM = _state getOrDefault ["selectedMarker",""];
if (_selM isNotEqualTo "" && {(markerShape _selM) isNotEqualTo ""}) then {
    private _r = (_scale * 600) max 8;
    _map drawEllipse [getMarkerPos _selM,_r,_r,0,[0.36,0.78,0.42,1],""];
};

// Trait ou dessin en cours
private _stroke = +(_state getOrDefault ["drawStroke",[]]);
if ((count _stroke) > 0) then {
    if ((_state getOrDefault ["mapMode",""]) isEqualTo "LINE" && {(count _cursor) >= 2}) then { _stroke pushBack [_cursor select 0,_cursor select 1,0]; };
    private _rgba = (getArray (configFile >> "CfgMarkerColors" >> (profileNamespace getVariable ["COMSPEC_ATAK_DrawColor","ColorRed"]) >> "color")) apply { if (_x isEqualType "") then { call compile _x } else { _x } };
    if ((count _rgba) < 4) then { _rgba = [0.9,0.1,0.1,1]; };
    for "_i" from 0 to ((count _stroke) - 2) do { _map drawLine [_stroke select _i,_stroke select (_i + 1),_rgba]; };
};
