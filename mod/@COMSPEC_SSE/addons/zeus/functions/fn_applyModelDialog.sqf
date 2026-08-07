private _display = findDisplay 93030;
if (isNull _display) exitWith { false };

private _lb = _display displayCtrl 93031;
private _id = _lb lbData (lbCurSel _lb);
if (_id isEqualTo "") exitWith { hint "Aucun modèle sélectionné."; false };

private _targets = missionNamespace getVariable ["comspec_sse_zeusPendingTargets", []];
{
    if (!isNull _x) then {
        [_x, _id, "ZEUS"] call comspec_sse_fnc_applyModel;
    };
} forEach _targets;

closeDialog 0;
private _model = [_id] call comspec_sse_fnc_loadModel;
private _name = if (isNil "_model") then { _id } else { _model getOrDefault ["name", _id] };
hint format ["Modèle « %1 » appliqué sur %2 cible(s)", _name, count _targets];
true
