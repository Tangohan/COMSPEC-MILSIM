/*
    Largeur d'un texte à l'écran (unités d'écran en x), pour poser un fond juste à la taille du texte sur la carte.
    Mesurée une fois par [texte, police, taille] sur un texte caché hors de l'écran (ctrlTextWidth), puis gardée en cache.
    Sans écran ATAK (ou mesure nulle) : estimation à 0,5 hauteur de lettre par caractère.
    Params : [texte, police, taille (unités d'écran en y)]
*/
params ["_txt", "_font", "_size"];
private _cache = uiNamespace getVariable "COMSPEC_ATAK_TextWCache";
if (isNil "_cache" || {(count _cache) > 800}) then { _cache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_TextWCache", _cache]; };
private _key = [_txt, _font, _size];
private _w = _cache getOrDefault [_key, -1];
if (_w >= 0) exitWith { _w };
private _c = uiNamespace getVariable ["COMSPEC_ATAK_TextWCtrl", controlNull];
if (isNull _c) then {
    private _d = [] call comspec_atak_native_fnc_display;
    if (!isNull _d) then {
        _c = _d ctrlCreate ["COMSPEC_RscText", -1];
        _c ctrlSetPosition [-10, -10, 2, 0.1];
        _c ctrlCommit 0;
        uiNamespace setVariable ["COMSPEC_ATAK_TextWCtrl", _c];
    };
};
if (!isNull _c) then {
    _c ctrlSetFont _font;
    _c ctrlSetFontHeight _size;
    _c ctrlSetText _txt; // i18n:brut (mesure de largeur)
    _w = ctrlTextWidth _c;
};
if (_w <= 0) then { _w = (count _txt) * _size * 0.5 * pixelW / pixelH; };
_cache set [_key, _w];
_w
