/*
    Impose le nom choisi en Eden / Zeus sur le cluster narratif.

    Priorité :
      1. Nom + prénom COMSPEC (attributs unité / module profil)
      2. Identité Arma de l’unité (panneau Identité Eden / setName)
         — sauf si un modèle a déjà forcé un nom (nameForced)
      3. Sinon on laisse le nom généré du cluster

    [_entity, _cluster] call comspec_sse_fnc_applyAuthoredIdentity
*/
params [
    ["_entity", objNull, [objNull]],
    ["_cluster", createHashMap, [createHashMap]]
];

if (isNull _entity) exitWith { _cluster };
if (!(_cluster isEqualType createHashMap)) exitWith { _cluster };

private _first = trim (_entity getVariable ["COMSPEC_SSE_FirstName", ""]);
private _last = trim (_entity getVariable ["COMSPEC_SSE_LastName", ""]);
private _alias = trim (_entity getVariable ["COMSPEC_SSE_Alias", ""]);
private _mode = toUpper (_entity getVariable ["comspec_sse_identityMode", "AUTO"]);

private _full = trim (format ["%1 %2", _first, _last]);
private _fromUnit = false;

private _useUnitName = (_full isEqualTo "")
    && {_entity isKindOf "CAManBase"}
    && {_mode isNotEqualTo "NONE"}
    && {(_mode isEqualTo "CUSTOM") || {!(_cluster getOrDefault ["nameForced", false])}};

if (_useUnitName) then {
    private _unitName = name _entity;
    if (_unitName isNotEqualTo "" && {(_unitName find "Error:") < 0}) then {
        _full = _unitName;
        _fromUnit = true;
        private _parts = _unitName splitString " ";
        if ((count _parts) > 1) then {
            _first = _parts select 0;
            _last = (_parts select [1, (count _parts) - 1]) joinString " ";
        } else {
            _first = _unitName;
            _last = "";
        };
    };
};

if (_full isNotEqualTo "") then {
    _cluster set ["primaryName", _full];
    _cluster set ["authoredFirst", _first];
    _cluster set ["authoredLast", _last];
    _cluster set ["authoredFromUnit", _fromUnit];
    if (!_fromUnit) then {
        _entity setVariable ["COMSPEC_SSE_NameAuthored", true, true];
    };
};

if (_alias isNotEqualTo "") then {
    _cluster set ["primaryAlias", _alias];
};

private _phoneAuth = trim (_entity getVariable ["comspec_sse_personPhone", ""]);
if (_phoneAuth isNotEqualTo "") then {
    _cluster set ["primaryPhone", _phoneAuth];
};

_cluster
