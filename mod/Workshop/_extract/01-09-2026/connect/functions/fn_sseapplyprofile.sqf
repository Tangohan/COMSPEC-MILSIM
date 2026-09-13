/*
    Applique un profil d'identité SSE à une unité.

    C'est le point d'entrée unique de la configuration mission maker : Eden, Zeus,
    ZEN et les scripts de mission passent tous par ici. Une seule fonction écrit les
    variables COMSPEC_SSE_*, donc un seul endroit à lire pour savoir ce qu'un chef
    de mission peut imposer.

    Les champs laissés vides ne sont pas écrits : un profil partiel complète la
    génération déterministe au lieu de l'écraser. C'est ce qui permet de ne forcer
    que l'alias d'un sujet sans avoir à réinventer sa nationalité et sa langue.

    Params: [_unit, _profile]
      _profile — tableau associatif souple, clés reconnues :
        "last_name" "first_name" "alias" "nationality" "language"
        "match"      → "none" | "possible" | "confirmed" | ""  (verdict imposé)
        "confidence" → 0-100, -1 pour laisser le calcul déterministe
        "record_ref" → référence de dossier affichée par le terminal
        "seed"       → graine ; 0 pour laisser dériver de l'identifiant réseau

    Returns: Bool
*/
params [["_unit", objNull, [objNull]], ["_profile", [], [[]]]];

if (isNull _unit) exitWith { false };

// Lecture souple : [["alias", "Le Meunier"], ...] ou ["alias", "Le Meunier", ...].
private _get = {
    params ["_key"];
    private _v = "";
    {
        if ((_x isEqualType []) && { (count _x) >= 2 } && { (_x select 0) isEqualType "" }) then {
            if ((toLower (_x select 0)) isEqualTo _key) exitWith { _v = _x select 1; };
        };
    } forEach _profile;
    _v
};

private _setText = {
    params ["_varName", "_key"];
    private _v = [_key] call _get;
    if (!(_v isEqualType "")) exitWith {};
    private _clean = trim _v;
    if (_clean isEqualTo "") exitWith {};
    _unit setVariable [_varName, _clean, true];
    if (_varName in ["COMSPEC_SSE_LastName", "COMSPEC_SSE_FirstName"]) then {
        _unit setVariable ["COMSPEC_SSE_NameAuthored", true, true];
    };
};

["COMSPEC_SSE_LastName",    "last_name"]   call _setText;
["COMSPEC_SSE_FirstName",   "first_name"]  call _setText;
["COMSPEC_SSE_Alias",       "alias"]       call _setText;
["COMSPEC_SSE_Nationality", "nationality"] call _setText;
["COMSPEC_SSE_Language",    "language"]    call _setText;
["COMSPEC_SSE_RecordRef",   "record_ref"]  call _setText;

// Les variables COMSPEC_SSE_* préremplissent le terminal. Sans ce pont, une
// identité déjà générée (lazy) garde le nom inventé : SEEK affiche Marc dudule
// et la fiche / la requête continuent de porter Ali Hassan.
if (!isNil "comspec_sse_fnc_setIdentity") then {
    private _first = trim (_unit getVariable ["COMSPEC_SSE_FirstName", ""]);
    private _last = trim (_unit getVariable ["COMSPEC_SSE_LastName", ""]);
    private _alias = trim (_unit getVariable ["COMSPEC_SSE_Alias", ""]);
    private _nat = trim (_unit getVariable ["COMSPEC_SSE_Nationality", ""]);
    private _lang = trim (_unit getVariable ["COMSPEC_SSE_Language", ""]);
    private _pairs = [];
    if (_first isNotEqualTo "") then { _pairs pushBack ["first_name", _first]; };
    if (_last isNotEqualTo "") then { _pairs pushBack ["last_name", _last]; };
    if (_alias isNotEqualTo "") then { _pairs pushBack ["alias", _alias]; };
    if (_nat isNotEqualTo "") then { _pairs pushBack ["nationality", _nat]; };
    if (_lang isNotEqualTo "") then { _pairs pushBack ["language", _lang]; };
    private _full = trim (format ["%1 %2", _first, _last]);
    if (_full isNotEqualTo "") then { _pairs pushBack ["name", _full]; };
    if (_pairs isNotEqualTo []) then {
        [_unit, _pairs] call comspec_sse_fnc_setIdentity;
    };
};

// Verdict imposé : seules trois valeurs ont un sens, tout le reste rend la main
// à la génération déterministe plutôt que d'afficher un état inconnu.
private _match = ["match"] call _get;
if (_match isEqualType "") then {
    private _m = toLower (trim _match);
    if (_m in ["none", "possible", "confirmed"]) then {
        _unit setVariable ["COMSPEC_SSE_MatchResult", _m, true];
    } else {
        if (_m isEqualTo "auto" || { _m isEqualTo "" }) then {
            _unit setVariable ["COMSPEC_SSE_MatchResult", nil, true];
        };
    };
};

private _confidence = ["confidence"] call _get;
if (_confidence isEqualType 0) then {
    if (_confidence >= 0) then {
        _unit setVariable ["COMSPEC_SSE_Confidence", (round _confidence) max 0 min 100, true];
    } else {
        _unit setVariable ["COMSPEC_SSE_Confidence", nil, true];
    };
};

private _seed = ["seed"] call _get;
if (_seed isEqualType 0 && { _seed > 0 }) then {
    _unit setVariable ["COMSPEC_SSE_Seed", round _seed, true];
};

true
