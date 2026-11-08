/*
    Apps regroupées (fn_appSpaces) : l'écran d'accueil montre 3 à 5 grandes apps ; chacune ouvre une page (SPACE) avec ses
    sous-groupes, dont les tuiles ouvrent les apps existantes par fn_navigate, sans rien changer à leur code.
    Params : ["launcher"] tuiles des espaces et « Toutes les apps » ; ["page"] contenu de l'espace ouvert
    (uiNamespace COMSPEC_ATAK_SpaceId). Rien par image : contrôles créés au rendu.
*/
params [["_mode", "page"]];
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { false };
private _g = _d displayCtrl 88531;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", ""];
private _cols = _l get "cols";
private _pad = (_l get "pad") * 2;
private _font = _l get "fontSmall";
private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
private _thm = [] call comspec_atak_native_fnc_theme;
private _gw = _bw - 0.012 - 2 * _pad;
private _tw = (_bw - 0.012 - _pad * (_cols + 1)) / _cols;
private _th = _tw * _ratio * ([0.92, 0.8] select (_l get "mini"));
private _secH = _font * 1.6;
private _y = _pad;
private _seen = profileNamespace getVariable ["COMSPEC_ATAK_AppSeen", []];
if !(_seen isEqualType []) then { _seen = []; };
private _track = {
    private _list = uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []];
    _list append _this;
    uiNamespace setVariable ["COMSPEC_ATAK_PageControls", _list];
};
private _note = {
    params ["_txt"];
    private _c = ["COMSPEC_RscText", [_pad, _y, _gw, _font * 1.3], _txt] call comspec_atak_native_fnc_pageCtrl;
    _c ctrlSetFontHeight ((_font * 0.85) max COMSPEC_ATAK_FontMin);
    _c ctrlSetTextColor (_thm get "textMuted");
    _c ctrlEnable false;
    _y = _y + _font * 1.3 + _pad * 0.4;
};
private _spaces = ["spaces"] call comspec_atak_native_fnc_appSpaces;

if (_mode isEqualTo "launcher") exitWith {
    private _role = ["role"] call comspec_atak_native_fnc_appSpaces;
    if (_role isNotEqualTo "all") then {
        [format [("Profil : %1. Tout reste accessible par « Toutes les apps ».") call comspec_atak_native_fnc_tr, (["roleName", _role] call comspec_atak_native_fnc_appSpaces) call comspec_atak_native_fnc_tr]] call _note;
    };
    private _big = (_cols min 3) max 2;
    private _bigW = (_bw - 0.012 - _pad * (_big + 1)) / _big;
    private _bigH = _bigW * _ratio * 0.72;
    private _cells = _spaces apply { [_x select 0, _x select 1, _x select 2, _x select 4] };
    _cells pushBack ["*ALL", "Toutes les apps", "\z\comspec_atak_native\addons\main\data\ui_apps.paa", 0];
    {
        _x params ["_key", "_title", "_icon", "_badge"];
        private _pos = [_pad + (_forEachIndex mod _big) * (_bigW + _pad), _y + (floor (_forEachIndex / _big)) * (_bigH + _pad), _bigW, _bigH];
        private _tile = [_d, _g, _pos, _icon, _title, _badge, "SPACE", _font] call comspec_atak_native_fnc_tileCreate;
        private _btn = _tile select ((count _tile) - 1);
        _btn setVariable ["spaceId", _key];
        _btn ctrlRemoveAllEventHandlers "ButtonClick";
        _btn ctrlAddEventHandler ["ButtonClick", {
            params ["_c"];
            private _k = _c getVariable ["spaceId", ""];
            if (_k isEqualTo "*ALL") exitWith { ["showAll", true] call comspec_atak_native_fnc_appSpaces; };
            uiNamespace setVariable ["COMSPEC_ATAK_SpaceId", _k];
            [{ ["SPACE"] call comspec_atak_native_fnc_navigate; }] call comspec_atak_native_fnc_nextFrame;
        }];
        _tile call _track;
    } forEach _cells;
    true
};

private _id = uiNamespace getVariable ["COMSPEC_ATAK_SpaceId", ""];
private _space = (_spaces select { (_x select 0) isEqualTo _id }) param [0, []];
if ((count _space) isEqualTo 0) exitWith {
    [{ ["LAUNCHER"] call comspec_atak_native_fnc_navigate; }] call comspec_atak_native_fnc_nextFrame;
    false
};
{
    _x params ["_sub", "_apps"];
    private _band = ["COMSPEC_RscText", [_pad, _y, _gw, _secH]] call comspec_atak_native_fnc_pageCtrl;
    private _bb = _thm get "bg0";
    _band ctrlSetBackgroundColor [_bb select 0, _bb select 1, _bb select 2, 0.62];
    _band ctrlEnable false;
    private _bar = ["COMSPEC_RscText", [_pad, _y + _secH * 0.22, pixelW * 3, _secH * 0.56]] call comspec_atak_native_fnc_pageCtrl;
    _bar ctrlSetBackgroundColor (_thm get "accent");
    _bar ctrlEnable false;
    private _lbl = ["COMSPEC_RscStructuredText", [_pad * 1.6, _y + _secH * 0.12, _gw - _pad, _secH]] call comspec_atak_native_fnc_pageCtrl;
    _lbl ctrlSetStructuredText parseText format ["<t size='0.85' font='COMSPEC_BarlowBold' color='%2'>%1</t>", toUpper (_sub call comspec_atak_native_fnc_tr), _thm get "hexText"]; // i18n:brut (déjà traduit)
    _lbl ctrlEnable false;
    _y = _y + _secH + _pad * 0.6;
    {
        private _page = _x get "page";
        private _pos = [_pad + (_forEachIndex mod _cols) * (_tw + _pad), _y + (floor (_forEachIndex / _cols)) * (_th + _pad), _tw, _th];
        private _tile = [_d, _g, _pos, _x get "icon", _x get "name", [_page] call comspec_atak_native_fnc_appBadge, _page, _font,
            _x getOrDefault ["wip", false], (_x getOrDefault ["new", false]) && {!(_page in _seen)}, false] call comspec_atak_native_fnc_tileCreate;
        _tile call _track;
    } forEach _apps;
    _y = _y + (ceil ((count _apps) / _cols)) * (_th + _pad) + _pad * 0.6;
} forEach (_space select 3);
true
