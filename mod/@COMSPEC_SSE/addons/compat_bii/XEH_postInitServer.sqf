/*
    Post-init serveur : enregistre matériel (alias) + hooks + import unités déjà seedées.
    Pas de scan allMissionObjects "ThingX" au démarrage (trop lourd / risqué sur S.O.A.R).
    Les preuves objet passent uniquement par les hooks BII.
*/
if !(missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]) exitWith {};
if !([] call comspec_sse_fnc_biiIsPresent) exitWith {};

try {
    [] call comspec_sse_fnc_biiRegisterEquipment;
    [] call comspec_sse_fnc_biiInstallHooks;
} catch {
    private _err = if (!isNil "_exception") then { str _exception } else { "unknown" };
    [format ["Passerelle BII Identifi: echec postInit serveur hooks (%1)", _err], "ERROR"] call comspec_sse_fnc_log;
};

// Import différé des unités déjà seedées par modules BII Identity (allUnits seulement)
[{
    if !(missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]) exitWith {};
    try {
        {
            private _hasName = !((_x getVariable ["BII_Identifi_name", ""]) isEqualTo "");
            private _hasAlias = !((_x getVariable ["BII_Identifi_alias", ""]) isEqualTo "");
            private _hasBioKey = !((_x getVariable ["BII_Identifi_bioKey", ""]) isEqualTo "");
            private _hasOrg = !((_x getVariable ["BII_Identifi_org", ""]) isEqualTo "");
            if (_hasName || {_hasAlias} || {_hasBioKey} || {_hasOrg}) then {
                [_x] call comspec_sse_fnc_biiImportEntityVars;
            };
        } forEach allUnits;
    } catch {
        private _err = if (!isNil "_exception") then { str _exception } else { "unknown" };
        [format ["Passerelle BII Identifi: echec import allUnits (%1)", _err], "ERROR"] call comspec_sse_fnc_log;
    };
}, [], 3] call CBA_fnc_waitAndExecute;
