/*
    Boutons SSE / ATAK / OVERWATCH dans le panneau Zeus « Éditer l’objet ».
    Injection SQF (comme le bouton Échap) : pas d’héritage de RscDisplayAttributes.
*/
params [["_display", displayNull]];
if (!hasInterface) exitWith {};
if (isNull _display) exitWith {};
if (!isNull (_display displayCtrl 86101)) exitWith {};

private _obj = [_display] call comspec_overwatch_connect_fnc_zeusAttributesTarget;
_display setVariable ["COMSPEC_AttrEntity", _obj];

private _ok = _display displayCtrl 1;
private _x0 = 0.22 * safezoneW + safezoneX;
private _y0 = 0.86 * safezoneH + safezoneY;
private _w = 0.072 * safezoneW;
private _h = 0.028 * safezoneH;
private _gap = 0.006 * safezoneW;
if (!isNull _ok) then {
    private _p = ctrlPosition _ok;
    _h = ((_p select 3) max 0.022);
    _w = ((_p select 2) min 0.12) max 0.055;
    _gap = _w * 0.08;
    _y0 = (_p select 1) - _h * 1.28;
    _x0 = (_p select 0) + (_p select 2) - (_w * 3 + _gap * 2);
    if (_x0 < safezoneX + 0.02 * safezoneW) then {
        _x0 = safezoneX + 0.22 * safezoneW;
    };
};

private _specs = [
    [86101, "SSE", "Identité, dossier et terminal de recueil sur cette personne (ou l’équipage).", 0, "sse"],
    [86102, "ATAK", "Carte de commandement : téléphone, données visibles, IA alliée, balise GPS.", 1, "atak"],
    [86103, "OVERWATCH", "Liaison Athena : indicatif, synchro, état du terminal.", 2, "overwatch"]
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
        private _e = _ctrl getVariable ["COMSPEC_Entity", objNull];
        if (isNull _e) then {
            _e = [ctrlParent _ctrl] call comspec_overwatch_connect_fnc_zeusAttributesTarget;
        };
        private _kind = _ctrl getVariable ["COMSPEC_AttrKind", "atak"];
        switch (_kind) do {
            case "sse": {
                [_e, 0.08] call comspec_overwatch_connect_fnc_zeusAttributesSse;
            };
            case "overwatch": {
                [_e, 0.08] call comspec_overwatch_connect_fnc_zeusAttributesOverwatch;
            };
            default {
                [_e, 0.08] call comspec_overwatch_connect_fnc_zeusAttributesAtak;
            };
        };
    }];
    _btn ctrlCommit 0;
} forEach _specs;
