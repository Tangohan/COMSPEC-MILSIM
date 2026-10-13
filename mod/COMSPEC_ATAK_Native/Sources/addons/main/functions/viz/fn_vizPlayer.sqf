/*
    App Musique, onglet LECTURE : lecteur « en cours ».
    Paysage : pochette à gauche (égaliseur animé en bas de la pochette), à droite titre, artiste / source / piste,
    barre de progression cliquable (aller à un passage) avec les temps, commandes en icônes (aléatoire, précédent,
    lecture / pause, suivant, répéter) et sauts ±10 / ±30 s avec STOP. Écran étroit (portrait) : pochette au-dessus.
    Params : [[x, y, w, hauteur max] dans la zone de contenu]. Renvoie la hauteur occupée.
    Rafraîchi toutes les 250 ms tant que la page est ouverte.
*/
params ["_rect"];
disableSerialization;
_rect params ["_x0", "_y0", "_w", "_hMax"];
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _fs = _l get "fontSmall";
private _font = _l get "font";
private _pad = (_l get "pad") * 2;
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
([] call comspec_atak_native_fnc_accent) params ["_acc"];
private _m = [] call comspec_atak_native_fnc_musicState;
private _mine = (_m get "kind") isNotEqualTo "";
private _dir = "\z\comspec_atak_native\addons\main\data\";
private _dim = [0.56, 0.62, 0.59, 1];
private _mk = { params ["_c", "_p", ["_t", ""]]; [_c, _p, _t] call comspec_atak_native_fnc_pageCtrl };
// Clic différé d'une image, comme dans fn_formRender : l'action redessine la page, qui supprimerait le bouton
// pendant son propre gestionnaire.
private _click = {
    params ["_b", "_code", ["_tip", ""]];
    _b setVariable ["comspec_click", _code];
    if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
    _b ctrlAddEventHandler ["ButtonClick", {
        params ["_c"];
        [{ params ["_c", "_code"]; [_c] call _code; }, [_c, _c getVariable ["comspec_click", {}]]] call CBA_fnc_execNextFrame;
    }];
};
private _act = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_musicAction;", str _a, str _v] };

// Hauteurs des blocs de la colonne d'infos (unités verticales).
private _g = _pad * 0.6;
private _tH = _font * 1.45;
private _sH = _fs * 1.35;
private _pgH = _fs * 1.1;
private _tmH = _fs * 1.15;
private _bigD = _font * 2.3;
private _skH = _font * 1.2;
private _infoH = _tH + _sH + _g + _pgH + _tmH + _g + _bigD + _g * 0.7 + _skH;
// Écran étroit : pochette au-dessus, centrée ; sinon à gauche, à la hauteur du bloc.
private _stack = (_w / _ratio) < (_hMax * 1.25);
private _side = 0;
private _h = 0;
if (_stack) then {
    _side = ((_w * 0.42) / _ratio) min (_hMax * 0.3);
    if ((_pad * 2 + _side + _g + _infoH) > _hMax * 0.75) then { _side = (_hMax * 0.75 - _pad * 2 - _g - _infoH) max 0; };
    if (_side < _font * 2.5) then { _side = 0; };
    _h = _pad * 2 + ([0, _side + _g] select (_side > 0)) + _infoH;
} else {
    _h = _infoH + _pad * 2;
    _side = (_h - _pad * 2) min ((_w * 0.34) / _ratio);
};
private _bg = ["COMSPEC_RscPanel", [_x0, _y0, _w, _h]] call _mk;
_bg ctrlSetBackgroundColor [0.025, 0.035, 0.03, 0.98];

// Pochette : carré teinté de l'accent, liseré en haut, note au centre, égaliseur en bas.
private _cw = _side * _ratio;
private _cx = [_x0 + _pad, _x0 + (_w - _cw) / 2] select _stack;
private _cy = [_y0 + (_h - _side) / 2, _y0 + _pad] select _stack;
private _eq = [];
private _eqGeo = [0, 0];
if (_side > 0) then {
    private _cover = ["COMSPEC_RscText", [_cx, _cy, _cw, _side]] call _mk;
    _cover ctrlSetBackgroundColor [(_acc select 0) * 0.26, (_acc select 1) * 0.26, (_acc select 2) * 0.26, 1];
    private _rim = ["COMSPEC_RscText", [_cx, _cy, _cw, pixelH * 2]] call _mk;
    _rim ctrlSetBackgroundColor [(_acc select 0), (_acc select 1), (_acc select 2), 0.55];
    private _note = ["COMSPEC_RscPicture", [_cx + _cw * 0.27, _cy + _side * 0.14, _cw * 0.46, _side * 0.46], _dir + "app_music.paa"] call _mk;
    _note ctrlSetTextColor _acc;
    private _n = 16;
    private _ew = (_cw * 0.84) / _n;
    private _eqY = _cy + _side * 0.66;
    private _eqH = _side * 0.26;
    for "_i" from 0 to (_n - 1) do {
        private _c = ["COMSPEC_RscText", [_cx + _cw * 0.08 + _i * _ew, _eqY + _eqH, _ew * 0.62, 0]] call _mk;
        _c ctrlSetBackgroundColor [(_acc select 0), (_acc select 1), (_acc select 2), 0.8];
        _eq pushBack _c;
    };
    _eqGeo = [_eqY, _eqH];
};

// Colonne d'infos
private _rx = [_cx + _cw + _pad * 1.5, _x0 + _pad * 1.5] select _stack;
private _rw = [_x0 + _w - _pad * 1.5 - _rx, _w - _pad * 3] select _stack;
private _y = [_y0 + (_h - _infoH) / 2, _y0 + _pad + ([0, _side + _g] select (_side > 0))] select _stack;
private _title = ["COMSPEC_RscStructuredText", [_rx, _y, _rw, _tH]] call _mk;
_y = _y + _tH;
private _sub = ["COMSPEC_RscStructuredText", [_rx, _y, _rw, _sH]] call _mk;
_y = _y + _sH + _g;

// Barre de progression : piste, remplissage, curseur rond ; 40 zones cliquables pour aller à un passage.
private _trH = pixelH * 6;
private _trY = _y + (_pgH - _trH) / 2;
private _track = ["COMSPEC_RscText", [_rx, _trY, _rw, _trH]] call _mk;
_track ctrlSetBackgroundColor [0.2, 0.23, 0.22, 1];
private _fill = ["COMSPEC_RscText", [_rx, _trY, 0, _trH]] call _mk;
_fill ctrlSetBackgroundColor _acc;
private _kH = _fs * 0.85;
private _knob = ["COMSPEC_RscPicture", [_rx - _kH * _ratio / 2, _y + (_pgH - _kH) / 2, _kH * _ratio, _kH], _dir + "ui_dot.paa"] call _mk;
_knob ctrlSetTextColor [0.95, 0.98, 0.96, 1];
private _segN = 40;
private _segW = _rw / _segN;
for "_i" from 0 to (_segN - 1) do {
    private _b = ["COMSPEC_RscButtonOverlay", [_rx + _i * _segW, _y, _segW, _pgH]] call _mk;
    [_b, ["seekFrac", (_i + 0.5) / _segN] call _act] call _click;
};
_y = _y + _pgH;
private _tL = ["COMSPEC_RscStructuredText", [_rx, _y, _rw / 2, _tmH]] call _mk;
private _tR = ["COMSPEC_RscStructuredText", [_rx + _rw / 2, _y, _rw / 2, _tmH]] call _mk;
_y = _y + _tmH + _g;

// Commandes : aléatoire, précédent, lecture / pause (pastille), suivant, répéter ; centrées sur la colonne.
private _rep = _m get "repeat";
private _shuffle = _m get "shuffle";
private _sS = _font * 1.15;
private _sM = _font * 1.45;
private _gap = _font * 1.1 * _ratio;
private _btns = [
    [_sS, ["ui_shuffle", [_dim, _acc] select _shuffle], ["shuffle"] call _act, ["Lecture aléatoire : non", "Lecture aléatoire : oui"] select _shuffle],
    [_sM, ["ui_prev", [0.92, 0.96, 0.93, 1]], ["prev"] call _act, "Précédent (au début du morceau après 5 s)"],
    [_bigD, [["ui_play_circle", "ui_pause_circle"] select (_mine && {!(_m get "paused")}), _acc], ["toggle"] call _act, "Lecture / pause"],
    [_sM, ["ui_next", [0.92, 0.96, 0.93, 1]], ["next"] call _act, "Suivant"],
    [_sS, [["ui_repeat", "ui_repeat_one"] select (_rep isEqualTo "one"), [_acc, _dim] select (_rep isEqualTo "off")], ["repeatCycle"] call _act,
        ((createHashMapFromArray [["off", "Répéter : non"], ["all", "Répéter : la file"], ["one", "Répéter : le morceau"]]) getOrDefault [_rep, ""])]
];
private _totW = (count _btns - 1) * _gap;
{ _totW = _totW + (_x select 0) * _ratio; } forEach _btns;
private _bx = _rx + (_rw - _totW) / 2;
private _playPic = controlNull;
{
    _x params ["_s", "_pic", "_code", "_tip"];
    _pic params ["_img", "_col"];
    private _py = _y + (_bigD - _s) / 2;
    private _p = ["COMSPEC_RscPicture", [_bx, _py, _s * _ratio, _s], _dir + _img + ".paa"] call _mk;
    _p ctrlSetTextColor _col;
    if (_forEachIndex isEqualTo 2) then { _playPic = _p; };
    private _b = [["COMSPEC_RscButtonOverlay", "COMSPEC_RscButtonInvisible"] select (_forEachIndex isEqualTo 2), [_bx, _py, _s * _ratio, _s]] call _mk;
    [_b, _code, _tip] call _click;
    _bx = _bx + _s * _ratio + _gap;
} forEach _btns;
_y = _y + _bigD + _g * 0.7;

// Sauts et STOP : petits boutons discrets, centrés.
private _skW = _font * 2.7 * _ratio;
private _skGap = _pad * 0.4;
private _sk = [["- 30 S", ["seek", -30] call _act], ["- 10 S", ["seek", -10] call _act], ["STOP", ["stop"] call _act], ["+ 10 S", ["seek", 10] call _act], ["+ 30 S", ["seek", 30] call _act]];
private _sx = _rx + (_rw - (count _sk) * _skW - ((count _sk) - 1) * _skGap) / 2;
{
    _x params ["_t", "_code"];
    private _b = ["COMSPEC_RscButton", [_sx + _forEachIndex * (_skW + _skGap), _y, _skW, _skH], _t] call _mk;
    _b ctrlSetFontHeight (_fs * 0.82);
    _b ctrlSetBackgroundColor [0.075, 0.09, 0.083, 1];
    _b ctrlSetTextColor ([[0.86, 0.91, 0.88, 1], [0.95, 0.55, 0.5, 1]] select (_t isEqualTo "STOP"));
    _b ctrlEnable _mine;
    [_b, _code] call _click;
} forEach _sk;

uiNamespace setVariable ["COMSPEC_ATAK_PlayerViz", createHashMapFromArray [
    ["title", _title], ["sub", _sub], ["fill", _fill], ["knob", _knob], ["bar", [_rx, _trY, _rw, _trH]], ["tL", _tL], ["tR", _tR],
    ["eq", _eq], ["eqGeo", _eqGeo], ["lv", []], ["play", _playPic]
]];
private _upd = {
    private _e = uiNamespace getVariable ["COMSPEC_ATAK_PlayerViz", createHashMap];
    if ((count _e) isEqualTo 0 || {isNull (_e get "title")}) exitWith { false };
    private _m = [] call comspec_atak_native_fnc_musicState;
    private _now = [time, serverTime] select isMultiplayer;
    private _heard = missionNamespace getVariable ["COMSPEC_ATAK_MusicHeard", []];
    private _mine = (_m get "kind") isNotEqualTo "";
    private _playing = (_mine && {!(_m get "paused")}) || {(count _heard) > 1};
    private _fmt = { params ["_s"]; _s = floor (_s max 0); format ["%1:%2", floor (_s / 60), [str (_s mod 60), "0" + str (_s mod 60)] select ((_s mod 60) < 10)] };
    private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
    private _src = createHashMapFromArray [["file", "Fichier local"], ["url", "Lien web"], ["srv", "Serveur"], ["lib", "Athena"]];
    private _pos = 0;
    private _len = 0;
    (_e get "bar") params ["_bx", "_by", "_bw", "_bh"];
    if (_mine) then {
        private _st = _m get "status";
        private _state = _st param [0, ""];
        _pos = if (_m get "paused") then { _m get "pausedAt" } else { (_now - (_m get "start")) max 0 };
        _len = _m get "len";
        // Artiste (pistes Athena) affiché s'il n'est pas déjà dans le titre ; puis source, piste, état.
        private _t = _m get "title";
        private _artist = ((_m get "queue") param [_m get "qi", []]) param [4, ""];
        private _bits = [];
        if (_artist isNotEqualTo "" && {((toLower _t) find (toLower _artist)) < 0}) then { _bits pushBack format ["<t color='#dfe8e3'>%1</t>", [_artist] call _esc]; };
        _bits pushBack (_src getOrDefault [_m get "kind", ""]);
        _bits pushBack format ["Piste %1 / %2", (_m get "qi") + 1, count (_m get "queue")];
        _bits = _bits select { _x isNotEqualTo "" };
        private _tag = switch (true) do {
            case (_m get "paused"): { "<t color='#f2ab33' font='COMSPEC_BarlowBold'>EN PAUSE</t>" };
            case (_state isEqualTo "loading"): { "<t color='#8a9a93' font='COMSPEC_BarlowBold'>CHARGEMENT…</t>" };
            case (_state isEqualTo "error"): { format ["<t color='#e5483a' font='COMSPEC_BarlowBold'>ERREUR · %1</t>", [_st param [4, ""]] call _esc] };
            default { "" };
        };
        if (_m get "speaker") then { _tag = _tag + (["", "   "] select (_tag isNotEqualTo "")) + "<t color='#5ccb6b' font='COMSPEC_BarlowBold'>HAUT-PARLEUR</t>"; };
        (_e get "title") ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' size='1.3'>%1</t>", [_t] call _esc]) call comspec_atak_native_fnc_tr);
        (_e get "sub") ctrlSetStructuredText parseText ((format ["<t size='0.8' color='#8a9a93'>%1</t>%2", _bits joinString "  ·  ", ["", "     <t size='0.75'>" + _tag + "</t>"] select (_tag isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
        (_e get "tL") ctrlSetStructuredText parseText ((format ["<t size='0.75' color='#dfe8e3'>%1</t>", [_pos] call _fmt]) call comspec_atak_native_fnc_tr);
        (_e get "tR") ctrlSetStructuredText parseText ((format ["<t size='0.75' color='#8a9a93' align='right'>%1</t>", ["DIRECT", [_len] call _fmt] select (_len > 0)]) call comspec_atak_native_fnc_tr);
    } else {
        if ((count _heard) > 1) then {
            (_e get "title") ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' size='1.3'>%1</t>", [_heard select 1] call _esc]) call comspec_atak_native_fnc_tr);
            (_e get "sub") ctrlSetStructuredText parseText ((format ["<t size='0.8' color='#8a9a93'>Haut-parleur de %1</t>", [_heard select 0] call _esc]) call comspec_atak_native_fnc_tr);
        } else {
            (_e get "title") ctrlSetStructuredText parseText (("<t font='COMSPEC_BarlowBold' size='1.3'>Rien en lecture</t>") call comspec_atak_native_fnc_tr);
            (_e get "sub") ctrlSetStructuredText parseText (("<t size='0.8' color='#8a9a93'>Choisissez un morceau dans ATHENA, FICHIERS, SERVEUR ou LIEN</t>") call comspec_atak_native_fnc_tr);
        };
        (_e get "tL") ctrlSetStructuredText parseText (("") call comspec_atak_native_fnc_tr);
        (_e get "tR") ctrlSetStructuredText parseText (("") call comspec_atak_native_fnc_tr);
    };
    private _fw = [0, _bw * ((_pos / (_len max 1)) min 1)] select (_mine && {_len > 0});
    (_e get "fill") ctrlSetPosition [_bx, _by, _fw, _bh];
    (_e get "fill") ctrlCommit 0;
    private _k = _e get "knob";
    private _kp = ctrlPosition _k;
    _k ctrlSetPosition [_bx + _fw - (_kp select 2) / 2, _kp select 1, _kp select 2, _kp select 3];
    _k ctrlCommit 0;
    _k ctrlShow (_mine && {_len > 0});
    // Pastille lecture / pause : suit aussi les changements venus d'ailleurs (fin de file, mort, coupure serveur).
    private _pp = _e get "play";
    if (!isNull _pp) then { _pp ctrlSetText ((format ["\z\comspec_atak_native\addons\main\data\%1.paa", ["ui_play_circle", "ui_pause_circle"] select (_mine && {!(_m get "paused")})]) call comspec_atak_native_fnc_tr); };
    // Égaliseur : valeurs lissées, plates à l'arrêt.
    (_e get "eqGeo") params ["_ey", "_eh"];
    private _lv = _e get "lv";
    private _eq = _e get "eq";
    if ((count _lv) isEqualTo 0) then { { _lv pushBack 0; } forEach _eq; };
    private _vol = ((_m get "vol") max ([0, _m getOrDefault ["spkVol", 70]] select (_m get "speaker"))) / 100;
    private _mid = (count _eq) / 3;
    {
        private _t = if (_playing) then { (0.15 + random 0.85) * (1 - abs (_forEachIndex - _mid) / 24) * (0.4 + 0.6 * _vol) } else { 0.05 };
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
if ((uiNamespace getVariable ["COMSPEC_ATAK_PlayerVizPfh", -1]) < 0) then {
    private _pfh = [{
        params ["", "_pfh"];
        if !(call (uiNamespace getVariable ["COMSPEC_ATAK_PlayerVizUpd", { false }])) then {
            [_pfh] call CBA_fnc_removePerFrameHandler;
            uiNamespace setVariable ["COMSPEC_ATAK_PlayerVizPfh", -1];
        };
    }, 0.25] call CBA_fnc_addPerFrameHandler;
    uiNamespace setVariable ["COMSPEC_ATAK_PlayerVizPfh", _pfh];
};
_h
