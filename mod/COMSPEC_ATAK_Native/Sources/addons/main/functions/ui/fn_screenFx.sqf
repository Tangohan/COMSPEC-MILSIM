/*
    Variantes de dégâts de l'écran et parasites du brouillage, dans l'écran du téléphone, au-dessus des fêlures et de la
    surface du verre (appelé à la fin de fn_deviceOverlay, chaque seconde). Textures : tools/gen_screen_fx.py.
    Dégâts (variante tirée au premier choc avec les fêlures, COMSPEC_ATAK_DevLook « seed » : même dessin d'une seconde
    à l'autre, oubliée à la réparation) :
      - fêlé 2 et plus : fuite de cristaux liquides (fx_bleed) à l'impact des fêlures, plus grande au niveau 3 ;
      - écran très abîmé (dégâts 0,5 et plus), une fois sur deux : fuite de rétroéclairage (fx_backlight) contre les bords ;
      - fêlé, une fois sur deux : arc-en-ciel de pression (fx_mura) autour de l'impact (dalle écrasée) ;
      - pixels morts 0,6 et plus : bande de lignes colorées (fx_band), une ou deux.
    Brouillage (fn_ewEffects : intensité 0-1, brouilleurs, Zeus, Crows EW) : parasites (fx_emi, 4 trames) qui changent
    à chaque seconde et clignotent, barre de ronflement qui défile ; plus le brouillage est fort, plus ils sont opaques
    et fréquents. Rien sans brouillage ni dégâts. Écran éteint ou détruit : rien (l'écran noir ou éclaté suffit).
    Contrôles de page hors zone de contenu, inactifs (les clics passent au travers), gardés dans uiNamespace
    COMSPEC_ATAK_ScreenFx [signature, [[genre, contrôle]...]] et remontés par fn_overlayRaise.
    Params : [état matériel (fn_deviceHealth)]
*/
params [["_hp", createHashMap]];
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { false };
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _rect = _l get "device";
private _ori = ["port", "land"] select (_l getOrDefault ["landscape", false]);
private _state = _hp getOrDefault ["state", "OK"];
private _dead = _state in ["OFF", "BROKEN"];
private _lvl = _hp getOrDefault ["crack", 0];
private _dmg = _hp getOrDefault ["damage", 0];
private _pix = (_hp getOrDefault ["parts", createHashMap]) getOrDefault ["pixels", 0];
private _jam = if (_dead) then { 0 } else { ([] call comspec_atak_native_fnc_ewEffects) param [3, 0] };
private _look = uiNamespace getVariable ["COMSPEC_ATAK_DevLook", createHashMap];
private _seed = _look getOrDefault ["seed", -1];
// Tirages reproductibles de la graine des fêlures (les deux premiers donnent leur point d'impact, fn_deviceOverlay).
private _n = 0;
private _rnd = { _n = _n + 1; private _v = (sin (_seed * 0.917 + _n * 113.37)) * 4375.85; _v - floor _v };
private _hasLook = _seed >= 0 && {!_dead};
private _bleed = _hasLook && {_lvl >= 2};
private _mura = _hasLook && {_lvl >= 1} && {(_seed mod 2) isEqualTo 0};
private _back = _hasLook && {_dmg >= 0.5} && {(floor (_seed / 2) mod 2) isEqualTo 0};
private _bands = [0, [1, 2] select (_pix >= 0.85)] select (_pix >= 0.6 && {!_dead});
private _jamOn = _jam > 0.05;
private _sig = str [_bleed, _lvl, _mura, _back, _bands, _jamOn, _seed, _rect, _ori];
(uiNamespace getVariable ["COMSPEC_ATAK_ScreenFx", ["", []]]) params ["_oldSig", "_ctrls"];
if (_sig isNotEqualTo _oldSig || {(_ctrls findIf { isNull (_x select 1) }) >= 0}) then {
    { ctrlDelete (_x select 1); } forEach _ctrls;
    _ctrls = [];
    _rect params ["_rx", "_ry", "_rw", "_rh"];
    private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
    private _tex = { format ["\z\comspec_atak_native\addons\main\data\%1.paa", _this] };
    private _pic = {
        params ["_kind", "_t", "_cx", "_cy", "_w", "_h", ["_a", 0]];
        private _c = ["COMSPEC_RscPhone", [_cx, _cy, _w, _h], _t, false] call comspec_atak_native_fnc_pageCtrl;
        _c ctrlEnable false;
        if (_a isNotEqualTo 0) then { _c ctrlSetAngle [_a, 0.5, 0.5]; };
        _ctrls pushBack [_kind, _c];
        _c
    };
    // Impact des fêlures (mêmes tirages que fn_deviceOverlay).
    private _ix = _rx + _rw * (0.12 + 0.76 * call _rnd);
    private _iy = _ry + _rh * (0.08 + 0.84 * call _rnd);
    // Rétroéclairage d'abord (lumière sous la dalle), puis la dalle (arc-en-ciel, encre), puis les lignes.
    if (_back) then { ["back", format ["fx_backlight_%1_%2", _seed mod 2, _ori] call _tex, _rx, _ry, _rw, _rh] call _pic; };
    if (_mura) then {
        private _s = _rh * (0.16 + 0.1 * call _rnd);
        ["mura", format ["fx_mura_%1", _seed mod 2] call _tex, _ix - _s / _ratio / 2, _iy - _s / 2, _s / _ratio, _s, 360 * call _rnd] call _pic;
    };
    if (_bleed) then {
        // Encre qui a coulé depuis l'impact : décalée le long d'une fêlure, gardée dans l'écran.
        private _s = _rh * ([0.14, 0.24] select (_lvl >= 3)) * (0.8 + 0.4 * call _rnd);
        private _w = _s / _ratio;
        private _bx = ((_ix + (call _rnd - 0.5) * _rw * 0.15) - _w / 2) max _rx min (_rx + _rw - _w);
        private _by = ((_iy + (call _rnd - 0.5) * _rh * 0.1) - _s / 2) max _ry min (_ry + _rh - _s);
        ["bleed", format ["fx_bleed_%1", floor ((call _rnd) * 2)] call _tex, _bx, _by, _w, _s, 360 * call _rnd] call _pic;
    };
    for "_i" from 1 to _bands do {
        // Bande de lignes colorées, verticale dans le sens de la dalle (horizontale à l'écran en paysage).
        private _k = (call _rnd);
        if (_ori isEqualTo "port") then {
            private _bw = _rw * (0.03 + 0.03 * call _rnd);
            ["band", format ["fx_band_%1", _i mod 2] call _tex, _rx + (_rw - _bw) * _k, _ry, _bw, _rh] call _pic;
        } else {
            // Texture verticale tournée de 90° : cadre aux côtés échangés (en pixels), centré sur la bande.
            private _bh = _rh * (0.05 + 0.05 * call _rnd);
            private _cy = _ry + _bh / 2 + (_rh - _bh) * _k;
            ["band", format ["fx_band_%1", _i mod 2] call _tex, _rx + (_rw - _bh / _ratio) / 2, _cy - _rw * _ratio / 2, _bh / _ratio, _rw * _ratio, 90] call _pic;
        };
    };
    if (_jamOn) then {
        ["emi", "fx_emi_0" call _tex, _rx, _ry, _rw, _rh] call _pic;
        // Barre de ronflement : bande claire qui descend lentement (fréquence du brouilleur battant avec l'écran).
        private _b = ["COMSPEC_RscText", [_rx, _ry, _rw, _rh * 0.07], "", false] call comspec_atak_native_fnc_pageCtrl;
        _b ctrlEnable false;
        _ctrls pushBack ["hum", _b];
    };
};
// Opacité et animation (chaque seconde, sans recréer).
_rect params ["_rx", "_ry", "_rw", "_rh"];
{
    _x params ["_kind", "_c"];
    switch (_kind) do {
        case "back": { _c ctrlSetTextColor [1, 0.98, 0.92, 0.18 + 0.3 * ((_dmg - 0.5) * 2 min 1)]; };
        case "mura": { _c ctrlSetTextColor [1, 1, 1, [0.35, 0.55, 0.75] select ((_lvl - 1) max 0 min 2)]; };
        case "bleed": { _c ctrlSetTextColor [1, 1, 1, [0.85, 0.97] select (_lvl >= 3)]; };
        case "band": { _c ctrlSetTextColor [1, 1, 1, 0.6 + 0.35 * random 1]; };
        case "emi": {
            // Parasites intermittents : plus le brouillage est fort, plus souvent et plus opaques.
            private _show = random 1 < (0.35 + 0.65 * _jam);
            _c ctrlShow _show;
            if (_show) then {
                _c ctrlSetText (format ["\z\comspec_atak_native\addons\main\data\fx_emi_%1.paa", floor random 4]); // i18n:brut
                _c ctrlSetTextColor [1, 1, 1, 0.12 + 0.55 * _jam];
                _c ctrlSetFade 0; _c ctrlCommit 0;
                _c ctrlSetFade (0.3 + random 0.6); _c ctrlCommit (0.4 + random 0.5);
            };
        };
        case "hum": {
            _c ctrlSetBackgroundColor [0.85, 0.95, 0.9, 0.05 + 0.12 * _jam];
            private _h = _rh * 0.07;
            private _y0 = _ry + (_rh - _h) * random 0.7;
            _c ctrlSetPosition [_rx, _y0, _rw, _h]; _c ctrlCommit 0;
            _c ctrlSetPosition [_rx, (_y0 + _rh * 0.3) min (_ry + _rh - _h), _rw, _h]; _c ctrlCommit 1;
        };
    };
} forEach _ctrls;
uiNamespace setVariable ["COMSPEC_ATAK_ScreenFx", [_sig, _ctrls]];
if ((count _ctrls) > 0) then {
    uiNamespace setVariable ["COMSPEC_ATAK_DevFront", true];
    [] call comspec_atak_native_fnc_overlayFront;
};
true
