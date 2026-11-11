/*
    Aperçu d'un papier dans la fenêtre « IA de la zone » (client du Zeus) : le document imprimé (jamais la vérité) dessiné
    dans un groupe de contrôles, au format réel, comme la visionneuse du joueur (fn_idViewerOnLoad) : gabarit du type et du
    pays (comspec_sse_fnc_idDocTypes, extensions comprises), champs aux coordonnées du type, MRZ, hologramme, nom du pays
    d'un gabarit de zone. Photo : silhouette imprimée (le portrait en direct reste dans la visionneuse : « Voir en grand »).
    ["draw", [groupe, document imprimé, "recto" | "verso"]]  -> true si un papier est dessiné
    ["clear", [groupe]]                                      efface ce qui a été dessiné
    ["sides", [document]]                                    -> nombre de faces du type (1 ou 2)
    Le document vient du serveur (fn_editorAiServer « docView ») : mêmes champs que celui que reçoit un joueur.
*/
params [["_op", ""], ["_a", []]];
if (!hasInterface) exitWith { false };
disableSerialization;
private _typeOf = {
    params ["_doc"];
    private _type = toLower (_doc getOrDefault ["type", "id_card"]);
    private _t = ["get", _type] call comspec_sse_fnc_idDocTypes;
    if ((count _t) isEqualTo 0) then { _type = "id_card"; _t = ["get", _type] call comspec_sse_fnc_idDocTypes; };
    [_type, _t]
};
switch (_op) do {
    case "clear": {
        _a params [["_g", controlNull]];
        if (isNull _g) exitWith { false };
        { ctrlDelete _x; } forEach (_g getVariable ["comspec_sse_cardCtrls", []]);
        _g setVariable ["comspec_sse_cardCtrls", []];
        true
    };
    case "sides": {
        _a params [["_doc", createHashMap]];
        if !(_doc isEqualType createHashMap) exitWith { 1 };
        (([_doc] call _typeOf) select 1) getOrDefault ["sides", 2]
    };
    case "draw": {
        _a params [["_g", controlNull], ["_doc", createHashMap], ["_side", "recto"]];
        ["clear", [_g]] call comspec_sse_fnc_editorAiCard;
        if (isNull _g || {!(_doc isEqualType createHashMap)} || {(count _doc) isEqualTo 0}) exitWith { false };
        private _d = ctrlParent _g;
        ([_doc] call _typeOf) params ["_type", "_t"];
        if ((count _t) isEqualTo 0) exitWith { false };
        if (_side isEqualTo "verso" && {(_t getOrDefault ["sides", 2]) < 2}) then { _side = "recto"; };
        private _lc = toLower (_doc getOrDefault ["country", "ALT"]);
        private _zone = _doc getOrDefault ["zone", ""];
        private _zoned = _zone in ["europe", "americas", "other"];
        private _tpl = ["texture", [_type, _side, _lc, _zone]] call comspec_sse_fnc_idDocTypes;
        private _layout = ["side", [_type, _side]] call comspec_sse_fnc_idDocTypes;
        private _dir = "\z\comspec_sse\addons\identity\data\";
        // Format réel dans le groupe (unités d'interface : 1 x = 4/3 d'un y), centré.
        private _ratio = _t getOrDefault ["ratio", 1.585];
        (ctrlPosition _g) params ["", "", "_gw", "_gh"];
        private _ch = _gh;
        private _cw = _ch * _ratio * 0.75;
        if (_cw > _gw) then { _cw = _gw; _ch = _cw / (_ratio * 0.75); };
        private _cx = (_gw - _cw) / 2;
        private _cy = (_gh - _ch) / 2;
        private _ctrls = [];
        private _cfgSize = configFile >> "RscStructuredText" >> "size";
        private _base = if (isNumber _cfgSize) then { getNumber _cfgSize } else { call compile (getText _cfgSize) };
        if (isNil "_base" || {!(_base isEqualType 0)} || {_base <= 0}) then { _base = 0.04; };
        private _esc = { private _s = _this; if !(_s isEqualType "") then { _s = str _s; }; { _s = [_s, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _s };
        private _dmy = { private _p = _this splitString "-"; if ((count _p) < 3) exitWith { _this }; format ["%1.%2.%3", _p select 2, _p select 1, _p select 0] };
        private _pic = {
            params ["_fx", "_fy", "_fw", "_fh", "_path", ["_angle", 0]];
            if (_path isEqualTo "") exitWith {};
            private _c = _d ctrlCreate ["RscPicture", -1, _g];
            _c ctrlSetText _path;
            _c ctrlSetPosition [_cx + _fx * _cw, _cy + _fy * _ch, _fw * _cw, _fh * _ch];
            _c ctrlCommit 0;
            if (_angle isNotEqualTo 0) then { _c ctrlSetAngle [_angle, 0.5, 0.5]; };
            _ctrls pushBack _c;
        };
        private _put = {
            params ["_fx", "_fy", "_fw", "_fh", "_txt", ["_font", "RobotoCondensed"], ["_color", "#15181d"]];
            if (_txt isEqualTo "") exitWith {};
            private _c = _d ctrlCreate ["RscStructuredText", -1, _g];
            _c ctrlSetPosition [_cx + _fx * _cw, _cy + _fy * _ch, _fw * _cw, _fh * _ch * 1.5];
            _c ctrlSetBackgroundColor [0, 0, 0, 0];
            _c ctrlSetStructuredText parseText format ["<t size='%1' font='%2' color='%3' shadow='0'>%4</t>", (_fh * _ch) / _base, _font, _color, _txt call _esc];
            _c ctrlCommit 0;
            _ctrls pushBack _c;
        };
        [0, 0, 1, 1, _tpl] call _pic;
        // Photo : silhouette imprimée (décalée et de travers si elle est rapportée sur un faux, comme dans la visionneuse).
        private _photoBox = _layout getOrDefault ["photo", []];
        if (_photoBox isNotEqualTo []) then {
            _photoBox params ["_fx", "_fy", "_fw", "_fh"];
            private _swap = (_doc getOrDefault ["photo", false]) || {_doc getOrDefault ["photoSwap", false]};
            private _spot = if (_swap) then { [_fx + 0.008, _fy + 0.012, _fw * 0.97, _fh * 0.97, 2.5] } else { [_fx, _fy, _fw, _fh, 0] };
            _spot params ["_px", "_py", "_pw", "_ph", "_angle"];
            [_px, _py, _pw, _ph, _dir + "silhouette_co.paa", _angle] call _pic;
            if (fileExists (_dir + "photo_paper_ca.paa")) then { [_px, _py, _pw, _ph, _dir + "photo_paper_ca.paa", _angle] call _pic; };
        };
        private _holo = _layout getOrDefault ["holo", []];
        if (_holo isNotEqualTo [] && {_doc getOrDefault ["holo", true]}) then {
            _holo params ["_fx", "_fy", "_fw"];
            [_fx, _fy, _fw, _fw * _ratio, _dir + "hologram_ca.paa"] call _pic;
        };
        private _nameFont = ["RobotoCondensedBold", "TahomaB"] select (_doc getOrDefault ["font", false]);
        {
            private _f = _x;
            private _k = toLower (_f get "key");
            private _v = _f get "text";
            if (_v isEqualTo "") then {
                _v = switch (_k) do {
                    case "country": { _doc getOrDefault ["country", ""] };
                    case "countryname": { _doc getOrDefault ["countryName", ""] };
                    default { _doc getOrDefault [_f get "key", _doc getOrDefault [_k, ""]] };
                };
                if (_v isEqualTo "" && {(_f get "default") isNotEqualTo ""}) then { _v = _f get "default"; };
            };
            if !(_v isEqualType "") then { _v = str _v; };
            _v = switch (_f get "transform") do { case "upper": { toUpper _v }; case "dmy": { _v call _dmy }; default { _v }; };
            private _font = switch (_f get "font") do { case "name": { _nameFont }; case "mono": { "EtelkaMonospaceProBold" }; default { "RobotoCondensed" }; };
            [_f get "x", _f get "y", _f get "w", _f get "h", _v, _font, _f get "color"] call _put;
        } forEach (_layout getOrDefault ["fields", []]);
        if ((_t getOrDefault ["mrz", ""]) isNotEqualTo "" && {(_t getOrDefault ["mrzSide", "verso"]) isEqualTo _side}) then {
            private _lines = _doc getOrDefault ["mrz", []];
            if !(_lines isEqualType []) then { _lines = [_lines]; };
            if (_lines isNotEqualTo []) then {
                private _n = count (_lines select 0);
                private _fh = (0.92 * _cw / ((_n max 1) * 0.6 * 0.75)) / _ch;
                { [0.04, (_t get "mrzY") + _forEachIndex * (_t get "mrzStep"), 0.94, _fh, _x, "EtelkaMonospaceProBold", "#101010"] call _put; } forEach _lines;
            };
        };
        if (_zoned && {_side isEqualTo "recto"} && {_photoBox isNotEqualTo []}) then {
            private _hb = [0.155, 0.105] select (_ratio < 1.5);
            private _cn = _doc getOrDefault ["countryNameEn", ""];
            if (_cn isEqualTo "") then { _cn = _doc getOrDefault ["countryName", ""]; };
            [0.135, _hb * 0.1, 0.7, _hb * 0.36, toUpper _cn, "RobotoCondensedBold", "#ffffff"] call _put;
        };
        _g setVariable ["comspec_sse_cardCtrls", _ctrls];
        true
    };
    default { false };
};
