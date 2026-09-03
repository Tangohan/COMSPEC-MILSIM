/*
    Applique filtres et couches aux marqueurs COMSPEC (pas IceMan 46600).
*/
if (!hasInterface) exitWith {};
private _layers = missionNamespace getVariable ["COMSPEC_MapLayers", createHashMap];
if (!(_layers isEqualType createHashMap)) then { _layers = createHashMap; };
private _filter = missionNamespace getVariable ["COMSPEC_MapFilter", "ALL"];
private _grp = group player;

{
    private _name = _x;
    private _low = toLower _name;
    private _txt = toLower (markerText _name);
    private _isComspec = (_low find "comspec") >= 0 || {(_low find "_comspec") >= 0};
    if (!_isComspec) then { continue };

    private _kind = "player_markers";
    if ((_low find "photo") >= 0) then { _kind = "photos"; };
    if ((_low find "intel") >= 0 || {(_low find "ping") >= 0} || {(_low find "sitrep") >= 0}) then { _kind = "intel"; };
    if ((_low find "jtac") >= 0 || {(_low find "nine") >= 0} || {(_low find "laser") >= 0}) then { _kind = "jtac"; };
    if ((_low find "cas") >= 0) then { _kind = "cas"; };
    if ((_low find "zone") >= 0 || {(_low find "obj") >= 0}) then { _kind = "objectives"; };
    if ((_low find "route") >= 0) then { _kind = "athena"; };
    if ((_low find "sigint") >= 0) then { _kind = "sigint"; };
    if ((_low find "log") >= 0) then { _kind = "logistics"; };

    private _on = _layers getOrDefault [_kind, true];
    if (_filter isEqualTo "INTEL" && {!(_kind in ["intel", "photos", "athena"])}) then { _on = false; };
    if (_filter isEqualTo "AIR" && {!(_kind in ["cas", "jtac"])}) then { _on = false; };
    if (_filter isEqualTo "JTAC" && {!(_kind in ["jtac", "cas"])}) then { _on = false; };
    if (_filter isEqualTo "MY GROUP" && {_isComspec}) then {
        if ((_txt find (toLower (groupId _grp))) < 0) then { _on = false; };
    };
    _name setMarkerAlphaLocal ([0.08, 1] select _on);
} forEach allMapMarkers;
