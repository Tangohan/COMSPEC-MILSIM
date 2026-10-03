/*
    Réglages du téléphone, par sections : apparence, emplacement, carte, alertes, réalisme (communauté), accès et touches.
    Les réglages que la communauté impose depuis Athena (Contrôle serveur) s'affichent « IMPOSÉ » et ne changent pas d'ici.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _rerender = { [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
uiNamespace setVariable ["COMSPEC_ATAK_SettingsRerender", _rerender];

// Réglage profil (bascule) : [variable, défaut, clé communauté, libellé, aide]
uiNamespace setVariable ["COMSPEC_ATAK_ProfToggle", {
    params ["_var", "_def"];
    profileNamespace setVariable [_var, !(profileNamespace getVariable [_var, _def])];
    saveProfileNamespace;
    [{ [] call comspec_atak_native_fnc_layoutApply; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];
private _profSwitch = {
    params ["_var", "_def", "_tenantKey", "_label", ["_help", ""]];
    ([_var, _def, _tenantKey] call comspec_atak_native_fnc_pref) params ["_on", "_locked"];
    ["switch", _label, _on, compile format ["['%1', %2] call (uiNamespace getVariable 'COMSPEC_ATAK_ProfToggle');", _var, _def], _help, _locked]
};
// Choix exclusif enregistré dans le profil : [variable, valeur actuelle, [[texte, valeur]...]]
uiNamespace setVariable ["COMSPEC_ATAK_ProfSet", {
    params ["_var", "_val"];
    profileNamespace setVariable [_var, _val];
    saveProfileNamespace;
    [{ [] call comspec_atak_native_fnc_layoutApply; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];
private _profSegment = {
    params ["_var", "_def", "_label", "_opts", ["_help", ""]];
    private _cur = profileNamespace getVariable [_var, _def];
    ["segment", _label, _opts apply {
        _x params ["_t", "_v"];
        [_t, compile format ["['%1', %2] call (uiNamespace getVariable 'COMSPEC_ATAK_ProfSet');", _var, str _v], _v isEqualTo _cur]
    }, _help]
};
// Réglage roleplay Overwatch (CBA côté joueur) : verrouillé si la communauté ou le serveur l'impose.
uiNamespace setVariable ["COMSPEC_ATAK_CbaToggle", {
    params ["_k"];
    [_k, !(missionNamespace getVariable [_k, false]), false, "client"] call CBA_settings_fnc_set;
    [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];
private _cbaSwitch = {
    params ["_k", "_tenantKey", "_label", ["_help", ""], ["_d", false]];
    private _v = missionNamespace getVariable [_k, _d];
    if !(_v isEqualType true) then { _v = _d; };
    private _rule = [_tenantKey] call comspec_atak_native_fnc_tenantRule;
    private _locked = _rule in ["on", "off"];
    if (_locked) then { _v = _rule isEqualTo "on"; };
    ["switch", _label, _v, compile format ["['%1'] call (uiNamespace getVariable 'COMSPEC_ATAK_CbaToggle');", _k], _help, _locked]
};

// Emplacement du mini : remet à zéro le décalage glissé pour partir du nouveau coin.
uiNamespace setVariable ["COMSPEC_ATAK_SetAnchor", {
    params ["_k"];
    profileNamespace setVariable ["COMSPEC_ATAK_MiniAnchor", _k];
    { profileNamespace setVariable [_x, [0, 0]]; } forEach ["COMSPEC_ATAK_Offset_MINI_PORTRAIT", "COMSPEC_ATAK_Offset_MINI_LANDSCAPE"];
    saveProfileNamespace;
    [{ [] call comspec_atak_native_fnc_layoutApply; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];
private _anchorNow = toUpper (profileNamespace getVariable ["COMSPEC_ATAK_MiniAnchor", "BR"]);
private _anchorSeg = { params ["_pairs"]; _pairs apply { [_x select 0, compile format ["['%1'] call (uiNamespace getVariable 'COMSPEC_ATAK_SetAnchor');", _x select 1], (_x select 1) isEqualTo _anchorNow] } };

private _tenantName = missionNamespace getVariable ["comspec_tenant_name", ""];
private _tenantOn = (missionNamespace getVariable ["COMSPEC_TenantExperience", 0]) isEqualType createHashMap;
private _lockedCount = 0;
if (_tenantOn) then { { if ((_y isEqualType "") && {(toLower _y) in ["on", "off"]}) then { _lockedCount = _lockedCount + 1; }; } forEach (missionNamespace getVariable ["COMSPEC_TenantExperience", createHashMap]); };

private _rows = [
    ["section", "Apparence", "Coque, fond d'écran et taille du texte"],
    ["COMSPEC_ATAK_Shell", "auto", "Coque", [["AUTO", "auto"], ["JOUR", "day"], ["NUIT", "night"]], "auto : nuit après le coucher du soleil"] call _profSegment,
    ["COMSPEC_ATAK_Wallpaper", "topo", "Fond d'écran de l'accueil", [["TOPO", "topo"], ["NUIT", "night"], ["ATHENA", "athena"], ["OPS", "ops"], ["AUCUN", "none"]]] call _profSegment,
    ["COMSPEC_ATAK_TextScale", 1, "Taille du texte", [["PETIT", 0.9], ["NORMAL", 1], ["GRAND", 1.15]]] call _profSegment,

    ["section", "Emplacement", "Où le téléphone se place quand il est porté ou en mini"],
    ["COMSPEC_ATAK_MiniScale", 1, "Taille du mini", [["PETIT", 0.8], ["NORMAL", 1], ["GRAND", 1.2]]] call _profSegment,
    ["COMSPEC_ATAK_Orientation", "PORTRAIT", "Sens du mini", [["VERTICAL", "PORTRAIT"], ["HORIZONTAL", "LANDSCAPE"]]] call _profSegment,
    ["segment", "Coin de l'écran", [[["HAUT G", "TL"], ["MILIEU G", "ML"], ["BAS G", "BL"]]] call _anchorSeg],
    ["segment", "", [[["HAUT D", "TR"], ["MILIEU D", "MR"], ["BAS D", "BR"]]] call _anchorSeg],
    ["buttons", [["REMETTRE LE TÉLÉPHONE EN PLACE", {
        { profileNamespace setVariable [_x, [0, 0]]; } forEach ["COMSPEC_ATAK_Offset_MINI_PORTRAIT", "COMSPEC_ATAK_Offset_MINI_LANDSCAPE", "COMSPEC_ATAK_Offset_FULL_LANDSCAPE"];
        saveProfileNamespace;
        [{ [] call comspec_atak_native_fnc_layoutApply; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    }]]],

    ["section", "Carte", "Ce qui s'affiche sur la carte du téléphone"],
    ["COMSPEC_ATAK_Labels", true, "native_map_labels", "Indicatifs des unités", "Nom à côté de chaque symbole ami"] call _profSwitch,
    ["COMSPEC_ATAK_MarkerTags", true, "native_marker_tags", "Cartouches des repères", "Nom et coordonnées E / N sous le repère"] call _profSwitch,
    ["COMSPEC_ATAK_Compass", true, "native_compass", "Boussole", "Rose des vents et cap en haut à gauche"] call _profSwitch,
    ["COMSPEC_ATAK_AllyFilter", "ALL", "Alliés affichés", [["TOUS", "ALL"], ["MON GROUPE", "GROUP"], ["MON ORBAT", "ORBAT"]], ["", format ["ORBAT : %1", missionNamespace getVariable ["comspec_profile_unit", ""]]] select ((missionNamespace getVariable ["comspec_profile_unit", ""]) isNotEqualTo "")] call _profSegment,
    ["COMSPEC_ATAK_AllyColor", "BLUE", "Couleur des alliés", [["BLEU", "BLUE"], ["VERT", "GREEN"], ["BLANC", "WHITE"], ["JAUNE", "YELLOW"], ["ROSE", "PINK"]]] call _profSegment,
    ["COMSPEC_ATAK_SelfColor", "CYAN", "Ma couleur", [["CYAN", "CYAN"], ["BLEU", "BLUE"], ["VERT", "GREEN"], ["BLANC", "WHITE"], ["ORANGE", "ORANGE"]]] call _profSegment,
    ["COMSPEC_ATAK_SelfIcon", "", "Mon icône (vue par tous)", [["FLÈCHE", ""], ["INF", "b_inf"], ["RECO", "b_recon"], ["MÉDIC", "b_med"], ["PC", "b_hq"], ["SOUTIEN", "b_support"]]] call _profSegment,
    ["COMSPEC_ATAK_GridDigits", 6, "Précision des grilles", [["6 CHIFFRES", 6], ["8 CHIFFRES", 8], ["10 CHIFFRES", 10]], "100 m, 10 m ou 1 m"] call _profSegment,

    ["section", "Alertes", "Messages, notifications et vibration"],
    ["COMSPEC_ATAK_Notifications", true, "", "Bandeaux de notification", "Messages du poste, missions de tir, photos"] call _profSwitch,
    ["COMSPEC_ATAK_Vibrate", true, "native_vibrate", "Vibration", "À chaque nouveau message ou alerte"] call _profSwitch
];

if (_bridge) then {
    _rows append [
        ["section", "Réalisme", ["Réglages roleplay d'Overwatch", format ["%1 réglage(s) imposé(s) par %2 sur Athena", _lockedCount, ["votre communauté", _tenantName] select (_tenantName isNotEqualTo "")]] select (_lockedCount > 0)],
        ["comspec_overwatch_roleplay_enabled", "rp_enabled", "Mode roleplay", "Active les simulations ci-dessous"] call _cbaSwitch,
        ["comspec_overwatch_roleplay_network_failures", "rp_network_failures", "Coupures réseau", "Selon le terrain et les relais"] call _cbaSwitch,
        ["comspec_overwatch_roleplay_sensor_failures", "rp_sensor_failures", "Défauts des capteurs médicaux"] call _cbaSwitch,
        ["comspec_overwatch_roleplay_visual_effects", "rp_visual_effects", "Effets visuels de dégradation", "Écran parasité quand la liaison faiblit", true] call _cbaSwitch,
        ["comspec_overwatch_link_degrade_sim", "rp_link_degrade", "Liaison dégradée simulée", "Latence et coupures courtes"] call _cbaSwitch,
        ["comspec_overwatch_show_link_strip", "rp_data_bar", "Barre de données sous la carte", "Vitesse, altitude, cap, grille"] call _cbaSwitch
    ];
    private _dmg = ["atak_realism"] call comspec_atak_native_fnc_tenantRule;
    private _lvl = missionNamespace getVariable ["comspec_overwatch_atak_realism", 0];
    _rows pushBack ["info", "Dommages au téléphone", format ["%1%2", ["aucun", "peut s'éteindre", "écran destructible", "téléphone destructible"] param [_lvl, str _lvl], [" <t color='#e8b84a'>· imposé</t>", ""] select (_dmg isEqualTo "player")]];
    if !(_tenantOn) then { _rows pushBack ["text", "<t size='0.8' color='#8a9a93'>Réglages de la communauté pas encore reçus : connectez-vous à Athena.</t>"]; };
    _rows append [
        ["section", "Live cam", "Partage de ma vue vers le poste Overwatch"],
        ["COMSPEC_ATAK_LivecamShare", false, "native_livecam_share", "Partager ma caméra", "Une image de ma vue à intervalle régulier vers Overwatch beta (onglet Live cam)"] call _profSwitch,
        ["COMSPEC_ATAK_LivecamShareEvery", 15, "Cadence", [["10 S", 10], ["15 S", 15], ["30 S", 30], ["60 S", 60]]] call _profSegment
    ];
};
private _hp = [] call comspec_atak_native_fnc_deviceHealth;
private _lq = [] call comspec_atak_native_fnc_linkQuality;
_rows append [
    ["section", "Matériel et réseau", "Simulations réglées par le serveur (réglages CBA)"],
    ["info", "Dégâts du téléphone", [ "<t color='#8a9a93'>simulation coupée</t>", format ["%1 · usure %2 %%", ["<t color='#5cc76b'>intact</t>", format ["<t color='#f2ab33'>%1</t>", _hp get "reason"]] select ((_hp get "state") isNotEqualTo "OK"), round ((_hp get "damage") * 100)]] select (missionNamespace getVariable ["comspec_atak_native_damage_sim", true])],
    ["info", "Débit simulé", [ "<t color='#8a9a93'>simulation coupée</t>", format ["%1 · %2", _lq get "label", [format ["%1 kbit/s", _lq get "kbps"], format ["%1 Mbit/s", ((_lq get "kbps") / 1000) toFixed 1]] select ((_lq get "kbps") >= 1000)]] select (_lq get "sim")]
];

// Applications : chaque app (sauf Réglages) peut être cachée du lanceur et du dock.
uiNamespace setVariable ["COMSPEC_ATAK_AppHide", {
    params ["_id"];
    private _h = profileNamespace getVariable ["COMSPEC_ATAK_HiddenApps", []];
    if (_id in _h) then { _h = _h - [_id]; } else { _h pushBack _id; };
    profileNamespace setVariable ["COMSPEC_ATAK_HiddenApps", _h];
    saveProfileNamespace;
    [{ [] call comspec_atak_native_fnc_dockRender; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];
private _hidden = profileNamespace getVariable ["COMSPEC_ATAK_HiddenApps", []];
_rows pushBack ["section", "Applications", format ["%1 app(s) cachée(s) du lanceur", count _hidden]];
{
    private _id = _x get "id";
    if (_id isNotEqualTo "Settings" && {!((_x get "section") isEqualTo "Civil" && {!(missionNamespace getVariable ["comspec_atak_native_civil_apps", true])})}) then {
        _rows pushBack ["switch", format ["%1  <t size='0.8' color='#8a9a93'>%2</t>", _x get "name", _x get "section"], !(_id in _hidden), compile format ["['%1'] call (uiNamespace getVariable 'COMSPEC_ATAK_AppHide');", _id]];
    };
} forEach ([] call comspec_atak_native_fnc_appList);

private _rule = ["require_equipment"] call comspec_atak_native_fnc_tenantRule;
private _require = if (_rule in ["on", "off"]) then { _rule isEqualTo "on" } else { missionNamespace getVariable ["comspec_atak_native_require_item", true] };
_rows append [
    ["section", "Accès et touches", ""],
    ["info", "Objet requis", [["non", format ["oui · %1 objets reconnus", count ([] call comspec_atak_native_fnc_deviceCatalog)]] select _require, "imposé par la communauté"] select (_rule in ["on", "off"])],
    ["info", "Sortir / ranger (porté)", "Ctrl+U"],
    ["info", "Prendre en main / reposer", "Ctrl+Maj+U"],
    ["text", "<t size='0.8' color='#8a9a93'>En main, glissez la coque pour l'ajuster à partir du coin choisi. Touches modifiables dans les réglages CBA.</t>"]
];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
