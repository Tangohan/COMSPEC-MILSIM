/*
    zen_dialog_fnc_create avec le titre, les libellés, les aides et les choix des listes dans la langue du téléphone
    (comspec_sse_fnc_tr) : ZEN ne traduit que les clés STR_…, et les formulaires du SSE sont écrits en français.
    Mêmes paramètres que zen_dialog_fnc_create ; les valeurs (texte saisi, valeurs des listes) ne sont pas touchées.
    Renvoie false sans ZEN.
*/
if (isNil "zen_dialog_fnc_create") exitWith { false };
private _tr = { if (_this isEqualType "") then { _this call comspec_sse_fnc_tr } else { _this } };
// Choix d'une liste : texte seul, ou [texte, aide, image, couleur].
private _trChoice = {
    if (_this isEqualType []) exitWith {
        private _e = +_this;
        _e set [0, (_e param [0, ""]) call _tr];
        if (count _e > 1) then { _e set [1, (_e select 1) call _tr]; };
        _e
    };
    _this call _tr
};
private _a = +_this;
_a set [0, (_a param [0, ""]) call _tr];
private _rows = (_a param [1, []]) apply {
    private _row = +_x;
    private _type = toUpper (_row param [0, ""]);
    // Libellé : texte, ou [texte, aide].
    private _lbl = _row param [1, ""];
    _row set [1, if (_lbl isEqualType []) then { _lbl apply { _x call _tr } } else { _lbl call _tr }];
    private _d = _row param [2, []];
    // COMBO / LIST : [valeurs, libellés, défaut] ; TOOLBOX : [défaut, lignes, colonnes, libellés].
    if ((_type find "COMBO") isEqualTo 0 || {(_type find "LIST") isEqualTo 0}) then {
        if (_d isEqualType [] && {count _d > 1} && {(_d select 1) isEqualType []}) then {
            _d = +_d;
            _d set [1, (_d select 1) apply { _x call _trChoice }];
            _row set [2, _d];
        };
    };
    if ((_type find "TOOLBOX") isEqualTo 0 && {_d isEqualType []} && {count _d > 3} && {(_d select 3) isEqualType []}) then {
        _d = +_d;
        _d set [3, (_d select 3) apply { _x call _trChoice }];
        _row set [2, _d];
    };
    _row
};
_a set [1, _rows];
_a call zen_dialog_fnc_create;
true
