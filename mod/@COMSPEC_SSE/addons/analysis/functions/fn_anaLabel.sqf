/*
    Nom et alias affichables d'une personne (identité SSE, sinon nom Arma).
    [_unit] call comspec_sse_fnc_anaLabel → [nom, alias]
*/
params [["_u", objNull, [objNull]]];
if (isNull _u) exitWith { ["?", ""] };

private _n = "";
private _a = "";
private _id = [_u, "identity"] call comspec_sse_fnc_getSection;
if (!isNil "_id" && {_id isEqualType createHashMap}) then {
    _n = _id getOrDefault ["name", ""];
    if (!(_n isEqualType "") || {_n isEqualTo ""}) then {
        _n = trim format ["%1 %2", _id getOrDefault ["first_name", ""], _id getOrDefault ["last_name", ""]];
    };
    _a = _id getOrDefault ["alias", ""];
};
if (!(_n isEqualType "") || {_n isEqualTo ""}) then {
    _n = _u getVariable ["COMSPEC_SSE_LastName", ""];
    if (_n isNotEqualTo "") then { _n = trim format ["%1 %2", _u getVariable ["COMSPEC_SSE_FirstName", ""], _n]; };
};
if (_n isEqualTo "") then { _n = name _u; };
if (!(_a isEqualType "")) then { _a = ""; };
if (_a isEqualTo "") then { _a = _u getVariable ["COMSPEC_SSE_Alias", ""]; };
[_n, _a]
