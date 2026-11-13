/*
    Suivi des papiers en jeu (SERVEUR) : porteur connu et chaîne de possession, évènements CBA pour les autres mods.
    ["take", [unité, classe, conteneur]]   la personne a pris l'exemplaire (corps, sol, caisse) : évènement Take de son poste,
                                           relayé par fn_idServer « paperMoved » (poste vérifié, jamais cru sur parole)
    ["put",  [unité, classe, conteneur]]   elle l'a posé (sol, corps, caisse) : évènement Put
    ["hold", [document, porteur]]          porteur connu sans évènement (matérialisation, transfert, saisie)
    ["log",  [document, évènement, unité]] ligne de la chaîne de possession (20 dernières gardées)
    Évènements CBA globaux (tous les postes ; rien de la vérité de jeu) :
      comspec_sse_identity_paperPicked  [unité, classe, conteneur]   un papier relié au registre a été ramassé
      comspec_sse_identity_paperDropped [unité, classe, conteneur]   un papier relié au registre a été posé
    Les scripts du serveur lisent ensuite l'identité liée : [classe] call comspec_sse_fnc_idPaperOf.
    Rien par image : seulement à l'évènement. Déplacement entre ses propres affaires (uniforme, gilet, sac) : ignoré.
*/
params [["_op", "", [""]], ["_a", []]];
if (!isServer) exitWith { false };
private _log = {
    params ["_d", "_ev", "_who"];
    private _c = _d getOrDefault ["custody", []];
    _c pushBack [round time, _ev, [name _who, ""] select (isNull _who), [getPlayerUID _who, ""] select (isNull _who)];
    if ((count _c) > 20) then { _c deleteAt 0; };
    _d set ["custody", _c];
};
switch (_op) do {
    case "log": {
        _a params [["_d", createHashMap], ["_ev", ""], ["_who", objNull]];
        if ((count _d) isEqualTo 0) exitWith { false };
        [_d, _ev, _who] call _log;
        true
    };
    case "hold": {
        _a params [["_d", createHashMap], ["_h", objNull]];
        if ((count _d) isEqualTo 0) exitWith { false };
        _d set ["holder", _h];
        true
    };
    case "take";
    case "put": {
        _a params [["_u", objNull, [objNull]], ["_cls", "", [""]], ["_box", objNull, [objNull]]];
        if (isNull _u || {(_cls select [0, 11]) isNotEqualTo "COMSPEC_ID_"}) exitWith { false };
        // Ses propres affaires : pas un ramassage.
        if (!isNull _box && {_box in [_u, uniformContainer _u, vestContainer _u, backpackContainer _u]}) exitWith { false };
        private _d = ["ofClass", [_cls]] call comspec_sse_fnc_idRegistry;
        if ((count _d) isEqualTo 0) exitWith { false };
        _d set ["holder", [_box, _u] select (_op isEqualTo "take" || {isNull _box})];
        [_d, _op, _u] call _log;
        private _owner = _d getOrDefault ["ownerUid", ""];
        if (_op isEqualTo "take" && {_owner isNotEqualTo ""} && {(getPlayerUID _u) isNotEqualTo _owner}) then {
            diag_log text format ["[COMSPEC SSE] Identité : %1 a ramassé le papier %2 de %3.", name _u, _d get "id", _d getOrDefault ["ownerName", _owner]];
        };
        [["comspec_sse_identity_paperDropped", "comspec_sse_identity_paperPicked"] select (_op isEqualTo "take"), [_u, _cls, _box]] call CBA_fnc_globalEvent;
        true
    };
    default { false };
}
