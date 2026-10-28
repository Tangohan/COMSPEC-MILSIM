/*
    Tous les marqueurs de la carte vers le web (Athena), y compris ceux de la mission et ceux posés avec la carte Arma.
    COMSPEC Link ne relaie la carte entière que s'il voit son propre terminal : le téléphone natif s'en charge.
    Un seul relais par camp (le joueur équipé au plus petit identifiant, remplacé s'il part) envoie, par différence de signature,
    40 marqueurs au plus par passage ; les marqueurs disparus sont supprimés côté web. Appelé toutes les 15 s.
*/
if (isNil "comspec_overwatch_connect_fnc_syncMapMarker") exitWith {};
if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith {};
private _key = format ["COMSPEC_ATAK_MkRelay_%1", side group player];
// Relais qui n'a plus de téléphone : il libère la place (les autres le voyaient toujours valide et ne prenaient jamais
// le relais, la carte d'Athena ne bougeait plus pour tout le camp).
if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {
    if ((missionNamespace getVariable [_key, objNull]) isEqualTo player) then { missionNamespace setVariable [_key, objNull, true]; };
};
// Relais choisi de la même façon sur chaque poste : le joueur du camp, vivant et équipé, au plus petit identifiant
// (avant : le premier qui le réclamait par variable publique ; deux réclamations croisées laissaient chacun voir l'autre
// comme relais, et plus personne n'envoyait la carte). Seul l'élu écrit la variable (affichée par Resynch).
private _side = side group player;
private _cands = allPlayers select { alive _x && {!(_x isKindOf "HeadlessClient_F")} && {(side group _x) isEqualTo _side} && {[_x] call comspec_atak_native_fnc_hasDevice} };
private _uids = _cands apply { getPlayerUID _x };
private _sorted = +_uids;
_sorted sort true;
private _relay = if ((count _sorted) isEqualTo 0) then { player } else { _cands select (_uids find (_sorted select 0)) };
if (_relay isNotEqualTo player) exitWith {};
if ((missionNamespace getVariable [_key, objNull]) isNotEqualTo player) then { missionNamespace setVariable [_key, player, true]; };
// Aucun marqueur créé, modifié ou supprimé depuis le dernier passage complet (compteur COMSPEC_ATAK_MkRev) : rien à
// comparer (avant, chaque marqueur de la carte était relu et sérialisé toutes les 15 s).
// Passage mémorisé dans missionNamespace comme MkRev (uiNamespace survivait à la mission : après un redémarrage, MkRev
// repartait à 0, égal au passage gardé, et plus aucun marqueur de la mission n'était envoyé tant que rien ne changeait).
private _rev = missionNamespace getVariable ["COMSPEC_ATAK_MkRev", 0];
if ((missionNamespace getVariable ["COMSPEC_ATAK_MkSweepRev", -1]) isEqualTo _rev) exitWith {};
private _skip = ["poi_local_", "qrf_contact_", "medevac_lz_", "vehicle_service_", "comspec_tabletmk_", "comspec_webmk_", "comspec_shape_",
    "comspec_relay_", "comspec_recon_", "_comspec_po_ring_", "_comspec_det_ring_", "comspec_gps_", "ctab_u_"];
private _prev = uiNamespace getVariable ["COMSPEC_ATAK_MkSnap", createHashMap];
private _next = createHashMap;
private _budget = 40;
{
    private _n = _x;
    private _ln = toLower _n;
    if ((_skip findIf { (_ln find _x) isEqualTo 0 }) >= 0) then { continue };
    if ((_n select [0, 1]) isEqualTo "_" && {!([_n] call comspec_overwatch_connect_fnc_isSyncableMapMarker)}) then { continue };
    if ((markerShape _n) isNotEqualTo "POLYLINE" && {(markerType _n) isEqualTo ""} && {(markerShape _n) isEqualTo "ICON"}) then { continue };
    private _sig = str [markerPos _n, markerType _n, markerText _n, markerColor _n, markerDir _n, markerAlpha _n, markerShape _n, markerSize _n, markerBrush _n];
    if ((_prev getOrDefault [_n, ""]) isNotEqualTo _sig && {_budget > 0}) then {
        _budget = _budget - 1;
        [_n, false, true] call comspec_overwatch_connect_fnc_syncMapMarker;
        _next set [_n, _sig];
    } else {
        // Hors budget : renvoyé au prochain passage.
        if ((_prev getOrDefault [_n, ""]) isEqualTo _sig) then { _next set [_n, _sig]; };
    };
} forEach allMapMarkers;
// Marqueurs existants lus une fois (allMapMarkers recopiait toute la liste à chaque marqueur suivi).
private _alive = createHashMapFromArray (allMapMarkers apply { [_x, true] });
{
    if !(_x in _next) then {
        if (!(_x in _alive)) then { [_x, true, true] call comspec_overwatch_connect_fnc_syncMapMarker; } else { _next set [_x, _y]; };
    };
} forEach _prev;
uiNamespace setVariable ["COMSPEC_ATAK_MkSnap", _next];
// Passage complet (budget non épuisé) : le prochain n'a lieu qu'après un changement de marqueur.
missionNamespace setVariable ["COMSPEC_ATAK_MkSweepRev", [-1, _rev] select (_budget > 0)];
