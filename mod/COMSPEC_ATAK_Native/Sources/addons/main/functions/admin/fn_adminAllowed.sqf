/*
    Ce joueur a-t-il l'app Admin en jeu (parc des ATAK de la partie, effets Zeus, debug de l'IA) ?
    Oui pour un Zeus (curateur assigné), l'hôte, un admin connecté au serveur (#login) et les gestionnaires désignés
    par le serveur (réglage comspec_atak_native_admin_uids : UID Steam séparés par des virgules).
    Sur le serveur : vérifie n'importe quelle unité (fait foi, fn_adminServer et fn_zeusServer). Sur un client : seulement
    le joueur, pour afficher l'app. Le parc Athena de l'app suit, lui, les droits du compte sur le site.
    Params : [unité]
*/
params [["_u", objNull]];
if (isNull _u || {!isPlayer _u && {_u isNotEqualTo player}}) exitWith { false };
if (!isNull (getAssignedCuratorLogic _u)) exitWith { true };
// Hôte d'une partie locale ou solo.
if (isServer && {hasInterface} && {_u isEqualTo player}) exitWith { true };
if (isServer && {(admin (owner _u)) > 0}) exitWith { true };
if (!isServer && {_u isEqualTo player} && {serverCommandAvailable "#kick"}) exitWith { true };
private _uids = missionNamespace getVariable ["comspec_atak_native_admin_uids", ""];
if !(_uids isEqualType "") exitWith { false };
(getPlayerUID _u) in ((_uids splitString ", ;") apply { trim _x })
