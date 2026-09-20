/*
    Contrat unique Eden / module / attribut d’unité.

    Les attributs sur une personne et le module « Profil d’identité SSE » passent
    par ici. Sur un sujet : variables COMSPEC_SSE_* lues par SEEK. Sur un module :
    les mêmes noms, plus les noms courts (LastName, Preset…) que le module relit
    au lancement pour les recopier sur les personnes synchronisées.

    Params: [_obj, _field, _value]
      _field : LastName FirstName Alias Nationality Language RecordRef Seed Confidence Preset
*/
params [
    ["_obj", objNull, [objNull]],
    ["_field", "", [""]],
    ["_value", ""]
];

if (isNull _obj) exitWith { false };

if (!isNil "comspec_sse_fnc_edenWriteField") exitWith {
    [_obj, _field, _value] call comspec_sse_fnc_edenWriteField
};

if (!(_field isEqualType "") || {_field isEqualTo ""}) exitWith { false };

private _person = _obj isKindOf "CAManBase";
private _name = _field;
if ((toLower _name) isEqualTo "lastname") then { _name = "LastName"; };
if ((toLower _name) isEqualTo "firstname") then { _name = "FirstName"; };
if ((toLower _name) isEqualTo "recordref") then { _name = "RecordRef"; };
if ((toLower _name) isEqualTo "preset") then { _name = "Preset"; };
if ((toLower _name) isEqualTo "seed") then { _name = "Seed"; };
if ((toLower _name) isEqualTo "confidence") then { _name = "Confidence"; };

private _prefixed = format ["COMSPEC_SSE_%1", _name];

if (_name isEqualTo "Preset") exitWith {
    private _preset = if (_value isEqualType "") then { toLower (trim _value) } else { "auto" };
    if (_preset isEqualTo "") then { _preset = "auto"; };
    _obj setVariable ["Preset", _preset, true];
    _obj setVariable ["COMSPEC_SSE_Preset", _preset, true];
    if (_person && {_preset isNotEqualTo "auto"}) then {
        [_obj, ([_preset] call comspec_overwatch_connect_fnc_sseProfilePreset)] call comspec_overwatch_connect_fnc_sseApplyProfile;
    };
    true
};

if (_name isEqualTo "Seed") exitWith {
    if (!(_value isEqualType 0) || {_value <= 0}) exitWith { false };
    private _seed = round _value;
    if (!_person) then { _obj setVariable ["Seed", _seed, true]; };
    _obj setVariable ["COMSPEC_SSE_Seed", _seed, true];
    true
};

if (_name isEqualTo "Confidence") exitWith {
    if (!(_value isEqualType 0) || {_value < 0}) exitWith { false };
    _obj setVariable ["COMSPEC_SSE_Confidence", ((round _value) max 0) min 100, true];
    true
};

if (!(_value isEqualType "")) exitWith { false };
private _text = trim _value;
if (_text isEqualTo "") exitWith { false };

if (!_person) then { _obj setVariable [_name, _text, true]; };
_obj setVariable [_prefixed, _text, true];
if (_person && {_name in ["LastName", "FirstName"]}) then {
    _obj setVariable ["COMSPEC_SSE_NameAuthored", true, true];
};

true
