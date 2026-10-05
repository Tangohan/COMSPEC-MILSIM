/*
    Le joueur a-t-il le droit d'utiliser le téléphone ?
    - Réglage admin « item obligatoire » coupé : toujours oui.
    - Équipage d'aéronef (pilote, copilote, tourelle) : oui, par la tablette de bord de l'appareil.
    - Sinon il faut porter un des objets du catalogue (équipé ou dans l'inventaire).
    - Si aucun mod chargé ne fournit d'objet téléphone et que l'admin n'en a pas listé, on laisse passer
      (sinon personne ne pourrait l'ouvrir) et on le signale dans le journal.
*/
params [["_unit", player]];
if (isNull _unit) exitWith { false };
// Pilote, copilote ou tourelle d'un aéronef : tablette de bord (réglage serveur « ATAK pour les équipages d'aéronef »).
if ([_unit] call comspec_atak_native_fnc_aircrewTerminal) exitWith { true };
// La communauté (Athena, « Exiger une tablette ou un GPS ») passe avant le réglage serveur CBA.
private _rule = ["require_equipment"] call comspec_atak_native_fnc_tenantRule;
private _require = if (_rule in ["on", "off"]) then { _rule isEqualTo "on" } else { missionNamespace getVariable ["comspec_atak_native_require_item", true] };
if !(_require) exitWith { true };
private _catalog = [] call comspec_atak_native_fnc_deviceCatalog;
if ((count _catalog) isEqualTo 0) exitWith {
    if (isNil "COMSPEC_ATAK_NoDeviceWarned") then { COMSPEC_ATAK_NoDeviceWarned = true; ["WARN", "DEVICE", "Item obligatoire mais aucun objet téléphone chargé : accès laissé libre."] call comspec_atak_native_fnc_log; };
    true
};
(((assignedItems _unit) + (items _unit)) findIf { (toLower _x) in _catalog }) >= 0
