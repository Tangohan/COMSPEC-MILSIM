/*
    Texte affiché dans la langue du téléphone : passe la main à comspec_atak_native_fnc_tr (ATAK natif, dictionnaire
    i18n/en.txt). Sans l'ATAK natif, ou téléphone en français, le texte reste tel quel. Les appels sont posés par
    mod/COMSPEC_ATAK_Native/tools/i18n.py --connect wrap.
*/
if (isNil "comspec_atak_native_fnc_tr") exitWith { _this };
_this call comspec_atak_native_fnc_tr
