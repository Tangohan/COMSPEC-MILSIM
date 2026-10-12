/*
    Dessin du tableau des liens sur la carte de fond (événement Draw, chaque image).
    La carte est recouverte d'un aplat sombre : seuls les nœuds et liens apparaissent.
*/
params ["_map"];
private _st = uiNamespace getVariable ["comspec_sse_chartState", createHashMap];
private _nodes = _st getOrDefault ["nodes", createHashMap];
private _edges = _st getOrDefault ["edges", []];
private _sel = _st getOrDefault ["sel", ""];
private _ws = worldSize;

_map drawRectangle [[_ws / 2, _ws / 2, 0], _ws * 1.5, _ws * 1.5, 0, [0.035, 0.05, 0.05, 1], "#(rgb,8,8,3)color(0.035,0.05,0.05,1)"];

private _scale = ctrlMapScale _map;
private _txt = 0.032;
private _clear = "#(rgba,8,8,3)color(0,0,0,0)";

{
    _x params ["_a", "_b", "_lbl", "_k"];
    private _pa = (_nodes get _a) get "pos";
    private _pb = (_nodes get _b) get "pos";
    private _hot = (_sel isNotEqualTo "") && {_a isEqualTo _sel || {_b isEqualTo _sel}};
    private _col = [[0.45, 0.55, 0.5, 0.35], [0.85, 0.7, 0.35, 0.7], [0.5, 0.85, 0.6, 0.9]] select ((_k max 0) min 2);
    if (_hot) then { _col = [1, 1, 1, 1]; };
    _map drawLine [_pa, _pb, _col];
    if (_lbl isNotEqualTo "" && {_hot || {_scale < 0.03}}) then {
        _map drawIcon [_clear, _col, (_pa vectorAdd _pb) vectorMultiply 0.5, 0, 0, 0, _lbl, 0, _txt * 0.8, "RobotoCondensed", "center"];
    };
} forEach _edges;

private _icons = createHashMapFromArray [
    ["org", "\A3\ui_f\data\map\markers\nato\o_hq.paa"],
    ["person", "\A3\ui_f\data\map\vehicleicons\iconMan_ca.paa"],
    ["vehicle", "\A3\ui_f\data\map\vehicleicons\iconCar_ca.paa"],
    ["device", "\A3\ui_f\data\igui\cfg\simpleTasks\types\radio_ca.paa"],
    ["doc", "\A3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa"],
    ["weapon", "\A3\ui_f\data\igui\cfg\simpleTasks\types\rifle_ca.paa"],
    ["place", "\A3\ui_f\data\map\markers\military\flag_ca.paa"],
    ["object", "\A3\ui_f\data\igui\cfg\simpleTasks\types\box_ca.paa"]
];
private _threat = createHashMapFromArray [
    ["faible", [0.55, 0.85, 0.55]], ["moyenne", [0.95, 0.78, 0.3]], ["elevee", [0.95, 0.5, 0.25]], ["critique", [0.95, 0.27, 0.25]]
];
private _orgs = missionNamespace getVariable ["comspec_sse_orgs", createHashMap];

{
    private _n = _y;
    private _kind = _n get "kind";
    private _k = _n get "known";
    private _rgb = switch (_kind) do {
        case "org": { _threat getOrDefault [((_orgs getOrDefault [(_x select [2]), createHashMap]) getOrDefault ["threat", "moyenne"]), [0.95, 0.78, 0.3]] };
        case "person": { [0.88, 0.92, 0.95] };
        case "place": { [0.78, 0.68, 0.98] };
        default { [0.62, 0.82, 0.98] };
    };
    private _alpha = [0.38, 0.8, 1] select ((_k max 0) min 2);
    if (_k isEqualTo 0) then { _rgb = [0.6, 0.6, 0.6]; };
    private _size = [24, 30] select (_kind isEqualTo "org");
    private _label = (_n get "label") + (["", " ?"] select (_k isEqualTo 1));
    if (_x isEqualTo _sel) then {
        _map drawIcon ["\A3\ui_f\data\map\groupicons\selector_selected_ca.paa", [1, 1, 1, 1], _n get "pos", _size + 16, _size + 16, 0, "", 0];
    };
    _map drawIcon [_icons getOrDefault [_kind, _icons get "object"], _rgb + [_alpha], _n get "pos", _size, _size, 0, "  " + _label, 1, _txt, "RobotoCondensedBold", "right"];
} forEach _nodes;
