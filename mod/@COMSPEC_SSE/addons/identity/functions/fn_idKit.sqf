/*
    Kit de papiers Athena d'un joueur (SERVEUR) : papiers listés pour son compte Athena (paquet, bloc « kits »).
    [_unit, _old] call comspec_sse_fnc_idKit -> nombre de papiers remis
      _unit  joueur (Steam ID : getPlayerUID, lu par le serveur)
      _old   ancien corps au respawn (objNull à la connexion, au JIP, au chargement du paquet)
    Liaison joueur <-> papiers : getPlayerUID = uid du kit (Steam ID du compte Athena). Joueur sans kit : rien ici
    (attribut Eden, module Zeus, comme avant).
    Pour chaque papier du kit qu'il ne porte pas déjà : au respawn, l'exemplaire resté sur l'ancien corps passe sur le
    nouveau (réglage « Rendre les papiers du kit au respawn ») ; exemplaire revenu au stock (corps supprimé, joueur
    reconnecté) : rematérialisé ; exemplaire ailleurs (saisi, jeté) ou jamais délivré : nouvel exemplaire du même papier.
    Registre : kits  uid -> [id Athena…]   kitDocs  "uid|id Athena" -> [docId…]   athenaById  id Athena -> "PAYS|type|numéro"
*/
params [["_u", objNull, [objNull]], ["_old", objNull, [objNull]]];
if (!isServer || {isNull _u} || {!alive _u} || {!(_u isKindOf "CAManBase")}) exitWith { 0 };
if !(missionNamespace getVariable ["comspec_sse_id_enabled", true]) exitWith { 0 };
private _uid = getPlayerUID _u;
if (_uid isEqualTo "") exitWith { 0 };
private _reg = ["init"] call comspec_sse_fnc_idRegistry;
private _kit = (_reg getOrDefault ["kits", createHashMap]) getOrDefault [_uid, []];
if (_kit isEqualTo []) exitWith { 0 };
// Ancien corps : seulement celui que ce joueur portait (marque serveur posée à la remise du kit), mort.
private _respawn = !isNull _old && {_old isNotEqualTo _u} && {!alive _old} && {(_old getVariable ["comspec_sse_idKitUid", ""]) isEqualTo _uid};
if (_respawn && {!(missionNamespace getVariable ["comspec_sse_id_kitRespawn", true])}) exitWith { 0 };
private _byId = _reg getOrDefault ["athenaById", createHashMap];
private _kitDocs = _reg getOrDefault ["kitDocs", createHashMap];
_reg set ["kitDocs", _kitDocs];
private _carried = ([_u, 0] call comspec_sse_fnc_idDocsOf) apply { _x select 0 };
private _onOld = if (_respawn) then { ([_old, 0] call comspec_sse_fnc_idDocsOf) apply { _x select 0 } } else { [] };
_u setVariable ["comspec_sse_idKitUid", _uid];
// Papiers du kit : l'automatique (IA, attribut Eden) ne s'ajoute plus par-dessus.
(_reg get "decided") set [[_u] call comspec_sse_fnc_bioPersonKey, true];
private _n = 0;
{
    private _aid = str _x;
    if (_x isEqualType "") then { _aid = _x; };
    private _k = _byId getOrDefault [_aid, ""];
    if (_k isEqualTo "") then { continue; };
    private _slot = format ["%1|%2", _uid, _aid];
    private _mine = (_kitDocs getOrDefault [_slot, []]) select { (count (["doc", [_x]] call comspec_sse_fnc_idRegistry)) > 0 };
    _kitDocs set [_slot, _mine];
    private _docs = _mine apply { ["doc", [_x]] call comspec_sse_fnc_idRegistry };
    // Déjà porté : rien à faire.
    if ((_docs findIf { ((_x get "cls") isNotEqualTo "") && {(_x get "cls") in _carried} }) >= 0) then { continue; };
    // Respawn : l'exemplaire de l'ancien corps passe sur le nouveau (pas de doublon sur le corps).
    private _i = if (_respawn) then { _docs findIf { ((_x get "cls") isNotEqualTo "") && {(_x get "cls") in _onOld} } } else { -1 };
    if (_i >= 0) then {
        ["comspec_sse_idLocalRemove", [_old, (_docs select _i) get "cls"], _old] call CBA_fnc_targetEvent;
        [(_docs select _i) get "id", _u, true] call comspec_sse_fnc_idMaterialize;
        _n = _n + 1;
        continue;
    };
    // Exemplaire revenu au stock (document redevenu virtuel) : rematérialisé chez le joueur.
    _i = _docs findIf { (_x get "cls") isEqualTo "" };
    if (_i >= 0) then {
        if (([(_docs select _i) get "id", _u] call comspec_sse_fnc_idMaterialize) isNotEqualTo "") then { _n = _n + 1; };
        continue;
    };
    // Jamais délivré, ou exemplaire ailleurs : nouvel exemplaire du papier Athena.
    private _spec = ["athenaSpec", _k] call comspec_sse_fnc_idModuleGive;
    if ((count _spec) isEqualTo 0) then { continue; };
    _spec set ["materialize", true];
    private _id = [_u, _spec] call comspec_sse_fnc_idIssue;
    if (_id isNotEqualTo "") then { _mine pushBack _id; _n = _n + 1; };
} forEach _kit;
// Identité active (réelle / couverture) et rôle de chaque papier porté : pour le menu ACE « Mes papiers » du joueur.
[{ ["push", [_this]] call comspec_sse_fnc_idActive; }, _u, 1] call CBA_fnc_waitAndExecute;
if (_n > 0) then {
    diag_log text format ["[COMSPEC SSE] Identité : kit Athena remis à %1 (%2 papier(s)%3).", name _u, _n, ["", ", respawn"] select _respawn];
    if (isPlayer _u) then {
        private _t = format ["Vos papiers de mission vous ont été remis (%1).", _n];
        if (local _u && {hasInterface}) then { ["notify", [_t, "INFO"]] call comspec_sse_fnc_idClient; } else { ["notify", [_t, "INFO"]] remoteExecCall ["comspec_sse_fnc_idClient", owner _u]; };
    };
};
_n
