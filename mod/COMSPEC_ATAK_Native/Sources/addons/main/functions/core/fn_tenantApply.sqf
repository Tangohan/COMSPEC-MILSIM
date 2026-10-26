/*
    Applique les réglages de réalisme imposés par la communauté (catalogue web « Contrôle serveur »)
    aux réglages roleplay d'Overwatch, pour que tous les joueurs aient les mêmes.
    Appelé quand l'expérience synchronisée change, et quand CBA réécrit un de ces réglages (CBA_SettingChanged).
    Surcharge locale seulement : CBA_settings_fnc_set avec la source « mission » n'est accepté que dans l'éditeur 3DEN
    (refusé en partie, avertissement dans le RPT de chaque client).
*/
if (isNil { missionNamespace getVariable "COMSPEC_ATAK_TenantCbaEH" } && {!isNil "CBA_fnc_addEventHandler"}) then {
    missionNamespace setVariable ["COMSPEC_ATAK_TenantCbaEH", ["CBA_SettingChanged", {
        params [["_setting", ""]];
        if (_setting in ["comspec_overwatch_roleplay_enabled", "comspec_overwatch_roleplay_network_failures", "comspec_overwatch_roleplay_sensor_failures",
            "comspec_overwatch_roleplay_visual_effects", "comspec_overwatch_show_link_strip", "comspec_overwatch_link_degrade_sim"]) then {
            [{ [] call comspec_atak_native_fnc_tenantApply; }] call CBA_fnc_execNextFrame;
        };
    }] call CBA_fnc_addEventHandler];
};
{
    _x params ["_key", "_cba"];
    private _rule = [_key] call comspec_atak_native_fnc_tenantRule;
    if (_rule in ["on", "off"]) then {
        private _on = _rule isEqualTo "on";
        if ((missionNamespace getVariable [_cba, !_on]) isNotEqualTo _on) then {
            missionNamespace setVariable [_cba, _on];
        };
    };
} forEach [
    ["rp_enabled", "comspec_overwatch_roleplay_enabled"],
    ["rp_network_failures", "comspec_overwatch_roleplay_network_failures"],
    ["rp_sensor_failures", "comspec_overwatch_roleplay_sensor_failures"],
    ["rp_visual_effects", "comspec_overwatch_roleplay_visual_effects"],
    ["rp_data_bar", "comspec_overwatch_show_link_strip"],
    ["rp_link_degrade", "comspec_overwatch_link_degrade_sim"]
];
true
