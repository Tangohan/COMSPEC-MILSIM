/*
    Commande du poste web (COMSPEC Athena) reçue par le poll des charges (ligne charge_id "@wc").
    Params : [id (chaîne), fil]
      fil = "WC1~<kind>~<uid cible | *>~<cmd>~<réf>~clé=valeur~..."
        kind "uav"   : drone appairé au téléphone du pilote (réf = netId du drone) → fn_webCmdUav
        kind "explo" : charges ACE suivies, cible "*" = le téléphone du propriétaire (réf = ids séparés par ,) → fn_webCmdExplo
        kind "notif" : téléphone du joueur (préférences de notifications, tout marquer lu) → fn_webCmdNotif
    Chaque commande n'est traitée qu'une fois par client (le poll renvoie la même ligne tant que le
    compte rendu n'est pas arrivé au serveur). Retour : true si la commande est pour ce client.
*/
params [["_id", ""], ["_wire", ""]];
if (!hasInterface || {isNull player}) exitWith { false };
if (!(_id isEqualType "") || {_id isEqualTo ""} || {_id isEqualTo "-"}) exitWith { false };

private _seen = missionNamespace getVariable ["COMSPEC_WebCmdSeen", createHashMap];
if (!(_seen isEqualType createHashMap)) then { _seen = createHashMap; };
if (_id in _seen) exitWith { false };

private _parts = _wire splitString "~";
if ((count _parts) < 5 || {(_parts select 0) isNotEqualTo "WC1"}) exitWith { _seen set [_id, true]; missionNamespace setVariable ["COMSPEC_WebCmdSeen", _seen]; false };
_parts params ["", "_kind", "_target", "_cmd", "_ref"];
if (_ref isEqualTo "-") then { _ref = ""; };
private _args = createHashMap;
{
    private _i = _x find "=";
    if (_i > 0) then { _args set [_x select [0, _i], _x select [_i + 1]]; };
} forEach (_parts select [5]);

private _uid = getPlayerUID player;
// Cible nommée : seul ce joueur traite. Cible "*" : le gestionnaire décide (propriétaire de la charge).
if (_target isNotEqualTo "*" && {_target isNotEqualTo _uid}) exitWith { _seen set [_id, true]; missionNamespace setVariable ["COMSPEC_WebCmdSeen", _seen]; false };

private _mine = switch (_kind) do {
    case "uav": { [_id, _cmd, _ref, _args] call comspec_overwatch_connect_fnc_webCmdUav };
    case "explo": { [_id, _cmd, _ref, _args] call comspec_overwatch_connect_fnc_webCmdExplo };
    case "notif": { [_id, _cmd, _ref, _args] call comspec_overwatch_connect_fnc_webCmdNotif };
    default { false };
};
_seen set [_id, true];
// Mémoire bornée : on repart à zéro au-delà de 400 ids (les commandes expirent en quelques minutes côté serveur).
if ((count _seen) > 400) then { _seen = createHashMapFromArray [[_id, true]]; };
missionNamespace setVariable ["COMSPEC_WebCmdSeen", _seen];
_mine
