/*
    Texte d'un menu CBA déclaré par un autre addon (Overwatch connect, SSE), dont l'initialisation peut passer avant
    celle de l'ATAK natif : langue du réglage « Langue / Language » s'il est déjà connu, sinon celle enregistrée dans
    le profil (réglages CBA du joueur), sinon l'anglais (valeur par défaut du réglage). Chaque traduction garde son texte français
    (COMSPEC_ATAK_MenuFr) : fn_menuRelang retraduit ces menus si la langue change en cours de partie.
*/
params [["_text", ""]];
if !(_text isEqualType "") exitWith { _text };
private _set = missionNamespace getVariable ["comspec_atak_native_language", ""];
if (_set isEqualTo "") then {
    // Réglages client enregistrés par CBA : ["#CBA_HASH#", clés, valeurs, défaut], valeur [valeur, priorité].
    private _h = profileNamespace getVariable ["cba_settings_hash", []];
    if (_h isEqualType [] && {count _h >= 3} && {(_h select 1) isEqualType []}) then {
        private _i = (_h select 1) find "comspec_atak_native_language";
        if (_i >= 0) then {
            private _v = (_h select 2) param [_i, ""];
            if (_v isEqualType []) then { _v = _v param [0, ""]; };
            if (_v isEqualType "") then { _set = _v; };
        };
    };
};
private _lang = [[_set, "en"] select (_set isEqualTo "")] call comspec_atak_native_fnc_langResolve;
if (_lang isEqualTo "fr") exitWith { _text };
private _prev = missionNamespace getVariable ["COMSPEC_ATAK_Lang", ""];
if ((missionNamespace getVariable ["COMSPEC_ATAK_I18nLoaded", ""]) isNotEqualTo _lang) then { [false, _lang] call comspec_atak_native_fnc_langApply; };
missionNamespace setVariable ["COMSPEC_ATAK_Lang", _lang];
private _r = _text call comspec_atak_native_fnc_tr;
if (_r isNotEqualTo _text) then {
    private _fr = uiNamespace getVariable ["COMSPEC_ATAK_MenuFr", createHashMap];
    uiNamespace setVariable ["COMSPEC_ATAK_MenuFr", _fr];
    _fr set [_r, _text];
};
missionNamespace setVariable ["COMSPEC_ATAK_Lang", [_prev, nil] select (_prev isEqualTo "")];
_r
