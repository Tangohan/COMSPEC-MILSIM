/*
    Vue 3D d'un point de la carte (menu contextuel de la carte, « Vue 3D ici ») : caméra locale en orbite autour du point,
    rendue dans l'écran du téléphone (même render-to-texture que la Vidéo live, détruite en quittant la page par
    fn_livecamStop). Relief, bâtiments et végétation réels du jeu, sans passer par le réseau (données carte du téléphone).
    Commandes : ◀ ▶ tourne de 30°, + − rapproche ou éloigne, ▲ ▼ monte ou descend, NORD remet la vue face au nord,
    CARTE revient à la carte. Vue réservée au joueur (rien n'est envoyé aux autres).
    État : COMSPEC_ATAK_State "v3d" = [point ASL, cap en degrés, distance m, hauteur m].
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
(_s getOrDefault ["v3d", []]) params [["_pt", []], ["_dir", 0], ["_dist", 300], ["_hgt", 120]];
if (_pt isEqualTo []) exitWith { ["MAP", false] call comspec_atak_native_fnc_navigate; true };
private _btnH = _font * 1.6;
private _vx = _pad;
private _vy = _pad;
private _vw = _bw - 2 * _pad;
private _vh = _bh - 3 * _pad - 2 * _btnH;
// Caméra : sur le cercle d'orbite, au-dessus du sol (jamais sous le relief), regard sur le point.
private _cam = missionNamespace getVariable ["COMSPEC_ATAK_LivecamCam", objNull];
if (isNull _cam) then {
    _cam = "camera" camCreate (ASLToATL _pt);
    _cam cameraEffect ["Internal", "Back", "comspec_livecam"];
    _cam camSetFov 0.7;
    missionNamespace setVariable ["COMSPEC_ATAK_LivecamCam", _cam];
};
missionNamespace setVariable ["COMSPEC_ATAK_LivecamKind", "view3d"];
private _eye = _pt vectorAdd [-(sin _dir) * _dist, -(cos _dir) * _dist, 0];
_eye set [2, ((_pt select 2) + _hgt) max ((getTerrainHeightASL _eye) + 15)];
_cam setPosASL _eye;
_cam setVectorDirAndUp [vectorNormalized (_pt vectorDiff _eye), [0, 0, 1]];
_cam camCommit 0;
"comspec_livecam" setPiPEffect [[0, 2] select (sunOrMoon < 0.5)];
["COMSPEC_RscPhone", [_vx, _vy, _vw, _vh], "#(argb,512,512,1)r2t(comspec_livecam,1.0)"] call comspec_atak_native_fnc_pageCtrl;
// Bandeau : grille du point, cap de la vue, distance.
private _osd = ["COMSPEC_RscStructuredText", [_vx, _vy, _vw, _font * 1.3]] call comspec_atak_native_fnc_pageCtrl;
_osd ctrlSetBackgroundColor [0, 0, 0, 0.45];
_osd ctrlSetStructuredText parseText format ["<t size='0.85' color='#e6efe9'>%1 · %2 · %3 m · %4</t>",
    ("VUE 3D") call comspec_atak_native_fnc_tr, [_pt, 8] call comspec_atak_native_fnc_gridRef, round _dist,
    format ["%1°", [round _dir, 3] call CBA_fnc_formatNumber]];
// Commandes : deux rangées de boutons.
private _set = {
    params ["_k", "_v"];
    private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    (_st getOrDefault ["v3d", []]) params [["_p", []], ["_d", 0], ["_r", 300], ["_h", 120]];
    switch (_k) do {
        case "turn": { _d = (_d + _v + 360) mod 360; };
        case "zoom": { _r = ((_r * _v) max 40) min 3000; _h = ((_h * _v) max 10) min 2000; };
        case "up": { _h = ((_h + _v * (_r / 4 max 20)) max 5) min 2000; };
        case "north": { _d = 0; };
    };
    _st set ["v3d", [_p, _d, _r, _h]];
    [{ ["VIEW3D", false] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
};
missionNamespace setVariable ["COMSPEC_ATAK_V3dSet", _set];
private _rows = [
    [["◀", "['turn', -30]"], ["▶", "['turn', 30]"], ["+", "['zoom', 0.7]"], ["−", "['zoom', 1.4]"]],
    [["▲", "['up', 1]"], ["▼", "['up', -1]"], ["NORD", "['north', 0]"], ["CARTE", ""]]
];
private _bw4 = (_vw - 3 * _pad / 2) / 4;
{
    private _ry = _vy + _vh + _pad + _forEachIndex * (_btnH + _pad / 2);
    {
        _x params ["_t", "_code"];
        private _b = ["COMSPEC_RscButton", [_vx + _forEachIndex * (_bw4 + _pad / 2), _ry, _bw4, _btnH], _t] call comspec_atak_native_fnc_pageCtrl;
        _b ctrlSetFontHeight _fs;
        _b ctrlAddEventHandler ["ButtonClick", compile ([
            format ["%1 call (missionNamespace getVariable 'COMSPEC_ATAK_V3dSet');", _code],
            "[{ ['MAP', false] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;"
        ] select (_code isEqualTo ""))];
    } forEach _x;
} forEach _rows;
true
