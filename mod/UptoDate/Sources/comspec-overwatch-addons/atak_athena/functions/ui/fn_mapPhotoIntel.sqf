/*
    Marqueur appareil pour une photo de renseignement, aperçu via le texte.
*/
params [["_world", []], ["_caption", "", [""]]];
if (!(_world isEqualType []) || {(count _world) < 2}) then { _world = getPos player; };
private _name = format ["COMSPEC_PHOTO_%1", round (diag_tickTime * 10)];
createMarkerLocal [_name, _world];
_name setMarkerTypeLocal "mil_unknown";
_name setMarkerColorLocal "ColorOrange";
_name setMarkerTextLocal (if (_caption isEqualTo "") then { "Photo" } else { _caption });
_name setMarkerSizeLocal [0.85, 0.85];
["INFO", "Photo placée sur la carte"] call comspec_overwatch_atak_athena_fnc_showNotification;
_name
