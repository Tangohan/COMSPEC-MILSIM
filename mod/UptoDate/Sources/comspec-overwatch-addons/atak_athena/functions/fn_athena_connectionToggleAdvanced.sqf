/*
    Affiche / masque les champs avancés de liaison au poste (Paramètres ATAK).
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Settings_group", controlNull];
if (isNull _group) exitWith {};
private _body = _group controlsGroupCtrl 9839;
if (!isNull _body) then { _group = _body; };

private _ctrl = {
    params ["_idc"];
    private _c = _group controlsGroupCtrl _idc;
    if (isNull _c) then {
        private _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
        if (!isNull _disp) then { _c = _disp displayCtrl _idc; };
    };
    _c
};

private _open = !(_group getVariable ["COMSPEC_AtakLinkAdvanced", false]);
_group setVariable ["COMSPEC_AtakLinkAdvanced", _open];

{
    private _c = [_x] call _ctrl;
    if (!isNull _c) then { _c ctrlShow _open; };
} forEach [9851, 9852, 9853, 9854, 9858, 9859, 9860];

private _tog = [9857] call _ctrl;
if (!isNull _tog) then {
    _tog ctrlSetText (if (_open) then { "Masquer les réglages avancés" } else { "Afficher les réglages avancés" });
};
