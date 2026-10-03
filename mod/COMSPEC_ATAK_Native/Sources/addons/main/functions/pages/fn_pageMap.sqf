/*
    Carte : contrôle carte natif + superpositions façon ATAK.
    - Carte « moi » en haut à gauche (indicatif, grille, cap, altitude), toujours visible, même porté.
    - En main : barre d'outils en icônes à droite (centrer, suivre, zoom, marqueur, mesure, libellés, effacer),
      panneau curseur en bas à gauche (grille, altitude, distance et azimut depuis moi), palette de marqueurs.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["_bx", "_by", "_bw", "_bh"];
private _mw = _bw - (_l get "inspW");
private _pad = (_l get "pad") * 2;
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _interactive = _l get "interactive";
private _dir = "\z\comspec_atak_native\addons\main\data\";
private _ov = createHashMap;

// Carte « moi »
private _meW = (_mw * 0.46) min (_font * 14 * pixelW / pixelH);
private _me = ["COMSPEC_RscMapPanel", [_bx + _pad, _by + _pad, _meW, _fs * 2.6], "", false] call comspec_atak_native_fnc_pageCtrl;
_ov set ["me", _me];

if (_interactive) then {
    // Barre d'outils verticale
    private _ih = ((_bh - 2 * _pad) / 9) min (_font * 2.2);
    private _iw = _ih * pixelW / pixelH;
    private _tx = _bx + _mw - _iw - _pad;
    private _bg = ["COMSPEC_RscMapPanel", [_tx - _pad / 2, _by + _pad / 2, _iw + _pad, _ih * 8 + _pad], "", false] call comspec_atak_native_fnc_pageCtrl;
    private _tools = [
        ["center", "map_center", "Centrer sur moi", { [player, 0.05] call comspec_atak_native_fnc_mapCenter; }],
        ["follow", "map_follow", "Suivre ma position", { private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; _s set ["mapFollow", !(_s getOrDefault ["mapFollow", false])]; [] call comspec_atak_native_fnc_mapOverlayUpdate; }],
        ["zoomin", "map_zoomin", "Zoom avant", { private _m = ([] call comspec_atak_native_fnc_display) displayCtrl 88530; (ctrlPosition _m) params ["_x", "_y", "_w", "_h"]; [_m ctrlMapScreenToWorld [_x + _w / 2, _y + _h / 2], ((ctrlMapScale _m) * 0.5) max 0.001] call comspec_atak_native_fnc_mapCenter; }],
        ["zoomout", "map_zoomout", "Zoom arrière", { private _m = ([] call comspec_atak_native_fnc_display) displayCtrl 88530; (ctrlPosition _m) params ["_x", "_y", "_w", "_h"]; [_m ctrlMapScreenToWorld [_x + _w / 2, _y + _h / 2], ((ctrlMapScale _m) * 2) min 1] call comspec_atak_native_fnc_mapCenter; }],
        ["MARKER", "map_marker", "Poser un marqueur (clic sur la carte)", { [["SELECT", "MARKER"] select (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["mapMode", ""]) isNotEqualTo "MARKER")] call comspec_atak_native_fnc_mapToolSet; ["MAP"] call comspec_atak_native_fnc_pageRender; }],
        ["MEASURE", "map_measure", "Mesurer distance et azimut (clic A puis B)", { [["SELECT", "MEASURE"] select (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["mapMode", ""]) isNotEqualTo "MEASURE")] call comspec_atak_native_fnc_mapToolSet; ["MAP"] call comspec_atak_native_fnc_pageRender; }],
        ["labels", "map_labels", "Afficher / masquer les indicatifs", { profileNamespace setVariable ["COMSPEC_ATAK_Labels", !(profileNamespace getVariable ["COMSPEC_ATAK_Labels", true])]; [] call comspec_atak_native_fnc_mapOverlayUpdate; }],
        ["clear", "map_clear", "Effacer la mesure et revenir à la sélection", { ["SELECT"] call comspec_atak_native_fnc_mapToolSet; ["MAP"] call comspec_atak_native_fnc_pageRender; }]
    ];
    {
        _x params ["_key", "_icon", "_tip", "_code"];
        private _b = ["COMSPEC_RscIconButton", [_tx, _by + _pad + _forEachIndex * _ih, _iw, _ih], _dir + _icon + ".paa", false] call comspec_atak_native_fnc_pageCtrl;
        _b ctrlSetTooltip _tip;
        _b ctrlAddEventHandler ["ButtonClick", _code];
        _ov set ["tool_" + _key, _b];
    } forEach _tools;

    // Panneau curseur
    private _cur = ["COMSPEC_RscMapPanel", [_bx + _pad, _by + _bh - _pad - _fs * 2.6, _meW, _fs * 2.6], "", false] call comspec_atak_native_fnc_pageCtrl;
    _ov set ["cursor", _cur];

    // Consigne de l'outil actif, et palette pour le marqueur
    private _mode = _s getOrDefault ["mapMode", "SELECT"];
    if (_mode in ["MARKER", "MEASURE"]) then {
        private _hint = ["COMSPEC_RscChip", [_bx + _pad * 2 + _meW, _by + _pad, (_tx - _bx - _meW - _pad * 4) max 0, _fs * 1.5], ["MESURE : clic A puis B · clic droit pour quitter", "MARQUEUR : clic pour poser · clic droit pour quitter"] select (_mode isEqualTo "MARKER"), false] call comspec_atak_native_fnc_pageCtrl;
        _hint ctrlSetFontHeight _fs;
    };
    if (_mode isEqualTo "MARKER") then {
        private _cur = _s getOrDefault ["markerKind", "ENI"];
        private _kinds = ["ENI", "AMI", "OBJ", "DNG", "PT"];
        private _cw = ((_tx - _bx - _meW - _pad * 4) / (count _kinds)) min (_font * 4 * pixelW / pixelH);
        {
            private _c = ["COMSPEC_RscButton", [_bx + _pad * 2 + _meW + _forEachIndex * (_cw + _pad / 2), _by + _pad * 1.5 + _fs * 1.5, _cw, _fs * 1.6], _x, false] call comspec_atak_native_fnc_pageCtrl;
            _c ctrlSetFontHeight _fs;
            if (_x isEqualTo _cur) then { _c ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.9]; _c ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
            _c setVariable ["kind", _x];
            _c ctrlAddEventHandler ["ButtonClick", { params ["_c"]; (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["markerKind", _c getVariable "kind"]; ["MAP"] call comspec_atak_native_fnc_pageRender; }];
        } forEach _kinds;
    };
};
uiNamespace setVariable ["COMSPEC_ATAK_MapOverlay", _ov];

if !(_s getOrDefault ["mapCentered", false]) then {
    _s set ["mapCentered", true];
    [player, 0.08] call comspec_atak_native_fnc_mapCenter;
};
[] call comspec_atak_native_fnc_mapOverlayUpdate;
if !(_l get "mini") then { [] call comspec_atak_native_fnc_inspectorUpdate; };
true
