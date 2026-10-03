/* Réglages du téléphone : affichage, carte, notifications et réglages roleplay d'Overwatch (CBA). */
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _prof = { params ["_k", "_d"]; profileNamespace getVariable [_k, _d] };
private _cba = { params ["_k", ["_d", false]]; private _v = missionNamespace getVariable [_k, _d]; if (_v isEqualType true) then { _v } else { _d } };
// Bascule d'un réglage CBA côté client (sans effet si le serveur le force).
private _cbaToggle = {
    params ["_c"];
    private _k = _c getVariable "setting";
    [_k, !(missionNamespace getVariable [_k, false]), false, "client"] call CBA_settings_fnc_set;
    [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
};
uiNamespace setVariable ["COMSPEC_ATAK_CbaToggle", _cbaToggle];
private _profToggle = {
    params ["_c"];
    private _k = _c getVariable "setting";
    profileNamespace setVariable [_k, !(profileNamespace getVariable [_k, true])];
    saveProfileNamespace;
    [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
};
uiNamespace setVariable ["COMSPEC_ATAK_ProfToggle", _profToggle];
private _rows = [
    ["title", "Téléphone"],
    ["toggle", "Vibrer à chaque nouveau message", ["COMSPEC_ATAK_Vibrate", true] call _prof, { (_this select 0) setVariable ["setting", "COMSPEC_ATAK_Vibrate"]; [_this select 0] call (uiNamespace getVariable "COMSPEC_ATAK_ProfToggle"); }],
    ["toggle", "Indicatifs sur la carte", ["COMSPEC_ATAK_Labels", true] call _prof, { (_this select 0) setVariable ["setting", "COMSPEC_ATAK_Labels"]; [_this select 0] call (uiNamespace getVariable "COMSPEC_ATAK_ProfToggle"); }],
    ["toggle", "Boussole sur la carte", ["COMSPEC_ATAK_Compass", true] call _prof, { (_this select 0) setVariable ["setting", "COMSPEC_ATAK_Compass"]; [_this select 0] call (uiNamespace getVariable "COMSPEC_ATAK_ProfToggle"); }],
    ["buttons", [["REMETTRE LE TÉLÉPHONE EN PLACE", {
        { profileNamespace setVariable [_x, [0, 0]]; } forEach ["COMSPEC_ATAK_Offset_MINI_PORTRAIT", "COMSPEC_ATAK_Offset_MINI_LANDSCAPE", "COMSPEC_ATAK_Offset_FULL_LANDSCAPE"];
        saveProfileNamespace;
        [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    }]]],
    ["text", "<t color='#8a9a93'>Ctrl+U : sortir / ranger le téléphone porté (on continue à jouer).<br/>Ctrl+Maj+U : le prendre en main ou le reposer. En main, glissez la coque pour le déplacer.<br/>Touches modifiables dans les réglages CBA.</t>"]
];
if (_bridge) then {
    _rows pushBack ["title", "Roleplay (Overwatch)"];
    {
        _x params ["_k", "_label", ["_d", false]];
        _rows pushBack ["toggle", _label, [_k, _d] call _cba, compile format ["(_this select 0) setVariable ['setting', '%1']; [_this select 0] call (uiNamespace getVariable 'COMSPEC_ATAK_CbaToggle');", _k]];
    } forEach [
        ["comspec_overwatch_roleplay_enabled", "Activer le mode roleplay"],
        ["comspec_overwatch_roleplay_network_failures", "Simulations réseau"],
        ["comspec_overwatch_roleplay_sensor_failures", "Défauts capteurs médicaux"],
        ["comspec_overwatch_roleplay_visual_effects", "Effets visuels de dégradation", true],
        ["comspec_overwatch_show_link_strip", "Barre de données sous la carte"],
        ["comspec_overwatch_link_degrade_sim", "Simulation de liaison dégradée"]
    ];
    _rows pushBack ["text", format ["Réalisme ATAK (dommages physiques) : niveau %1 <t color='#8a9a93'>(réglage CBA du serveur ou du joueur)</t>", missionNamespace getVariable ["comspec_overwatch_atak_realism", 0]]];
    _rows pushBack ["text", "<t color='#8a9a93'>Un réglage imposé par le serveur ne change pas d'ici.</t>"];
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
