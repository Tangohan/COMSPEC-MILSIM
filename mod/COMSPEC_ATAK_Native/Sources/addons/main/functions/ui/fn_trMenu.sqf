/*
    Texte d'un menu CBA déclaré par un autre addon (Overwatch connect, SSE), dont l'initialisation peut passer avant
    celle de l'ATAK natif : langue du réglage « Langue du téléphone » s'il est déjà connu, sinon celle enregistrée dans
    le profil (réglages CBA du joueur), sinon la langue du jeu. Le menu garde cette langue jusqu'au redémarrage de la
    mission, comme celui de l'ATAK natif (fn_addSetting).
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
private _en = switch (_set) do {
    case "en": { true };
    case "fr": { false };
    default { !(language isEqualTo "French") };
};
if (!_en) exitWith { _text };
private _prev = missionNamespace getVariable ["COMSPEC_ATAK_Lang", ""];
if (isNil { missionNamespace getVariable "COMSPEC_ATAK_I18nExact" }) then { [false, "en"] call comspec_atak_native_fnc_langApply; };
missionNamespace setVariable ["COMSPEC_ATAK_Lang", "en"];
private _r = _text call comspec_atak_native_fnc_tr;
missionNamespace setVariable ["COMSPEC_ATAK_Lang", [_prev, nil] select (_prev isEqualTo "")];
_r
