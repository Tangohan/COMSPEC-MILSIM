/*
    Place la coque, la barre d'état (batterie, météo, heure, GPS, réseau), la barre d'app,
    le dock ou le rail, la carte et la zone de contenu.
*/
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { false };
// Disposition déjà calculée par l'appelant (glisser du téléphone, fn_phoneDrag) : pas de second calcul.
private _l = param [0, createHashMap, [createHashMap]];
if ((count _l) isEqualTo 0) then { _l = [] call comspec_atak_native_fnc_layoutGet; };
// Calques hors zone de contenu recréés à la nouvelle place (faux : fn_pageRender, qui les recrée lui-même après la page).
private _overlays = param [1, true, [true]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
// Glisser de la coque en cours (fn_phoneDrag MOVE, à chaque image) : contrôles existants déplacés, pas recréés.
private _dragging = (count (_s getOrDefault ["drag", []])) > 0;
private _page = _s getOrDefault ["activePage", "LAUNCHER"];
private _interactive = _l get "interactive";
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);

private _set = {
    params ["_idc", "_pos"];
    private _c = _d displayCtrl _idc;
    _c ctrlSetPosition _pos;
    _c ctrlCommit 0;
    _c
};

private _phone = [88509, _l get "phone"] call _set;
_phone ctrlSetText (((_l get "phoneTexture")) call comspec_atak_native_fnc_tr);
(_l get "device") params ["_dx", "_dy", "_dw", "_dh"];
(_l get "status") params ["", "_sy", "", "_sh"];
(_l get "appbar") params ["", "_ay", "", "_ah"];
private _pad = _l get "pad";
private _font = _l get "font";
private _fontS = _l get "fontSmall";

private _devOld = ctrlPosition (_d displayCtrl 88510);
[88510, _l get "device"] call _set;
[88511, _l get "status"] call _set;

// Barre d'état : batterie | météo ........ heure ........ GPS | réseau
private _ih = _sh * 0.8;
private _iw = _ih * 2 / _ratio;
private _iy = _sy + (_sh - _ih) / 2;
[88512, [_dx + _pad, _iy, _iw, _ih]] call _set;
[88513, [_dx + _pad + _iw, _sy, _iw * 1.3, _sh]] call _set;
private _wx = _dx + _pad * 2 + _iw * 2.3;
private _ww = _iw * 3.6;
[88514, [_wx, _sy + _sh * 0.08, _ww, _sh * 0.84]] call _set;
[88523, [_wx + _pad * 0.5, _iy, _ih / _ratio, _ih]] call _set;
[88524, [_wx + _pad + _ih / _ratio, _sy, _ww - _ih / _ratio - _pad, _sh]] call _set;
[88525, [_dx + _dw * 0.42, _sy, _dw * 0.16, _sh]] call _set;
[88548, [_dx + _dw * 0.58, _iy, _ih / _ratio, _ih]] call _set;
[88549, [_dx + _dw * 0.58 + _pad + _ih / _ratio, _iy, _ih / _ratio, _ih]] call _set;
[88527, [_dx + _dw - _pad - _iw, _iy, _iw, _ih]] call _set;
[88526, [_dx + _dw - _pad * 2 - _iw - _ih / _ratio, _iy, _ih / _ratio, _ih]] call _set;
{ (_d displayCtrl _x) ctrlSetFontHeight _fontS; } forEach [88513, 88524];
// Police choisie (Réglages > Police) sur les textes fixes : batterie, météo, heure, titre de l'app, aide HUD.
{ _x params ["_idc", "_cls"]; [_d displayCtrl _idc, _cls, true] call comspec_atak_native_fnc_fontApply; } forEach [[88513, "COMSPEC_RscLabel"], [88524, "COMSPEC_RscText"], [88525, "COMSPEC_RscText"], [88518, "COMSPEC_RscLabel"], [88528, "COMSPEC_RscLabel"]];
// Mémoire vive (« RAM nn % », mise à jour par fn_statusUpdate) : entre le logo de liaison et le GPS, si la place suffit.
private _ram = uiNamespace getVariable ["COMSPEC_ATAK_RamCtrl", controlNull];
if (isNull _ram || {(ctrlParent _ram) isNotEqualTo _d}) then {
    _ram = _d ctrlCreate ["COMSPEC_RscTextRight", -1];
    _ram ctrlSetTextColor [0.62, 0.70, 0.66, 1];
    uiNamespace setVariable ["COMSPEC_ATAK_RamCtrl", _ram];
};
private _rx0 = _dx + _dw * 0.58 + _pad * 2 + 2 * _ih / _ratio;
private _rx1 = _dx + _dw - _pad * 3 - _iw - ([0, _ih / _ratio] select _interactive);
_ram ctrlSetPosition [_rx0, _sy, (_rx1 - _rx0) max 0, _sh];
_ram ctrlSetFontHeight (_fontS * 0.9);
_ram ctrlCommit 0;
if ((ctrlText _ram) isEqualTo "") then { _ram ctrlSetText (("RAM 00%") call comspec_atak_native_fnc_tr); };
// Mode PETIT : barre d'état allégée (batterie, heure, notifications, réseau) ; météo et mémoire vive masquées.
private _slimBar = (_l get "mini") && {!(_l get "texture")};
{ (_d displayCtrl _x) ctrlShow !_slimBar; } forEach [88514, 88523, 88524];
_ram ctrlShow (!_slimBar && {(ctrlTextWidth _ram) <= (_rx1 - _rx0)});
(_d displayCtrl 88525) ctrlSetFontHeight _font;

// Barre d'app : retour | titre ........ rotation | mini/plein | apps
[88515, _l get "appbar"] call _set;
private _bh = _ah * 0.62;
private _bw = _bh / _ratio;
private _by = _ay + (_ah - _bh) / 2;
private _step = _bw * 1.7;
[88517, [_dx + _pad, _by, _bw, _bh]] call _set;
[88518, [_dx + _pad * 2 + _bw * 1.2, _ay, _dw - _step * 4, _ah]] call _set;
[88519, [_dx + _dw - _pad - _bw, _by, _bw, _bh]] call _set;
[88521, [_dx + _dw - _pad - _bw - _step, _by, _bw, _bh]] call _set;
[88522, [_dx + _dw - _pad - _bw - _step * 2, _by, _bw, _bh]] call _set;
[88528, [_dx + _dw * 0.45, _ay, _dw * 0.55 - _pad, _ah]] call _set;
(_d displayCtrl 88518) ctrlSetFontHeight _font;
(_d displayCtrl 88528) ctrlSetFontHeight _fontS;
// Téléphone porté : pastille « main + Ctrl+Maj+U » à droite de la barre d'app au lieu de la phrase ;
// coupée dans Réglages > Personnalisation (profil COMSPEC_ATAK_HandHint) pour un écran sans indication.
(_d displayCtrl 88528) ctrlSetText (("") call comspec_atak_native_fnc_tr);
(uiNamespace getVariable ["COMSPEC_ATAK_HandHintCtrls", []]) params [["_hBg", controlNull], ["_hIc", controlNull], ["_hKey", controlNull]];
if (isNull _hBg || {(ctrlParent _hBg) isNotEqualTo _d}) then {
    _hBg = _d ctrlCreate ["COMSPEC_RscText", -1];
    _hBg ctrlSetBackgroundColor [0, 0, 0, 0.55];
    _hIc = _d ctrlCreate ["COMSPEC_RscPicture", -1];
    _hIc ctrlSetText (("\z\comspec_atak_native\addons\main\data\ui_hand.paa") call comspec_atak_native_fnc_tr);
    _hIc ctrlSetTextColor [0.88, 0.93, 0.9, 0.95];
    _hKey = _d ctrlCreate ["COMSPEC_RscTextCenter", -1];
    _hKey ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
    _hKey ctrlSetBackgroundColor [1, 1, 1, 0.13];
    _hKey ctrlSetTextColor [0.92, 0.96, 0.93, 1];
    uiNamespace setVariable ["COMSPEC_ATAK_HandHintCtrls", [_hBg, _hIc, _hKey]];
};
// Touche affichée : celle choisie pour « prendre en main » (Réglages > Accès et touches ou menu CBA), sinon la principale.
private _hBind = (["get", "PhoneHold"] call comspec_atak_native_fnc_keybinds) select 0;
if (_hBind isEqualTo []) then { _hBind = (["get", "PhoneMain"] call comspec_atak_native_fnc_keybinds) select 0; };
_hKey ctrlSetText ((["name", _hBind] call comspec_atak_native_fnc_keybinds) call comspec_atak_native_fnc_tr);
if (isNil "cba_keybinding_actions") then { _hKey ctrlSetText (("Ctrl+Maj+U") call comspec_atak_native_fnc_tr); };
private _hShow = !_interactive && {profileNamespace getVariable ["COMSPEC_ATAK_HandHint", true]};
_hKey ctrlSetFontHeight ((_fontS * 0.8) max COMSPEC_ATAK_FontMin);
private _hh = _ah * 0.58;
private _hIcH = _hh * 0.78;
private _hKw = (ctrlTextWidth _hKey) + _pad * 2;
private _hW = _pad * 3 + _hIcH / _ratio + _hKw;
private _hX = _dx + _dw - _pad * 2 - _hW;
private _hY = _ay + (_ah - _hh) / 2;
_hBg ctrlSetPosition [_hX, _hY, _hW, _hh];
_hIc ctrlSetPosition [_hX + _pad, _hY + (_hh - _hIcH) / 2, _hIcH / _ratio, _hIcH];
_hKey ctrlSetPosition [_hX + _pad * 2 + _hIcH / _ratio, _hY + _hh * 0.14, _hKw, _hh * 0.72];
{ _x ctrlCommit 0; _x ctrlShow _hShow; _x ctrlEnable false; } forEach [_hBg, _hIc, _hKey];
{ (_d displayCtrl _x) ctrlShow _interactive; } forEach [88517, 88519, 88521, 88522, 88526];
(_d displayCtrl 88522) ctrlShow (_interactive && {_l get "mini"});
(_d displayCtrl 88521) ctrlSetText ((format ["\z\comspec_atak_native\addons\main\data\ui_%1.paa", ["collapse", "expand"] select (_l get "mini")]) call comspec_atak_native_fnc_tr);
// Retour actif dans toute app (sans historique, il ramène à l'accueil) et sur un sous-écran (fn_back).
(_d displayCtrl 88517) ctrlEnable ((count (_s getOrDefault ["history", []])) > 1 || {(_s getOrDefault ["activePage", "LAUNCHER"]) isNotEqualTo "LAUNCHER"});

// Touches physiques de la coque (fractions mesurées sur tools/src/android_s7_ca.png, paysage ;
// en portrait la texture est tournée d'un quart de tour horaire : (x, y) devient (1 - y, x)).
(_l get "phone") params ["_px", "_py", "_pw", "_ph"];
{
    _x params ["_idc", "_fx", "_fy", "_fw", "_fh"];
    if !(_l get "landscape") then { private _t = _fx; _fx = 1 - _fy; _fy = _t; _t = _fw; _fw = _fh; _fh = _t; };
    private _c = [_idc, [_px + _pw * (_fx - _fw / 2), _py + _ph * (_fy - _fh / 2), _pw * _fw, _ph * _fh]] call _set;
    _c ctrlShow _interactive;
} forEach [[88544, 0.782, 0.405, 0.045, 0.07], [88545, 0.783, 0.500, 0.045, 0.075], [88546, 0.782, 0.594, 0.045, 0.07], [88547, 0.396, 0.298, 0.06, 0.03]];

[88516, _l get "dockRect"] call _set;
[88520, _l get "rail"] call _set;

(_l get "body") params ["_bx", "_byy", "_bww", "_bhh"];
private _isMap = _page isEqualTo "MAP";
private _inspW = [0, _l get "inspW"] select _isMap;
// Les trois cartes (topo, satellite, hybride : fn_mapCtrl) à la même place ; seule celle du fond choisi est montrée.
private _mapC = [_d] call comspec_atak_native_fnc_mapCtrl;
{ [_x, [_bx, _byy, _bww - _inspW, _bhh]] call _set; } forEach [88530, 88532, 88533];
// Zone de contenu : la repositionner remet son défilement en haut. On ne la déplace que si sa place change
// (chaque bascule des Réglages appelait layoutApply avant le redessin : la page remontait en haut à chaque clic),
// et le défilement est rendu après un vrai déplacement.
private _cg = _d displayCtrl 88531;
private _cgPos = [_bx, _byy, _bww, _bhh];
private _cgCur = ctrlPosition _cg;
private _cgMoved = (count _cgCur) < 4;
{ if ((abs (_x - (_cgCur param [_forEachIndex, -1]))) > 0.00001) then { _cgMoved = true; }; } forEach _cgPos;
if (_cgMoved) then {
    private _sv = ctrlScrollValues _cg;
    _cg ctrlSetPosition _cgPos;
    _cg ctrlCommit 0;
    if ((_sv select 0) > 0) then { _cg ctrlSetScrollValues [_sv select 0, -1]; };
};
[88540, [_bx + _bww - _inspW, _byy, _inspW, _bhh]] call _set;
[88541, [_bx + _bww - _inspW + _pad, _byy + _pad, (_inspW - 2 * _pad) max 0, (_bhh - 2 * _pad) max 0]] call _set;
// Fond d'écran de l'accueil (réglage profil : athena, ops ou aucun).
private _wall = profileNamespace getVariable ["COMSPEC_ATAK_Wallpaper", "topo"];
private _walls = ["topo", "night", "desert", "olive", "dark", "soar", "athena", "ops"];
private _blur = ["", "_blur"] select (profileNamespace getVariable ["COMSPEC_ATAK_WallBlur", false]);
private _wc = [88542, [_bx, _byy, _bww, _bhh]] call _set;
_wc ctrlSetText (((["", format ["\z\comspec_atak_native\addons\main\data\wall_%1%3_%2.paa", _wall, ["port", "land"] select (_l get "landscape"), _blur]] select (_wall in _walls))) call comspec_atak_native_fnc_tr);
_wc ctrlShow (_page isEqualTo "LAUNCHER" && {_wall in _walls});
{ if (!isNull _x) then { _x ctrlShow (_isMap && {_x isEqualTo _mapC}); }; } forEach ([_d, true] call comspec_atak_native_fnc_mapCtrl);
(_d displayCtrl 88531) ctrlShow !_isMap;
(_d displayCtrl 88540) ctrlShow (_isMap && {_inspW > 0});
// Texte SITUATION : il vit désormais dans le volet défilant de la carte (fn_pageMap, fn_inspectorUpdate).
(_d displayCtrl 88541) ctrlShow false;

// Contrôles d'une liste (listes imbriquées comprises) posés directement sur l'écran du téléphone.
private _collect = {
    private _out = [];
    {
        if (_x isEqualType controlNull) then {
            if (!isNull _x && {isNull (ctrlParentControlsGroup _x)}) then { _out pushBack _x; };
        } else {
            if (_x isEqualType []) then { _out append (_x call _collect); };
        };
    } forEach _this;
    _out
};
if (_dragging) then {
    // Glisser : tuiles du dock / rail, effets d'écran, toasts, volet, écran de verrouillage et alertes décalés comme la
    // coque (UP redessine la page et recrée tout).
    private _ddx = _dx - (_devOld param [0, _dx]);
    private _ddy = _dy - (_devOld param [1, _dy]);
    private _surf = (uiNamespace getVariable ["COMSPEC_ATAK_SurfOverlay", ["", []]]) param [1, []];
    private _sfx = (uiNamespace getVariable ["COMSPEC_ATAK_ScreenFx", ["", []]]) param [1, []];
    private _all = ([
        uiNamespace getVariable ["COMSPEC_ATAK_DockControls", []],
        uiNamespace getVariable ["COMSPEC_ATAK_DevOverlay", []],
        _surf apply { _x param [1, controlNull] },
        _sfx apply { _x param [1, controlNull] },
        uiNamespace getVariable ["COMSPEC_ATAK_ZeusOv", []],
        uiNamespace getVariable ["COMSPEC_ATAK_ToastControls", []],
        uiNamespace getVariable ["COMSPEC_ATAK_ToastPill", []],
        uiNamespace getVariable ["COMSPEC_ATAK_LockCtrls", []],
        uiNamespace getVariable ["COMSPEC_ATAK_ShadeCtrls", []],
        uiNamespace getVariable ["COMSPEC_ATAK_FullAlertCtrls", []],
        uiNamespace getVariable ["COMSPEC_ATAK_BuzzCtrls", []]
    ] call _collect);
    _all = _all arrayIntersect _all;
    if ((abs _ddx) > 0.000001 || {(abs _ddy) > 0.000001}) then {
        {
            private _p = ctrlPosition _x;
            _x ctrlSetPosition [(_p select 0) + _ddx, (_p select 1) + _ddy, _p select 2, _p select 3];
            _x ctrlCommit 0;
        } forEach _all;
    };
} else {
    [] call comspec_atak_native_fnc_dockRender;
};
// Câble DL-7 branché (app Extraction) : suit la coque.
[] call comspec_atak_native_fnc_dataOverlay;
// Antenne d'extension vissée sur le port SMA (fn_antenna).
["overlay"] call comspec_atak_native_fnc_antenna;
// Volet rapide : zone cliquable de la barre d'état et poignée (fn_quickSettings).
["handle"] call comspec_atak_native_fnc_quickSettings;
// Mise en page seule (terminal de bord, taille CBA, antenne, menu ACE, touches) : les calques posés hors de la zone de
// contenu (filtre de nuit, luminosité, fêlures, écran éteint et son bouton bloquant, toasts, verrouillage, volet,
// alertes) restaient à l'ancienne place, en partie hors du téléphone. Ils sont recréés comme après un rendu de page.
if (_overlays && {!_dragging}) then {
    private _surf = (uiNamespace getVariable ["COMSPEC_ATAK_SurfOverlay", ["", []]]) param [1, []];
    private _sfx = (uiNamespace getVariable ["COMSPEC_ATAK_ScreenFx", ["", []]]) param [1, []];
    { ctrlDelete _x; } forEach ([
        uiNamespace getVariable ["COMSPEC_ATAK_DevOverlay", []],
        _surf apply { _x param [1, controlNull] },
        _sfx apply { _x param [1, controlNull] },
        uiNamespace getVariable ["COMSPEC_ATAK_ZeusOv", []]
    ] call _collect);
    uiNamespace setVariable ["COMSPEC_ATAK_DevOverlay", []];
    uiNamespace setVariable ["COMSPEC_ATAK_SurfOverlay", ["", []]];
    uiNamespace setVariable ["COMSPEC_ATAK_ScreenFx", ["", []]];
    uiNamespace setVariable ["COMSPEC_ATAK_ZeusOv", []];
    [] call comspec_atak_native_fnc_deviceOverlay;
    uiNamespace setVariable ["COMSPEC_ATAK_OvSeq", uiNamespace getVariable ["COMSPEC_ATAK_CtrlSeq", 0]];
    uiNamespace setVariable ["COMSPEC_ATAK_ToastSig", []];
    [] call comspec_atak_native_fnc_notificationsRender;
    ["raise"] call comspec_atak_native_fnc_fx;
};
true
