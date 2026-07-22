/*
    Crée un marqueur carte à la position pointée depuis la vue radar de la tablette
    (double-clic dans web/tablet.html, protocole marker:place|wx|wy).
    Marqueur Arma standard (createMarker, global) : la synchronisation vers Athena se fait
    automatiquement via l'event handler MarkerCreated déjà enregistré (XEH_postInit.sqf →
    fn_syncMapMarker), pas besoin de la déclencher ici.
    Params: [_wx, _wy]
*/
params [["_wx", 0, [0]], ["_wy", 0, [0]]];
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if (_wx isEqualTo 0 && {_wy isEqualTo 0}) exitWith {};

private _z = getTerrainHeightASL [_wx, _wy];
private _callSign = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_callSign isEqualTo "") then { _callSign = name player; };

private _name = format ["comspec_tabletmk_%1_%2", round (diag_tickTime * 10), floor (random 100000)];
private _marker = createMarker [_name, [_wx, _wy, _z]];
_marker setMarkerType "mil_dot";
_marker setMarkerColor "ColorRed";
_marker setMarkerText _callSign;
_marker setMarkerAlpha 1;

["COMSPEC_Info", [format ["Marqueur posé depuis la tablette — %1", _callSign]]] call comspec_overwatch_connect_fnc_showNotification;
[format ["[Marqueur] %1 a posé un marqueur depuis la tablette", _callSign], "system"] call comspec_overwatch_connect_fnc_appendLinkLog;
