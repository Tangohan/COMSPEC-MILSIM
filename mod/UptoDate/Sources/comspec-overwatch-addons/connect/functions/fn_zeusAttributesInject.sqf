/*
    Barre SSE / ATAK / OVERWATCH en tête du panneau Zeus « Éditer ».
    Uniquement personne / véhicule / groupe. Si le haut du panneau est
    introuvable, on n’injecte rien (pas de superposition sur le reste de Zeus).
*/
params [["_display", displayNull]];
if (!hasInterface) exitWith {};
if (isNull _display) exitWith {};
[_display] call comspec_overwatch_connect_fnc_fillZeusGroupId;
if (isNull (findDisplay 312)) exitWith {};
if (ctrlIDD _display == 312) exitWith {};
if (!isNull (_display displayCtrl 86101)) exitWith {};

private _obj = [_display] call comspec_overwatch_connect_fnc_zeusAttributesTarget;
if (isNull _obj) exitWith {};
private _editable = (_obj isKindOf "CAManBase")
    || {_obj isKindOf "LandVehicle"}
    || {_obj isKindOf "Air"}
    || {_obj isKindOf "Ship"}
    || {_obj isKindOf "StaticWeapon"};
if (!_editable) exitWith {};

private _title = _display displayCtrl 30001;
if (isNull _title) then { _title = _display displayCtrl 10; };
private _titleTxt = if (isNull _title) then { "" } else { toLower (ctrlText _title) };
if (
    (_titleTxt find "atak —" >= 0)
    || {_titleTxt find "sse —" >= 0}
    || {_titleTxt find "overwatch —" >= 0}
    || {_titleTxt find "editable object" >= 0}
    || {_titleTxt find "editable objects" >= 0}
    || {_titleTxt find "update editable" >= 0}
    || {_titleTxt find "remove objects" >= 0}
    || {_titleTxt find "add objects" >= 0}
    || {(_titleTxt find "objet" >= 0) && {(_titleTxt find "édit" >= 0) || {_titleTxt find "edit" >= 0}}}
    || {_titleTxt find "module" >= 0}
) exitWith {};

private _hay = _titleTxt;
{
    _hay = _hay + " " + toLower (ctrlText _x);
} forEach (allControls _display);
if (
    (_hay find "editable object" >= 0)
    || {_hay find "update editable" >= 0}
    || {_hay find "remove objects" >= 0}
    || {_hay find "add objects" >= 0}
    || {(_hay find "filtre" >= 0) && {(_hay find "rayon" >= 0) || {_hay find "portée" >= 0}}}
) exitWith {};

private _anchorPos = [];
if (!isNull _title) then {
    _anchorPos = ctrlPosition _title;
};
if ((count _anchorPos) < 4 || {(_anchorPos select 2) < 0.10}) then {
    private _ok = _display displayCtrl 1;
    if (isNull _ok) exitWith {};
    private _okP = ctrlPosition _ok;
    private _okL = _okP select 0;
    private _okR = (_okP select 0) + (_okP select 2);
    private _bestY = 1e9;
    {
        if (!ctrlShown _x) then { continue };
        private _c = ctrlPosition _x;
        if ((count _c) < 4) then { continue };
        if ((_c select 2) < 0.12) then { continue };
        if ((_c select 3) < 0.014) then { continue };
        if ((_c select 3) > 0.08 * safezoneH) then { continue };
        private _cl = _c select 0;
        private _cr = (_c select 0) + (_c select 2);
        if (_cr < _okL - 0.08) then { continue };
        if (_cl > _okR + 0.12) then { continue };
        if ((_c select 1) < _bestY) then {
            _bestY = _c select 1;
            _anchorPos = _c;
        };
    } forEach (allControls _display);
};
if ((count _anchorPos) < 4 || {(_anchorPos select 2) < 0.10}) exitWith {};

_display setVariable ["COMSPEC_AttrEntity", _obj];

private _ok = _display displayCtrl 1;
private _h = 0.026 * safezoneH;
if (!isNull _ok) then {
    _h = ((((ctrlPosition _ok) select 3) max 0.022) min 0.032);
};
private _gap = 0.004 * safezoneW;
private _pad = 0.003 * safezoneH;
private _barW = _anchorPos select 2;
private _w = (_barW - 2 * _gap) / 3;
private _x0 = _anchorPos select 0;
private _y0 = (_anchorPos select 1) - _h - _pad;

if (_y0 < (safezoneY + 0.004 * safezoneH)) then {
    _y0 = _anchorPos select 1;
    _h = (((_anchorPos select 3) max 0.022) min 0.032);
    private _rightW = (_barW * 0.52) min (0.24 * safezoneW);
    _w = (_rightW - 2 * _gap) / 3;
    _x0 = (_anchorPos select 0) + (_anchorPos select 2) - _rightW;
};

if (_w < 0.048) exitWith {};
private _owLabel = if (_w < 0.07) then { "OW" } else { "OVERWATCH" };

private _bar = _display ctrlCreate ["RscText", 86100];
_bar ctrlSetPosition [_x0 - 0.002, _y0 - 0.002, (3 * _w + 2 * _gap) + 0.004, _h + 0.004];
_bar ctrlSetBackgroundColor [0.01, 0.07, 0.06, 0.92];
_bar ctrlEnable false;
_bar ctrlCommit 0;

private _specs = [
    [86101, "SSE", "Identité, dossier et terminal de recueil sur cette personne (ou l’équipage).", 0, "sse"],
    [86102, "ATAK", "Carte de commandement : téléphone, données visibles, IA alliée, balise GPS.", 1, "atak"],
    [86103, _owLabel, "Overwatch — liaison Athena : indicatif, synchro, état du terminal.", 2, "overwatch"]
];

{
    _x params ["_idc", "_label", "_tip", "_idx", "_kind"];
    private _btn = _display ctrlCreate ["RscButtonMenu", _idc];
    _btn ctrlSetPosition [_x0 + _idx * (_w + _gap), _y0, _w, _h];
    _btn ctrlSetText _label;
    _btn ctrlSetTooltip _tip;
    _btn ctrlSetFont "PuristaMedium";
    _btn ctrlSetBackgroundColor [0.02, 0.16, 0.14, 0.95];
    _btn ctrlSetTextColor [0.85, 0.95, 0.9, 1];
    _btn setVariable ["COMSPEC_Entity", _obj];
    _btn setVariable ["COMSPEC_AttrKind", _kind];
    _btn ctrlAddEventHandler ["ButtonClick", {
        params ["_ctrl"];
        private _disp = ctrlParent _ctrl;
        private _e = _ctrl getVariable ["COMSPEC_Entity", objNull];
        if (isNull _e) then {
            _e = [_disp] call comspec_overwatch_connect_fnc_zeusAttributesTarget;
        };
        private _kind = _ctrl getVariable ["COMSPEC_AttrKind", "atak"];
        if (!isNull _disp) then { _disp closeDisplay 2; };
        switch (_kind) do {
            case "sse": {
                [_e, 0.32] call comspec_overwatch_connect_fnc_zeusAttributesSse;
            };
            case "overwatch": {
                [_e, 0.32] call comspec_overwatch_connect_fnc_zeusAttributesOverwatch;
            };
            default {
                [_e, 0.32] call comspec_overwatch_connect_fnc_zeusAttributesAtak;
            };
        };
    }];
    _btn ctrlCommit 0;
} forEach _specs;
