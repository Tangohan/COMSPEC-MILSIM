/*
    App Relief : champ de vision depuis ma position (cases vues / cachées par le terrain)
    et carte des altitudes autour de moi. Calcul par comspec_atak_native_fnc_reliefAction,
    affiché en calque sur l'app Carte (vert vu, rouge caché, ou dégradé d'altitude).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _radius = _s getOrDefault ["rlRadius", 1000];
private _mode = _s getOrDefault ["rlMode", "VIS"];
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _dist = { params ["_d"]; [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 1]] select (_d >= 1000) };
private _busy = missionNamespace getVariable ["COMSPEC_ATAK_ViewshedBusy", false];
private _vs = missionNamespace getVariable ["COMSPEC_ATAK_Viewshed", []];
private _show = missionNamespace getVariable ["COMSPEC_ATAK_ViewshedShow", true];
private _opt = { params ["_t", "_k", "_v", "_cur"]; [_t, compile format ["['set', '%1', %2] call comspec_atak_native_fnc_reliefAction;", _k, str _v], _v isEqualTo _cur] };

private _rows = [
    ["hero", "\z\comspec_atak_native\addons\main\data\app_relief.paa", format ["<t size='1.2' font='RobotoCondensedBold'>Relief et vues</t><br/><t color='#8a9a93'>Altitude %1 m · %2</t><br/><t size='0.8' color='#8a9a93'>Ce que je vois depuis ici, et ce qui me voit.</t>",
        round ((getPosASL player) select 2), [getPosASL player] call comspec_atak_native_fnc_gridRef]],
    ["section", "Réglages", "Grille de 41 x 41 cases autour de moi, œil à hauteur actuelle, cible à 1,7 m du sol"],
    ["segment", "Rayon", [["500 m", "rlRadius", 500, _radius] call _opt, ["1 km", "rlRadius", 1000, _radius] call _opt, ["2 km", "rlRadius", 2000, _radius] call _opt], format ["case de %1 m", round ((2 * _radius) / 40)]],
    ["segment", "Affichage", [["VISIBILITÉ", "rlMode", "VIS", _mode] call _opt, ["ALTITUDE", "rlMode", "ALT", _mode] call _opt]],
    ["switch", "Calque sur la carte", _show, { ["show"] call comspec_atak_native_fnc_reliefAction; }, ["Masqué : le calcul est conservé", "Vert : vu · rouge : caché (ou dégradé bleu / vert / rouge en altitude)"] select _show]
];
if (_busy) then {
    _rows pushBack ["text", format ["<t color='#f2ab33'>● Calcul en cours : %1 %%</t>", round (100 * (missionNamespace getVariable ["COMSPEC_ATAK_ViewshedProgress", 0]))]];
    _rows pushBack ["buttons", [["ACTUALISER", { [{ ['RELIEF'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }]]];
} else {
    _rows pushBack ["buttons", [["CALCULER", { ["compute"] call comspec_atak_native_fnc_reliefAction; }, true], ["EFFACER", { ["clear"] call comspec_atak_native_fnc_reliefAction; }]]];
};

if ((count _vs) > 8) then {
    _vs params ["_center", "_cell", "_cells", "_r", "_minH", "_maxH", "_pctVis", "_best", "_when"];
    private _moved = player distance2D _center;
    _rows append [
        ["section", "Résultat", format ["Calculé à %1 sur %2 de rayon", [_when, "HH:MM"] call BIS_fnc_timeToString, [_r] call _dist]],
        ["info", "Terrain visible", format ["<t color='%1'>%2 %%</t>", ["#e5483a", "#f2ab33", "#5cc76b"] select ((floor (_pctVis / 34)) min 2), _pctVis]],
        ["info", "Terrain caché", format ["%1 %%", 100 - _pctVis]],
        ["info", "Altitude mini / maxi", format ["%1 m / %2 m", round _minH, round _maxH]],
        ["info", "Dénivelé", format ["%1 m", round (_maxH - _minH)]]
    ];
    if (_best >= 0) then {
        private _bp = (_cells select _best) select 0;
        _rows pushBack ["person", "\A3\ui_f\data\map\markers\military\triangle_CA.paa", format ["<t font='RobotoCondensedBold'>Point haut : %1 m</t><br/><t size='0.85' color='#8a9a93'>%2 · %3 %4 (%5°)</t>",
            round (_bp select 2), [_bp, 8] call comspec_atak_native_fnc_gridRef, [player distance2D _bp] call _dist, [player getDir _bp] call _card, round (player getDir _bp)],
            [["CARTE", { ["locate"] call comspec_atak_native_fnc_reliefAction; }, true]], [0.95, 0.55, 0.2, 1]];
    };
    // Secteurs : part visible par huitième de cercle, pour savoir d'où vient le danger.
    private _sect = [0, 0, 0, 0, 0, 0, 0, 0];
    private _sectN = [0, 0, 0, 0, 0, 0, 0, 0];
    {
        _x params ["_p", "_seen"];
        if ((_p distance2D _center) < _cell) then { continue };
        private _k = (round ((_center getDir _p) / 45)) mod 8;
        _sectN set [_k, (_sectN select _k) + 1];
        if (_seen) then { _sect set [_k, (_sect select _k) + 1]; };
    } forEach _cells;
    _rows pushBack ["section", "Vues par secteur", "Part du terrain visible dans chaque direction"];
    {
        private _p = round (100 * (_sect select _forEachIndex) / ((_sectN select _forEachIndex) max 1));
        _rows pushBack ["info", _x, format ["<t color='%1'>%2 %%</t>", ["#e5483a", "#f2ab33", "#5cc76b"] select ((floor (_p / 34)) min 2), _p]];
    } forEach ["Nord", "Nord-est", "Est", "Sud-est", "Sud", "Sud-ouest", "Ouest", "Nord-ouest"];
    if (_moved > _cell * 2) then {
        _rows pushBack ["text", format ["<t size='0.8' color='#f2ab33'>Vous avez bougé de %1 depuis le calcul : relancez-le pour une vue à jour.</t>", [_moved] call _dist]];
    };
    _rows pushBack ["buttons", [["VOIR SUR LA CARTE", { ["locate", "center"] call comspec_atak_native_fnc_reliefAction; }]]];
} else {
    if !(_busy) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun calcul. Touchez CALCULER : quelques secondes selon le rayon.</t>"]; };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
