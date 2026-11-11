/*
    Habillage d'un papier de pays réel (registre CfgComspecCountries) posé en jeu sur un gabarit sans texte :
    bandeau à la couleur du pays et drapeau simple en bandes (gabarit neutre « bare » seulement ; le gabarit propre du pays
    les porte déjà), nom du pays (ou de l'État, États-Unis), titre du papier dans la langue du pays et en anglais,
    étiquettes des champs dans la langue du pays et en anglais (« Vornamen / Given names »).
    Papiers de JEU : aucun emblème, la mention SPÉCIMEN · SIMULATION est peinte sur le gabarit.
    [display, [x, y, w, h], doc, type, côté, mise en page, chemin du gabarit] call comspec_sse_fnc_idCountryChrome
      -> [contrôles créés] (à supprimer par l'appelant) ; rien pour un pays fictif ou un gabarit qui peint ses étiquettes.
    Utilisé par la visionneuse (fn_idViewerOnLoad) et l'aperçu du sélecteur (fn_idDocPicker).
*/
params ["_d", "_geo", "_doc", "_type", "_side", "_layout", ["_tpl", ""]];
disableSerialization;
_geo params ["_cx", "_cy", "_cw", "_ch"];
private _ctrls = [];
private _code = toUpper (_doc getOrDefault ["country", ""]);
private _c = ([] call comspec_sse_fnc_idCountries) getOrDefault [_code, createHashMap];
if ((count _c) isEqualTo 0 || {_c getOrDefault ["fictional", true]}) exitWith { _ctrls };
private _low = toLower _tpl;
private _ownArt = (_low find format ["_%1_", toLower _code]) >= 0;
private _bareArt = (_low find "_bare_") >= 0;
if (!_ownArt && {!_bareArt}) exitWith { _ctrls };
private _t = ["get", _type] call comspec_sse_fnc_idDocTypes;
private _ratio = _t getOrDefault ["ratio", 1.585];
private _en = (missionNamespace getVariable ["COMSPEC_ATAK_Lang", "fr"]) isNotEqualTo "fr";
private _cfgSize = configFile >> "RscStructuredText" >> "size";
private _base = if (isNumber _cfgSize) then { getNumber _cfgSize } else { call compile (getText _cfgSize) };
if (isNil "_base" || {!(_base isEqualType 0)} || {_base <= 0}) then { _base = 0.04; };
private _esc = { private _s = _this; { _s = [_s, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _s };
private _rgba = {
    // « #RRGGBB » -> [r, g, b, 1]
    private _h = toArray (toUpper (_this select [1]));
    private _v = { private _a = _this; [_a - 48, _a - 55] select (_a >= 65) };
    private _o = [0, 2, 4] apply { (((_h param [_x, 48]) call _v) * 16 + ((_h param [_x + 1, 48]) call _v)) / 255 };
    _o pushBack 1;
    _o
};
private _text = {
    params ["_fx", "_fy", "_fw", "_fh", "_txt", ["_font", "RobotoCondensed"], ["_color", "#15181d"]];
    if (_txt isEqualTo "") exitWith {};
    private _k = _d ctrlCreate ["RscStructuredText", -1];
    _k ctrlSetPosition [_cx + _fx * _cw, _cy + _fy * _ch, _fw * _cw, _fh * _ch * 1.5];
    _k ctrlSetBackgroundColor [0, 0, 0, 0];
    _k ctrlSetStructuredText parseText format ["<t size='%1' font='%2' color='%3' shadow='0'>%4</t>", (_fh * _ch) / _base, _font, _color, _txt call _esc];
    _k ctrlCommit 0;
    _ctrls pushBack _k;
};
// Texte ajusté à une largeur (fraction du papier) : chasse estimée des capitales condensées, partie anglaise retirée si
// elle ne tient pas, puis taille réduite jusqu'à 60 %, puis coupé. -> [texte, hauteur]
private _fit = {
    params ["_txt", "_avail", "_h", ["_k", 0.5]];
    private _w = { (count _this) * _k * _h / _ratio };
    if ((_txt call _w) > _avail && {(_txt find " · ") > 0 || {(_txt find " / ") > 0}}) then {
        private _cut = [_txt find " / ", _txt find " · "] select ((_txt find " / ") < 0);
        if ((_txt find " / ") > 0) then { _cut = _txt find " / "; };
        _txt = _txt select [0, _cut];
    };
    private _need = _txt call _w;
    if (_need > _avail) then { _h = _h * ((_avail / _need) max 0.6); };
    private _max = floor (_avail / (_k * _h / _ratio));
    if ((count _txt) > _max) then { _txt = (_txt select [0, (_max - 1) max 1]) + "…"; };
    [_txt, _h]
};
private _rect = {
    params ["_fx", "_fy", "_fw", "_fh", "_col"];
    private _k = _d ctrlCreate ["RscText", -1];
    _k ctrlSetPosition [_cx + _fx * _cw, _cy + _fy * _ch, _fw * _cw, _fh * _ch];
    _k ctrlSetBackgroundColor _col;
    _k ctrlCommit 0;
    _ctrls pushBack _k;
};
// Drapeau simple (bandes ; croix ; canton) dans [x, y, w, h] : jamais d'armoiries ni d'emblème.
private _flag = {
    params ["_fx", "_fy", "_fw", "_fh"];
    private _cols = (_c getOrDefault ["flagColors", ["#FFFFFF"]]) apply { _x call _rgba };
    private _n = count _cols;
    switch (_c getOrDefault ["flagKind", "h"]) do {
        case "v": { { [_fx + _fw * _forEachIndex / _n, _fy, _fw / _n, _fh, _x] call _rect; } forEach _cols; };
        case "canton": {
            for "_i" from 0 to 6 do { [_fx, _fy + _fh * _i / 7, _fw, _fh / 7, _cols select (_i mod 2)] call _rect; };
            [_fx, _fy, _fw * 0.42, _fh * 4 / 7, _cols param [2, _cols select 0]] call _rect;
        };
        case "cross": {
            [_fx, _fy, _fw, _fh, _cols select 0] call _rect;
            [_fx, _fy + _fh * 0.36, _fw, _fh * 0.28, [1, 1, 1, 1]] call _rect;
            [_fx + _fw * 0.42, _fy, _fw * 0.16, _fh, [1, 1, 1, 1]] call _rect;
            [_fx, _fy + _fh * 0.41, _fw, _fh * 0.18, _cols param [1, [0.8, 0, 0, 1]]] call _rect;
            [_fx + _fw * 0.45, _fy, _fw * 0.1, _fh, _cols param [1, [0.8, 0, 0, 1]]] call _rect;
        };
        case "nordic": {
            [_fx, _fy, _fw, _fh, _cols select 0] call _rect;
            [_fx, _fy + _fh * 0.38, _fw, _fh * 0.24, _cols param [1, [1, 1, 1, 1]]] call _rect;
            [_fx + _fw * 0.28, _fy, _fw * 0.16, _fh, _cols param [1, [1, 1, 1, 1]]] call _rect;
            if (_n > 2) then {
                [_fx, _fy + _fh * 0.44, _fw, _fh * 0.12, _cols select 2] call _rect;
                [_fx + _fw * 0.32, _fy, _fw * 0.08, _fh, _cols select 2] call _rect;
            };
        };
        case "swiss": {
            [_fx, _fy, _fw, _fh, _cols select 0] call _rect;
            [_fx + _fw * 0.4, _fy + _fh * 0.18, _fw * 0.2, _fh * 0.64, _cols param [1, [1, 1, 1, 1]]] call _rect;
            [_fx + _fw * 0.27, _fy + _fh * 0.4, _fw * 0.46, _fh * 0.2, _cols param [1, [1, 1, 1, 1]]] call _rect;
        };
        default { { [_fx, _fy + _fh * _forEachIndex / _n, _fw, _fh / _n, _x] call _rect; } forEach _cols; };
    };
};

// --- Bandeau, drapeau, nom du pays, titre (recto) ---------------------------------------------------------------
private _photo = (_layout getOrDefault ["photo", []]) isNotEqualTo [];
if (_side isEqualTo "recto") then {
    private _hb = [0.155, 0.105] select (_ratio < 1.5);
    private _fh = _hb * 0.62;
    // Drapeau 3:2 au format réel du papier (même place que tools/countries/gen_country_assets.py).
    private _fw = 1.5 * _fh / _ratio;
    private _fx = 0.025;
    private _fy = _hb * 0.19;
    if (_bareArt) then {
        [0, 0, 1, _hb, (_c getOrDefault ["colorMain", "#22529E"]) call _rgba] call _rect;
        [_fx - 0.004, _fy - 0.012, _fw + 0.008, _fh + 0.024, [1, 1, 1, 0.9]] call _rect;
        [_fx, _fy, _fw, _fh] call _flag;
    };
    private _nx = _fx + _fw + 0.025;
    private _name = toUpper (_c getOrDefault ["nameDoc", _c getOrDefault ["nameEn", _code]]);
    // États-Unis : l'État délivre permis et carte d'identité (« CALIFORNIA »), le passeport reste fédéral.
    if ((_doc getOrDefault ["state", ""]) isNotEqualTo "" && {_code isEqualTo "USA"} && {_type in ["driving_license", "id_card", "vehicle_registration"]}) then {
        _name = format ["STATE OF %1", toUpper (_doc get "state")];
    };
    // Bandeau : nom à gauche jusqu'au symbole e-document du passeport ou au bord.
    private _room = [0.965, 0.9] select (_type isEqualTo "passport");
    ([_name, _room - _nx, _hb * 0.36, 0.55] call _fit) params ["_nt", "_nh"];
    [_nx, _hb * 0.08 + (_hb * 0.36 - _nh) / 2, _room - _nx, _nh, _nt, "RobotoCondensedBold", "#ffffff"] call _text;
    private _dn = ((_c getOrDefault ["docNames", createHashMap]) getOrDefault [_type, []]);
    private _title = if ((count _dn) isEqualTo 3) then {
        [toUpper (_dn select 0), format ["%1 · %2", toUpper (_dn select 0), toUpper (_dn select 2)]] select ((toUpper (_dn select 0)) isNotEqualTo (toUpper (_dn select 2)))
    } else { toUpper (["label", _type] call comspec_sse_fnc_idDocTypes) };
    ([_title, _room - _nx, _hb * 0.26, 0.5] call _fit) params ["_tt", "_th"];
    [_nx, _hb * 0.56, _room - _nx, _th, _tt, "RobotoCondensed", "#f2f4f7"] call _text;
};

// --- Étiquettes des champs (langue du pays / anglais) -----------------------------------------------------------------
private _enL = createHashMapFromArray [
    ["last", "Surname"], ["first", "Given names"], ["sex", "Sex"], ["nat", "Nationality"], ["dob", "Date of birth"], ["pob", "Place of birth"],
    ["number", "Document No."], ["expires", "Date of expiry"], ["issued", "Date of issue"], ["authority", "Authority"], ["address", "Address"],
    ["profession", "Occupation"], ["height", "Height"], ["personal", "Personal No."], ["plate", "Registration No."], ["vehicle", "Make / model"],
    ["vin", "VIN"], ["permit", "Type of permit"], ["cat", "Categories"], ["holder", "Holder"], ["firstreg", "First registration"],
    ["rank", "Rank"], ["force", "Service"], ["type", "Type"], ["code", "Code"], ["country", "Code"], ["remarks", "Remarks"]
];
private _labels = createHashMap;
{ _labels set [toLower _x, _y]; } forEach (_c getOrDefault ["labels", createHashMap]);
private _optional = ["height", "personal", "permit", "state", "plate", "vin", "vehicle", "holder", "firstreg"];
private _labCol = ["#4f5a68", "#3d4652"] select _bareArt;
{
    private _f = _x;
    private _k = toLower (_f get "key");
    if (_k isEqualTo "") then { _k = toLower (_f getOrDefault ["name", ""]); };
    if (_k isEqualTo "ptype") then { _k = "type"; };
    private _v = _doc getOrDefault [_f get "key", ""];
    if (_k in _optional && {!(_v isEqualType "") || {_v isEqualTo ""}}) then { continue };
    private _nat = _labels getOrDefault [_k, ""];
    private _eng = _enL getOrDefault [_k, ""];
    private _lab = switch (true) do {
        case (_nat isEqualTo ""): { _eng };
        case (_eng isEqualTo "" || {(toUpper _nat) isEqualTo (toUpper _eng)}): { _nat };
        default { format ["%1 / %2", _nat, _eng] };
    };
    if (_en && {_lab isEqualTo ""}) then { _lab = _eng; };
    // Place jusqu'au champ suivant de la même ligne (ou au bord) : l'étiquette ne déborde jamais sur sa voisine.
    private _fx = _f get "x";
    private _fy = _f get "y";
    private _avail = 0.97 - _fx;
    { if ((_x get "x") > _fx + 0.001 && {abs ((_x get "y") - _fy) < 0.02}) then { _avail = _avail min ((_x get "x") - _fx - 0.012); }; } forEach (_layout getOrDefault ["fields", []]);
    ([toUpper _lab, _avail, 0.021, 0.46] call _fit) params ["_lt", "_lh"];
    [_fx, _fy - 0.038 + (0.021 - _lh), _avail, _lh, _lt, "RobotoCondensed", _labCol] call _text;
} forEach (_layout getOrDefault ["fields", []]);
_ctrls
