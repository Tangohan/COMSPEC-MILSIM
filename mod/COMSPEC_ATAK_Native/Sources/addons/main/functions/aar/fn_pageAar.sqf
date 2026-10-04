/*
    App AAR : rejeu de la mission sur la carte du téléphone, à partir de ce qu'il a enregistré
    (positions du camp toutes les 10 s, pertes amies). Lecture, pas à pas, curseur, vitesse,
    traces et suivi de ma position. Données : fn_aarRecord ; commandes : fn_aarAction ; dessin : fn_aarDraw.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["_bx", "_by", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _rowH = _font * 1.5;
private _log = missionNamespace getVariable ["COMSPEC_ATAK_Aar", []];
private _p = uiNamespace getVariable ["COMSPEC_ATAK_AarPlay", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_AarPlay", _p];
if ((count _log) < 2) exitWith {
    [[["title", "Rejeu de mission"], ["text", format ["<t color='#8a9a93'>Le téléphone enregistre les positions de votre camp toutes les 10 secondes depuis le début de la mission. Revenez dans un instant (%1 image%2 pour l'instant).</t>", count _log, ["", "s"] select ((count _log) > 1)]]], [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
private _last = (count _log) - 1;
if ((_p getOrDefault ["idx", -1]) < 0 || {(_p getOrDefault ["idx", 0]) > _last}) then { _p set ["idx", _last]; };

private _btn = {
    params ["_rect", "_label", "_code", ["_active", false]];
    private _b = ["COMSPEC_RscButton", _rect, _label] call comspec_atak_native_fnc_pageCtrl;
    _b ctrlSetFontHeight _fs;
    if (_active) then { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
    _b ctrlAddEventHandler ["ButtonClick", _code];
    _b
};

// Commandes en bas : heure, curseur, boutons.
private _ctlH = _rowH * 3 + _pad * 4;
private _mapH = _bh - _ctlH;
private _map = ["COMSPEC_RscMap", [_bx, _by, _bw, _mapH], "", false] call comspec_atak_native_fnc_pageCtrl;
_map ctrlAddEventHandler ["Draw", { [_this select 0] call comspec_atak_native_fnc_aarDraw; }];
private _view = uiNamespace getVariable ["COMSPEC_ATAK_AarView", []];
if ((count _view) isEqualTo 2) then {
    _map ctrlMapAnimAdd [0, _view select 0, _view select 1];
} else {
    // Première ouverture : vue centrée sur l'ensemble des positions de l'image affichée.
    private _pts = ((_log select (floor (_p get "idx"))) select 2) apply { [_x select 0, _x select 1] };
    private _xs = _pts apply { _x select 0 }; private _ys = _pts apply { _x select 1 };
    private _c = [(selectMin _xs + selectMax _xs) / 2, (selectMin _ys + selectMax _ys) / 2];
    private _span = ((selectMax _xs - selectMin _xs) max (selectMax _ys - selectMin _ys)) max 800;
    _map ctrlMapAnimAdd [0, ((_span / worldSize) * 1.3) min 1, _c];
};
ctrlMapAnimCommit _map;

private _y = _mapH + _pad;
private _label = ["COMSPEC_RscStructuredText", [_pad, _y, _bw - 2 * _pad, _rowH]] call comspec_atak_native_fnc_pageCtrl;
_y = _y + _rowH + _pad;
private _slider = ["COMSPEC_RscSlider", [_pad, _y, _bw - 2 * _pad, _rowH * 0.8]] call comspec_atak_native_fnc_pageCtrl;
_slider sliderSetRange [0, _last];
_slider sliderSetSpeed [1, 6];
_slider ctrlAddEventHandler ["SliderPosChanged", { params ["", "_v"]; ["seek", round _v] call comspec_atak_native_fnc_aarAction; }];
_y = _y + _rowH + _pad;
private _playing = _p getOrDefault ["playing", false];
private _n = 7;
private _w = (_bw - 2 * _pad - (_n - 1) * _pad / 2) / _n;
{
    _x params ["_t", "_code", ["_on", false]];
    [[_pad + _forEachIndex * (_w + _pad / 2), _y, _w, _rowH], _t, _code, _on] call _btn;
} forEach [
    ["◀ 10 s", { ["step", -1] call comspec_atak_native_fnc_aarAction; }],
    [["LECTURE", "PAUSE"] select _playing, { ["play"] call comspec_atak_native_fnc_aarAction; }, _playing],
    ["10 s ▶", { ["step", 1] call comspec_atak_native_fnc_aarAction; }],
    [format ["x%1", _p getOrDefault ["speed", 30]], { ["speed"] call comspec_atak_native_fnc_aarAction; }],
    ["TRACES", { ["trails"] call comspec_atak_native_fnc_aarAction; }, _p getOrDefault ["trails", true]],
    ["SUIVRE", { ["follow"] call comspec_atak_native_fnc_aarAction; }, _p getOrDefault ["follow", false]],
    ["EFFACER", { ["clear"] call comspec_atak_native_fnc_aarAction; }]
];
uiNamespace setVariable ["COMSPEC_ATAK_AarCtrls", [_slider, _label, _map]];
["sync"] call comspec_atak_native_fnc_aarAction;

// Boucle de lecture, tant que l'app est ouverte.
if ((uiNamespace getVariable ["COMSPEC_ATAK_AarPfh", -1]) < 0) then {
    uiNamespace setVariable ["COMSPEC_ATAK_AarPfh", [{
        params ["", "_id"];
        if (isNull ([] call comspec_atak_native_fnc_display) || {((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isNotEqualTo "AAR"}) exitWith {
            [_id] call CBA_fnc_removePerFrameHandler;
            uiNamespace setVariable ["COMSPEC_ATAK_AarPfh", -1];
            (uiNamespace getVariable ["COMSPEC_ATAK_AarPlay", createHashMap]) set ["playing", false];
        };
        ["tick", 0.2] call comspec_atak_native_fnc_aarAction;
    }, 0.2] call CBA_fnc_addPerFrameHandler];
};
true
