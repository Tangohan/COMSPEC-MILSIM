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
// Choix du coin du mini : remet à zéro le décalage glissé du mini pour partir du nouveau coin.
private _wallNow = profileNamespace getVariable ["COMSPEC_ATAK_Wallpaper", "athena"];
private _anchorNow = toUpper (profileNamespace getVariable ["COMSPEC_ATAK_MiniAnchor", "BR"]);
private _anchorLabel = { params ["_t", "_k"]; [_t, format ["● %1", _t]] select (_k isEqualTo _anchorNow) };
uiNamespace setVariable ["COMSPEC_ATAK_SetAnchor", {
    params ["_k"];
    profileNamespace setVariable ["COMSPEC_ATAK_MiniAnchor", _k];
    { profileNamespace setVariable [_x, [0, 0]]; } forEach ["COMSPEC_ATAK_Offset_MINI_PORTRAIT", "COMSPEC_ATAK_Offset_MINI_LANDSCAPE"];
    saveProfileNamespace;
    [{ [] call comspec_atak_native_fnc_layoutApply; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];
private _rows = [
    ["title", "Téléphone"],
    ["toggle", "Vibrer à chaque nouveau message", ["COMSPEC_ATAK_Vibrate", true] call _prof, { (_this select 0) setVariable ["setting", "COMSPEC_ATAK_Vibrate"]; [_this select 0] call (uiNamespace getVariable "COMSPEC_ATAK_ProfToggle"); }],
    ["toggle", "Indicatifs sur la carte", ["COMSPEC_ATAK_Labels", true] call _prof, { (_this select 0) setVariable ["setting", "COMSPEC_ATAK_Labels"]; [_this select 0] call (uiNamespace getVariable "COMSPEC_ATAK_ProfToggle"); }],
    ["toggle", "Boussole sur la carte", ["COMSPEC_ATAK_Compass", true] call _prof, { (_this select 0) setVariable ["setting", "COMSPEC_ATAK_Compass"]; [_this select 0] call (uiNamespace getVariable "COMSPEC_ATAK_ProfToggle"); }],
    ["title", "Fond d'écran"],
    ["buttons", [["AUCUN", "none"], ["ATHENA", "athena"], ["OPS", "ops"]] apply {
        _x params ["_t", "_k"];
        [[_t, format ["● %1", _t]] select (_k isEqualTo _wallNow), compile format ["profileNamespace setVariable ['COMSPEC_ATAK_Wallpaper', '%1']; saveProfileNamespace; ['SETTINGS'] call comspec_atak_native_fnc_pageRender;", _k], _k isEqualTo _wallNow]
    }],
    ["title", "Emplacement du mini (porté et en main)"],
    ["buttons", [["HG", "TL"], ["HD", "TR"]] apply { [_x call _anchorLabel, compile format ["['%1'] call (uiNamespace getVariable 'COMSPEC_ATAK_SetAnchor');", _x select 1], (_x select 1) isEqualTo _anchorNow] }],
    ["buttons", [["MILIEU G", "ML"], ["MILIEU D", "MR"]] apply { [_x call _anchorLabel, compile format ["['%1'] call (uiNamespace getVariable 'COMSPEC_ATAK_SetAnchor');", _x select 1], (_x select 1) isEqualTo _anchorNow] }],
    ["buttons", [["BG", "BL"], ["BD", "BR"]] apply { [_x call _anchorLabel, compile format ["['%1'] call (uiNamespace getVariable 'COMSPEC_ATAK_SetAnchor');", _x select 1], (_x select 1) isEqualTo _anchorNow] }],
    ["buttons", [["REMETTRE LE TÉLÉPHONE EN PLACE", {
        { profileNamespace setVariable [_x, [0, 0]]; } forEach ["COMSPEC_ATAK_Offset_MINI_PORTRAIT", "COMSPEC_ATAK_Offset_MINI_LANDSCAPE", "COMSPEC_ATAK_Offset_FULL_LANDSCAPE"];
        saveProfileNamespace;
        [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    }]]],
    ["text", format ["Accès : %1 <t color='#8a9a93'>(réglage serveur CBA « COMSPEC ATAK natif »)</t>", ["libre, sans item", format ["item obligatoire (%1 objets reconnus)", count ([] call comspec_atak_native_fnc_deviceCatalog)]] select (missionNamespace getVariable ["comspec_atak_native_require_item", true])]],
    ["text", "<t color='#8a9a93'>Ctrl+U : sortir / ranger le téléphone porté (on continue à jouer).<br/>Ctrl+Maj+U : le prendre en main ou le reposer. En main, glissez la coque pour ajuster la position finement à partir du coin choisi.<br/>Touches modifiables dans les réglages CBA.</t>"]
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
