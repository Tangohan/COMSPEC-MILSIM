/*
    Intro du mod sur l'écran de démarrage d'Arma (RscDisplayStart, Extended_DisplayLoad de config.cpp), une fois par
    lancement du jeu : noir, grésillements, emblème COMSPEC qui se cale dans un impact (copies rouge et cyan décalées,
    bandes de bruit, lignes de balayage), « ATAK · TACTICAL ASSAULT KIT » tapé, puis sortie en glitch et fondu sur les
    logos d'Arma. Son data\intro_glitch.ogg (tools/gen_intro.py). Pas une fonction CfgFunctions : elles ne sont pas
    encore compilées à ce moment, d'où l'appel par chemin. Le logo fixe d'Overwatch (idc 88001) est masqué.
    Params : [display]
*/
params [["_display", displayNull, [displayNull]]];
if (isNull _display) exitWith {};
if (uiNamespace getVariable ["COMSPEC_ATAK_IntroDone", false]) exitWith {};
uiNamespace setVariable ["COMSPEC_ATAK_IntroDone", true];

private _data = "\z\comspec_atak_native\addons\main\data\";
private _x0 = safeZoneX; private _y0 = safeZoneY; private _w = safeZoneW; private _h = safeZoneH;
private _mk = {
    params ["_cls", "_pos", ["_color", [0, 0, 0, 1]]];
    private _c = _display ctrlCreate [_cls, -1];
    _c ctrlSetPosition _pos;
    if (_cls isEqualTo "RscText") then { _c ctrlSetBackgroundColor _color; } else { _c ctrlSetTextColor _color; };
    _c ctrlCommit 0;
    _c
};
// Emblème 1024x512 : largeur 46 % de l'écran, hauteur selon le rapport des pixels.
private _lw = 0.46 * _w;
private _lh = _lw * 0.5 * pixelH / pixelW;
private _lx = _x0 + (_w - _lw) / 2;
private _ly = _y0 + _h * 0.4 - _lh / 2;
private _logoPos = [_lx, _ly, _lw, _lh];

private _bg = ["RscText", [_x0, _y0, _w, _h], [0, 0, 0, 1]] call _mk;
private _bars = [];
for "_i" from 1 to 7 do {
    private _b = ["RscText", [_x0, _y0, _w, _h * 0.01], [0.36, 0.78, 0.42, 0.5]] call _mk;
    _b ctrlSetFade 1; _b ctrlCommit 0;
    _bars pushBack _b;
};
private _red = ["RscPicture", _logoPos, [1, 0.12, 0.16, 0.75]] call _mk;
private _cyan = ["RscPicture", _logoPos, [0.1, 0.9, 1, 0.75]] call _mk;
private _logo = ["RscPicture", _logoPos, [0.93, 0.97, 0.95, 1]] call _mk;
{ _x ctrlSetText (((_data + "intro_logo_ca.paa")) call comspec_atak_native_fnc_tr); _x ctrlSetFade 1; _x ctrlCommit 0; } forEach [_red, _cyan];
// État de repli lisible si le script animé ne tourne pas pendant le chargement : emblème et sous-titre fixes.
_logo ctrlSetText (((_data + "intro_logo_ca.paa")) call comspec_atak_native_fnc_tr);
private _sub = _display ctrlCreate ["RscStructuredText", -1];
_sub ctrlSetPosition [_x0 + _w * 0.2, _ly + _lh * 0.98, _w * 0.6, _h * 0.06];
_sub ctrlCommit 0;
_sub ctrlSetStructuredText parseText (("<t align='center' font='PuristaSemibold' size='1.6' color='#5cc76b'>ATAK  ·  TACTICAL ASSAULT KIT</t>") call comspec_atak_native_fnc_tr);
private _status = _display ctrlCreate ["RscStructuredText", -1];
_status ctrlSetPosition [_x0 + _w * 0.3, _y0 + _h * 0.84, _w * 0.4, _h * 0.04];
_status ctrlCommit 0;
private _track = ["RscText", [_x0 + _w * 0.3, _y0 + _h * 0.885, _w * 0.4, pixelH * 2], [1, 1, 1, 0.12]] call _mk;
private _fill = ["RscText", [_x0 + _w * 0.3, _y0 + _h * 0.885, 0, pixelH * 2], [0.36, 0.78, 0.42, 1]] call _mk;
private _scan = ["RscPicture", [_x0, _y0, _w, _h], [1, 1, 1, 1]] call _mk;
_scan ctrlSetText (((_data + "intro_scan_ca.paa")) call comspec_atak_native_fnc_tr);
private _flash = ["RscText", [_x0, _y0, _w, _h], [0.85, 1, 0.9, 1]] call _mk;
_flash ctrlSetFade 1; _flash ctrlCommit 0;
private _all = [_bg, _red, _cyan, _logo, _sub, _status, _track, _fill, _scan, _flash] + _bars;
uiNamespace setVariable ["COMSPEC_ATAK_IntroCtrls", _all];

// Le logo fixe d'Overwatch (comspec_overwatch_main) se pose aussi sur cet écran : on garde le nôtre seul.
private _ow = _display displayCtrl 88001;
if (!isNull _ow) then { _ow ctrlShow false; };

playSoundUI ["z\comspec_atak_native\addons\main\data\intro_glitch.ogg", 0.9, 1];

[_display, _logoPos, _bars, _red, _cyan, _logo, _sub, _status, _fill, _flash, _all, _w, _h, _x0, _y0] spawn {
    params ["_display", "_logoPos", "_bars", "_red", "_cyan", "_logo", "_sub", "_status", "_fill", "_flash", "_all", "_w", "_h", "_x0", "_y0"];
    disableSerialization;
    private _alive = { !isNull _display };
    _logoPos params ["_lx", "_ly", "_lw", "_lh"];
    // Le logo d'Overwatch peut arriver après nous (ordre des addons) : masqué ici aussi.
    private _ow = _display displayCtrl 88001;
    if (!isNull _ow) then { _ow ctrlShow false; };
    // Une image de glitch : bandes de bruit au hasard, copies colorées décalées, emblème qui saute.
    private _glitch = {
        params [["_amp", 1], ["_logoAlpha", 1]];
        {
            if (random 1 < 0.6) then {
                _x ctrlSetPosition [_x0 - _w * 0.05 + random (_w * 0.1), _y0 + random _h, _w * (0.4 + random 0.8), _h * (0.003 + random 0.02)];
                _x ctrlSetBackgroundColor (selectRandom [[0.36, 0.78, 0.42, 0.55], [1, 0.12, 0.16, 0.45], [0.1, 0.9, 1, 0.45], [1, 1, 1, 0.3]]);
                _x ctrlSetFade 0;
            } else { _x ctrlSetFade 1; };
            _x ctrlCommit 0;
        } forEach _bars;
        private _dx = (_w * 0.012 * _amp) * (selectRandom [-1, 1]) * (0.4 + random 0.6);
        _red ctrlSetPosition [_lx + _dx, _ly + (random 0.004 - 0.002) * _h, _lw, _lh];
        _cyan ctrlSetPosition [_lx - _dx, _ly - (random 0.004 - 0.002) * _h, _lw, _lh];
        _logo ctrlSetPosition [_lx + (random 0.006 - 0.003) * _w * _amp, _ly, _lw, _lh];
        { _x ctrlSetFade (1 - _logoAlpha * (0.5 + random 0.5)); _x ctrlCommit 0; } forEach [_red, _cyan, _logo];
    };
    private _clean = {
        { _x ctrlSetFade 1; _x ctrlCommit 0; } forEach _bars;
        { _x ctrlSetPosition _logoPos; _x ctrlCommit 0; } forEach [_red, _cyan, _logo];
    };

    _sub ctrlSetStructuredText parseText (("") call comspec_atak_native_fnc_tr);
    // 0 à 0,9 s : écran noir qui grésille, l'emblème clignote en morceaux.
    private _t = diag_tickTime + 0.9;
    while { call _alive && {diag_tickTime < _t} } do {
        [1.6, [0, 0.35] select (random 1 < 0.4)] call _glitch;
        uiSleep (0.03 + random 0.05);
    };
    if !(call _alive) exitWith {};
    call _clean;
    { _x ctrlSetFade 1; _x ctrlCommit 0; } forEach [_red, _cyan, _logo];
    // Montée (0,9 à 1,9 s) : l'emblème apparaît agrandi et voilé.
    private _big = [_lx - _lw * 0.06, _ly - _lh * 0.06, _lw * 1.12, _lh * 1.12];
    _logo ctrlSetPosition _big; _logo ctrlCommit 0;
    _logo ctrlSetFade 0.75; _logo ctrlCommit 0.9;
    uiSleep 1;
    if !(call _alive) exitWith {};
    // Impact (1,9 s) : éclair, emblème calé à sa taille, copies colorées qui se rejoignent.
    _flash ctrlSetFade 0.35; _flash ctrlCommit 0;
    _flash ctrlSetFade 1; _flash ctrlCommit 0.45;
    _logo ctrlSetFade 0; _logo ctrlSetPosition _logoPos; _logo ctrlCommit 0.12;
    _red ctrlSetPosition [_lx + _w * 0.02, _ly, _lw, _lh]; _cyan ctrlSetPosition [_lx - _w * 0.02, _ly, _lw, _lh];
    { _x ctrlSetFade 0.2; _x ctrlCommit 0; } forEach [_red, _cyan];
    { _x ctrlSetPosition _logoPos; _x ctrlSetFade 1; _x ctrlCommit 0.35; } forEach [_red, _cyan];
    // Sous-titre tapé lettre à lettre, ligne d'état et barre de chargement.
    private _txt = "ATAK  ·  TACTICAL ASSAULT KIT";
    private _st = "ATHENA LINK  //  INITIALISATION";
    _fill ctrlSetPosition [_x0 + _w * 0.3, _y0 + _h * 0.885, _w * 0.4, pixelH * 2];
    _fill ctrlCommit 1.9;
    for "_i" from 1 to (count _txt) do {
        if !(call _alive) exitWith {};
        _sub ctrlSetStructuredText parseText ((format ["<t align='center' font='PuristaSemibold' size='1.6' color='#5cc76b'>%1</t>", _txt select [0, _i]]) call comspec_atak_native_fnc_tr);
        _status ctrlSetStructuredText parseText ((format ["<t align='center' font='EtelkaMonospacePro' size='0.85' color='#8a9a92'>%1%2</t>", _st select [0, floor (_i * (count _st) / (count _txt))], ["_", " "] select (_i % 2)]) call comspec_atak_native_fnc_tr);
        uiSleep 0.035;
    };
    _t = diag_tickTime + 1.1;
    while { call _alive && {diag_tickTime < _t} } do {
        _status ctrlSetStructuredText parseText ((format ["<t align='center' font='EtelkaMonospacePro' size='0.85' color='#8a9a92'>%1%2</t>", _st, ["_", " "] select ((floor (diag_tickTime * 3)) % 2)]) call comspec_atak_native_fnc_tr);
        if (random 1 < 0.12) then { [0.3, 1] call _glitch; uiSleep 0.05; call _clean; { _x ctrlSetFade 1; _x ctrlCommit 0; } forEach [_red, _cyan]; _logo ctrlSetFade 0; _logo ctrlCommit 0; };
        uiSleep 0.1;
    };
    if !(call _alive) exitWith {};
    _status ctrlSetStructuredText parseText (("<t align='center' font='EtelkaMonospacePro' size='0.85' color='#5cc76b'>ATHENA LINK  //  OK</t>") call comspec_atak_native_fnc_tr);
    uiSleep 0.6;
    // Sortie (≈ 3,9 s) : glitch fort puis fondu sur les logos d'Arma.
    _t = diag_tickTime + 0.4;
    while { call _alive && {diag_tickTime < _t} } do {
        [2.2, 1] call _glitch;
        uiSleep (0.03 + random 0.04);
    };
    if !(call _alive) exitWith {};
    call _clean;
    { _x ctrlSetFade 1; _x ctrlCommit 0.5; } forEach _all;
    uiSleep 0.6;
    { ctrlDelete _x; } forEach _all;
};
