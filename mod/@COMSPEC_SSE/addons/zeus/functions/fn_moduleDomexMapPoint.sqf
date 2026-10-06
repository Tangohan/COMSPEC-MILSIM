params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

if (!_activated) exitWith { true };
// Un seul exécutant : le poste où la logique est locale (Zeus qui pose / serveur en Eden).
if (!local _logic) exitWith { true };

private _pos = getPosATL _logic;
private _obj = _logic getVariable ["bis_fnc_curatorAttachObject_object", objNull];
private _label = _logic getVariable ["Label", ""];
// Eden (pas d'interface Zeus) : pas de formulaire, libellé générique.
if (_label isEqualTo "" && {isNull curatorCamera}) then { _label = "Point de renseignement"; };

if (_label isNotEqualTo "") then {
    [_pos, _label, _obj, "complet"] call comspec_sse_fnc_domexPlaceMapPoint;
    if (hasInterface) then {
        ["Point posé sur la carte du bureau (invisible des joueurs)."] call comspec_sse_fnc_zeusNotify;
    };
} else {
    private _open = uiNamespace getVariable ["COMSPEC_SSE_DomexOpenMapPoint", {}];
    if (_open isEqualTo {}) then {
        [] call comspec_sse_fnc_registerZenDomexLive;
        _open = uiNamespace getVariable ["COMSPEC_SSE_DomexOpenMapPoint", {}];
    };
    [_obj, _pos] call _open;
};

deleteVehicle _logic;
true
