/*
    Pose un marqueur joueur sur le canal courant, comme la carte Arma : il est partagé avec le canal
    et Overwatch connect l'envoie à Athena (EH MarkerCreated).
*/
params ["_pos", ["_kind", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
// Palette : camp + type + couleur + taille choisis dans le mode MARQUEUR.
([] call comspec_atak_native_fnc_markerPaletteData) params ["_affs", "_typesBy"];
private _aff = _s getOrDefault ["markerAff", "o"];
private _type = _s getOrDefault ["markerType", ""];
if !(isClass (configFile >> "CfgMarkers" >> _type)) then { _type = ((_typesBy getOrDefault [_aff, []]) param [0, ["mil_dot"]]) select 0; };
private _affRow = _affs select ((_affs findIf { (_x select 0) isEqualTo _aff }) max 0);
private _color = _s getOrDefault ["markerColor", "AUTO"];
if (_color isEqualTo "AUTO" || {!isClass (configFile >> "CfgMarkerColors" >> _color)}) then { _color = _affRow select 2; };
private _label = ((_typesBy getOrDefault [_aff, []]) select { (_x select 0) isEqualTo _type }) param [0, ["", getText (configFile >> "CfgMarkers" >> _type >> "name")]] select 1;
if (_aff isEqualTo "lib") then { _label = ([] call comspec_atak_native_fnc_markerLabels) getOrDefault [toLower _type, getText (configFile >> "CfgMarkers" >> _type >> "name")]; };
private _prefix = if (_aff in ["mil", "lib"]) then { toUpper _label } else { format ["%1 %2", ["ENI", "AMI", "NEU", "INC"] select ((["o", "b", "n", "u"] find _aff) max 0), toUpper _label] };
private _size = _s getOrDefault ["markerSize", 1];
private _index = (missionNamespace getVariable ["COMSPEC_ATAK_MarkerIndex", 0]) + 1;
missionNamespace setVariable ["COMSPEC_ATAK_MarkerIndex", _index];
private _channel = currentChannel;
// Même schéma de nom que les marqueurs posés à la main : visible sur le canal, supprimable par le joueur.
private _name = format ["_USER_DEFINED #%1/%2/%3", clientOwner, 9000 + _index, _channel];
private _m = createMarker [_name, _pos, _channel, player];
if (_m isEqualTo "") exitWith {
    ["WARNING", "Marqueur refusé sur ce canal", 3, 20] call comspec_atak_native_fnc_notify;
    ""
};
_m setMarkerTypeLocal _type;
_m setMarkerColorLocal _color;
_m setMarkerSizeLocal [_size, _size];
_m setMarkerText format ["%1 %2", _prefix, _index];
["SUCCESS", format ["%1 %2 · %3", _prefix, _index, mapGridPosition _pos], 3, 20] call comspec_atak_native_fnc_notify;
if (_s getOrDefault ["markerEditAfter", false]) then { [_m] call comspec_atak_native_fnc_markerEditOpen; };
_m
