/*
    Tous les marqueurs de la carte vers le web (Athena), y compris ceux de la mission et ceux posés avec la carte Arma.
    Overwatch connect ne relaie la carte entière que s'il voit son propre terminal : le téléphone natif s'en charge.
    Un seul relais par camp (le premier joueur qui le réclame, remplacé s'il part) envoie, par différence de signature,
    40 marqueurs au plus par passage ; les marqueurs disparus sont supprimés côté web. Appelé toutes les 15 s.
*/
if (isNil "comspec_overwatch_connect_fnc_syncMapMarker") exitWith {};
if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith {};
if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
private _key = format ["COMSPEC_ATAK_MkRelay_%1", side group player];
private _relay = missionNamespace getVariable [_key, objNull];
if (isNull _relay || {!alive _relay} || {!isPlayer _relay} || {(side group _relay) isNotEqualTo (side group player)}) then {
    missionNamespace setVariable [_key, player, true];
    _relay = player;
};
if (_relay isNotEqualTo player) exitWith {};
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
{
    if !(_x in _next) then {
        if (!(_x in allMapMarkers)) then { [_x, true, true] call comspec_overwatch_connect_fnc_syncMapMarker; } else { _next set [_x, _y]; };
    };
} forEach _prev;
uiNamespace setVariable ["COMSPEC_ATAK_MkSnap", _next];
