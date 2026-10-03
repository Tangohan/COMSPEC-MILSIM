/*
    Applique les réglages de réalisme imposés par la communauté (catalogue web « Contrôle serveur »)
    aux réglages roleplay d'Overwatch, pour que tous les joueurs aient les mêmes.
    Appelé quand l'expérience synchronisée change.
*/
{
    _x params ["_key", "_cba"];
    private _rule = [_key] call comspec_atak_native_fnc_tenantRule;
    if (_rule in ["on", "off"]) then {
        private _on = _rule isEqualTo "on";
        if ((missionNamespace getVariable [_cba, !_on]) isNotEqualTo _on) then {
            missionNamespace setVariable [_cba, _on];
            if (!isNil "CBA_settings_fnc_set") then { [_cba, _on, 2, "mission"] call CBA_settings_fnc_set; };
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
