/*
 * Identifiant Steam pour la liaison Athena.
 * Priorité : saisie manuelle (options du pack) > identifiant détecté en jeu.
 */
private _manual = missionNamespace getVariable ["COMSPEC_ATAK_steam_id", ""];
if (!(_manual isEqualType "")) then { _manual = ""; };
private _digits = "";
{
    if (_x >= 48 && {_x <= 57}) then { _digits = _digits + toString [_x]; };
} forEach (toArray _manual);
if ((count _digits) >= 16) exitWith { _digits };

private _detected = if (isNull player) then {""} else {getPlayerUID player};
if (!(_detected isEqualType "")) then { _detected = ""; };
_detected
