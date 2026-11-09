// Menus ACE (une fois par classe) et modules Zeus du contrôle d'identité.
if (!hasInterface) exitWith {};
[] call comspec_sse_fnc_initAceIdentity;
[] call comspec_sse_fnc_idZenRegister;
// BOLO / HVT diffusé par le commandement (fn_idHvt) : notification ATAK, onglet BOLO de Contrôle ID, app Recherchés.
["comspec_sse_idBoloNew", {
    params [["_t", ""]];
    [{ ["wantedSync"] call comspec_sse_fnc_idHvt; }, [], 1] call CBA_fnc_waitAndExecute;
    if (_t isEqualTo "" || {!(missionNamespace getVariable ["comspec_sse_id_boloNotify", true])}) exitWith {};
    [_t, "WARNING", "", "IDCHECK"] call comspec_sse_fnc_notify;
}] call CBA_fnc_addEventHandler;
// Kit Athena (papiers du compte, bloc « kits » du paquet) : demandé au serveur à l'arrivée (connexion, JIP) et après
// chaque respawn ; le serveur lit lui-même le Steam ID et ne remet que les papiers manquants (fn_idKit).
[{ !isNull player && {alive player} && {(getPlayerUID player) isNotEqualTo ""} }, {
    ["send", ["kit", [player, objNull]]] call comspec_sse_fnc_idClient;
    // Couverture importée d'un fichier (app Ma couverture) : le registre du serveur ne garde rien d'une mission à l'autre.
    ["resend"] call comspec_sse_fnc_idCoverImport;
}, [], 300] call CBA_fnc_waitUntilAndExecute;
// Papier pris ou posé par ce joueur (corps, sol, caisse) : le serveur tient le porteur et la chaîne de possession, puis
// diffuse comspec_sse_identity_paperPicked / paperDropped (fn_idPaperTrack). Seulement les exemplaires (« …_007 »).
comspec_sse_idPaperMovedLocal = {
    params ["_how", "_u", "_box", "_item"];
    if (_u isNotEqualTo player || {!(_item isEqualType "")} || {(_item select [0, 11]) isNotEqualTo "COMSPEC_ID_"}) exitWith {};
    if !(((_item splitString "_") select -1) regexMatch "[0-9]+") exitWith {};
    ["send", ["paperMoved", [player, _item, _box, _how]]] call comspec_sse_fnc_idClient;
};
["CAManBase", "Take", { (["take"] + _this) call comspec_sse_idPaperMovedLocal; }] call CBA_fnc_addClassEventHandler;
["CAManBase", "Put", { (["put"] + _this) call comspec_sse_idPaperMovedLocal; }] call CBA_fnc_addClassEventHandler;
// App Ma couverture : fiche gardée en cache, marquée périmée sur évènement au lieu d'être relue en boucle (fn_idCoverPage).
// Équipement, arme en main, véhicule et unité changent la cohérence ou les papiers portés : drapeau seulement, la fiche
// est relue à la prochaine ouverture de la page. Paquet Athena rechargé par le serveur : la page ouverte se relit.
{ [_x, { missionNamespace setVariable ["comspec_sse_idCoverDirty", true]; }] call CBA_fnc_addPlayerEventHandler; } forEach ["loadout", "weapon", "vehicle", "unit"];
["comspec_sse_idCoverDirty", { ["act", "dirty", "server"] call comspec_sse_fnc_idCoverPage; }] call CBA_fnc_addEventHandler;
["CAManBase", "Respawn", {
    params ["_new", "_old"];
    if (!local _new) exitWith {};
    [{
        params ["_new", "_old"];
        if (_new isEqualTo player && {alive _new}) then { ["send", ["kit", [_new, _old]]] call comspec_sse_fnc_idClient; missionNamespace setVariable ["comspec_sse_idCoverImportSent", false]; ["resend"] call comspec_sse_fnc_idCoverImport; };
    }, [_new, _old], 2] call CBA_fnc_waitAndExecute;
}] call CBA_fnc_addClassEventHandler;
