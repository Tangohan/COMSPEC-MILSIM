/*
    « Joindre à une FRM » : fiche de renseignement (FRM) pré-remplie avec le contrôle en cours, ouverte dans l'app FRS
    de l'ATAK (même mécanisme que l'app SSE : uiNamespace COMSPEC_ATAK_FrsDraft puis navigation vers FRS).
    [_rec] call comspec_sse_fnc_idFrm
    Thèmes : PERSON, plus TERROR et INSURG pour une personne recherchée ou une HVT. Texte : 1 000 caractères au plus ;
    un brouillon déjà commencé est gardé, le contrôle est ajouté à la suite.
*/
params [["_rec", createHashMap, [createHashMap]]];
if ((count _rec) isEqualTo 0) exitWith { false };
if (!isNil "comspec_atak_native_fnc_bridge" && {!([] call comspec_atak_native_fnc_bridge)}) exitWith {
    ["WARNING", "Fiches : COMSPEC Overwatch doit être chargé" call comspec_sse_fnc_tr, 3, 20, "IDCHECK"] call comspec_atak_native_fnc_notify;
    false
};
private _d = _rec get "doc";
private _v = _rec getOrDefault ["verdict", "ras"];
private _type = ["Carte d'identité", "Passeport", "Permis de conduire"] select ((["id_card", "passport", "driving_license"] find (_d getOrDefault ["type", ""])) max 0);
private _lines = [
    format ["Contrôle d'identité %1 au %2 : %3 %4, né(e) le %5 à %6.", _rec getOrDefault ["clock", ""], _rec getOrDefault ["grid", ""],
        toUpper (_d getOrDefault ["last", ""]), _d getOrDefault ["first", ""], _d getOrDefault ["dob", ""], _d getOrDefault ["pob", ""]],
    format ["Document : %1 %2 n° %3, expire le %4.", _type, _d getOrDefault ["country", ""], _d getOrDefault ["number", ""], _d getOrDefault ["expires", ""]],
    format ["Verdict : %1.", toUpper _v]
];
{
    _x params ["_lvl", "_name"];
    private _r = (_rec get "levels") getOrDefault [_lvl, createHashMap];
    if ((count _r) > 0) then { _lines pushBack format ["%1 : %2", _name, (_r getOrDefault ["lines", []]) joinString " "]; };
} forEach [[0, "N0"], [1, "N1 MRZ"], [2, "N2 puce"], [3, "N3 base"]];
private _block = _lines joinString toString [10];

private _draft = uiNamespace getVariable ["COMSPEC_ATAK_FrsDraft", createHashMap];
private _body = trim (_draft getOrDefault ["body", ""]);
_body = if (_body isEqualTo "") then { _block } else { _body + toString [10, 10] + _block };
if ((count _body) > 1000) then { _body = (_body select [0, 997]) + "..."; };
_draft set ["body", _body];
_draft set ["kind", "FRM"];
private _themes = _draft getOrDefault ["themes", []];
if !(_themes isEqualType []) then { _themes = []; };
_themes pushBackUnique "PERSON";
if (_v in ["recherche", "hvt"]) then { _themes pushBackUnique "TERROR"; _themes pushBackUnique "INSURG"; };
_draft set ["themes", _themes];
private _case = _rec getOrDefault ["case", ""];
if (!isNil "comspec_overwatch_connect_fnc_sseActiveCase") then { private _c = ["get"] call comspec_overwatch_connect_fnc_sseActiveCase; if (_c isNotEqualTo "") then { _case = _c; }; };
if (_case isNotEqualTo "") then { _draft set ["case", _case]; };
uiNamespace setVariable ["COMSPEC_ATAK_FrsDraft", _draft];
// Volets refermés : la fiche s'ouvre sur le texte (comme l'app SSE).
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_FrsUi", createHashMap];
{ _ui set [_x, false]; } forEach ["head", "pj", "fab"];
_ui set ["view", ""];
uiNamespace setVariable ["COMSPEC_ATAK_FrsUi", _ui];
["INFO", ("Fiche FRM pré-remplie avec le contrôle" call comspec_sse_fnc_tr), 3, 20, "FRS"] call comspec_atak_native_fnc_notify;
[{ ["FRS"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
true
