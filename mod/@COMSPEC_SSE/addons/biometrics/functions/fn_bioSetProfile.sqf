/*
    Réglage biométrique d'une personne (script, Zeus, Eden). Écrit les variables
    COMSPEC_SSE_Bio* lues par le moteur ; un champ vide n'est pas écrit.

    [_unit, [["db", "bewl1"], ["reason", "..."], ...]] call comspec_sse_fnc_bioSetProfile
      db              auto | absent | enrole | bewl1 | bewl2 | bewl3 | bewl4
      reason          motif de signalement affiché
      bioId           identifiant biométrique fixe (campagne)
      trueName        vraie identité si le sujet porte de faux papiers
      falsePapers     auto | oui | non
      relativeOf      nom de variable (ou identifiant) d'un parent
      relation        parent | enfant | frere
      missingFingers  ex. "2,7" (numérotation FBI 1-10)
      irisState       auto | cataracte | prothese_od | prothese_og | lesion_od | lesion_og
*/
params [["_unit", objNull, [objNull]], ["_pairs", [], [[]]]];
if (isNull _unit) exitWith { false };

private _map = createHashMapFromArray [
    ["db", "COMSPEC_SSE_BioDb"], ["reason", "COMSPEC_SSE_BioReason"], ["bioid", "COMSPEC_SSE_BioId"],
    ["truename", "COMSPEC_SSE_BioTrueName"], ["falsepapers", "COMSPEC_SSE_BioFalsePapers"],
    ["relativeof", "COMSPEC_SSE_BioRelativeOf"], ["relation", "COMSPEC_SSE_BioRelation"],
    ["missingfingers", "COMSPEC_SSE_BioMissingFingers"], ["irisstate", "COMSPEC_SSE_BioIrisState"]
];
{
    if ((_x isEqualType []) && {(count _x) >= 2}) then {
        _x params ["_k", "_v"];
        private _var = _map getOrDefault [toLower _k, ""];
        if (_var isNotEqualTo "" && {_v isEqualType ""}) then {
            private _t = trim _v;
            if (_t isEqualTo "" || {(toLower _t) isEqualTo "auto"}) then {
                _unit setVariable [_var, nil, true];
            } else {
                _unit setVariable [_var, _t, true];
            };
        };
    };
} forEach _pairs;
// Nouvel identifiant fixé : le registre réattribue la clé (et vérifie qu'elle n'est pas déjà prise).
if (isServer && {(_pairs findIf { (_x isEqualType []) && {(toLower (_x param [0, ""])) isEqualTo "bioid"} }) >= 0}) then {
    private _old = _unit getVariable ["COMSPEC_SSE_BioKey", ""];
    private _reg = missionNamespace getVariable ["comspec_sse_bioReg", createHashMap];
    if (_old isNotEqualTo "" && {(count _reg) > 0}) then {
        (_reg get "owners") deleteAt _old;
        private _tpl = _reg get "templates";
        { if ((_tpl getOrDefault [_x, ""]) isEqualTo _old) then { _tpl deleteAt _x; }; } forEach ([_old] call comspec_sse_fnc_bioTemplates);
    };
    _unit setVariable ["COMSPEC_SSE_BioKey", nil, true];
    ["assign", _unit] call comspec_sse_fnc_bioRegistry;
};
true
