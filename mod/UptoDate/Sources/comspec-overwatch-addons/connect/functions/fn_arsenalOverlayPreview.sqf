/*
    Met à jour la bande d’icônes sous les listes de tenues.
*/
params [
    ["_display", displayNull, [displayNull]],
    ["_loadout", [], [[]]],
    ["_caption", "", [""]]
];

if (isNull _display) exitWith {};
private _grp = _display getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (isNull _grp) exitWith {};

private _pics = _grp getVariable ["COMSPEC_ArsenalPreviewPics", []];
private _namesCtrl = _grp getVariable ["COMSPEC_ArsenalPreviewNames", controlNull];
private _icons = [_loadout] call comspec_overwatch_connect_fnc_arsenalLoadoutIcons;
private _shown = _icons select { (_x select 2) isNotEqualTo "" };

{
    if (isNull _x) then { continue };
    if (_forEachIndex >= count _shown) then {
        _x ctrlSetText "";
        _x ctrlSetTooltip "";
        _x ctrlShow false;
        continue;
    };
    (_shown select _forEachIndex) params ["_kind", "_class", "_pic", "_dn"];
    _x ctrlSetText _pic;
    _x ctrlSetTooltip (if (_dn isEqualTo "") then { _kind } else { format ["%1 — %2", _kind, _dn] });
    _x ctrlShow true;
} forEach _pics;

if (!isNull _namesCtrl) then {
    private _bits = [];
    {
        _x params ["_kind", "", "", "_dn"];
        if (_dn isEqualTo "") then { continue };
        _bits pushBack format ["<t color='#8aa8a0'>%1</t>  %2", _kind, _dn];
    } forEach _shown;

    private _head = if (_caption isEqualTo "") then {
        "Cliquez une tenue pour voir l’équipement."
    } else {
        _caption
    };
    private _body = if (_bits isEqualTo []) then {
        ""
    } else {
        "<br/>" + (_bits joinString "   ·   ")
    };
    _namesCtrl ctrlSetStructuredText parseText format [
        "<t size='0.95' color='#d8f6ec'>%1</t><t size='0.82' color='#c5d4cf'>%2</t>",
        _head,
        _body
    ];
};
