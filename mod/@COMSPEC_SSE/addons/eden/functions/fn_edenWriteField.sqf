/*
    Contrat unique Eden — pack SSE autonome.

    Les attributs de la personne écrivent ici les variables COMSPEC_SSE_*
    lues par le terminal SEEK (nom, alias, verdict imposé). Même contrat
    que le module / les attributs Overwatch.

    Params: [_obj, _field, _value]
      _field : LastName FirstName Alias Nationality Language RecordRef Seed Confidence Preset
*/
params [
    ["_obj", objNull, [objNull]],
    ["_field", "", [""]],
    ["_value", ""]
];

if (isNull _obj) exitWith { false };
if (!(_field isEqualType "") || {_field isEqualTo ""}) exitWith { false };

// L'attribut s'exécute sur chaque machine (JIP compris) : seul le serveur diffuse et écrit la fiche,
// sinon un joueur arrivant en cours de partie réimposerait les valeurs d'origine (verdict, confiance…).
private _pub = isServer;

private _person = _obj isKindOf "CAManBase";
private _name = _field;
if ((toLower _name) isEqualTo "lastname") then { _name = "LastName"; };
if ((toLower _name) isEqualTo "firstname") then { _name = "FirstName"; };
if ((toLower _name) isEqualTo "recordref") then { _name = "RecordRef"; };
if ((toLower _name) isEqualTo "preset") then { _name = "Preset"; };
if ((toLower _name) isEqualTo "seed") then { _name = "Seed"; };
if ((toLower _name) isEqualTo "confidence") then { _name = "Confidence"; };

private _prefixed = format ["COMSPEC_SSE_%1", _name];

private _fnc_pushIdentity = {
    if (!_person || {!isServer}) exitWith {};
    if (isNil "comspec_sse_fnc_setIdentity") exitWith {};
    private _first = trim (_obj getVariable ["COMSPEC_SSE_FirstName", ""]);
    private _last = trim (_obj getVariable ["COMSPEC_SSE_LastName", ""]);
    private _alias = trim (_obj getVariable ["COMSPEC_SSE_Alias", ""]);
    private _nat = trim (_obj getVariable ["COMSPEC_SSE_Nationality", ""]);
    private _lang = trim (_obj getVariable ["COMSPEC_SSE_Language", ""]);
    private _pairs = [];
    if (_first isNotEqualTo "") then { _pairs pushBack ["first_name", _first]; };
    if (_last isNotEqualTo "") then { _pairs pushBack ["last_name", _last]; };
    if (_alias isNotEqualTo "") then { _pairs pushBack ["alias", _alias]; };
    if (_nat isNotEqualTo "") then { _pairs pushBack ["nationality", _nat]; };
    if (_lang isNotEqualTo "") then { _pairs pushBack ["language", _lang]; };
    private _full = trim (format ["%1 %2", _first, _last]);
    if (_full isNotEqualTo "") then { _pairs pushBack ["name", _full]; };
    if (_pairs isNotEqualTo []) then {
        [_obj, _pairs] call comspec_sse_fnc_setIdentity;
    };
};

if (_name isEqualTo "Preset") exitWith {
    private _preset = if (_value isEqualType "") then { toLower (trim _value) } else { "auto" };
    if (_preset isEqualTo "") then { _preset = "auto"; };
    _obj setVariable ["Preset", _preset, _pub];
    _obj setVariable ["COMSPEC_SSE_Preset", _preset, _pub];
    if (_person) then {
        _obj setVariable ["comspec_sse_enabled", true, _pub];
        if (isServer && {!isNil "comspec_sse_fnc_applyProfile"} && {_preset isNotEqualTo "auto"}) then {
            [_obj, ([_preset] call comspec_sse_fnc_profilePreset)] call comspec_sse_fnc_applyProfile;
        } else {
            switch (_preset) do {
                case "inconnu": {
                    _obj setVariable ["COMSPEC_SSE_MatchResult", "none", _pub];
                    _obj setVariable ["COMSPEC_SSE_Confidence", 0, _pub];
                };
                case "signale": {
                    _obj setVariable ["COMSPEC_SSE_MatchResult", "possible", _pub];
                    _obj setVariable ["COMSPEC_SSE_Confidence", 58, _pub];
                };
                case "recherche": {
                    _obj setVariable ["COMSPEC_SSE_MatchResult", "confirmed", _pub];
                    _obj setVariable ["COMSPEC_SSE_Confidence", 93, _pub];
                };
                default {
                    _obj setVariable ["COMSPEC_SSE_MatchResult", nil, _pub];
                    _obj setVariable ["COMSPEC_SSE_Confidence", nil, _pub];
                };
            };
        };
        call _fnc_pushIdentity;
    };
    true
};

if (_name isEqualTo "Seed") exitWith {
    if (!(_value isEqualType 0) || {_value <= 0}) exitWith { false };
    private _seed = round _value;
    if (!_person) then { _obj setVariable ["Seed", _seed, _pub]; };
    _obj setVariable ["COMSPEC_SSE_Seed", _seed, _pub];
    _obj setVariable ["COMSPEC_SSE_SeedAuthored", true, _pub];
    true
};

if (_name isEqualTo "Confidence") exitWith {
    if (!(_value isEqualType 0) || {_value < 0}) exitWith { false };
    _obj setVariable ["COMSPEC_SSE_Confidence", ((round _value) max 0) min 100, _pub];
    true
};

if (!(_value isEqualType "")) exitWith { false };
private _text = trim _value;
if (_text isEqualTo "") exitWith { false };

if (!_person) then { _obj setVariable [_name, _text, _pub]; };
_obj setVariable [_prefixed, _text, _pub];
if (_person) then {
    _obj setVariable ["comspec_sse_enabled", true, _pub];
    if (_name isEqualTo "Nationality") then {
        _obj setVariable ["comspec_sse_personNationality", _text, _pub];
    };
    if (_name isEqualTo "Language") then {
        _obj setVariable ["comspec_sse_personLanguage", _text, _pub];
    };
    if (_name in ["LastName", "FirstName"]) then {
        _obj setVariable ["COMSPEC_SSE_NameAuthored", true, _pub];
    };
    if (_name in ["LastName", "FirstName", "Alias", "Nationality", "Language"]) then {
        call _fnc_pushIdentity;
    };
};

true
