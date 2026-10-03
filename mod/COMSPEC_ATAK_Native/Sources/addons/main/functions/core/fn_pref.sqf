/*
    Réglage d'affichage du téléphone : choix du joueur (profil), sauf si la communauté l'impose.
    Params : [variable profil, valeur par défaut, clé du catalogue web]
    Renvoie [valeur, imposé].
*/
params ["_var", ["_default", true], ["_tenantKey", ""]];
private _rule = if (_tenantKey isEqualTo "") then { "player" } else { [_tenantKey] call comspec_atak_native_fnc_tenantRule };
if (_rule in ["on", "off"]) exitWith { [_rule isEqualTo "on", true] };
[profileNamespace getVariable [_var, _default], false]
