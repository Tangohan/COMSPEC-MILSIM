/*
    Applique au lancement les appartenances saisies dans Eden (serveur).
    Attributs de la catégorie « COMSPEC SSE — Organisation » des personnes :
      COMSPEC_SSE_Org_Name, _Cell, _Role, _Alias, _Known
    [] call comspec_sse_fnc_orgApplyEden
*/
if (!isServer) exitWith { false };
private _n = 0;
{
    private _org = _x getVariable ["COMSPEC_SSE_Org_Name", ""];
    if (_org isEqualType "" && {(trim _org) isNotEqualTo ""}) then {
        private _role = _x getVariable ["COMSPEC_SSE_Org_Role", "membre"];
        if (!(_role isEqualType "") || {_role in ["", "auto"]}) then { _role = "membre"; };
        private _known = _x getVariable ["COMSPEC_SSE_Org_Known", 0];
        if (!(_known isEqualType 0)) then { _known = parseNumber str _known; };
        ["member", [_x, _org, _role, _x getVariable ["COMSPEC_SSE_Org_Alias", ""], _x getVariable ["COMSPEC_SSE_Org_Cell", ""], _known]] call comspec_sse_fnc_orgOp;
        if (_known > 0) then { ["revealOrg", [_org, 1, "renseignement initial"]] call comspec_sse_fnc_orgOp; };
        _n = _n + 1;
    };
} forEach (allUnits + allDeadMen);
_n
