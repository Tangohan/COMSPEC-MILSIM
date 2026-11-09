/*
    Déplace un exemplaire (SERVEUR) : retiré de son porteur, ajouté à une personne (au sol à ses pieds si son
    inventaire est plein). removeItem / addItem ont un argument local : évènements CBA vers la machine de l'unité.
    [_cls, _from, _to] call comspec_sse_fnc_idTransfer -> Boolean
      _from : personne ou conteneur (le conteneur est vidé puis rechargé sans cet exemplaire : pas de retrait unitaire)
*/
params [["_cls", "", [""]], ["_from", objNull, [objNull]], ["_to", objNull, [objNull]]];
if (!isServer || {_cls isEqualTo ""} || {isNull _from} || {isNull _to}) exitWith { false };
if (_from isKindOf "CAManBase") then {
    ["comspec_sse_idLocalRemove", [_from, _cls], _from] call CBA_fnc_targetEvent;
} else {
    private _items = itemCargo _from;
    private _i = _items find _cls;
    if (_i < 0) exitWith {};
    _items deleteAt _i;
    clearItemCargoGlobal _from;
    { _from addItemCargoGlobal [_x, 1]; } forEach _items;
};
// Porteur connu et chaîne de possession (API fn_idPaperOf) : saisie, remise, rendu.
private _doc = ["ofClass", [_cls]] call comspec_sse_fnc_idRegistry;
["hold", [_doc, _to]] call comspec_sse_fnc_idPaperTrack;
["log", [_doc, "transfer", [objNull, _to] select (_to isKindOf "CAManBase")]] call comspec_sse_fnc_idPaperTrack;
if (_to isKindOf "CAManBase") then {
    ["comspec_sse_idLocalAdd", [_to, _cls], _to] call CBA_fnc_targetEvent;
} else {
    _to addItemCargoGlobal [_cls, 1];
};
true
