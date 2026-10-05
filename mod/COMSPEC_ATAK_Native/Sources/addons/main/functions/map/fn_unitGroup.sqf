/*
    Groupe d'une unité tel qu'enregistré dans Athena (rattachement ORBAT du profil),
    à défaut l'identifiant de groupe Arma (Alpha 2-2...).
    Joueur local : comspec_profile_unit ; autres joueurs : COMSPEC_ATAK_Orbat diffusé par leur téléphone.
    Params : [unité (player), abrégé pour l'affichage (false)]
    Avec l'abrégé : celui saisi dans Athena (COMSPEC_ATAK_OrbatShort) sinon fn_abbrev ; le nom complet
    reste la clé des ordres et des filtres.
*/
params [["_unit", player], ["_short", false]];
if (isNull _unit) exitWith { "" };
private _raw = if (_unit isEqualTo player) then { missionNamespace getVariable ["comspec_profile_unit", ""] } else { _unit getVariable ["COMSPEC_ATAK_Orbat", ""] };
if !(_raw isEqualType "") then { _raw = str _raw; };
_raw = trim _raw;
private _tenant = toLower trim str (missionNamespace getVariable ["comspec_tenant_name", ""]);
if (_raw in ["", "-", "<null>", "any"] || {(toLower _raw) isEqualTo _tenant} || {(_raw find "://") >= 0}) exitWith { groupId group _unit };
if (!_short) exitWith { _raw };
private _manual = if (_unit isEqualTo player) then { missionNamespace getVariable ["comspec_profile_unit_short", ""] } else { _unit getVariable ["COMSPEC_ATAK_OrbatShort", ""] };
if (_manual isEqualType "" && {trim _manual isNotEqualTo ""}) exitWith { trim _manual };
[_raw] call comspec_atak_native_fnc_abbrev
