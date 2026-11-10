/*
    « Joindre à une FRM » : fiche de renseignement (FRM) pré-remplie avec le contrôle en cours, ouverte dans l'app FRS
    de l'ATAK (même mécanisme que l'app SSE : uiNamespace COMSPEC_ATAK_FrsDraft puis navigation vers FRS).
    [_rec] call comspec_sse_fnc_idFrm
    Thèmes : PERSON, plus TERROR et INSURG pour une personne recherchée ou une HVT. Texte : 1 000 caractères au plus ;
    un brouillon déjà commencé est gardé, le contrôle est ajouté à la suite.
    Contenu : identité imprimée, document, verdict, examen visuel, lecture MRZ (N1), puce NFC (N2), base (N3), position
    (carroyage et coordonnées), heure, contrôleur, photo du document (fn_idPhoto) jointe aux pièces de la fiche ;
    en-tête de la fiche : carroyage, date et heure du contrôle, lieu.
*/
params [["_rec", createHashMap, [createHashMap]]];
if ((count _rec) isEqualTo 0) exitWith { false };
if (!isNil "comspec_atak_native_fnc_bridge" && {!([] call comspec_atak_native_fnc_bridge)}) exitWith {
    ["WARNING", "Fiches : COMSPEC Overwatch doit être chargé" call comspec_sse_fnc_tr, 3, 20, "IDCHECK"] call comspec_atak_native_fnc_notify;
    false
};
private _d = _rec get "doc";
private _v = _rec getOrDefault ["verdict", "ras"];
private _type = ["label", _d getOrDefault ["type", ""]] call comspec_sse_fnc_idDocTypes;
private _pos = _rec getOrDefault ["pos", getPosASL player];
private _callsign = if (isNil "comspec_overwatch_connect_fnc_getCallsign") then { "" } else { [] call comspec_overwatch_connect_fnc_getCallsign };
if (_callsign isEqualTo "") then { _callsign = groupId group player; };
private _date = _rec getOrDefault ["date", date];
private _dmy = format ["%1/%2/%3", [_date select 2, 2] call CBA_fnc_formatNumber, [_date select 1, 2] call CBA_fnc_formatNumber, _date select 0];
private _lines = [
    format ["Contrôle d'identité %1 au %2 : %3 %4, né(e) le %5 à %6.", _rec getOrDefault ["clock", ""], _rec getOrDefault ["grid", ""],
        toUpper (_d getOrDefault ["last", ""]), _d getOrDefault ["first", ""], _d getOrDefault ["dob", ""], _d getOrDefault ["pob", ""]],
    format ["Document : %1 %2 n° %3, expire le %4.", _type, _d getOrDefault ["country", ""], _d getOrDefault ["number", ""], _d getOrDefault ["expires", ""]],
    format ["Verdict : %1.", toUpper _v],
    format ["Contrôleur : %1 (%2), le %3 à %4, position %5 (%6, %7).", name player, _callsign, _dmy, _rec getOrDefault ["clock", ""], _rec getOrDefault ["grid", ""], round (_pos select 0), round (_pos select 1)]
];
{
    _x params ["_lvl", "_name"];
    private _r = (_rec get "levels") getOrDefault [_lvl, createHashMap];
    if ((count _r) > 0) then { _lines pushBack format ["%1 : %2", _name, (_r getOrDefault ["lines", []]) joinString " "]; } else {
        if (_lvl in [1, 2]) then { _lines pushBack format ["%1 : non effectuée.", _name]; };
    };
} forEach [[0, "N0 examen visuel"], [1, "N1 lecture MRZ"], [2, "N2 puce NFC"], [3, "N3 base"], [4, "N4 visage"]];
// Réponses aux questions de légende (Lot 4) ; PID confirmé (Lot 5).
private _talk = _rec getOrDefault ["talk", []];
if (_talk isNotEqualTo []) then { _lines pushBack format ["Réponses : %1", (_talk apply { format ["« %1 »", _x select 1] }) joinString " "]; };
if (_rec getOrDefault ["pid", false]) then { _lines pushBack "PID confirmé par le contrôleur."; };
private _photo = _rec getOrDefault ["photo", ""];
// Relevé du document (fn_idPhoto) : dans le texte de la fiche, pas en pièce jointe.
_lines append (_rec getOrDefault ["releve", []]);
// Ancien contrôle avec un vrai fichier image : pièce jointe comme avant.
if (_photo isNotEqualTo "" && {_photo isNotEqualTo "releve"}) then { _lines pushBack format ["Photo du document prise à %1 (pièce jointe).", _rec getOrDefault ["photoClock", ""]]; };
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
// En-tête de la fiche : où et quand (le contrôle, pas l'envoi).
private _grid8 = if (isNil "comspec_atak_native_fnc_gridRef") then { _rec getOrDefault ["grid", ""] } else { [_pos, 8] call comspec_atak_native_fnc_gridRef };
_draft set ["grid", _grid8];
_draft set ["date", format ["%1 %2", _dmy, _rec getOrDefault ["clock", ""]]];
private _loc = nearestLocations [ASLToAGL _pos, ["NameCityCapital", "NameCity", "NameVillage", "NameLocal"], 1500];
if ((trim (_draft getOrDefault ["place", ""])) isEqualTo "" && {_loc isNotEqualTo []}) then { _draft set ["place", format ["Contrôle d'identité, %1", text (_loc select 0)]]; };
// Photo du document : pièce jointe de la fiche (si elle n'y est pas déjà et qu'il reste de la place).
if (_photo isNotEqualTo "" && {_photo isNotEqualTo "releve"}) then {
    private _pieces = uiNamespace getVariable ["COMSPEC_ATAK_FrsPieces", []];
    if ((_pieces findIf { (_x select 1) isEqualTo _photo }) < 0 && {(count _pieces) < 4}) then {
        _pieces pushBack ["capture", _photo, format ["Document %1", toUpper (_d getOrDefault ["last", ""])], _grid8, "", format ["Contrôle d'identité %1", _rec getOrDefault ["clock", ""]]];
    };
    uiNamespace setVariable ["COMSPEC_ATAK_FrsPieces", _pieces];
};
private _case = _rec getOrDefault ["case", ""];
if (!isNil "comspec_overwatch_connect_fnc_sseActiveCase") then { private _c = ["get"] call comspec_overwatch_connect_fnc_sseActiveCase; if (_c isNotEqualTo "") then { _case = _c; }; };
if (_case isNotEqualTo "") then { _draft set ["case", _case]; };
// Contrôle à l'origine de la fiche (ATAK 3.0.2 et plus : id_check_ref) : le site rattache la fiche et ses photos au
// contrôle (référence CTRL-… une fois transmis, sinon clé d'idempotence du contrôle). Ignoré par un ATAK plus ancien.
private _ref = _rec getOrDefault ["ref", ""];
_draft set ["idCheck", [_rec getOrDefault ["idem", ""], _ref] select ((_ref select [0, 5]) isEqualTo "CTRL-")];
uiNamespace setVariable ["COMSPEC_ATAK_FrsDraft", _draft];
// Volets refermés : la fiche s'ouvre sur le texte (comme l'app SSE).
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_FrsUi", createHashMap];
{ _ui set [_x, false]; } forEach ["head", "pj", "fab"];
_ui set ["view", ""];
uiNamespace setVariable ["COMSPEC_ATAK_FrsUi", _ui];
["INFO", ("Fiche FRM pré-remplie avec le contrôle" call comspec_sse_fnc_tr), 3, 20, "FRS"] call comspec_atak_native_fnc_notify;
[{ ["FRS"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
true
