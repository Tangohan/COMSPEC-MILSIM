/*
    BFT (Blue Force Tracking) : tableau des amis suivis, comme un terminal JBC-P.
    En-tête : ma position (grille, cap, altitude) et l'état des pistes (direct / ancienne / perdue).
    Filtres : TOUS, MON ÉQUIPE (équipe de feu), MON GROUPE, VÉHICULES, PERDUS. Tri par distance.
    Équipes de feu : l'icône de chaque piste prend la couleur de son équipe, nom de l'équipe et rôle dans la fiche.
    Chaque piste : symbole, indicatif, distance et gisement, temps de trajet, âge de la dernière position.
    Une piste hors ligne (téléphone éteint, cassé ou sans signal) reste figée à sa dernière position connue,
    et sans réseau toutes les pistes vieillissent.
    Piste choisie : fiche (trajet, rapprochement, itinéraire GPS) et actions (carte, GPS, SMS, faire vibrer).
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
// Temps de trajet : ma vitesse si je bouge, sinon la marche (5 km/h).
private _mySpd = vectorMagnitude velocity vehicle player;
private _walk = 5 / 3.6;
private _etaTxt = {
    params ["_m", "_v"];
    private _t = round (_m / (_v max 0.5) / 60);
    switch (true) do { case (_t < 1): { "< 1 min" }; case (_t < 60): { format ["%1 min", _t] }; default { format ["%1 h %2", floor (_t / 60), [str (_t mod 60), "0" + str (_t mod 60)] select ((_t mod 60) < 10)] }; }
};
private _distTxt = { params ["_d"]; [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 1]] select (_d >= 1000) };
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _fresh = createHashMapFromArray [["LIVE", ["DIRECT", "#5cc76b"]], ["STALE", ["ANCIENNE", "#f2ab33"]], ["LOST", ["PERDUE", "#e5483a"]], ["OFFLINE", ["HORS LIGNE", "#6c7671"]]];

// Pistes amies (hors moi), avec distance et gisement.
private _me = getPosASL player;
private _tracks = [];
{
    if ((_y getOrDefault ["affiliation", ""]) isEqualTo "friend" && {!(_y getOrDefault ["self", false])}) then {
        private _obj = _y getOrDefault ["object", objNull];
        private _live = (_y getOrDefault ["freshness", "LIVE"]) isEqualTo "LIVE";
        private _pos = if (isNull _obj || {!_live}) then { _y getOrDefault ["position", [0, 0, 0]] } else { getPosASL _obj };
        _tracks pushBack [player distance2D _pos, _x, _y, _obj, _pos];
    };
} forEach (_data getOrDefault ["units", createHashMap]);
private _counts = [0, 0, 0, 0];
{
    private _k = ["LIVE", "STALE", "LOST", "OFFLINE"] find ((_x select 2) getOrDefault ["freshness", "LIVE"]);
    if (_k < 0) then { _k = 2; };
    _counts set [_k, (_counts select _k) + 1];
} forEach _tracks;
private _myFt = player getVariable ["COMSPEC_FT", ""];
_tracks = _tracks select {
    private _e = _x select 2;
    switch (_filter) do {
        case "GROUP": { !isNull (_x select 3) && {group (_x select 3) isEqualTo group player} };
        case "FT": { !isNull (_x select 3) && {group (_x select 3) isEqualTo group player} && {_myFt isNotEqualTo ""} && {((_x select 3) getVariable ["COMSPEC_FT", ""]) isEqualTo _myFt} };
        case "VEH": { (_e getOrDefault ["type", "infantry"]) in ["vehicle", "armor", "air"] };
        case "LOST": { (_e getOrDefault ["freshness", "LIVE"]) in ["LOST", "OFFLINE"] };
        default { true };
    }
};
_tracks sort true;

// En-tête : ma position et le bilan des pistes.
private _y = 0;
private _meOn = (([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 1]) > 0;
private _head = ["COMSPEC_RscCard", [0, 0, _bw, _font * 2.6]] call comspec_atak_native_fnc_pageCtrl;
_head ctrlSetBackgroundColor [0.035, 0.05, 0.07, 1];
_head ctrlSetStructuredText parseText format [
    "<t size='0.75' color='#7fb6e6'> MOI </t><t font='EtelkaMonospacePro' size='0.9'>%1</t>  <t size='0.8' color='#8a9a93'>cap</t> %2° %3  <t size='0.8' color='#8a9a93'>alt</t> %4 m  <t size='0.8' color='#8a9a93'>vit</t> %9 km/h%10<br/><t size='0.75' color='#7fb6e6'> PISTES </t><t color='#5cc76b'>● %5 direct</t>   <t color='#f2ab33'>● %6 ancienne(s)</t>   <t color='#e5483a'>● %7 perdue(s)</t>   <t color='#8a948f'>○ %8 hors ligne</t>",
    [player, 8] call comspec_atak_native_fnc_gridRef, round getDir player, [getDir player] call _card, round (_me select 2), _counts select 0, _counts select 1, _counts select 2, _counts select 3,
    round (_mySpd * 3.6), ["  <t color='#e5483a'>● PAS DE RÉSEAU : pistes figées</t>", ""] select _meOn];
_y = _font * 2.6 + _pad / 2;

// Filtres
private _opts = [["TOUS", "ALL"], ["MON ÉQUIPE", "FT"], ["MON GROUPE", "GROUP"], ["VÉHICULES", "VEH"], ["PERDUS", "LOST"]];
private _fw = (_bw - _pad * 2) / 5;
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
_cols ctrlSetText "  INDICATIF                 DISTANCE · GISEMENT · TRAJET · ÂGE";
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
    // Équipe de feu (camp ami) : couleur de l'icône et nom de l'équipe après l'indicatif.
    private _ft = if (isNull _obj) then { createHashMap } else { [_obj] call comspec_atak_native_fnc_ftInfo };
    private _ftName = _ft getOrDefault ["name", ""];
    if (_ftName isNotEqualTo "") then { _color = _ft get "rgba"; };
    private _i = _list lbAdd format ["%1%2%3%4", _e getOrDefault ["callsign", _id], ["", format ["  · %1%2", _ftName, ["", " " + (_ft get "roleShort")] select ((_ft getOrDefault ["roleShort", ""]) isNotEqualTo "")]] select (_ftName isNotEqualTo ""), ["", "  (blessé)"] select _dead, ["", "  (hors ligne)"] select (_f isEqualTo "OFFLINE")];
    _list lbSetPicture [_i, _icon];
    _list lbSetPictureColor [_i, [_color, [0.45, 0.50, 0.55, 1]] select (_f in ["LOST", "OFFLINE"])];
    _list lbSetPictureColorSelected [_i, [1, 1, 1, 1]];
    _list lbSetTextRight [_i, format ["%1 · %2° %3 · %4 · %5", [_dist] call _distTxt, round (player getDir _pos), [player getDir _pos] call _card, [_dist, _mySpd max _walk] call _etaTxt, [format ["%1 s", _age], format ["%1 min", floor (_age / 60)]] select (_age >= 60)]];
    _list lbSetColor [_i, switch (true) do { case _dead: { [0.88, 0.25, 0.22, 1] }; case (_f isEqualTo "LIVE"): { [0.90, 0.94, 0.91, 1] }; case (_f isEqualTo "STALE"): { [0.95, 0.67, 0.20, 1] }; default { [0.55, 0.60, 0.58, 1] }; }];
    _list lbSetColorRight [_i, [0.62, 0.70, 0.66, 1]];
    _list lbSetTooltip [_i, format ["%1 · %2 · trajet %3 %4", (_fresh getOrDefault [_f, [_f]]) select 0, mapGridPosition _pos, [_dist, _mySpd max _walk] call _etaTxt, ["à pied", "à ma vitesse"] select (_mySpd > _walk)]];
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
    private _isLive = _f isEqualTo "LIVE";
    private _speed = if (isNull _obj || {!_isLive}) then { "—" } else { format ["%1 km/h", round (vectorMagnitude velocity vehicle _obj * 3.6)] };
    private _age = round (diag_tickTime - (_e getOrDefault ["updated", diag_tickTime]));
    private _ageTxt = [format ["%1 s", _age], format ["%1 min", floor (_age / 60)]] select (_age >= 60);
    // Trajet : à pied, à ma vitesse, et rapprochement mutuel (vitesse relative le long de l'axe).
    private _eta = format ["<t color='#8a9a93'>Trajet</t> %1 à pied", [_dist, _walk] call _etaTxt];
    if (_mySpd > _walk) then { _eta = _eta + format [" · %1 à ma vitesse", [_dist, _mySpd] call _etaTxt]; };
    private _close = "";
    if (_isLive && {!isNull _obj} && {_dist > 5}) then {
        private _ax = vectorNormalized ((getPosASL _obj) vectorDiff (getPosASL player));
        private _vc = ((velocity vehicle player) vectorDiff (velocity vehicle _obj)) vectorDotProduct _ax;
        _close = switch (true) do {
            case (_vc > 0.4): { format ["<br/><t color='#5cc76b'>Se rapproche</t> à %1 km/h · jonction dans %2", round (_vc * 3.6), [_dist, _vc] call _etaTxt] };
            case (_vc < -0.4): { format ["<br/><t color='#f2ab33'>S'éloigne</t> à %1 km/h", round (abs _vc * 3.6)] };
            default { "<br/><t color='#8a9a93'>Distance stable</t>" };
        };
    };
    // Itinéraire GPS en cours vers cette piste.
    private _rt = missionNamespace getVariable ["COMSPEC_ATAK_Route", createHashMap];
    if ((count _rt) > 0 && {(_rt getOrDefault ["label", ""]) isEqualTo (_e getOrDefault ["callsign", _id])}) then {
        // Reste calculé sur le dernier point suivi par le guidage (sans relancer le guidage lui-même).
        private _ri = _rt getOrDefault ["idx", 0];
        private _left = ((_rt get "total") - (((_rt get "cum") select _ri) + (((_rt get "pts") select _ri) distance2D (_rt getOrDefault ["proj", (_rt get "pts") select _ri])))) max 0;
        _close = _close + format ["<br/><t color='#7fb6e6'>GPS</t> %1 par la route · arrivée dans %2", [_left] call _distTxt, [_left, _mySpd max _walk] call _etaTxt];
    };
    private _offTxt = switch (true) do {
        case (_f isEqualTo "OFFLINE"): { format ["<br/><t color='#e5483a'>Hors ligne</t> <t color='#8a9a93'>(téléphone éteint, cassé ou sans signal) · dernière position il y a %1</t>", _ageTxt] };
        case (!_isLive): { format ["<br/><t color='#f2ab33'>Dernière position il y a %1</t>", _ageTxt] };
        default { "" };
    };
    private _health = if (isNull _obj) then { "—" } else { if (_f isEqualTo "OFFLINE") then { "<t color='#8a9a93'>inconnu</t>" } else { switch (true) do { case (!alive _obj): { "<t color='#e5483a'>Mort</t>" }; case (lifeState _obj isEqualTo "INCAPACITATED"): { "<t color='#e5483a'>Inconscient</t>" }; case ((damage _obj) > 0.25): { "<t color='#f2ab33'>Blessé</t>" }; default { "<t color='#5cc76b'>Apte</t>" }; } } };
    private _grp = if (isNull _obj) then { "Athena" } else { groupId group _obj };
    private _ftI = if (isNull _obj) then { createHashMap } else { [_obj] call comspec_atak_native_fnc_ftInfo };
    private _ftTxt = switch (true) do {
        case ((_ftI getOrDefault ["name", ""]) isNotEqualTo ""): { format ["<br/><t color='#8a9a93'>Équipe</t> <t color='%1' font='RobotoCondensedBold'>● %2</t>%3", _ftI get "hex", [_ftI get "name"] call _esc, ["", format [" · %1", _ftI get "roleLabel"]] select ((_ftI get "roleLabel") isNotEqualTo "")] };
        case ((_ftI getOrDefault ["roleLabel", ""]) isNotEqualTo ""): { format ["<br/><t color='#8a9a93'>Rôle</t> %1", _ftI get "roleLabel"] };
        default { "" };
    };
    private _crew = if (!isNull _obj && {vehicle _obj isNotEqualTo _obj}) then { format ["<br/><t color='#8a9a93'>Dans</t> %1", getText (configOf vehicle _obj >> "displayName")] } else { "" };
    _det ctrlSetStructuredText parseText format [
        "<t size='1.15' font='RobotoCondensedBold'>%1</t>  <t size='0.8' color='%2'>● %3</t><br/><t color='#8a9a93'>%4 · %5</t>%6%18<br/><t font='EtelkaMonospacePro'>%7</t><br/>%8 · %9° %10<br/>%15%16<br/><t color='#8a9a93'>Vitesse</t> %11  <t color='#8a9a93'>Cap</t> %12°  <t color='#8a9a93'>Alt</t> %13 m<br/><t color='#8a9a93'>État</t> %14%17",
        [_e getOrDefault ["callsign", _id]] call _esc, _fc, _ft, _type, [_grp] call _esc, _crew,
        [_pos, 8] call comspec_atak_native_fnc_gridRef,
        [format ["%1 m", round _dist], format ["%1 km", (_dist / 1000) toFixed 2]] select (_dist >= 1000), round (player getDir _pos), [player getDir _pos] call _card,
        _speed, round (_e getOrDefault ["heading", 0]), round (_pos select 2), _health, _eta, _close, _offTxt, _ftTxt];
};
private _bw3 = (_dw - _pad * 3) / 4;
{
    _x params ["_t", "_act", "_on"];
    private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select (_forEachIndex isEqualTo 1), [_dx + _forEachIndex * (_bw3 + _pad), _dy + _dh - _font * 1.5, _bw3, _font * 1.4], _t] call comspec_atak_native_fnc_pageCtrl;
    _b ctrlSetFontHeight _fs;
    _b ctrlEnable _on;
    _b ctrlAddEventHandler ["ButtonClick", compile format ["[{ ['%1'] call comspec_atak_native_fnc_bftAction; }] call CBA_fnc_execNextFrame;", _act]];
} forEach [
    ["CARTE", "center", (count _sel) > 0],
    ["Y ALLER", "route", (count _sel) > 0],
    ["SMS", "sms", (count _sel) > 0 && {!isNull (_sel select 3)} && {isPlayer (_sel select 3)}],
    [["VIBRER", "HORS LIGNE"] select ((count _sel) > 0 && {((_sel select 2) getOrDefault ["freshness", "LIVE"]) isEqualTo "OFFLINE"}), "buzz",
        _meOn && {(count _sel) > 0} && {!isNull (_sel select 3)} && {isPlayer (_sel select 3)} && {((_sel select 2) getOrDefault ["freshness", "LIVE"]) isNotEqualTo "OFFLINE"}]
];
true
