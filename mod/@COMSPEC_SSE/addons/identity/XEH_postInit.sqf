/*
    Contrôle d'identité : gestes locaux (toutes machines) et registre (serveur).
    Aucune boucle, aucun coût par image : tout part d'une action, d'un évènement ou d'une mort.
*/
// Ajout / retrait d'un exemplaire là où la personne est locale (addItem, removeItem : argument local).
["comspec_sse_idLocalAdd", {
    params [["_u", objNull], ["_cls", ""]];
    if (isNull _u || {!local _u} || {_cls isEqualTo ""}) exitWith {};
    if (alive _u && {_u canAdd _cls}) exitWith { _u addItem _cls; };
    // Corps : dans son uniforme ; plein, ou sans uniforme : au sol à ses pieds.
    if (!alive _u && {(uniform _u) isNotEqualTo ""}) then { _u addItemToUniform _cls; };
    if (_cls in (items _u)) exitWith {};
    private _h = createVehicle ["GroundWeaponHolder", getPosATL _u, [], 0.3, "CAN_COLLIDE"];
    _h addItemCargoGlobal [_cls, 1];
}] call CBA_fnc_addEventHandler;
["comspec_sse_idLocalRemove", {
    params [["_u", objNull], ["_cls", ""]];
    if (isNull _u || {!local _u}) exitWith {};
    _u removeItem _cls;
}] call CBA_fnc_addEventHandler;
// IA recherchée qui s'enfuit (fn_idAiAnswer) ; occupants qu'on fait descendre (fn_idServer « dismount »).
["comspec_sse_idAiFlee", {
    params [["_u", objNull], ["_to", []]];
    if (isNull _u || {!local _u} || {!alive _u}) exitWith {};
    _u allowFleeing 1;
    _u setUnitPos "UP";
    (group _u) setSpeedMode "FULL";
    _u doMove _to;
}] call CBA_fnc_addEventHandler;
["comspec_sse_idMoveOut", {
    params [["_u", objNull]];
    if (isNull _u || {!local _u} || {!alive _u}) exitWith {};
    [_u] allowGetIn false;
    unassignVehicle _u;
    moveOut _u;
}] call CBA_fnc_addEventHandler;

if (!isServer) exitWith {};
["init"] call comspec_sse_fnc_idRegistry;
// Paquet Athena (documents délivrés, avis de recherche) : une fois la liaison établie.
[{ ["load"] call comspec_sse_fnc_idAthenaPack; }, [], 15] call CBA_fnc_waitAndExecute;
// Mort : les papiers (encore virtuels) deviennent des objets sur le corps, trouvés par la fouille ou l'inventaire.
addMissionEventHandler ["EntityKilled", {
    params ["_u"];
    if (!(_u isKindOf "CAManBase") || {!(missionNamespace getVariable ["comspec_sse_id_enabled", true])}) exitWith {};
    // Joueur : ses papiers sont déjà des objets ; pas de papiers « d'IA » tirés sur son corps.
    if (isPlayer _u || {(getPlayerUID _u) isNotEqualTo ""}) exitWith {};
    [{
        params ["_u"];
        if (isNull _u) exitWith {};
        { [_x, _u] call comspec_sse_fnc_idMaterialize; } forEach (["unitDocs", [_u]] call comspec_sse_fnc_idRegistry);
    }, [_u], 1] call CBA_fnc_waitAndExecute;
}];
