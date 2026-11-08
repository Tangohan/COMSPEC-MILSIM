/*
    Toast Android : petite bulle grise en bas de l'écran (au-dessus du dock), qui apparaît en fondu, reste
    quelques secondes puis s'efface. Pour les confirmations brèves (« Code enregistré », « Numéro copié »…),
    sans passer par le centre de notifications. Un seul toast à la fois : le nouveau remplace l'ancien.
    Params : [texte, durée en s (1 à 8, 2,5 par défaut)]
    Sans texte : redessine le toast en cours (fn_fx « raise », après un rendu de page) pour qu'il reste devant.
    Renvoie true si le toast est affiché (false : téléphone fermé, pendant un rejeu, ou petits messages désactivés
    dans Réglages > Alertes, profil COMSPEC_ATAK_Toasts).
*/
params [["_text", ""], ["_dur", 2.5]];
disableSerialization;
private _now = diag_tickTime;
private _new = _text isNotEqualTo "";
if (_new && {!(profileNamespace getVariable ["COMSPEC_ATAK_Toasts", true])}) exitWith { false };
if (!_new) then {
    (uiNamespace getVariable ["COMSPEC_ATAK_Toast", []]) params [["_t", ""], ["_end", -1]];
    _text = _t;
    _dur = _end - _now;
} else {
    if !(_dur isEqualType 0) then { _dur = 2.5; };
    _dur = (_dur max 1) min 8;
};
{ ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_ToastPill", []]);
uiNamespace setVariable ["COMSPEC_ATAK_ToastPill", []];
if (_text isEqualTo "" || {_dur <= 0.1} || {missionNamespace getVariable ["COMSPEC_ATAK_Replaying", false]}) exitWith {
    if (!_new) then { uiNamespace setVariable ["COMSPEC_ATAK_Toast", []]; };
    false
};
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { false };
// Verrou à distance du Zeus (écran rouge de fn_zeusOverlay) : pas de bulle par-dessus.
if ((["lock", ""] call comspec_atak_native_fnc_zeusFx) isNotEqualTo "") exitWith { false };
if (_new) then {
    uiNamespace setVariable ["COMSPEC_ATAK_ToastSeq", (uiNamespace getVariable ["COMSPEC_ATAK_ToastSeq", 0]) + 1];
    uiNamespace setVariable ["COMSPEC_ATAK_Toast", [_text, _now + _dur]];
};
private _seq = uiNamespace getVariable ["COMSPEC_ATAK_ToastSeq", 0];

private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["_bx", "_by", "_bw", "_bh"];
private _pad = _l get "pad";
private _fs = _l get "fontSmall";
private _th = [] call comspec_atak_native_fnc_theme;
private _bg = _d ctrlCreate ["COMSPEC_RscText", -1];
private _tx = _d ctrlCreate ["COMSPEC_RscTextCenter", -1];
{ _x ctrlEnable false; } forEach [_bg, _tx];
// Bulle : un gris-vert un cran plus clair que le relief des cartes, texte du thème (lisible en jour comme en nuit JVN).
private _sb = _th get "surfaceHi";
_bg ctrlSetBackgroundColor [(_sb select 0) + 0.06, (_sb select 1) + 0.06, (_sb select 2) + 0.06, 0.96];
_tx ctrlSetFontHeight _fs;
_tx ctrlSetTextColor (_th get "text");
// Traduit une fois : la coupe se fait sur le texte affiché (chaque morceau coupé passait par fn_tr et son cache).
private _shown = (_text) call comspec_atak_native_fnc_tr;
_tx ctrlSetText _shown; // i18n:brut
private _maxW = _bw * 0.86;
private _h = _fs * 1.9;
// Trop long (3.1.0) : la bulle passe sur deux lignes (texte structuré, centré) au lieu d'être coupée ; au-delà de deux
// lignes, coupée avec « … ».
if ((ctrlTextWidth _tx) > _maxW - _pad * 4) then {
    ctrlDelete _tx;
    _tx = _d ctrlCreate ["COMSPEC_RscStructuredText", -1];
    _tx ctrlEnable false;
    _tx ctrlSetFontHeight _fs;
    _tx ctrlSetPosition [_bx, _by, _maxW - _pad * 4, _fs * 3];
    _tx ctrlCommit 0;
    private _put = { params ["_s"]; _tx ctrlSetStructuredText parseText (format ["<t align='center' color='%2'>%1</t>", (_s regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"], _th get "hexText"]); }; // i18n:brut
    [_shown] call _put;
    private _two = _fs * 2.45;
    if ((ctrlTextHeight _tx) > _two) then {
        private _codes = toArray _shown;
        private _n = (floor ((count _codes) * _two / (ctrlTextHeight _tx))) max 1;
        while {_n > 1} do {
            [(toString (_codes select [0, _n])) + "…"] call _put;
            if ((ctrlTextHeight _tx) <= _two) exitWith {};
            _n = _n - 3;
        };
    };
    _h = (ctrlTextHeight _tx) + _fs * 0.9;
};
private _w = [((ctrlTextWidth _tx) + _pad * 5) min _maxW, _maxW] select ((ctrlType _tx) isEqualTo 13);
private _px = _bx + (_bw - _w) / 2;
private _y = _by + _bh - _h - _pad * 4;
private _anim = _new && {["anim"] call comspec_atak_native_fnc_fx};
{
    // Texte structuré (deux lignes) : centré dans la bulle, marges intérieures ; texte simple : la bulle entière.
    private _p = [[_px, _y, _w, _h], [_px + _pad * 2, _y + _fs * 0.45, _w - _pad * 4, _h - _fs * 0.9]] select (_x isEqualTo _tx && {(ctrlType _tx) isEqualTo 13});
    if (_anim) then {
        _x ctrlSetPosition [_p select 0, (_p select 1) + _h * 0.5, _p select 2, _p select 3];
        _x ctrlSetFade 1;
        _x ctrlCommit 0;
        _x ctrlSetPosition _p;
        _x ctrlSetFade 0;
        _x ctrlCommit 0.15;
    } else {
        _x ctrlSetPosition _p;
        _x ctrlCommit 0;
    };
} forEach [_bg, _tx];
uiNamespace setVariable ["COMSPEC_ATAK_ToastPill", [_bg, _tx]];
// Sous les bris d'écran, comme le reste de l'affichage (fn_overlayRaise).
[] call comspec_atak_native_fnc_overlayRaise;
// Effacement en fondu à la fin (seulement si aucun autre toast n'a pris la place entre-temps).
if (_new) then {
    [{
        params ["_seq"];
        if ((uiNamespace getVariable ["COMSPEC_ATAK_ToastSeq", 0]) isNotEqualTo _seq) exitWith {};
        private _ctrls = uiNamespace getVariable ["COMSPEC_ATAK_ToastPill", []];
        { if (!isNull _x) then { _x ctrlSetFade 1; _x ctrlCommit 0.3; }; } forEach _ctrls;
        [{
            params ["_seq"];
            if ((uiNamespace getVariable ["COMSPEC_ATAK_ToastSeq", 0]) isNotEqualTo _seq) exitWith {};
            { ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_ToastPill", []]);
            uiNamespace setVariable ["COMSPEC_ATAK_ToastPill", []];
            uiNamespace setVariable ["COMSPEC_ATAK_Toast", []];
        }, [_seq], 0.32] call CBA_fnc_waitAndExecute;
    }, [_seq], (_dur - 0.3) max 0.5] call CBA_fnc_waitAndExecute;
};
true
