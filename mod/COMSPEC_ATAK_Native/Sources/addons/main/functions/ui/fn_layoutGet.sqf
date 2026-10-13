/*
    Géométrie du terminal.
    - Porté (HUD) ou en main mini : téléphone vertical ou horizontal, dans le coin choisi (bas droit par défaut).
    - En main plein écran : téléphone horizontal agrandi au centre.
    La coque est la texture carrée du S7 (tools/src) ou d'un autre modèle (tactique, premium, tablette de bord des
    véhicules : tools/render_phones.py) ; l'écran occupe la même zone transparente sur toutes.
*/
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _interactive = _s getOrDefault ["interactive", false];
private _mode = toUpper (profileNamespace getVariable ["COMSPEC_ATAK_Mode", "MINI"]);
if (!_interactive || {!(_mode in ["MINI", "FULL"])}) then { _mode = "MINI"; };
private _orient = toUpper (profileNamespace getVariable ["COMSPEC_ATAK_Orientation", "PORTRAIT"]);
if (_mode isEqualTo "FULL" || {!(_orient in ["PORTRAIT", "LANDSCAPE"])}) then { _orient = ["PORTRAIT", "LANDSCAPE"] select (_mode isEqualTo "FULL"); };
// Écran 3D de la tablette sur le JPC (fn_jpcScreen) : texture UI 1024 x 576, toujours horizontale, l'écran la remplit.
private _texture = _s getOrDefault ["texture", false];
if (_texture) then { _orient = "LANDSCAPE"; };
private _mini = _mode isEqualTo "MINI";
private _land = _orient isEqualTo "LANDSCAPE";
private _ratio = [pixelH / pixelW, 1024 / 576] select _texture; // hauteur (unités écran) d'un carré de largeur 1

// Coque S7 : texture carrée 2048 px. Fractions mesurées dans tools/src/android_s7_ca.png.
// _vis = boîte visible de la coque (avec la fixation), _scr = écran transparent.
// Modèle choisi (profil COMSPEC_ATAK_PhoneModel) ou tablette de bord (véhicule équipé, poste de commandement).
// Toutes les coques gardent la même ouverture d'écran ; seule la boîte visible change (paysage, mesurée sur les rendus).
private _term = [player] call comspec_atak_native_fnc_vehicleTerminal;
private _model = profileNamespace getVariable ["COMSPEC_ATAK_PhoneModel", "s7"];
// La tablette du véhicule est commune, pour qui n'a pas son ATAK : avec son téléphone, on garde son téléphone.
if (_term in ["vehicle", "cp"] && {profileNamespace getVariable ["COMSPEC_ATAK_VehTablet", true]} && {!([player, true] call comspec_atak_native_fnc_hasDevice)}) then { _model = "tablet"; };
private _visLand = (createHashMapFromArray [
    ["s7", [0.1025, 0.2798, 0.9263, 0.8535]], ["rugged", [0.1509, 0.2744, 0.8267, 0.7573]], ["iphone", [0.1914, 0.3159, 0.7861, 0.6851]], ["tablet", [0.0850, 0.2510, 0.9258, 0.7549]],
    ["keys", [0.0923, 0.2495, 0.9083, 0.7178]],
    ["plate", [0.0728, 0.2974, 0.9272, 0.8013]],
    ["flip", [0.0972, 0.2671, 0.9028, 0.7104]],
    ["ribbed", [0.1191, 0.2974, 0.8809, 0.6987]]
]) getOrDefault [_model, []];
if (_visLand isEqualTo []) then { _model = "s7"; _visLand = [0.1025, 0.2798, 0.9263, 0.8535]; };
// Vertical : texture tournée d'un quart de tour horaire.
_visLand params ["_lx0", "_ly0", "_lx1", "_ly1"];
private _vis = [[1 - _ly1, _lx0, 1 - _ly0, _lx1], _visLand] select _land;
private _scr = [[0.3491, 0.2222, 0.6504, 0.7549], [0.2222, 0.3496, 0.7549, 0.6509]] select _land;
// L'écran déborde légèrement sous la coque : le bord flou de la texture (filtrage) laissait voir un liseré.
private _bleed = 0.003;
_scr = [(_scr select 0) - _bleed, (_scr select 1) - _bleed, (_scr select 2) + _bleed, (_scr select 3) + _bleed];
_vis params ["_vx0", "_vy0", "_vx1", "_vy1"];
// Côté du carré en unités verticales (_dh) ; largeur = _dh / _ratio pour rester carré à l'écran.
// Taille du mini choisie dans les réglages (petit, normal, grand).
private _miniScale = [1, (profileNamespace getVariable ["COMSPEC_ATAK_MiniScale", 1]) max 0.7 min 1.3] select _mini;
// Taille du plein écran (réglage CBA « Taille du téléphone en plein écran », aussi dans Réglages > Emplacement) :
// 60 à 100 %, toujours centré ; toutes les zones (écran, touches, carte) sont recalculées depuis cette taille.
private _fullScale = missionNamespace getVariable ["comspec_atak_native_full_scale", 1];
if (_mini || {!(_fullScale isEqualType 0)}) then { _fullScale = 1; };
_fullScale = _fullScale max 0.6 min 1;
private _dh = if (_land) then {
    private _wantW = [safeZoneW * 0.94 * _fullScale, safeZoneW * 0.40 * _miniScale] select _mini;
    private _h = _wantW / (_vx1 - _vx0) * _ratio;
    _h min (([safeZoneH * 0.96 * _fullScale, (safeZoneH * 0.50 * _miniScale) min (safeZoneH * 0.9)] select _mini) / (_vy1 - _vy0))
} else {
    ([safeZoneH * 0.94 * _fullScale, (safeZoneH * 0.62 * _miniScale) min (safeZoneH * 0.94)] select _mini) / (_vy1 - _vy0)
};
private _dw = _dh / _ratio;
// Emplacement du mini choisi dans les réglages : coin ou milieu de bord (T/M/B + L/R), bas droit par défaut.
private _anchor = toUpper (profileNamespace getVariable ["COMSPEC_ATAK_MiniAnchor", "BR"]);
if !(_anchor in ["TL", "TR", "ML", "MR", "BL", "BR"]) then { _anchor = "BR"; };
private _dx = if (_mini) then {
    if ((_anchor select [1, 1]) isEqualTo "L") then { safeZoneX + safeZoneW * 0.01 - _dw * _vx0 } else { safeZoneX + safeZoneW * 0.99 - _dw * _vx1 }
} else { safeZoneX + (safeZoneW - _dw * (_vx0 + _vx1)) / 2 };
private _dy = if (_mini) then {
    switch (_anchor select [0, 1]) do {
        case "T": { safeZoneY + safeZoneH * 0.02 - _dh * _vy0 };
        case "M": { safeZoneY + (safeZoneH - _dh * (_vy0 + _vy1)) / 2 };
        default { safeZoneY + safeZoneH * 0.98 - _dh * _vy1 };
    }
} else { safeZoneY + (safeZoneH - _dh * (_vy0 + _vy1)) / 2 };
// Décalage choisi par le joueur (glisser la coque), par mode et orientation.
private _offKey = format ["COMSPEC_ATAK_Offset_%1_%2", _mode, _orient];
(profileNamespace getVariable [_offKey, [0, 0]]) params [["_ox", 0], ["_oy", 0]];
_dx = _dx + _ox;
_dy = _dy + _oy;
// Texture UI : coordonnées 0..1 sur toute la texture ; la coque est le modèle 3D, l'écran occupe tout.
if (_texture) then {
    _scr = [0.2222, 0.3496, 0.7549, 0.6509];
    _dw = 1 / ((_scr select 2) - (_scr select 0));
    _dh = 1 / ((_scr select 3) - (_scr select 1));
    _dx = -_dw * (_scr select 0);
    _dy = -_dh * (_scr select 1);
};

private _sx = _dx + _dw * (_scr select 0);
private _sy = _dy + _dh * (_scr select 1);
private _sw = _dw * ((_scr select 2) - (_scr select 0));
private _sh = _dh * ((_scr select 3) - (_scr select 1));

// Échelle typographique du téléphone (une seule source pour toutes les apps) :
//   font        corps de texte (champs, listes, titre de l'app) ;
//   fontSmall   texte courant des pages (lignes, boutons, tuiles) ;
//   fontCaption légendes, en-têtes de section, aides : la plus petite taille autorisée ;
//   fontTitle / fontDisplay  titres de carte et grands chiffres (heure, valeurs mises en avant).
// Les tailles suivent la taille de l'écran du téléphone (mini, plein écran, échelle #709) et le réglage
// Réglages > Taille du texte, avec un plancher en pixels réels : sur un écran 1080p en mode mini, les libellés
// descendaient à 10 px de haut (« _fs * 0.7 »), illisibles. COMSPEC_ATAK_FontMin (plancher des légendes) sert aux
// pages qui calculent leurs tailles à la main : (_fs * 0.7) max COMSPEC_ATAK_FontMin.
// Police choisie dans Réglages > Police : familles remises à jour dès que le réglage change.
private _fontFam = missionNamespace getVariable ["comspec_atak_native_font", "roboto"];
if (_fontFam isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_FontFam", ""])) then { [_fontFam] call comspec_atak_native_fnc_fontSet; };
private _textScale = (profileNamespace getVariable ["COMSPEC_ATAK_TextScale", 1]) max 0.8 min 1.35;
private _px = [pixelH, 0] select _texture; // texture UI (JPC) : coordonnées de texture, pas de plancher en pixels
private _font = (((_sh * ([[[0.040, 0.060] select _land, 0.036] select !_mini, 0.052] select _texture)) max ([safeZoneH * 0.015, 0.03] select _texture)) * _textScale) max (_px * 16);
private _fontSmall = (_font * 0.82) max (_px * 15);
private _fontCaption = (_font * 0.68) max (_px * 13);
COMSPEC_ATAK_FontMin = [_fontCaption, 0] select _texture; // texture : coordonnées propres, pas de plancher
private _statusH = _font * 1.45;
private _appH = _font * 1.9;
private _dock = !_land;
// Carte en mode PETIT : ni dock ni rail (le bouton « apps » de la barre d'app y mène), la carte prend toute la largeur.
private _slim = _mini && {!_texture} && {(_s getOrDefault ["activePage", ""]) isEqualTo "MAP"};
private _dockH = [0, _font * 2.9] select (_dock && {!_slim});
private _railW = [_font * 2.9 / _ratio, 0] select (_dock || _slim);
// Panneau SITUATION repliable (réglage profil).
private _inspW = [0, _sw * 0.24] select (!_mini && {missionNamespace getVariable ["COMSPEC_ATAK_InspOpen", false]});
private _pad = _sw * 0.012;
private _bodyY = _sy + _statusH + _appH;
private _bodyH = _sh - _statusH - _appH - _dockH;

// Coque : texture de nuit (silhouette sombre) ou de jour à la couleur choisie (coyote, vert OD, noir).
private _night = switch (profileNamespace getVariable ["COMSPEC_ATAK_Shell", "auto"]) do { case "day": { false }; case "night": { true }; default { sunOrMoon < 0.5 }; };
private _tint = profileNamespace getVariable ["COMSPEC_ATAK_ShellTint", "coyote"];
private _shellSfx = if (_night) then { "_night" } else { ["", "_" + _tint] select (_tint in ["od", "black"]) };
createHashMapFromArray [
    ["mode", _mode], ["offsetKey", _offKey], ["visible", [_dx + _dw * _vx0, _dy + _dh * _vy0, _dw * (_vx1 - _vx0), _dh * (_vy1 - _vy0)]], ["mini", _mini], ["orientation", _orient], ["landscape", _land], ["interactive", _interactive], ["dock", _dock],
    ["phone", [_dx, _dy, _dw, _dh]], ["texture", _texture], ["phoneTexture", if (_texture) then { "" } else { if (_model isEqualTo "s7") then { format ["\z\comspec_atak_native\addons\main\data\phone_%1%2.paa", ["portrait", "landscape"] select _land, _shellSfx] } else {
        format ["\z\comspec_atak_native\addons\main\data\phone_%1_%2%3.paa", ["portrait", "landscape"] select _land, _model, ["", "_night"] select _night] } }],
    ["model", _model], ["terminal", _term],
    ["device", [_sx, _sy, _sw, _sh]],
    ["status", [_sx, _sy, _sw, _statusH]],
    ["appbar", [_sx, _sy + _statusH, _sw, _appH]],
    ["dockRect", [_sx, _sy + _sh - _dockH, _sw, _dockH]],
    ["rail", [_sx, _bodyY, _railW, _bodyH]],
    ["body", [_sx + _railW, _bodyY, _sw - _railW, _bodyH]],
    ["inspW", _inspW],
    ["font", _font],
    ["fontSmall", _fontSmall],
    ["fontCaption", _fontCaption],
    ["fontTitle", _font * 1.2],
    ["fontDisplay", _font * 1.9],
    ["cols", (([[6, 5] select _mini, 3] select (!_land)) + (profileNamespace getVariable ["COMSPEC_ATAK_IconDensity", 0])) max 2],
    ["pad", _pad]
]
