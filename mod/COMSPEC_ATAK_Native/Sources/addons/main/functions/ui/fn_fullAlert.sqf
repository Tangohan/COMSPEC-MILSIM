/*
    Alerte du téléphone (poste de commandement, commandement Zeus, compromission, administration ATHENA, top de brèche) :
    grand bandeau de couleur DANS l'écran du téléphone, jamais sur l'écran de jeu. Titre, message, auteur et heure,
    barre de durée et bouton OK ; vibration. Téléphone rangé : l'alerte attend deux minutes (ou l'attente donnée) et
    s'affiche à la prochaine ouverture, puis pendant sa durée. Redessinée devant après chaque rendu de page (fn_fx « raise »).
    Params :
      [titre, message, auteur, couleur RGBA, durée s]   nouvelle alerte (remplace la précédente)
        6e paramètre facultatif : attente s (120 par défaut) au-delà de laquelle une alerte jamais vue est abandonnée
      ["draw"]                                          redessine l'alerte en cours (sinon rien)
      ["close"]                                         bouton OK
*/
disableSerialization;
private _clear = { { ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_FullAlertCtrls", []]); uiNamespace setVariable ["COMSPEC_ATAK_FullAlertCtrls", []]; };
if (_this isEqualTo ["close"]) exitWith { uiNamespace setVariable ["COMSPEC_ATAK_FullAlert", []]; call _clear; true };
if (_this isEqualTo ["draw"]) exitWith {
    call _clear;
    private _cur = uiNamespace getVariable ["COMSPEC_ATAK_FullAlert", []];
    if ((count _cur) < 8) exitWith { false };
    _cur params ["_title", "_msg", "_from", "_rgba", "_dur", "_at", "_hh", "_shown", ["_wait", 120]];
    // Jamais vue (téléphone rangé) pendant l'attente (deux minutes par défaut), ou affichée pendant toute sa durée : terminée.
    if ((_shown < 0 && {(diag_tickTime - _at) > _wait}) || {_shown >= 0 && {(diag_tickTime - _shown) > _dur}}) exitWith { uiNamespace setVariable ["COMSPEC_ATAK_FullAlert", []]; false };
    private _d = [] call comspec_atak_native_fnc_display;
    if (isNull _d) exitWith { false };
    if ((([] call comspec_atak_native_fnc_deviceHealth) get "state") in ["OFF", "BROKEN"]) exitWith { false };
    if (_shown < 0) then {
        _shown = diag_tickTime;
        _cur set [7, _shown];
        [] call comspec_atak_native_fnc_vibrate;
        // Fin de l'alerte : retirée si c'est toujours la même.
        [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_FullAlert", []]) param [5, -1]) isEqualTo _this) then { ["close"] call comspec_atak_native_fnc_fullAlert; }; }, _at, _dur] call CBA_fnc_waitAndExecute;
    };
    private _left = (_dur - (diag_tickTime - _shown)) max 0.1;
    private _l = [] call comspec_atak_native_fnc_layoutGet;
    (_l get "body") params ["_bx", "_by", "_bw", "_bh"];
    private _pad = _l get "pad";
    private _font = _l get "font";
    private _fs = _l get "fontSmall";
    private _ax = _bx + _pad * 2;
    private _w = _bw - _pad * 4;
    private _h = (_font * 8) min (_bh * 0.86);
    private _y = _by + (_bh - _h) * 0.3;
    private _bg = _d ctrlCreate ["COMSPEC_RscText", -1];
    _bg ctrlSetPosition [_ax, _y, _w, _h];
    _bg ctrlSetBackgroundColor _rgba;
    private _bar = _d ctrlCreate ["COMSPEC_RscText", -1];
    _bar ctrlSetPosition [_ax, _y, _w * (_left / (_dur max 0.1)), _pad * 0.5];
    _bar ctrlSetBackgroundColor [1, 1, 1, 0.85];
    private _t = _d ctrlCreate ["COMSPEC_RscStructuredText", -1];
    _t ctrlSetPosition [_ax + _pad, _y + _pad * 1.2, _w - _pad * 2, _h - _font * 2.2];
    _t ctrlSetStructuredText parseText ((format ["<t align='center' size='1.6' font='COMSPEC_BarlowBold' color='#ffffff'>%1</t><br/><t align='center' size='1.05' color='#ffffff'>%2</t><br/><t align='center' size='0.85' color='#ffe0d8'>%3 · %4</t>",
        _title, _msg, _from, _hh]) call comspec_atak_native_fnc_tr);
    private _ok = _d ctrlCreate ["COMSPEC_RscButtonPrimary", -1];
    [_ok, "COMSPEC_RscButtonPrimary"] call comspec_atak_native_fnc_fontApply;
    _ok ctrlSetPosition [_ax + _w * 0.3, _y + _h - _font * 1.7, _w * 0.4, _font * 1.35];
    _ok ctrlSetText (("OK") call comspec_atak_native_fnc_tr);
    _ok ctrlSetFontHeight _fs;
    _ok ctrlAddEventHandler ["ButtonClick", { [{ ["close"] call comspec_atak_native_fnc_fullAlert; }] call CBA_fnc_execNextFrame; }];
    private _ctrls = [_bg, _bar, _t, _ok];
    { _x ctrlCommit 0; } forEach _ctrls;
    // La barre blanche se vide pendant le temps restant.
    _bar ctrlSetPosition [_ax, _y, 0, _pad * 0.5];
    _bar ctrlCommit _left;
    uiNamespace setVariable ["COMSPEC_ATAK_FullAlertCtrls", _ctrls];
    true
};
params [["_title", "ALERTE"], ["_msg", ""], ["_from", ""], ["_rgba", [0.55, 0.08, 0.06, 0.92]], ["_dur", 10], ["_wait", 120]];
if (!hasInterface) exitWith { false };
// Événements rejoués par la synchro serveur (arrivée en cours de partie) : pas d'alerte.
if (missionNamespace getVariable ["COMSPEC_ATAK_Replaying", false]) exitWith { false };
// Texte brut (poste Athena, administration) : &, < et > échappés pour le texte structuré ; entités déjà échappées gardées.
private _esc = {
    private _s = [str _this, _this] select (_this isEqualType "");
    _s = _s regexReplace ["&(?!(amp|lt|gt|quot|apos|#[0-9]+);)", "&amp;"];
    [[_s, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace
};
_title = _title call _esc;
_msg = _msg call _esc;
_from = _from call _esc;
uiNamespace setVariable ["COMSPEC_ATAK_FullAlert", [_title, _msg, _from, _rgba, _dur max 1, diag_tickTime, [dayTime, "HH:MM"] call BIS_fnc_timeToString, -1, _wait]];
["draw"] call comspec_atak_native_fnc_fullAlert;
true
