/*
    Matérialise un document (SERVEUR) : prend une classe d'exemplaire libre et pose l'objet.
    [_docId, _where] call comspec_sse_fnc_idMaterialize -> classe ("" si stock épuisé)
      _where : personne (addItem là où elle est locale ; inventaire plein : au sol à ses pieds) ou conteneur
               (addItemCargoGlobal). objNull : classe réservée sans objet posé.
    Déjà matérialisé : renvoie sa classe sans rien poser (l'objet existe quelque part), sauf _move = true : l'objet est
    posé chez _where (kit Athena au respawn : l'exemplaire a été retiré de l'ancien corps).
*/
params [["_id", "", [""]], ["_where", objNull, [objNull]], ["_move", false, [false]]];
if (!isServer) exitWith { "" };
private _doc = ["doc", [_id]] call comspec_sse_fnc_idRegistry;
if ((count _doc) isEqualTo 0) exitWith { "" };
private _cls = _doc getOrDefault ["cls", ""];
if (_cls isNotEqualTo "" && {!_move}) exitWith { _cls };
if (_cls isEqualTo "") then {
    _cls = ["alloc", [_doc get "type"]] call comspec_sse_fnc_idRegistry;
    if (_cls isEqualTo "") exitWith {};
    _doc set ["cls", _cls];
    (comspec_sse_idReg get "byClass") set [_cls, _id];
};
if (_cls isEqualTo "") exitWith { "" };
if (isNull _where) exitWith { _cls };
// Porteur connu et chaîne de possession (API fn_idPaperOf).
["hold", [_doc, _where]] call comspec_sse_fnc_idPaperTrack;
["log", [_doc, "issued", [objNull, _where] select (_where isKindOf "CAManBase")]] call comspec_sse_fnc_idPaperTrack;
if (_where isKindOf "CAManBase") then {
    // Corps supprimé (nettoyage) : ses exemplaires reviennent au stock, les documents redeviennent virtuels.
    if !(_where getVariable ["comspec_sse_idDelEH", false]) then {
        _where setVariable ["comspec_sse_idDelEH", true];
        _where addEventHandler ["Deleted", {
            params ["_e"];
            {
                if ((count (["ofClass", [_x select 0]] call comspec_sse_fnc_idRegistry)) > 0) then { ["release", [_x select 0]] call comspec_sse_fnc_idRegistry; };
            } forEach ([_e, 0] call comspec_sse_fnc_idDocsOf);
        }];
    };
    // addItem : argument local. Évènement CBA vers la machine de l'unité (serveur, client ou HC).
    ["comspec_sse_idLocalAdd", [_where, _cls], _where] call CBA_fnc_targetEvent;
} else {
    _where addItemCargoGlobal [_cls, 1];
};
_cls
