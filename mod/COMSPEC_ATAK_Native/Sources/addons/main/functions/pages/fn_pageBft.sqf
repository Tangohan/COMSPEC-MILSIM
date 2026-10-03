/*
    BFT (Blue Force Tracking) : tableau des amis suivis, comme un terminal JBC-P.
    En-tête : ma position (grille, cap, altitude) et l'état des pistes (direct / ancienne / perdue).
    Filtres : TOUS, MON GROUPE, VÉHICULES, PERDUS. Tri par distance.
    Chaque piste : symbole, indicatif, distance et gisement, âge de la dernière position.
    Piste choisie : fiche détaillée et actions (centrer la carte, SMS).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _land = _l get "landscape";
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _filter = _s getOrDefault ["bftFilter", "ALL"];
private _selId = _s getOrDefault ["bftSel", ""];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[_t, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _fresh = createHashMapFromArray [["LIVE", ["DIRECT", "#5cc76b"]], ["STALE", ["ANCIENNE", "#f2ab33"]], ["LOST", ["PERDUE", "#e5483a"]], ["OFFLINE", ["HORS LIGNE", "#6c7671"]]];

// Pistes amies (hors moi), avec distance et gisement.
private _me = getPosASL player;
private _tracks = [];
{
    if ((_y getOrDefault ["affiliation", ""]) isEqualTo "friend" && {!(_y getOrDefault ["self", false])}) then {
        private _obj = _y getOrDefault ["object", objNull];
        private _pos = if (isNull _obj) then { _y getOrDefault ["position", [0, 0, 0]] } else { getPosASL _obj };
        _tracks pushBack [player distance2D _pos, _x, _y, _obj, _pos];
    };
} forEach (_data getOrDefault ["units", createHashMap]);
private _counts = [0, 0, 0];
{
    private _k = ["LIVE", "STALE"] find ((_x select 2) getOrDefault ["freshness", "LIVE"]);
    if (_k < 0) then { _k = 2; };
    _counts set [_k, (_counts select _k) + 1];
} forEach _tracks;
_tracks = _tracks select {
    private _e = _x select 2;
    switch (_filter) do {
        case "GROUP": { !isNull (_x select 3) && {group (_x select 3) isEqualTo group player} };
        case "VEH": { (_e getOrDefault ["type", "infantry"]) in ["vehicle", "armor", "air"] };
        case "LOST": { (_e getOrDefault ["freshness", "LIVE"]) in ["LOST", "OFFLINE"] };
        default { true };
    }
};
_tracks sort true;

// En-tête : ma position et le bilan des pistes.
private _y = 0;
private _head = ["COMSPEC_RscCard", [0, 0, _bw, _font * 2.6]] call comspec_atak_native_fnc_pageCtrl;
_head ctrlSetBackgroundColor [0.035, 0.05, 0.07, 1];
_head ctrlSetStructuredText parseText format [
    "<t size='0.75' color='#7fb6e6'> MOI </t><t font='EtelkaMonospacePro' size='0.9'>%1</t>  <t size='0.8' color='#8a9a93'>cap</t> %2° %3  <t size='0.8' color='#8a9a93'>alt</t> %4 m<br/><t size='0.75' color='#7fb6e6'> PISTES </t><t color='#5cc76b'>● %5 direct</t>   <t color='#f2ab33'>● %6 ancienne(s)</t>   <t color='#e5483a'>● %7 perdue(s)</t>",
    [player, 8] call comspec_atak_native_fnc_gridRef, round getDir player, [getDir player] call _card, round (_me select 2), _counts select 0, _counts select 1, _counts select 2];
_y = _font * 2.6 + _pad / 2;

// Filtres
private _opts = [["TOUS", "ALL"], ["MON GROUPE", "GROUP"], ["VÉHICULES", "VEH"], ["PERDUS", "LOST"]];
private _fw = (_bw - _pad * 2) / 4;
{
    _x params ["_t", "_k"];
    private _b = ["COMSPEC_RscButton", [_pad + _forEachIndex * _fw, _y, _fw - _pad / 4, _font * 1.4], _t] call comspec_atak_native_fnc_pageCtrl;
    _b ctrlSetFontHeight (_fs * 0.9);
    if (_k isEqualTo _filter) then { _b ctrlSetBackgroundColor [0.28, 0.55, 0.85, 1]; _b ctrlSetTextColor [1, 1, 1, 1]; };
    _b ctrlAddEventHandler ["ButtonClick", compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['bftFilter', '%1']; [{ ['BFT'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k]];
} forEach _opts;
_y = _y + _font * 1.4 + _pad / 2;

// Tableau des pistes (à gauche en paysage, en haut en portrait) et fiche de la piste choisie.
private _detailH = [_bh * 0.36, 0] select _land;
private _listW = [_bw, _bw * 0.58] select _land;
private _listH = _bh - _y - _detailH - _pad / 2;
private _cols = ["COMSPEC_RscLabel", [_pad, _y, _listW - _pad, _fs * 1.2], ""] call comspec_atak_native_fnc_pageCtrl;
_cols ctrlSetText "  INDICATIF                        DISTANCE · GISEMENT · ÂGE";
_cols ctrlSetFontHeight (_fs * 0.8);
_y = _y + _fs * 1.2;
_listH = _listH - _fs * 1.2;
private _list = ["COMSPEC_RscListBox", [0, _y, _listW, _listH]] call comspec_atak_native_fnc_pageCtrl;
_list ctrlSetFontHeight _font;
_list ctrlSetBackgroundColor [0.03, 0.04, 0.05, 0.9];
private _ids = [];
private _selIdx = -1;
{
    _x params ["_dist", "_id", "_e", "_obj", "_pos"];
    private _f = _e getOrDefault ["freshness", "LIVE"];
    private _age = round (diag_tickTime - (_e getOrDefault ["updated", diag_tickTime]));
    ([_e] call comspec_atak_native_fnc_symbology) params ["_icon", "_color"];
    private _dead = !isNull _obj && {!alive _obj || {lifeState _obj isEqualTo "INCAPACITATED"}};
    private _i = _list lbAdd format ["%1%2", _e getOrDefault ["callsign", _id], ["", "  (blessé)"] select _dead];
    _list lbSetPicture [_i, _icon];
    _list lbSetPictureColor [_i, [_color, [0.45, 0.50, 0.55, 1]] select (_f in ["LOST", "OFFLINE"])];
    _list lbSetPictureColorSelected [_i, [1, 1, 1, 1]];
    _list lbSetTextRight [_i, format ["%1 · %2° %3 · %4", [format ["%1 m", round _dist], format ["%1 km", (_dist / 1000) toFixed 1]] select (_dist >= 1000), round (player getDir _pos), [player getDir _pos] call _card, [format ["%1 s", _age], format ["%1 min", floor (_age / 60)]] select (_age >= 60)]];
    _list lbSetColor [_i, switch (true) do { case _dead: { [0.88, 0.25, 0.22, 1] }; case (_f isEqualTo "LIVE"): { [0.90, 0.94, 0.91, 1] }; case (_f isEqualTo "STALE"): { [0.95, 0.67, 0.20, 1] }; default { [0.55, 0.60, 0.58, 1] }; }];
    _list lbSetColorRight [_i, [0.62, 0.70, 0.66, 1]];
    _list lbSetTooltip [_i, format ["%1 · %2", (_fresh getOrDefault [_f, [_f]]) select 0, mapGridPosition _pos]];
    _ids pushBack _id;
    if (_id isEqualTo _selId) then { _selIdx = _i; };
} forEach _tracks;
if ((count _tracks) isEqualTo 0) then {
    private _i = _list lbAdd (["Aucune piste amie suivie.", "Aucune piste pour ce filtre."] select (_filter isNotEqualTo "ALL"));
    _list lbSetColor [_i, [0.55, 0.60, 0.58, 1]];
};
uiNamespace setVariable ["COMSPEC_ATAK_BftIds", _ids];
if (_selIdx < 0 && {(count _ids) > 0}) then { _selIdx = 0; _selId = _ids select 0; _s set ["bftSel", _selId]; };
if (_selIdx >= 0) then { _list lbSetCurSel _selIdx; };
_list ctrlAddEventHandler ["LBSelChanged", {
    params ["", "_i"];
    private _id = (uiNamespace getVariable ["COMSPEC_ATAK_BftIds", []]) param [_i, ""];
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    if (_id isEqualTo "" || {_id isEqualTo (_s getOrDefault ["bftSel", ""])}) exitWith {};
    _s set ["bftSel", _id];
    [{ ['BFT'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];
_list ctrlAddEventHandler ["LBDblClick", { [{ ["center"] call comspec_atak_native_fnc_bftAction; }] call CBA_fnc_execNextFrame; }];

// Fiche de la piste choisie.
private _dRect = if (_land) then { [_listW + _pad / 2, _y - _fs * 1.2, _bw - _listW - _pad / 2, _bh - _y + _fs * 1.2] } else { [0, _y + _listH + _pad / 2, _bw, _detailH] };
_dRect params ["_dx", "_dy", "_dw", "_dh"];
private _sel = (_tracks select { (_x select 1) isEqualTo _selId }) param [0, []];
private _det = ["COMSPEC_RscCard", [_dx, _dy, _dw, _dh - _font * 1.6], ""] call comspec_atak_native_fnc_pageCtrl;
_det ctrlSetBackgroundColor [0.035, 0.05, 0.07, 1];
if ((count _sel) isEqualTo 0) then {
    _det ctrlSetStructuredText parseText "<t color='#8a9a93'>Choisissez une piste dans le tableau.</t>";
} else {
    _sel params ["_dist", "_id", "_e", "_obj", "_pos"];
    private _f = _e getOrDefault ["freshness", "LIVE"];
    (_fresh getOrDefault [_f, [_f, "#8a9a93"]]) params ["_ft", "_fc"];
    private _type = switch (_e getOrDefault ["type", "infantry"]) do { case "air": { "Aérien" }; case "armor": { "Blindé" }; case "vehicle": { "Véhicule" }; default { "Infanterie" }; };
    private _speed = if (isNull _obj) then { "—" } else { format ["%1 km/h", round (vectorMagnitude velocity vehicle _obj * 3.6)] };
    private _health = if (isNull _obj) then { "—" } else { switch (true) do { case (!alive _obj): { "<t color='#e5483a'>Mort</t>" }; case (lifeState _obj isEqualTo "INCAPACITATED"): { "<t color='#e5483a'>Inconscient</t>" }; case ((damage _obj) > 0.25): { "<t color='#f2ab33'>Blessé</t>" }; default { "<t color='#5cc76b'>Apte</t>" }; } };
    private _grp = if (isNull _obj) then { "Athena" } else { groupId group _obj };
    private _crew = if (!isNull _obj && {vehicle _obj isNotEqualTo _obj}) then { format ["<br/><t color='#8a9a93'>Dans</t> %1", getText (configOf vehicle _obj >> "displayName")] } else { "" };
    _det ctrlSetStructuredText parseText format [
        "<t size='1.15' font='RobotoCondensedBold'>%1</t>  <t size='0.8' color='%2'>● %3</t><br/><t color='#8a9a93'>%4 · %5</t>%6<br/><t font='EtelkaMonospacePro'>%7</t><br/>%8 · %9° %10<br/><t color='#8a9a93'>Vitesse</t> %11  <t color='#8a9a93'>Cap</t> %12°  <t color='#8a9a93'>Alt</t> %13 m<br/><t color='#8a9a93'>État</t> %14",
        [_e getOrDefault ["callsign", _id]] call _esc, _fc, _ft, _type, [_grp] call _esc, _crew,
        [_pos, 8] call comspec_atak_native_fnc_gridRef,
        [format ["%1 m", round _dist], format ["%1 km", (_dist / 1000) toFixed 2]] select (_dist >= 1000), round (player getDir _pos), [player getDir _pos] call _card,
        _speed, round (_e getOrDefault ["heading", 0]), round (_pos select 2), _health];
};
private _bw3 = (_dw - _pad) / 2;
{
    _x params ["_t", "_act", "_on"];
    private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select (_forEachIndex isEqualTo 0), [_dx + _forEachIndex * (_bw3 + _pad), _dy + _dh - _font * 1.5, _bw3, _font * 1.4], _t] call comspec_atak_native_fnc_pageCtrl;
    _b ctrlSetFontHeight _fs;
    _b ctrlEnable _on;
    _b ctrlAddEventHandler ["ButtonClick", compile format ["[{ ['%1'] call comspec_atak_native_fnc_bftAction; }] call CBA_fnc_execNextFrame;", _act]];
} forEach [["CENTRER SUR LA CARTE", "center", (count _sel) > 0], ["ENVOYER UN SMS", "sms", (count _sel) > 0 && {!isNull (_sel select 3)} && {isPlayer (_sel select 3)}]];
true
