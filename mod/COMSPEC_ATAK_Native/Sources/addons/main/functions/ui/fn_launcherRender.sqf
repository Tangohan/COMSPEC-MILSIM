/*
    Lanceur (V2) : champ de recherche (en main seulement), puis grille d'icônes par section, 3 colonnes en mini, 6 en plein écran.
    En-tête de section : barre d'accent, titre, nombre d'apps et filet, sur un bandeau léger (lisible sur tout fond d'écran).
    Recherche : filtre sur le nom de l'app (uiNamespace COMSPEC_ATAK_LauncherFilter), redessin 0,25 s après la frappe,
    le champ garde le focus et le curseur en fin de texte. Entrée ouvre la première app trouvée.
*/
disableSerialization;
private _d = ([] call comspec_atak_native_fnc_display);
if (isNull _d) exitWith { false };
private _g = _d displayCtrl 88531;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", ""];
private _cols = _l get "cols";
private _pad = (_l get "pad") * 2;
private _font = _l get "fontSmall";
private _ratio = pixelH / pixelW;
([] call comspec_atak_native_fnc_accent) params ["_acc", "_accHex"];
private _tw = (_bw - 0.012 - _pad * (_cols + 1)) / _cols;
private _th = _tw * _ratio * 0.92;
private _secH = _font * 1.6;
private _gw = _bw - 0.012 - 2 * _pad;
private _apps = ([] call comspec_atak_native_fnc_appList) select { [_x] call comspec_atak_native_fnc_appVisible };
private _interactive = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false];
private _filter = [uiNamespace getVariable ["COMSPEC_ATAK_LauncherFilter", ""], ""] select (!_interactive || {!(profileNamespace getVariable ["COMSPEC_ATAK_LauncherSearch", true])});
private _y = _pad;

// Recherche
if (_interactive && {profileNamespace getVariable ["COMSPEC_ATAK_LauncherSearch", true]}) then {
    private _sh = _font * 1.7;
    private _sbg = ["COMSPEC_RscText", [_pad, _y, _gw, _sh]] call comspec_atak_native_fnc_pageCtrl;
    _sbg ctrlSetBackgroundColor [0.05, 0.06, 0.055, 0.82];
    _sbg ctrlEnable false;
    private _ic = ["COMSPEC_RscPicture", [_pad * 1.6, _y + _sh * 0.2, _sh * 0.6 / _ratio, _sh * 0.6], "\z\comspec_atak_native\addons\main\data\map_zoomin.paa"] call comspec_atak_native_fnc_pageCtrl;
    _ic ctrlSetTextColor [0.62, 0.7, 0.66, 1];
    _ic ctrlEnable false;
    private _ex = _pad * 2.4 + _sh * 0.6 / _ratio;
    private _hint = ["COMSPEC_RscText", [_ex, _y, _gw - _ex, _sh], "Rechercher une app"] call comspec_atak_native_fnc_pageCtrl;
    _hint ctrlSetFontHeight (_font * 0.95);
    _hint ctrlSetTextColor [0.5, 0.57, 0.53, 1];
    _hint ctrlEnable false;
    _hint ctrlShow (_filter isEqualTo "");
    private _ed = ["COMSPEC_RscEdit", [_ex, _y + _sh * 0.1, _gw - _ex - _sh * 1.2, _sh * 0.8], _filter] call comspec_atak_native_fnc_pageCtrl;
    _ed ctrlSetBackgroundColor [0, 0, 0, 0];
    _ed ctrlSetFontHeight (_font * 0.95);
    _ed setVariable ["hint", _hint];
    _ed ctrlAddEventHandler ["KeyUp", {
        params ["_e", "_key"];
        (_e getVariable ["hint", controlNull]) ctrlShow ((ctrlText _e) isEqualTo "");
        if ((ctrlText _e) isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_LauncherFilter", ""]) && {!(_key in [0x1C, 0x9C])}) exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_LauncherFilter", ctrlText _e];
        // Entrée : ouvre la première app trouvée.
        if (_key in [0x1C, 0x9C]) exitWith {
            private _f = toLower trim (ctrlText _e);
            private _hit = (([] call comspec_atak_native_fnc_appList) select { [_x] call comspec_atak_native_fnc_appVisible && {(toLower (_x get "name")) find _f >= 0} }) param [0, createHashMap];
            if ((count _hit) > 0 && {_f isNotEqualTo ""}) then {
                uiNamespace setVariable ["COMSPEC_ATAK_LauncherFilter", ""];
                [{ [_this] call comspec_atak_native_fnc_navigate; }, _hit get "page"] call CBA_fnc_execNextFrame;
            };
        };
        private _tok = diag_tickTime;
        uiNamespace setVariable ["COMSPEC_ATAK_LauncherTok", _tok];
        [{
            params ["_tok"];
            if ((uiNamespace getVariable ["COMSPEC_ATAK_LauncherTok", -1]) isNotEqualTo _tok) exitWith {};
            if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["pageDrawn", ""]) isNotEqualTo "LAUNCHER") exitWith {};
            ["LAUNCHER"] call comspec_atak_native_fnc_pageRender;
            // Le champ recréé reprend le focus, curseur en fin de texte.
            private _e2 = uiNamespace getVariable ["COMSPEC_ATAK_LauncherEdit", controlNull];
            if (!isNull _e2) then { ctrlSetFocus _e2; _e2 ctrlSetTextSelection [count (ctrlText _e2), 0]; };
        }, [_tok], 0.25] call CBA_fnc_waitAndExecute;
    }];
    uiNamespace setVariable ["COMSPEC_ATAK_LauncherEdit", _ed];
    if (_filter isNotEqualTo "") then {
        private _clr = ["COMSPEC_RscButtonOverlay", [_pad + _gw - _sh * 1.1, _y, _sh * 1.1, _sh], "×"] call comspec_atak_native_fnc_pageCtrl;
        _clr ctrlSetFontHeight _font;
        _clr ctrlSetTooltip "Effacer la recherche";
        _clr ctrlAddEventHandler ["ButtonClick", { uiNamespace setVariable ["COMSPEC_ATAK_LauncherFilter", ""]; [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }];
    };
    _y = _y + _sh + _pad;
};

// Sections (ou une seule section de résultats quand on cherche).
private _groups = [];
if (_filter isNotEqualTo "") then {
    private _f = toLower trim _filter;
    _groups pushBack [format ["Résultats pour « %1 »", _filter], _apps select { ((toLower (_x get "name")) find _f) >= 0 || {((toLower (_x get "section")) find _f) >= 0} }];
} else {
    private _sections = [];
    { _sections pushBackUnique (_x get "section"); } forEach _apps;
    { private _sec = _x; _groups pushBack [_sec, _apps select { (_x get "section") isEqualTo _sec }]; } forEach _sections;
};
{
    _x params ["_sec", "_items"];
    // En-tête : bandeau, barre d'accent, titre, nombre, filet.
    private _band = ["COMSPEC_RscText", [_pad, _y, _gw, _secH]] call comspec_atak_native_fnc_pageCtrl;
    _band ctrlSetBackgroundColor [0.03, 0.04, 0.035, 0.55];
    _band ctrlEnable false;
    private _bar = ["COMSPEC_RscText", [_pad, _y + _secH * 0.22, pixelW * 3, _secH * 0.56]] call comspec_atak_native_fnc_pageCtrl;
    _bar ctrlSetBackgroundColor _acc;
    _bar ctrlEnable false;
    private _lbl = ["COMSPEC_RscStructuredText", [_pad * 1.6, _y + _secH * 0.12, _gw - _pad, _secH]] call comspec_atak_native_fnc_pageCtrl;
    _lbl ctrlSetStructuredText parseText format ["<t size='0.85' font='RobotoCondensedBold' color='#e8eee9'>%1</t>  <t size='0.75' color='#8a9a93'>%2</t>", toUpper _sec, count _items];
    _lbl ctrlEnable false;
    private _line = ["COMSPEC_RscText", [_pad, _y + _secH - pixelH, _gw, pixelH]] call comspec_atak_native_fnc_pageCtrl;
    _line ctrlSetBackgroundColor [1, 1, 1, 0.08];
    _line ctrlEnable false;
    _y = _y + _secH + _pad * 0.6;
    if ((count _items) isEqualTo 0) then {
        private _none = ["COMSPEC_RscText", [_pad, _y, _gw, _font * 1.4], "Aucune app ne correspond."] call comspec_atak_native_fnc_pageCtrl;
        _none ctrlSetFontHeight _font;
        _none ctrlSetTextColor [0.55, 0.62, 0.58, 1];
        _y = _y + _font * 1.4 + _pad;
    };
    {
        private _col = _forEachIndex mod _cols;
        private _row = floor (_forEachIndex / _cols);
        private _tile = [_d, _g, [_pad + _col * (_tw + _pad), _y + _row * (_th + _pad), _tw, _th], _x get "icon", _x get "name", [_x get "page"] call comspec_atak_native_fnc_appBadge, _x get "page", _font] call comspec_atak_native_fnc_tileCreate;
        private _list = uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []];
        _list append _tile;
        uiNamespace setVariable ["COMSPEC_ATAK_PageControls", _list];
    } forEach _items;
    _y = _y + (ceil ((count _items) / _cols)) * (_th + _pad) + _pad * 0.6;
} forEach _groups;
true
