/*
    Papier saisi : pièce à conviction du module evidence, avec sa chaîne de possession (SERVEUR).
    ["seize",  [_doc, _subject, _by]]   crée la pièce (COMSPEC_SSE_Obj_Documents masqué, un par papier) rattachée au dossier
    ["return", [_doc, _subject, _by]]   inscrit la restitution au journal de la pièce
    Le dossier (fn_getCaseReference) donne le numéro de la pièce (fn_custody). La fiche de l'objet garde le papier
    imprimé (section identityDoc), celle de la personne la référence de la pièce (section seizedDocs) : les deux partent
    vers le portail avec la fiche. Jamais la vérité du papier (nature, vraie identité).
*/
params [["_op", "", [""]], ["_args", [], [[]]]];
if (!isServer || {isNil "comspec_sse_fnc_custody"}) exitWith { "" };
_args params [["_doc", createHashMap, [createHashMap]], ["_subject", objNull, [objNull]], ["_by", objNull, [objNull]]];
if ((count _doc) isEqualTo 0) exitWith { "" };
private _carrier = _doc getOrDefault ["pieceObject", objNull];

if (_op isEqualTo "return") exitWith {
    if (isNull _carrier) exitWith { "" };
    ["event", [_carrier, "RESTITUTION", _by, format ["rendu à %1", [name _subject, "?"] select (isNull _subject)]]] call comspec_sse_fnc_custody
};
if (_op isNotEqualTo "seize") exitWith { "" };

private _label = format ["%1 %2 n° %3 au nom de %4 %5", _doc getOrDefault ["type", "?"], _doc getOrDefault ["country", "?"],
    _doc getOrDefault ["number", "?"], toUpper (_doc getOrDefault ["last", ""]), _doc getOrDefault ["first", ""]];
// Saisi de nouveau après restitution : même pièce, la chaîne continue.
if (!isNull _carrier) exitWith { ["event", [_carrier, "SAISIE", _by, _label]] call comspec_sse_fnc_custody };

// comspec_sse_siteManaged : pas de contenu généré au hasard (fn_evidenceObjInit) ; la pièce suit le papier, sans décor.
private _pos = if (isNull _by) then { getPosATL _subject } else { getPosATL _by };
_carrier = createVehicle ["COMSPEC_SSE_Obj_Documents", _pos, [], 0, "CAN_COLLIDE"];
_carrier setVariable ["comspec_sse_siteManaged", true, true];
_carrier enableSimulationGlobal false;
_carrier hideObjectGlobal true;
_carrier setVariable ["COMSPEC_SSE_PieceDesc", _label];
_doc set ["pieceObject", _carrier];

private _case = if (isNil "comspec_sse_fnc_getCaseReference") then { "" } else { [] call comspec_sse_fnc_getCaseReference };
[_carrier, "identityDoc", createHashMapFromArray [
    ["type", _doc getOrDefault ["type", ""]], ["country", _doc getOrDefault ["country", ""]], ["number", _doc getOrDefault ["number", ""]],
    ["last", _doc getOrDefault ["last", ""]], ["first", _doc getOrDefault ["first", ""]], ["dob", _doc getOrDefault ["dob", ""]],
    ["expires", _doc getOrDefault ["expires", ""]], ["case", _case]
], true] call comspec_sse_fnc_setSection;

private _id = ["open", [_carrier, _by, ""]] call comspec_sse_fnc_custody;
if (!isNull _subject) then {
    private _seized = [_subject, "seizedDocs"] call comspec_sse_fnc_getSection;
    if (isNil "_seized" || {!(_seized isEqualType [])}) then { _seized = []; };
    _seized pushBack [_id, _label, _case];
    [_subject, "seizedDocs", _seized, true] call comspec_sse_fnc_setSection;
};
_id
