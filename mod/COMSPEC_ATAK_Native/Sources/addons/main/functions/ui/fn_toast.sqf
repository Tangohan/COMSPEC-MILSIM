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
if (_new) then {
    uiNamespace setVariable ["COMSPEC_ATAK_ToastSeq", (uiNamespace getVariable ["COMSPEC_ATAK_ToastSeq", 0]) + 1];
    uiNamespace setVariable ["COMSPEC_ATAK_Toast", [_text, _now + _dur]];
};
private _seq = uiNamespace getVariable ["COMSPEC_ATAK_ToastSeq", 0];

private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["_bx", "_by", "_bw", "_bh"];
private _pad = _l get "pad";
private _fs = _l get "fontSmall";
private _bg = _d ctrlCreate ["COMSPEC_RscText", -1];
private _tx = _d ctrlCreate ["COMSPEC_RscTextCenter", -1];
{ _x ctrlEnable false; } forEach [_bg, _tx];
_bg ctrlSetBackgroundColor [0.20, 0.22, 0.21, 0.94];
_tx ctrlSetFontHeight _fs;
_tx ctrlSetTextColor [0.95, 0.97, 0.95, 1];
// Traduit une fois : la coupe se fait sur le texte affiché (chaque morceau coupé passait par fn_tr et son cache).
private _shown = (_text) call comspec_atak_native_fnc_tr;
_tx ctrlSetText _shown; // i18n:brut
private _maxW = _bw * 0.86;
// Trop long : coupé au dernier caractère qui tient, avec « … ».
if ((ctrlTextWidth _tx) > _maxW - _pad * 4) then {
    private _codes = toArray _shown;
    private _n = floor ((count _codes) * (_maxW - _pad * 4) / (ctrlTextWidth _tx)) max 1;
    while {_n > 1} do {
        _tx ctrlSetText ((toString (_codes select [0, _n])) + "…"); // i18n:brut
        if ((ctrlTextWidth _tx) <= _maxW - _pad * 4) exitWith {};
        _n = _n - 2;
    };
};
private _w = ((ctrlTextWidth _tx) + _pad * 5) min _maxW;
private _h = _fs * 1.9;
private _px = _bx + (_bw - _w) / 2;
private _y = _by + _bh - _h - _pad * 4;
private _anim = _new && {["anim"] call comspec_atak_native_fnc_fx};
{
    if (_anim) then {
        _x ctrlSetPosition [_px, _y + _h * 0.5, _w, _h];
        _x ctrlSetFade 1;
        _x ctrlCommit 0;
        _x ctrlSetPosition [_px, _y, _w, _h];
        _x ctrlSetFade 0;
        _x ctrlCommit 0.15;
    } else {
        _x ctrlSetPosition [_px, _y, _w, _h];
        _x ctrlCommit 0;
    };
} forEach [_bg, _tx];
uiNamespace setVariable ["COMSPEC_ATAK_ToastPill", [_bg, _tx]];
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
