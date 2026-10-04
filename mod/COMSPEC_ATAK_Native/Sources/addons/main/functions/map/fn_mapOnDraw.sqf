params ["_map"];
if (isNull _map) exitWith {};

private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap];
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
private _selected = (_state getOrDefault ["selectedEntity",createHashMap]) getOrDefault ["id",""];
private _labels = (["COMSPEC_ATAK_Labels", true, "native_map_labels"] call comspec_atak_native_fnc_pref) select 0;
private _scale = ctrlMapScale _map;
// Taille des symboles et textes proportionnelle à la carte (mini = petite carte, petits symboles).
private _k = ((((ctrlPosition _map) select 3) / (safeZoneH * 0.7)) max 0.5) min 1;
// Couleurs choisies dans les réglages (alliés, moi) ; icône choisie par chaque joueur (variable publique).
private _palette = createHashMapFromArray [["BLUE",[0.28,0.70,1,1]],["CYAN",[0.20,0.90,0.95,1]],["GREEN",[0.36,0.85,0.42,1]],["WHITE",[0.95,0.95,0.95,1]],["YELLOW",[1,0.85,0.15,1]],["ORANGE",[1,0.55,0.15,1]],["PINK",[1,0.45,0.75,1]]];
private _allyRgb = _palette getOrDefault [profileNamespace getVariable ["COMSPEC_ATAK_AllyColor","BLUE"],[0.28,0.70,1,1]];
private _selfRgb = _palette getOrDefault [profileNamespace getVariable ["COMSPEC_ATAK_SelfColor","CYAN"],[0.20,0.90,0.95,1]];

// Carte nuit : voile sombre sous tous les symboles (dessiné en premier).
if (profileNamespace getVariable ["COMSPEC_ATAK_LayerNight", false]) then {
    _map drawRectangle [[worldSize / 2, worldSize / 2, 0], worldSize, worldSize, 0, [0.02, 0.03, 0.08, 0.55], "#(rgb,8,8,3)color(1,1,1,1)"];
};
// Heatmap : activité ennemie repérée par mon camp (cases de 200 m, s'efface avec le temps).
if (profileNamespace getVariable ["COMSPEC_ATAK_LayerHeat", false]) then {
    {
        _y params ["_cx", "_cy", "_w"];
        private _k = (_w / 6) min 1;
        _map drawRectangle [[_cx, _cy, 0], 100, 100, 0, [0.95, 0.75 - 0.6 * _k, 0.1, 0.12 + 0.43 * _k], "#(rgb,8,8,3)color(1,1,1,1)"];
    } forEach (missionNamespace getVariable ["COMSPEC_ATAK_Heat", createHashMap]);
};

// GPS : itinéraire en trait épais (bordure sombre, bleu à parcourir, gris déjà parcouru), arrivée en drapeau.
private _route = missionNamespace getVariable ["COMSPEC_ATAK_Route", createHashMap];
if ((count _route) > 0) then {
    private _pts = _route get "pts";
    private _idx = _route getOrDefault ["idx", 0];
    private _mpu = (_map ctrlMapScreenToWorld [0, 0]) distance2D (_map ctrlMapScreenToWorld [0.0035, 0]);
    private _tex = "#(rgb,8,8,3)color(1,1,1,1)";
    private _seg = {
        params ["_a", "_b", "_w", "_c"];
        private _len = _a distance2D _b;
        if (_len < 0.5) exitWith {};
        _map drawRectangle [[((_a select 0) + (_b select 0)) / 2, ((_a select 1) + (_b select 1)) / 2], _w, _len / 2 + _w * 0.6, _a getDir _b, _c, _tex];
    };
    private _proj = _route getOrDefault ["proj", _pts select 0];
    for "_i" from 0 to ((count _pts) - 2) do {
        private _a = _pts select _i; private _b = _pts select (_i + 1);
        private _past = _i < _idx;
        if (_i isEqualTo _idx) then {
            [_a, _proj, _mpu, [0.45, 0.48, 0.50, 0.75]] call _seg;
            _a = _proj;
        };
        if (_past) then { [_a, _b, _mpu, [0.45, 0.48, 0.50, 0.75]] call _seg; } else {
            [_a, _b, _mpu * 1.45, [0.05, 0.18, 0.40, 0.9]] call _seg;
            [_a, _b, _mpu, [0.26, 0.52, 0.96, 1]] call _seg;
        };
    };
    _map drawIcon ["\A3\ui_f\data\map\markers\military\flag_CA.paa", [0.92, 0.26, 0.21, 1], _route get "dest", 26, 26, 0, _route getOrDefault ["label", ""], 2, 0.028, "RobotoCondensedBold", "right"];
};

// Calques Relief (champ de vision / altitudes) et Wave Relay (liens du maillage).
[_map] call comspec_atak_native_fnc_reliefDraw;
[_map] call comspec_atak_native_fnc_meshDraw;
[_map] call comspec_atak_native_fnc_droneDraw;

// Points de passage : traits pointillés entre étapes, étape active en vert, trait de cap depuis moi en navigation.
private _wp = missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap];
private _wpts = _wp getOrDefault ["pts", []];
if ((count _wpts) > 0) then {
    private _wi = _wp getOrDefault ["idx", 0];
    private _dash = {
        params ["_a", "_b", "_c"];
        private _n = ((round ((_a distance2D _b) / 40)) max 1) min 60;
        for "_k" from 0 to (_n - 1) step 2 do { _map drawLine [_a vectorAdd ((_b vectorDiff _a) vectorMultiply (_k / _n)), _a vectorAdd ((_b vectorDiff _a) vectorMultiply ((_k + 1) / _n)), _c]; };
    };
    for "_i" from 0 to ((count _wpts) - 2) do { [(_wpts select _i) select 0, (_wpts select (_i + 1)) select 0, [0.95, 0.75, 0.18, 0.9]] call _dash; };
    if (_wp getOrDefault ["nav", false]) then { _map drawArrow [getPosATL vehicle player, (_wpts select _wi) select 0, [0.36, 0.78, 0.42, 0.9]]; };
    {
        _map drawIcon ["\A3\ui_f\data\map\markers\military\flag_CA.paa", [[0.95, 0.75, 0.18, 1], [0.36, 0.85, 0.42, 1]] select (_forEachIndex isEqualTo _wi), _x select 0, 22, 22, 0, _x select 1, 2, 0.026, "RobotoCondensedBold", "right"];
    } forEach _wpts;
};

// Guerre électronique : position brouillée (GPS) et pistes BFT dégradées.
([] call comspec_atak_native_fnc_ewEffects) params ["_ewGps", "_ewBft", "_ewOff"];
{
    private _entity = _y;
    private _pos = _entity getOrDefault ["position",[]];
    if ((count _pos) < 2) then { continue };
    ([_entity] call comspec_atak_native_fnc_symbology) params ["_icon","_color"];
    private _freshness = _entity getOrDefault ["freshness","LIVE"];
    if (_freshness isEqualTo "STALE") then { _color set [3,0.65]; };
    if (_freshness in ["LOST","OFFLINE"]) then { _color set [3,0.3]; };
    private _self = _entity getOrDefault ["self",false];
    private _size = ([16, 21] select (_x isEqualTo _selected || _self)) * _k;
    if ((_entity getOrDefault ["affiliation",""]) isEqualTo "friend") then { _color = +_allyRgb; };
    if (_self) then { _color = +_selfRgb; };
    if (_self && {_ewGps > 0}) then { _pos = [(_pos select 0) + (_ewOff select 0), (_pos select 1) + (_ewOff select 1), 0]; };
    if (_ewBft && {!_self}) then { _color set [3, ((_color select 3) min 0.65) * 0.5]; };
    private _dir = _entity getOrDefault ["heading",0];
    private _custom = _entity getOrDefault ["icon",""];
    if (_custom isNotEqualTo "" && {(_entity getOrDefault ["type",""]) isEqualTo "infantry"}) then {
        private _ci = getText (configFile >> "CfgMarkers" >> _custom >> "icon");
        if (_ci isNotEqualTo "") then { _icon = _ci; _dir = 0; };
    };
    _map drawIcon [
        _icon,_color,_pos,_size,_size,_dir,
        if (_labels && {_scale < 0.25}) then {_entity getOrDefault ["callsign",""]} else {""},
        1,0.022 * _k,"RobotoCondensedBold","right"
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
            _map drawIcon [_icon,_rgba,_pos,16 * _k,16 * _k,_marker getOrDefault ["dir",0],if (_labels && {_scale < 0.3}) then {_marker getOrDefault ["text",""]} else {""},1,0.02 * _k,"RobotoCondensed","right"];
        };
    };
} forEach (_data getOrDefault ["markers",createHashMap]);

{
    if ((count _x) >= 2) then {
        _map drawLine [_x select 0,_x select 1,[0.36,0.78,0.42,0.8]];
        _map drawArrow [_x select 0,_x select 1,[0.36,0.78,0.42,0.9]];
    };
} forEach (_data getOrDefault ["routes",[]]);

if (profileNamespace getVariable ["COMSPEC_ATAK_ZonesLayer", true]) then {
    {
        if ((count _x) >= 3) then { _map drawPolygon [_x,[0.95,0.67,0.20,0.18]]; };
    } forEach (_data getOrDefault ["zones",[]]);
} else {
    // Calque masqué : Overwatch recrée ses marqueurs de zone à chaque synchro, on les remasque.
    if (diag_tickTime > (uiNamespace getVariable ["COMSPEC_ATAK_ZonesHideAt", 0])) then {
        uiNamespace setVariable ["COMSPEC_ATAK_ZonesHideAt", diag_tickTime + 2];
        { if ((_x select [0, 11]) isEqualTo "COMSPEC_TZ_" && {(markerAlpha _x) > 0}) then { _x setMarkerAlphaLocal 0; }; } forEach allMapMarkers;
    };
};

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

// Zones tactiques Athena (LZ, objectif, zone dangereuse…) et zones roleplay (brouillage, sans couverture).
if (profileNamespace getVariable ["COMSPEC_ATAK_ZonesLayer", true]) then {
    private _zc = createHashMapFromArray [["LZ",[0.36,0.85,0.42,1]],["DZ",[0.36,0.85,0.42,1]],["EXTRACT_POINT",[0.36,0.85,0.42,1]],["RALLY_POINT",[0.28,0.70,1,1]],["SAFE_ZONE",[0.28,0.70,1,1]],["OBJECTIVE",[0.95,0.67,0.20,1]],["DANGER_ZONE",[0.95,0.22,0.18,1]],["NO_GO_AREA",[0.95,0.22,0.18,1]],["RESTRICTED_AREA",[0.95,0.5,0.15,1]]];
    {
        _x params [["_id",""],["_geom","CIRCLE"],["_geo",[]],["_r",0],["_type",""],["_threat",""],["_label",""]];
        private _c = +(_zc getOrDefault [toUpper _type,[0.95,0.67,0.20,1]]);
        if ((toUpper _geom) in ["CIRCLE","RECTANGLE"] && {(count _geo) >= 2}) then {
            private _p = [_geo select 0,_geo select 1,0];
            _map drawEllipse [_p,_r max 20,_r max 20,0,_c,""];
            _map drawEllipse [_p,_r max 20,_r max 20,0,[_c select 0,_c select 1,_c select 2,0.12],"#(rgb,8,8,3)color(1,1,1,1)"];
            _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)",_c,_p,0,0,0,_label,2,0.028,"RobotoCondensedBold","center"];
        } else {
            if ((count _geo) >= 2 && {(_geo select 0) isEqualType []}) then {
                private _pts = _geo apply { [_x select 0,_x select 1,0] };
                if ((toUpper _geom) isEqualTo "POLYGON" && {(count _pts) >= 3}) then { _map drawPolygon [_pts,_c]; } else { for "_i" from 0 to ((count _pts) - 2) do { _map drawLine [_pts select _i,_pts select (_i + 1),_c]; }; };
                _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)",_c,_pts select 0,0,0,0,_label,2,0.028,"RobotoCondensedBold","right"];
            };
        };
    } forEach (missionNamespace getVariable ["COMSPEC_DangerZones",[]]);
    {
        if (_x isEqualType createHashMap) then {
            private _p = _x getOrDefault ["position",[]];
            if ((count _p) >= 2) then {
                private _r = _x getOrDefault ["radius",200];
                private _c = switch (_x getOrDefault ["type",""]) do { case "jammer": {[0.85,0.25,0.95,1]}; case "no_coverage": {[0.6,0.6,0.6,1]}; default {[0.95,0.5,0.15,1]}; };
                _map drawEllipse [[_p select 0,_p select 1,0],_r,_r,0,_c,""];
                _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)",_c,[_p select 0,_p select 1,0],0,0,0,format ["%1 · %2 %%",_x getOrDefault ["name","Zone"],_x getOrDefault ["intensity",0]],2,0.026,"RobotoCondensed","center"];
            };
        };
    } forEach (missionNamespace getVariable ["COMSPEC_RoleplayZones",[]]);
};

// Mes charges (app Explosifs) : numéro de pose, mode de mise à feu, temps restant des minuteries, T- de la séquence.
{
    private _e = _x get "obj";
    private _c = [[0.95,0.67,0.20,1], [0.90,0.78,0.29,1], [0.90,0.28,0.23,1], [0.79,0.83,0.81,1]] select ((["atak","command","timer","mine"] find (_x get "kind")) max 0);
    private _txt = format ["C%1", _forEachIndex + 1];
    if ((_x get "remaining") >= 0) then { _txt = format ["%1 · %2 s", _txt, ceil (_x get "remaining")]; };
    private _key = _x get "key";
    private _q = ((uiNamespace getVariable ["COMSPEC_ATAK_Explo", createHashMap]) getOrDefault ["queue", []]) select { ((_x select 1) get "key") isEqualTo _key };
    if ((count _q) > 0) then { _txt = format ["%1 · T-%2", _txt, ((((_q select 0) select 0) - diag_tickTime) max 0) toFixed 1]; _c = [0.90,0.28,0.23,1]; };
    _map drawIcon ["\z\comspec_atak_native\addons\main\data\app_explo.paa", _c, getPosASL _e, 18 * _k, 18 * _k, 0, _txt, 2, 0.03 * _k, "RobotoCondensedBold", "right"];
} forEach ([] call comspec_atak_native_fnc_exploList);

// Goniométrie : émetteurs estimés (cercle d'incertitude) et relèvements seuls (azimut tracé sur 3 km).
if (profileNamespace getVariable ["COMSPEC_ATAK_SigintLayer", true]) then {
    private _sc = [0.95,0.35,0.85,1];
    {
        _x params ["_cs","_kind","_p","_r","_n","_brg"];
        private _pp = [_p select 0,_p select 1,0];
        if (_kind isEqualTo "azimuth") then {
            private _end = _pp vectorAdd [3000 * sin _brg,3000 * cos _brg,0];
            [_pp,_end,_sc] call _dashed;
            _map drawIcon [_dot,_sc,_pp,8,8,0,format ["%1 · %2°",_cs,round _brg],2,0.026,"RobotoCondensed","right"];
        } else {
            _map drawEllipse [_pp,_r,_r,0,_sc,""];
            _map drawIcon ["\A3\ui_f\data\map\markers\military\unknown_CA.paa",_sc,_pp,18,18,0,format ["Émetteur probable %1 · ±%2 m · %3 relevés",_cs,round _r,_n],2,0.026,"RobotoCondensedBold","right"];
        };
    } forEach (uiNamespace getVariable ["COMSPEC_ATAK_Sigint",[]]);
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

// App Feux : pièce, cible et ligne de tir.
private _fires = uiNamespace getVariable ["COMSPEC_ATAK_Fires", createHashMap];
private _ft = _fires getOrDefault ["target", []];
if ((count _ft) >= 2) then {
    private _red = [0.90, 0.25, 0.22, 1];
    private _rr = (_scale * 700) max 10;
    _map drawEllipse [_ft, _rr, _rr, 0, _red, ""];
    _map drawIcon ["\A3\ui_f\data\map\markers\military\destroy_CA.paa", _red, _ft, 22, 22, 0, "TGT", 2, 0.032, "RobotoCondensedBold", "right"];
    private _gid = _fires getOrDefault ["gun", "MAN"];
    private _g = if (_gid isEqualTo "MAN") then { objNull } else { objectFromNetId _gid };
    if (!isNull _g) then {
        _map drawLine [getPosATL _g, _ft, [0.90, 0.25, 0.22, 0.7]];
        _map drawIcon ["\A3\ui_f\data\map\markers\nato\b_mortar.paa", [0.30, 0.70, 1, 1], getPosATL _g, 22, 22, 0, "GUN", 2, 0.03, "RobotoCondensedBold", "right"];
    };
};

// Cartouches des marqueurs (style plan de mission) : cercle de couleur + boîte noire « nom / E / N ».
// Seuls les marqueurs posés par les joueurs ou Athena et ayant un titre ; réglage profil COMSPEC_ATAK_MarkerTags.
private _pool = uiNamespace getVariable ["COMSPEC_ATAK_MarkerTagPool", []];
private _used = 0;
if (((["COMSPEC_ATAK_MarkerTags", true, "native_marker_tags"] call comspec_atak_native_fnc_pref) select 0) && {_k > 0.75} && {_scale < 0.12}) then {
    private _disp = ctrlParent _map;
    private _mp = ctrlPosition _map;
    private _gridCache = uiNamespace getVariable ["COMSPEC_ATAK_TagGridCache", createHashMap];
    private _fs = ((_mp select 3) * 0.024) max (safeZoneH * 0.010);
    {
        if (_used >= 30) then { break };
        private _m = _y;
        private _id = _m getOrDefault ["id", _x];
        private _title = _m getOrDefault ["text", ""];
        if (_title isEqualTo "") then { continue };
        if ((_m getOrDefault ["local", false]) && {(_id find "_USER_DEFINED") < 0}) then { continue };
        if ((toUpper (_m getOrDefault ["shape", "ICON"])) in ["POLYLINE", "RECTANGLE", "ELLIPSE"]) then { continue };
        private _pos = _m getOrDefault ["position", []];
        if ((count _pos) < 2) then { continue };
        private _sp = _map ctrlMapWorldToScreen _pos;
        if ((_sp select 0) < (_mp select 0) || {(_sp select 0) > ((_mp select 0) + (_mp select 2))} || {(_sp select 1) < (_mp select 1)} || {(_sp select 1) > ((_mp select 1) + (_mp select 3))}) then { continue };
        private _rgba = (getArray (configFile >> "CfgMarkerColors" >> (_m getOrDefault ["color", "ColorBlack"]) >> "color")) apply { if (_x isEqualType "") then { call compile _x } else { _x } };
        if ((count _rgba) < 4) then { _rgba = [0.1, 0.1, 0.1, 1]; };
        _rgba set [3, 1];
        private _r = (_scale * 900) max 12;
        _map drawEllipse [_pos, _r, _r, 0, _rgba, ""];
        _map drawEllipse [_pos, _r * 0.93, _r * 0.93, 0, _rgba, ""];
        // Coordonnées : grille 10 chiffres coupée en E / N, mises en cache par position.
        private _key = format ["%1|%2|%3", _id, round (_pos select 0), round (_pos select 1)];
        private _g = _gridCache getOrDefault [_key, ""];
        if (_g isEqualTo "") then {
            _g = [_pos, 10] call comspec_atak_native_fnc_gridRef;
            _gridCache set [_key, _g];
        };
        (_g splitString " ") params [["_e", ""], ["_n", ""]];
        private _c = _pool param [_used, controlNull];
        if (isNull _c) then {
            _c = _disp ctrlCreate ["COMSPEC_RscStructuredText", -1];
            _pool set [_used, _c];
        };
        _c ctrlSetBackgroundColor [0.02, 0.02, 0.02, 0.86];
        _c ctrlSetStructuredText parseText format ["<t size='%4' font='RobotoCondensedBold' color='#ffffff'>%1<br/>%2E<br/>%3N</t>", toUpper ([_title, "<", "&lt;"] call CBA_fnc_replace), _e, _n, 1];
        _c ctrlSetFontHeight _fs;
        private _w = (_fs * 0.42 * (((count _title) max 9) + 1)) max (_fs * 4);
        private _ringPx = (_map ctrlMapWorldToScreen [(_pos select 0) + _r, _pos select 1]) select 0;
        _c ctrlSetPosition [_ringPx + _fs * 0.3, (_sp select 1) - _fs * 0.2, _w, _fs * 3.3];
        _c ctrlShow true;
        _c ctrlCommit 0;
        _used = _used + 1;
    } forEach (_data getOrDefault ["markers", createHashMap]);
    if ((count _gridCache) > 400) then { _gridCache = createHashMap; };
    uiNamespace setVariable ["COMSPEC_ATAK_TagGridCache", _gridCache];
};
for "_i" from _used to ((count _pool) - 1) do { (_pool select _i) ctrlShow false; };
uiNamespace setVariable ["COMSPEC_ATAK_MarkerTagPool", _pool select { !isNull _x }];

// Calques Logistique (points de largage) et guerre électronique (gonio, brouilleurs).
if (profileNamespace getVariable ["COMSPEC_ATAK_LayerLogi", true]) then { [_map] call comspec_atak_native_fnc_logisticsDraw; };
if (profileNamespace getVariable ["COMSPEC_ATAK_LayerEw", true]) then { [_map] call comspec_atak_native_fnc_ewDraw; };
if (profileNamespace getVariable ["COMSPEC_ATAK_LayerDrone", true]) then { [_map] call comspec_atak_native_fnc_droneDetectDraw; };
// Calques des modules externes : configFile >> "COMSPEC_ATAK_MapLayers" >> classe >> function (reçoit [carte]).
private _ext = uiNamespace getVariable "COMSPEC_ATAK_MapLayersExt";
if (isNil "_ext") then {
    _ext = ("true" configClasses (configFile >> "COMSPEC_ATAK_MapLayers")) apply { getText (_x >> "function") } select { _x isNotEqualTo "" };
    uiNamespace setVariable ["COMSPEC_ATAK_MapLayersExt", _ext];
};
{ if (!isNil _x) then { [_map] call (missionNamespace getVariable _x); }; } forEach _ext;
