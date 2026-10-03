/*
    App Musique, onglet LECTURE : pochette, titre, source, barre de progression, temps et égaliseur animé.
    Params : [[x, y, w, h] dans la zone de contenu]. Rafraîchi toutes les 250 ms tant que la page est ouverte.
*/
params ["_rect"];
disableSerialization;
_rect params ["_x0", "_y0", "_w", "_h"];
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _fs = _l get "fontSmall";
private _font = _l get "font";
private _pad = (_l get "pad") * 2;
private _ratio = pixelH / pixelW;
([] call comspec_atak_native_fnc_accent) params ["_acc"];
private _mk = { params ["_c", "_p", ["_t", ""]]; [_c, _p, _t] call comspec_atak_native_fnc_pageCtrl };
private _bg = ["COMSPEC_RscPanel", _rect] call _mk;
_bg ctrlSetBackgroundColor [0.025, 0.035, 0.03, 0.98];
// Pochette : carré teinté de l'accent avec la note.
private _ch = _h - _pad * 2;
private _cw = (_ch * _ratio) min (_w * 0.38);
_ch = _cw / _ratio;
private _cy = _y0 + (_h - _ch) / 2;
private _cover = ["COMSPEC_RscText", [_x0 + _pad, _cy, _cw, _ch]] call _mk;
_cover ctrlSetBackgroundColor [(_acc select 0) * 0.35, (_acc select 1) * 0.35, (_acc select 2) * 0.35, 1];
private _note = ["COMSPEC_RscPicture", [_x0 + _pad + _cw * 0.2, _cy + _ch * 0.2, _cw * 0.6, _ch * 0.6], "\z\comspec_atak_native\addons\main\data\app_music.paa"] call _mk;
_note ctrlSetTextColor _acc;
// Colonne de droite
private _rx = _x0 + _pad * 2 + _cw;
private _rw = _w - _cw - _pad * 3;
private _title = ["COMSPEC_RscStructuredText", [_rx, _cy, _rw, _font * 2.6]] call _mk;
private _barY = _cy + _ch * 0.62;
private _barBg = ["COMSPEC_RscText", [_rx, _barY, _rw, pixelH * 4]] call _mk;
_barBg ctrlSetBackgroundColor [0.2, 0.23, 0.22, 1];
private _bar = ["COMSPEC_RscText", [_rx, _barY, 0, pixelH * 4]] call _mk;
_bar ctrlSetBackgroundColor _acc;
private _times = ["COMSPEC_RscStructuredText", [_rx, _barY + pixelH * 6, _rw, _fs * 1.3]] call _mk;
// Égaliseur sous le titre
private _eqY = _cy + _ch * 0.36;
private _eqH = _ch * 0.22;
private _n = 24;
private _ew = _rw / _n;
private _eq = [];
for "_i" from 0 to (_n - 1) do {
    private _c = ["COMSPEC_RscText", [_rx + _i * _ew, _eqY + _eqH, _ew * 0.6, 0]] call _mk;
    _c ctrlSetBackgroundColor [(_acc select 0), (_acc select 1), (_acc select 2), 0.75];
    _eq pushBack _c;
};
uiNamespace setVariable ["COMSPEC_ATAK_PlayerViz", [_title, [_rx, _barY, _rw], _bar, _times, _eq, [_eqY, _eqH, _ew], []]];
private _upd = {
    private _e = uiNamespace getVariable ["COMSPEC_ATAK_PlayerViz", []];
    if ((count _e) < 7 || {isNull (_e select 0)}) exitWith { false };
    _e params ["_title", "_bar0", "_bar", "_times", "_eq", "_eqGeo", "_lv"];
    private _m = [] call comspec_atak_native_fnc_musicState;
    private _now = [time, serverTime] select isMultiplayer;
    private _heard = missionNamespace getVariable ["COMSPEC_ATAK_MusicHeard", []];
    private _mine = (_m get "kind") isNotEqualTo "";
    private _playing = (_mine && {!(_m get "paused")}) || {(count _heard) > 1};
    private _fmt = { params ["_s"]; _s = floor (_s max 0); format ["%1:%2", floor (_s / 60), [str (_s mod 60), "0" + str (_s mod 60)] select ((_s mod 60) < 10)] };
    private _src = createHashMapFromArray [["file", "Fichier local"], ["url", "Lien web"], ["srv", "Bibliothèque serveur"]];
    if (_mine) then {
        private _st = _m get "status";
        private _state = _st param [0, ""];
        private _pos = if (_m get "paused") then { _m get "pausedAt" } else { (_now - (_m get "start")) max 0 };
        private _len = _m get "len";
        private _tag = switch (true) do {
            case (_m get "paused"): { "<t color='#f2ab33'>EN PAUSE</t>" };
            case (_state isEqualTo "loading"): { "<t color='#8a9a93'>CHARGEMENT...</t>" };
            case (_state isEqualTo "error"): { format ["<t color='#e5483a'>ERREUR · %1</t>", _st param [4, ""]] };
            default { "" };
        };
        _title ctrlSetStructuredText parseText format ["<t font='RobotoCondensedBold' size='1.15'>%1</t><br/><t size='0.75' color='#8a9a93'>%2%3 · piste %4/%5</t>%6",
            _m get "title", _src getOrDefault [_m get "kind", ""], ["", " · HAUT-PARLEUR"] select (_m get "speaker"), (_m get "qi") + 1, count (_m get "queue"), ["", "<br/><t size='0.75'>" + _tag + "</t>"] select (_tag isNotEqualTo "")];
        _bar0 params ["_bx", "_by", "_bw"];
        _bar ctrlSetPosition [_bx, _by, [0, _bw * ((_pos / _len) min 1)] select (_len > 0), pixelH * 4]; _bar ctrlCommit 0;
        _times ctrlSetStructuredText parseText format ["<t size='0.75' color='#8a9a93'>%1 / %2</t>", [_pos] call _fmt, ["direct", [_len] call _fmt] select (_len > 0)];
    } else {
        if ((count _heard) > 1) then {
            _title ctrlSetStructuredText parseText format ["<t font='RobotoCondensedBold' size='1.15'>%1</t><br/><t size='0.75' color='#8a9a93'>Haut-parleur de %2</t>", _heard select 1, _heard select 0];
        } else {
            _title ctrlSetStructuredText parseText "<t font='RobotoCondensedBold' size='1.15'>Rien en lecture</t><br/><t size='0.75' color='#8a9a93'>Choisissez un fichier, une piste du serveur ou un lien</t>";
        };
        _bar ctrlSetPosition [_bar0 select 0, _bar0 select 1, 0, pixelH * 4]; _bar ctrlCommit 0;
        _times ctrlSetStructuredText parseText "";
    };
    // Égaliseur : valeurs lissées, plates à l'arrêt.
    _eqGeo params ["_ey", "_eh"];
    if ((count _lv) isEqualTo 0) then { { _lv pushBack 0; } forEach _eq; };
    private _vol = (_m get "vol") / 100;
    {
        private _t = if (_playing) then { (0.15 + random 0.85) * (1 - abs (_forEachIndex - 8) / 30) * (0.4 + 0.6 * _vol) } else { 0.04 };
        private _v = (_lv select _forEachIndex) * 0.55 + _t * 0.45;
        _lv set [_forEachIndex, _v];
        private _p = ctrlPosition _x;
        _x ctrlSetPosition [_p select 0, _ey + _eh * (1 - _v), _p select 2, _eh * _v];
        _x ctrlCommit 0.2;
    } forEach _eq;
    true
};
call _upd;
uiNamespace setVariable ["COMSPEC_ATAK_PlayerVizUpd", _upd];
if ((uiNamespace getVariable ["COMSPEC_ATAK_PlayerVizPfh", -1]) >= 0) exitWith {};
private _pfh = [{
    params ["", "_pfh"];
    if !(call (uiNamespace getVariable ["COMSPEC_ATAK_PlayerVizUpd", { false }])) then {
        [_pfh] call CBA_fnc_removePerFrameHandler;
        uiNamespace setVariable ["COMSPEC_ATAK_PlayerVizPfh", -1];
    };
}, 0.25] call CBA_fnc_addPerFrameHandler;
uiNamespace setVariable ["COMSPEC_ATAK_PlayerVizPfh", _pfh];
