/*
    Marqueurs posés à la main sur la carte Arma (canal global, _USER_DEFINED, Widget).
    À appeler à la fermeture de la carte et en rattrapage périodique.
*/
if (!hasInterface) exitWith { 0 };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { 0 };
if (!(missionNamespace getVariable ["comspec_overwatch_sync_map_markers", true])) exitWith { 0 };

private _n = 0;
{
    private _name = _x;
    if (_name isEqualTo "") then { continue };
    private _ul = toLower _name;
    private _isUser = (
        (_ul find "_user_defined") >= 0
        || {(_ul find "user_defined") >= 0}
        || {(_ul find "_defined #") >= 0}
        || {(_ul find "_ictab_defined") >= 0}
        || {(_ul find "ictab_defined") >= 0}
    );
    if (!_isUser) then { continue };
    if (isMultiplayer && {!isNil "comspec_overwatch_connect_fnc_userMapMarkerMeta"}) then {
        private _meta = [_name] call comspec_overwatch_connect_fnc_userMapMarkerMeta;
        private _own = _meta getOrDefault ["owner_id", -1];
        if (_own >= 0 && {_own isNotEqualTo clientOwner}) then { continue };
    };
    if ((abs ((markerPos _name) select 0) < 0.5) && {(abs ((markerPos _name) select 1) < 0.5)}) then { continue };
    if ([_name, false, true] call comspec_overwatch_connect_fnc_syncMapMarker) then {
        _n = _n + 1;
    };
} forEach (+allMapMarkers);

if (_n > 0 && {!isNil "comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers"}) then {
    [] call comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers;
};
_n
