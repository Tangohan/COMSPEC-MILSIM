/*
    Affiche les champs consignes libres ou le formulaire FRAGO (SMEAC).
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_OrderCompose_Display", displayNull];
if (isNull _disp) exitWith {};

private _kindIdx = lbCurSel (_disp displayCtrl 9502);
private _kind = if (_kindIdx < 0) then { "MOVE" } else { (_disp displayCtrl 9502) lbData _kindIdx };
private _isFrago = (_kind isEqualTo "FRAGO");

{
    (_disp displayCtrl _x) ctrlShow !_isFrago;
} forEach [9510, 9511];

{
    (_disp displayCtrl _x) ctrlShow _isFrago;
} forEach [9520, 9521, 9522, 9523, 9524, 9525, 9526, 9527, 9528, 9529];

private _title = _disp displayCtrl 9500;
if (_isFrago) then {
    _title ctrlSetStructuredText parseText "<t font='RobotoCondensedBold' size='1' align='center' color='#e8f4f0'>Ordre fragmentaire</t>";
} else {
    _title ctrlSetStructuredText parseText "<t font='RobotoCondensedBold' size='1' align='center' color='#e8f4f0'>Émettre un ordre</t>";
};

private _hint = _disp displayCtrl 9530;
private _tgtIdx = lbCurSel (_disp displayCtrl 9504);
private _tgtLabel = if (_tgtIdx < 0) then { "groupe" } else { (_disp displayCtrl 9504) lbText _tgtIdx };
_hint ctrlSetStructuredText parseText "";

