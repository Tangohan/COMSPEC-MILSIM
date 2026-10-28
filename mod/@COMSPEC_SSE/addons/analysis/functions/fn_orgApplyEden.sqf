/*
    Applique au lancement les appartenances saisies dans Eden (serveur).
    Attributs de la catégorie « COMSPEC SSE — Organisation » des personnes :
      COMSPEC_SSE_Org_Name, _Cell, _Role, _Alias, _Known
    [] call comspec_sse_fnc_orgApplyEden
*/
if (!isServer) exitWith { false };
private _n = 0;
// Diffusion regroupée : chaque « member » publiait trois tables entières et un événement global (jusqu'à neuf
// diffusions par personne) ; une seule publication à la fin.
private _wasBatch = missionNamespace getVariable ["comspec_sse_orgBatch", false];
missionNamespace setVariable ["comspec_sse_orgBatch", true];
{
    private _org = _x getVariable ["COMSPEC_SSE_Org_Name", ""];
    if (_org isEqualType "" && {(trim _org) isNotEqualTo ""}) then {
        private _role = _x getVariable ["COMSPEC_SSE_Org_Role", "membre"];
        if (!(_role isEqualType "") || {_role in ["", "auto"]}) then { _role = "membre"; };
        private _known = _x getVariable ["COMSPEC_SSE_Org_Known", 0];
        if (!(_known isEqualType 0)) then { _known = parseNumber str _known; };
        // 0 = rien d'imposé : une campagne chargée garde ce que les joueurs savaient déjà (jamais moins).
        private _prev = ((missionNamespace getVariable ["comspec_sse_orgMembers", createHashMap]) getOrDefault [[_x] call comspec_sse_fnc_bioPersonKey, createHashMap]) getOrDefault ["known", 0];
        if (_prev >= _known) then { _known = 0; };
        ["member", [_x, _org, _role, _x getVariable ["COMSPEC_SSE_Org_Alias", ""], _x getVariable ["COMSPEC_SSE_Org_Cell", ""], [-1, _known] select (_known > 0)]] call comspec_sse_fnc_orgOp;
        if (_known > 0) then { ["revealOrg", [_org, 1, "renseignement initial"]] call comspec_sse_fnc_orgOp; };
        _n = _n + 1;
    };
} forEach (allUnits + allDeadMen);
missionNamespace setVariable ["comspec_sse_orgBatch", _wasBatch];
if (!_wasBatch && {_n > 0}) then { ["publish", []] call comspec_sse_fnc_orgOp; };
_n
