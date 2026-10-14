/*
    Réglages du téléphone, par catégories (accueil puis une page par catégorie) : personnalisation, emplacement, carte,
    alertes, applications, matériel et réseau, réalisme (communauté), accès et touches.
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
// Choix exclusif enregistré en réglage CBA côté joueur (étiquettes de la carte) : le même que dans le menu CBA.
uiNamespace setVariable ["COMSPEC_ATAK_CbaSet", {
    params ["_k", "_v"];
    [_k, _v, false, "client"] call CBA_settings_fnc_set;
    [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];
private _cbaSegment = {
    params ["_k", "_def", "_label", "_opts", ["_help", ""]];
    private _cur = missionNamespace getVariable [_k, _def];
    ["segment", _label, _opts apply {
        _x params ["_t", "_v"];
        [_t, compile format ["['%1', %2] call (uiNamespace getVariable 'COMSPEC_ATAK_CbaSet');", _k, str _v], _v isEqualTo _cur]
    }, _help]
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

// Personnalisation : accent, densité des icônes, dock choisi.
uiNamespace setVariable ["COMSPEC_ATAK_DockToggle", {
    params ["_id"];
    private _d = +(profileNamespace getVariable ["COMSPEC_ATAK_DockApps", []]);
    if ((count _d) isEqualTo 0) then { _d = (([] call comspec_atak_native_fnc_appList) select { _x get "dock" }) apply { _x get "id" }; };
    if (_id in _d) then { _d = _d - [_id]; } else { if ((count _d) < 6) then { _d pushBack _id; } else { ["WARNING", "6 apps au maximum dans le dock", 3, 20] call comspec_atak_native_fnc_notify; }; };
    profileNamespace setVariable ["COMSPEC_ATAK_DockApps", _d];
    saveProfileNamespace;
    [{ [] call comspec_atak_native_fnc_dockRender; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];
private _dockNow = profileNamespace getVariable ["COMSPEC_ATAK_DockApps", []];
if ((count _dockNow) isEqualTo 0) then { _dockNow = (([] call comspec_atak_native_fnc_appList) select { _x get "dock" }) apply { _x get "id" }; };
private _perso = [
    ["section", "Thème", "Couleur d'accent des réglages, onglets et boutons"],
    ["COMSPEC_ATAK_Accent", "GREEN", "Couleur d'accent", [["VERT", "GREEN"], ["BLEU", "BLUE"], ["ORANGE", "ORANGE"], ["ROUGE", "RED"], ["VIOLET", "PURPLE"], ["SABLE", "SAND"]]] call _profSegment,
    ["COMSPEC_ATAK_Shell", "auto", "Coque", [["AUTO", "auto"], ["JOUR", "day"], ["NUIT", "night"]], "auto : nuit après le coucher du soleil"] call _profSegment,
    ["COMSPEC_ATAK_ShellTint", "coyote", "Couleur de la coque", [["COYOTE", "coyote"], ["VERT OD", "od"], ["NOIR", "black"]]] call _profSegment,
    ["COMSPEC_ATAK_PhoneModel", "s7", "Modèle du téléphone", [["DURCI S7", "s7"], ["TACTIQUE", "rugged"], ["PREMIUM", "iphone"], ["TABLETTE A/B", "keys"]]] call _profSegment,
    ["COMSPEC_ATAK_PhoneModel", "s7", "", [["SUPPORT JPC", "plate"], ["ÉTUI MULTICAM", "flip"], ["COQUE NERVURÉE", "ribbed"]], "Coque affichée autour de l'écran ; la couleur de coque ne change que le S7. TABLETTE A/B : tablette durcie à touches, celle du JPC. SUPPORT JPC : coque tan à fixation porte-plaques ; ÉTUI MULTICAM : étui à rabat et velcro"] call _profSegment,
    ["COMSPEC_ATAK_CarryStyle", "hud", "Terminal porté", [["MINIATURE", "hud"], ["SUR LE JPC", "jpc"]], "SUR LE JPC : la tablette durcie relevée sur le porte-plaques affiche l'ATAK en 3D, on baisse les yeux pour lire (vue libre, arme). Sans gilet ou en véhicule : miniature"] call _profSegment,
    ["COMSPEC_ATAK_VehTablet", true, "", "Tablette dans les véhicules", "Tablette durcie de bord affichée à la place du téléphone dans un véhicule équipé ou au poste de commandement"] call _profSwitch,
    ["COMSPEC_ATAK_ModuleTint", "olive", "Module DL-7", [["OLIVE", "olive"], ["NOIR", "black"], ["TAN", "tan"]], "Couleur du module de l'app Extraction"] call _profSegment,
    ["segment", "Antenne (port SMA)", [["AUCUNE", { ["unmount"] call comspec_atak_native_fnc_antenna; [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }, (["get"] call comspec_atak_native_fnc_antenna) isEqualTo []]]
        + ((["owned"] call comspec_atak_native_fnc_antenna) apply {
            [toUpper ((_x select 2) select [8]), compile format ["['mount', '%1'] call comspec_atak_native_fnc_antenna; [{ ['SETTINGS'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _x select 0],
                ((["get"] call comspec_atak_native_fnc_antenna) param [0, ""]) isEqualTo (_x select 0)]
        }), "Antennes d'extension de débit portées dans l'inventaire : courte, fouet ou patch (à l'arrêt)"],
    ["COMSPEC_ATAK_Patch", "none", "Patch au dos", [["AUCUN", "none"], ["FRANCE", "fr"], ["FRANCE BD", "fr_sub"], ["USA", "us"], ["USA BD", "us_ir"]]] call _profSegment,
    ["COMSPEC_ATAK_Patch", "none", "", [["CRÂNE", "skull"], ["CROIX", "crusader"], ["ALIEN", "alien"], ["COMSPEC", "comspec"], ["NOMINATIF", "name"]]] call _profSegment,
    ["COMSPEC_ATAK_Patch", "none", "", [["INSIGNE DE L'UNIFORME", "uniform"]], "Patch velcro visible avec « Voir l'arrière » ; BD = basse visibilité. Nominatif : votre nom, ou le texte ci-dessous. Insignes des mods et de la mission (RHS, 3CB, unités…) : « Choisir un insigne » ci-dessous"] call _profSegment,
    // Tous les patchs (nos patchs, insignes CfgUnitInsignia des mods et de la mission) : page PATCHES dédiée avec
    // recherche, sources et vignettes (fn_pagePatches), retour aux Réglages par la touche retour.
    ["person", ([player] call comspec_atak_native_fnc_patchTexture) select 0, format ["<t font='COMSPEC_BarlowBold'>Patch porté : %1</t><br/><t size='0.8' color='#8a9a93'>Recherche, nos patchs, insignes des mods et de la mission</t>",
        (["name", profileNamespace getVariable ["COMSPEC_ATAK_Patch", "none"]] call comspec_atak_native_fnc_patchCatalog) regexReplace ["[<>&]", ""]], [], [1, 1, 1, 1]],
    ["buttons", [[format ["CHOISIR UN INSIGNE (%1 disponibles)", count (["list"] call comspec_atak_native_fnc_patchCatalog)], { [{ ["PATCHES"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame; }, true]]],
    ["COMSPEC_ATAK_VestMount", "off", "Téléphone sur le gilet", [["NON", "off"], ["GAUCHE", "left"], ["CENTRE", "center"], ["DROITE", "right"]], "Le téléphone (notre modèle 3D) est accroché au torse et visible par les autres joueurs, sur tous les gilets"] call _profSegment,
    ["COMSPEC_ATAK_VestDepth", 0.19, "Épaisseur du gilet", [["FIN", 0.15], ["NORMAL", 0.19], ["ÉPAIS", 0.23]], "Écarte le téléphone du torse s'il rentre dans le gilet"] call _profSegment,
    ["COMSPEC_ATAK_VestFace", "back", "Sens sur le gilet", [["DOS ET PATCH", "back"], ["ÉCRAN", "screen"]], "Dos vers l'extérieur (pochette rabattue, patch visible) ou écran vers l'extérieur"] call _profSegment,
    ["edit", "patchText", "Texte du patch nominatif", profileNamespace getVariable ["COMSPEC_ATAK_PatchText", ""]],
    ["buttons", [["ENREGISTRER LE TEXTE", {
        private _t = ["patchText"] call comspec_atak_native_fnc_formValue;
        if (_t isEqualType "") then { profileNamespace setVariable ["COMSPEC_ATAK_PatchText", (trim _t) select [0, 14]]; saveProfileNamespace; ["Patch nominatif enregistré", 2] call comspec_atak_native_fnc_toast; };
    }]]],
    ["buttons", [["VOIR L'ARRIÈRE DU TÉLÉPHONE", { [{ [] call comspec_atak_native_fnc_backView; }] call CBA_fnc_execNextFrame; }]]],
    ["section", "Accessoires", "Batterie externe et filtre d'écran JVN (objets de l'inventaire)"],
    ["switch", format ["Batterie externe sur la plaque%1", ["", format [" (%1 %%)", round (missionNamespace getVariable ["COMSPEC_ATAK_PowerBankCharge", 100])]] select (missionNamespace getVariable ["COMSPEC_ATAK_PowerBankOn", false])],
        missionNamespace getVariable ["COMSPEC_ATAK_PowerBankOn", false],
        { [["attach", "detach"] select (missionNamespace getVariable ["COMSPEC_ATAK_PowerBankOn", false])] call comspec_atak_native_fnc_powerBank; [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }],
    ["switch", "Filtre d'écran JVN", ["filter"] call comspec_atak_native_fnc_screenGlow,
        { ["toggle"] call comspec_atak_native_fnc_screenGlow; [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }],
    ["section", "Sécurité", "En cas de capture, l'ennemi peut brancher un câble DL-7 sur votre téléphone. Le code le protège (3 essais, puis effacement ; forçable en 2 min)."],
    ["edit", "lockCode", "Code de verrouillage (vide = aucun)", profileNamespace getVariable ["COMSPEC_ATAK_LockCode", ""]],
    ["buttons", [["ENREGISTRER LE CODE", {
        private _t = ["lockCode"] call comspec_atak_native_fnc_formValue;
        if (_t isEqualType "") then { profileNamespace setVariable ["COMSPEC_ATAK_LockCode", (trim _t) select [0, 12]]; saveProfileNamespace; ["Code de verrouillage enregistré", 2] call comspec_atak_native_fnc_toast; };
    }], [["EFFACEMENT D'URGENCE", "CONFIRMER L'EFFACEMENT"] select (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_WipeArm", -10]) < 5), {
        // Deux appuis en moins de 5 s : rien ne s'efface par erreur.
        if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_WipeArm", -10]) < 5) then {
            uiNamespace setVariable ["COMSPEC_ATAK_WipeArm", -10];
            ["wipe"] call comspec_atak_native_fnc_phoneSecurity;
        } else {
            uiNamespace setVariable ["COMSPEC_ATAK_WipeArm", diag_tickTime];
            ["WARNING", "Appuyez encore pour tout effacer", 4, 40] call comspec_atak_native_fnc_notify;
        };
        [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    }]]],
    ["COMSPEC_ATAK_NightMode", "OFF", "Mode nuit", [["NORMAL", "OFF"], ["ROUGE", "RED"], ["SOMBRE", "DIM"]], "Filtre rouge ou écran assombri, économise la batterie (raccourci configurable dans les touches CBA)"] call _profSegment,
    ["COMSPEC_ATAK_Wallpaper", "topo", "Fond d'écran de l'accueil", [["TOPO", "topo"], ["NUIT", "night"], ["DÉSERT", "desert"], ["OLIVE", "olive"], ["SOMBRE", "dark"]]] call _profSegment,
    ["COMSPEC_ATAK_Wallpaper", "topo", "", [["SOAR", "soar"], ["ATHENA", "athena"], ["OPS", "ops"], ["AUCUN", "none"]]] call _profSegment,
    ["COMSPEC_ATAK_WallBlur", false, "", "Flou du fond", "Fond d'écran flouté et assombri : icônes plus lisibles"] call _profSwitch,
    // Thèmes rapides : accent, fond d'écran, flou et heure du verrouillage en un appui (chaque réglage reste modifiable).
    ["text", "<t size='0.9'>Thème rapide</t>  <t size='0.75' color='#8a9a93'>Accent, fond d'écran et heure du verrouillage d'un coup</t>"],
    ["buttons", [
        ["OPÉRATIONS", { { profileNamespace setVariable _x; } forEach [["COMSPEC_ATAK_Accent", "GREEN"], ["COMSPEC_ATAK_Wallpaper", "topo"], ["COMSPEC_ATAK_WallBlur", false], ["COMSPEC_ATAK_LockClockStyle", "light"]]; saveProfileNamespace; [{ [] call comspec_atak_native_fnc_layoutApply; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }],
        ["NUIT", { { profileNamespace setVariable _x; } forEach [["COMSPEC_ATAK_Accent", "RED"], ["COMSPEC_ATAK_Wallpaper", "night"], ["COMSPEC_ATAK_WallBlur", true], ["COMSPEC_ATAK_LockClockStyle", "mil"]]; saveProfileNamespace; [{ [] call comspec_atak_native_fnc_layoutApply; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }],
        ["DÉSERT", { { profileNamespace setVariable _x; } forEach [["COMSPEC_ATAK_Accent", "SAND"], ["COMSPEC_ATAK_Wallpaper", "desert"], ["COMSPEC_ATAK_WallBlur", false], ["COMSPEC_ATAK_LockClockStyle", "bold"]]; saveProfileNamespace; [{ [] call comspec_atak_native_fnc_layoutApply; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }],
        ["ATHENA", { { profileNamespace setVariable _x; } forEach [["COMSPEC_ATAK_Accent", "GREEN"], ["COMSPEC_ATAK_Wallpaper", "athena"], ["COMSPEC_ATAK_WallBlur", false], ["COMSPEC_ATAK_LockClockStyle", "light"]]; saveProfileNamespace; [{ [] call comspec_atak_native_fnc_layoutApply; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }]
    ]],
    ["COMSPEC_ATAK_TextScale", 1, "Taille du texte", [["PETIT", 0.9], ["NORMAL", 1], ["GRAND", 1.15], ["TRÈS GRAND", 1.3]]] call _profSegment,
    // Police : réglage CBA comspec_atak_native_font (aussi dans Options des addons > COMSPEC).
    ["segment", "Police", [["ROBOTO", "roboto"], ["PURISTA", "purista"], ["BARLOW (TEST)", "barlow2"]] apply {
        _x params ["_t", "_v"];
        [_t, compile format ["['comspec_atak_native_font', '%1', false, 'client'] call CBA_settings_fnc_set;", _v], _v isEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_FontFam", "roboto"])]
    }, "Police de tout le téléphone : Roboto Condensed par défaut, ou Purista. Barlow est en test."],
    ["COMSPEC_ATAK_IconDensity", 0, "Icônes du lanceur", [["GRANDES", -1], ["NORMALES", 0], ["SERRÉES", 1]], "Moins ou plus d'icônes par ligne"] call _profSegment,
    ["section", "Animations et sons", "Un téléphone qui réagit : mouvement, clic et vibration légère, écran de verrouillage"],
    ["COMSPEC_ATAK_Animations", true, "", "Animations", "App qui s'ouvre en zoomant, page qui glisse au retour, onde sous le doigt, volet rapide animé"] call _profSwitch,
    ["COMSPEC_ATAK_PageFx", false, "", "Transitions entre les pages", "Voile qui grandit à l'ouverture d'une app, fondu de l'accueil, glissé au retour (désactivé : aucun clignotement sombre)"] call _profSwitch,
    ["COMSPEC_ATAK_TapFeedback", true, "", "Clic et vibration des appuis", "Clic doux et légère vibration de la coque à chaque appui (aucun son en mode discrétion)"] call _profSwitch,
    ["COMSPEC_ATAK_LockScreen", true, "", "Écran de verrouillage", "Heure, date, dernières notifications : à la première sortie, après une minute rangé ou un redémarrage"] call _profSwitch,
    ["COMSPEC_ATAK_LockClockStyle", "light", "Heure de l'écran de verrouillage", [["FINE", "light"], ["GRASSE", "bold"], ["MILITAIRE", "mil"]], "Militaire : chiffres à chasse fixe, couleur d'accent"] call _profSegment,
    ["COMSPEC_ATAK_HandHint", true, "", "Raccourci « prendre en main » en mode porté", "Pastille main + Ctrl+Maj+U dans la barre du téléphone porté ; coupée : écran sans indication, plus immersif"] call _profSwitch,
    ["COMSPEC_ATAK_LauncherSearch", true, "", "Recherche dans le lanceur", "Champ « Rechercher une app » en haut des applications (en main) ; Entrée ouvre la première trouvée"] call _profSwitch,
    ["COMSPEC_ATAK_Brightness", 1, "Luminosité", [["30 %", 0.3], ["50 %", 0.5], ["75 %", 0.75], ["100 %", 1]], "Aussi dans le volet rapide (clic sur l'heure) ; écran moins lumineux : moins visible de nuit, moins de batterie"] call _profSegment,
    ["section", "Dock", format ["%1 app(s) en raccourci en bas de l'écran (6 au plus)", count _dockNow]]
];
{
    _perso pushBack ["switch", _x get "name", (_x get "id") in _dockNow, compile format ["['%1'] call (uiNamespace getVariable 'COMSPEC_ATAK_DockToggle');", _x get "id"]];
} forEach (([] call comspec_atak_native_fnc_appList) select { [_x] call comspec_atak_native_fnc_appVisible });
_perso pushBack ["buttons", [["DOCK PAR DÉFAUT", { profileNamespace setVariable ["COMSPEC_ATAK_DockApps", []]; saveProfileNamespace; [{ [] call comspec_atak_native_fnc_dockRender; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }]]];
_perso pushBack ["buttons", [["TOUT RÉINITIALISER (APPARENCE)", {
    { profileNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_Accent", "COMSPEC_ATAK_Shell", "COMSPEC_ATAK_ShellTint", "COMSPEC_ATAK_ModuleTint", "COMSPEC_ATAK_Patch", "COMSPEC_ATAK_PatchText", "COMSPEC_ATAK_VestMount", "COMSPEC_ATAK_VestDepth", "COMSPEC_ATAK_VestFace", "COMSPEC_ATAK_PhoneModel", "COMSPEC_ATAK_CarryStyle", "COMSPEC_ATAK_VehTablet", "COMSPEC_ATAK_Wallpaper", "COMSPEC_ATAK_WallBlur", "COMSPEC_ATAK_TextScale", "COMSPEC_ATAK_IconDensity", "COMSPEC_ATAK_DockApps", "COMSPEC_ATAK_HiddenApps", "COMSPEC_ATAK_LockClockStyle", "COMSPEC_ATAK_HandHint", "COMSPEC_ATAK_LauncherSearch"];
    saveProfileNamespace;
    [{ [] call comspec_atak_native_fnc_layoutApply; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}]]];
// Taille du plein écran : réglage CBA par joueur (comspec_atak_native_full_scale), le même que dans le menu CBA.
uiNamespace setVariable ["COMSPEC_ATAK_SetFullScale", {
    params ["_v"];
    ["comspec_atak_native_full_scale", _v, false, "client"] call CBA_settings_fnc_set;
    [{ [] call comspec_atak_native_fnc_layoutApply; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];
private _fullNow = missionNamespace getVariable ["comspec_atak_native_full_scale", 1];
if !(_fullNow isEqualType 0) then { _fullNow = 1; };
private _place = [
    ["COMSPEC_ATAK_MiniScale", 1, "Taille du mini", [["PETIT", 0.8], ["NORMAL", 1], ["GRAND", 1.2]]] call _profSegment,
    ["segment", "Taille en plein écran", [["60 %", 0.6], ["70 %", 0.7], ["80 %", 0.8], ["90 %", 0.9], ["100 %", 1]] apply {
        _x params ["_t", "_v"];
        [_t, compile format ["[%1] call (uiNamespace getVariable 'COMSPEC_ATAK_SetFullScale');", _v], (abs (_fullNow - _v)) < 0.05]
    }, "Téléphone en main, ouvert en plein écran : reste centré, 100 % = taille d'origine (aussi dans le menu CBA, ATAK · Affichage)"],
    ["COMSPEC_ATAK_Orientation", "PORTRAIT", "Sens du mini", [["VERTICAL", "PORTRAIT"], ["HORIZONTAL", "LANDSCAPE"]]] call _profSegment,
    ["segment", "Coin de l'écran", [[["HAUT G", "TL"], ["MILIEU G", "ML"], ["BAS G", "BL"]]] call _anchorSeg],
    ["segment", "", [[["HAUT D", "TR"], ["MILIEU D", "MR"], ["BAS D", "BR"]]] call _anchorSeg],
    ["buttons", [["REMETTRE LE TÉLÉPHONE EN PLACE", {
        { profileNamespace setVariable [_x, [0, 0]]; } forEach ["COMSPEC_ATAK_Offset_MINI_PORTRAIT", "COMSPEC_ATAK_Offset_MINI_LANDSCAPE", "COMSPEC_ATAK_Offset_FULL_LANDSCAPE"];
        saveProfileNamespace;
        [{ [] call comspec_atak_native_fnc_layoutApply; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    }]]]
];
private _map = [
    ["COMSPEC_ATAK_Labels", true, "native_map_labels", "Indicatifs des unités", "Nom à côté de chaque symbole ami"] call _profSwitch,
    ["COMSPEC_ATAK_LabelStyle", "FULL", "Cartouches des unités", [["COMPLET", "FULL"], ["INDICATIF SEUL", "CALLSIGN"], ["AUCUN", "NONE"]], "Boîte sombre près du symbole : indicatif, puis distance et azimut depuis moi en zoom rapproché ; contacts ENI bordés de rouge avec heure et auteur"] call _profSegment,
    ["COMSPEC_ATAK_MarkerTags", true, "native_marker_tags", "Cartouches des repères", "Nom et coordonnées E / N sous le repère"] call _profSwitch,
    ["comspec_atak_native_map_marker_style", "plate", "Noms des repères", [["PLAQUE", "plate"], ["PILULE", "pill"], ["CARTOUCHE", "card"], ["CONTOUR", "outline"], ["CLASSIQUE", "classic"]], "Repères et cibles accrochées (LOCK) : plaque sombre au liseré de couleur, pilule, cartouche relié avec distance et azimut, texte détouré ou texte Arma d'avant"] call _cbaSegment,
    ["comspec_atak_native_map_place_style", "tactical", "Noms des lieux", [["TACTIQUE", "tactical"], ["ÉTIQUETTE", "tag"], ["DISCRET", "quiet"], ["CLASSIQUE", "classic"]], "Villes, villages, lieux-dits et sites : capitales détourées, plaque sombre, petit texte clair ou couleurs Arma d'avant"] call _cbaSegment,
    ["comspec_atak_native_map_show_marker_text", "", "Afficher les noms des repères", "Les symboles restent, seul le texte disparaît (aussi dans CALQUES)", true] call _cbaSwitch,
    ["comspec_atak_native_map_show_cities", "", "Afficher les villes", "", true] call _cbaSwitch,
    ["comspec_atak_native_map_show_villages", "", "Afficher les villages", "", true] call _cbaSwitch,
    ["comspec_atak_native_map_show_locals", "", "Afficher les lieux-dits", "", true] call _cbaSwitch,
    ["comspec_atak_native_map_show_sites", "", "Afficher les sites", "Aéroports, bases et camps militaires", true] call _cbaSwitch,
    ["COMSPEC_ATAK_Compass", true, "native_compass", "Boussole", "Rose des vents et cap en haut à gauche"] call _profSwitch,
    ["COMSPEC_ATAK_CompassStyle", "CLASSIC", "Style de la boussole", [["CLASSIQUE", "CLASSIC"], ["CADRAN", "CARD"], ["FLÈCHE", "ARROW"], ["BANDEAU", "TAPE"]], "CLASSIQUE : rose fixe, aiguille au cap. CADRAN : le cadran tourne, le haut reste l'avant. FLÈCHE : aiguille seule. BANDEAU : cap gradué en haut à gauche"] call _profSegment,
    ["COMSPEC_ATAK_AllyFilter", "ALL", "Alliés affichés", [["TOUS", "ALL"], ["MON GROUPE", "GROUP"], ["MON ORBAT", "ORBAT"]], ["", format ["ORBAT : %1", missionNamespace getVariable ["comspec_profile_unit", ""]]] select ((missionNamespace getVariable ["comspec_profile_unit", ""]) isNotEqualTo "")] call _profSegment,
    ["COMSPEC_ATAK_HideAllyAi", false, "", "Masquer les IA alliées", "Seuls les joueurs alliés restent sur la carte et le BFT"] call _profSwitch,
    ["COMSPEC_ATAK_UnitSymbols", "NATO", "Icônes des unités", [["OTAN MIL-STD", "NATO"], ["POINTS", "DOTS"]], "OTAN MIL-STD-2525 / APP-6 : cadre et fonction (infanterie, véhicule, sanitaire, PC, reco…), échelon des chefs"] call _profSegment,
    ["COMSPEC_ATAK_AllyColor", "BLUE", "Couleur des alliés", [["BLEU", "BLUE"], ["VERT", "GREEN"], ["BLANC", "WHITE"], ["JAUNE", "YELLOW"], ["ROSE", "PINK"]]] call _profSegment,
    ["COMSPEC_ATAK_SelfColor", "CYAN", "Ma couleur", [["CYAN", "CYAN"], ["BLEU", "BLUE"], ["VERT", "GREEN"], ["BLANC", "WHITE"], ["ORANGE", "ORANGE"]]] call _profSegment,
    ["COMSPEC_ATAK_SelfIcon", "", "Mon icône (vue par tous)", [["FLÈCHE", ""], ["INF", "b_inf"], ["RECO", "b_recon"], ["MÉDIC", "b_med"], ["PC", "b_hq"], ["SOUTIEN", "b_support"]]] call _profSegment,
    ["COMSPEC_ATAK_MapToolbarScale", 1, "Barre d'outils de la carte", [["PETITE", 0.75], ["NORMALE", 1], ["GRANDE", 1.3]], "Boutons à droite de la carte : centrer, suivre, zoom, marqueur, libellés"] call _profSegment,
    ["COMSPEC_ATAK_GridDigits", 6, "Précision des grilles", [["6 CHIFFRES", 6], ["8 CHIFFRES", 8], ["10 CHIFFRES", 10]], "100 m, 10 m ou 1 m"] call _profSegment,
    ["section", "Lisibilité et fond", "Taille des textes et symboles de la carte, fond de carte"],
    ["COMSPEC_ATAK_MapTextScale", 1.25, "Textes de la carte", [["PETIT", 1], ["NORMAL", 1.25], ["GRAND", 1.5], ["TRÈS GRAND", 1.8]], "Indicatifs, numéros de grille, cartouches, panneaux d'info"] call _profSegment,
    ["COMSPEC_ATAK_MapIconScale", 1.25, "Symboles de la carte", [["PETIT", 1], ["NORMAL", 1.25], ["GRAND", 1.5], ["TRÈS GRAND", 1.8]], "Unités, repères, charges, points de passage"] call _profSegment,
    ["COMSPEC_ATAK_MapStyle", [] call comspec_atak_native_fnc_mapStyle, "Fond de carte", [["TOPO", "TOPO"], ["CLAIR", "LIGHT"], ["SOMBRE", "DARK"], ["NUIT", "NIGHT"]], "Topographique Arma, éclairci, assombri ou bleu nuit (aussi dans CALQUES)"] call _profSegment
];
private _alerts = [
    ["COMSPEC_ATAK_Notifications", true, "", "Bandeaux de notification", "Messages du poste, missions de tir, photos"] call _profSwitch,
    ["COMSPEC_ATAK_Vibrate", true, "native_vibrate", "Vibration", "À chaque nouveau message ou alerte"] call _profSwitch,
    ["COMSPEC_ATAK_BftAlerts", true, "", "Alertes BFT du groupe", "Équipier hors ligne, inconscient ou qui ne répond plus"] call _profSwitch,
    ["COMSPEC_ATAK_Silent", false, "", "Mode discrétion", "Aucun son (vibration visible seulement), raccourci configurable dans les touches CBA"] call _profSwitch
];
private _real = [];
private _live = [];
if (_bridge) then {
    _real append [
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
    _real pushBack ["info", "Dommages au téléphone", format ["%1%2", ["aucun", "peut s'éteindre", "écran destructible", "téléphone destructible"] param [_lvl, str _lvl], [" <t color='#e8b84a'>· imposé</t>", ""] select (_dmg isEqualTo "player")]];
    if !(_tenantOn) then { _real pushBack ["text", "<t size='0.8' color='#8a9a93'>Réglages de la communauté pas encore reçus : connectez-vous à Athena.</t>"]; };
    _live = [
        ["section", "Live cam", "Partage de ma vue vers le poste Overwatch"],
        ["COMSPEC_ATAK_LivecamShare", false, "native_livecam_share", "Partager ma caméra", "Une image de ma vue à intervalle régulier vers COMSPEC Overwatch (onglet Live cam)"] call _profSwitch,
        ["COMSPEC_ATAK_LivecamShareEvery", 15, "Cadence", [["10 S", 10], ["15 S", 15], ["30 S", 30], ["60 S", 60]]] call _profSegment
    ];
};
// Téléphone (dégâts, saleté) et données médicales affichées (fn_medShow) : choix du joueur, sauf si le serveur
// (réglages CBA) ou la communauté (Athena) les imposent.
uiNamespace setVariable ["COMSPEC_ATAK_MedToggle", {
    params ["_k"];
    private _h = profileNamespace getVariable ["COMSPEC_ATAK_MedHide", []];
    if !(_h isEqualType []) then { _h = []; };
    _h = +_h;
    if (_k in _h) then { _h = _h - [_k]; } else { _h pushBack _k; };
    profileNamespace setVariable ["COMSPEC_ATAK_MedHide", _h];
    saveProfileNamespace;
    [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];
private _medRow = {
    params ["_k", "_label"];
    (["rule", _k] call comspec_atak_native_fnc_medShow) params ["_on", "_locked", "_by"];
    ["switch", _label, _on, compile format ["['%1'] call (uiNamespace getVariable 'COMSPEC_ATAK_MedToggle');", _k], ["", format ["Imposé par %1", _by]] select _locked, _locked]
};
_real append [
    ["section", "Téléphone", "Dégâts selon où il est porté, saleté de l'écran"],
    ["info", "Dégâts localisés", ["<t color='#8a9a93'>coupés par le serveur</t>", "actifs : main, gilet, poche ou sac ; balle, éclat, souffle, chute, eau"] select (missionNamespace getVariable ["comspec_atak_native_damage_sim", true])],
    if (missionNamespace getVariable ["comspec_atak_native_dirt_sim", true]) then {
        ["COMSPEC_ATAK_DirtFx", true, "native_dirt", "Saleté et sang sur l'écran", "Poussière, gouttes de pluie, traces de doigts, sang après un soin ; nettoyage par l'action ACE ou l'app Profil"] call _profSwitch
    } else { ["info", "Saleté et sang sur l'écran", "<t color='#8a9a93'>coupés par le serveur</t>"] },
    ["section", "Données médicales affichées", "Une donnée masquée disparaît de tout l'ATAK"]
];
{ _real pushBack (_x call _medRow); } forEach (["fields"] call comspec_atak_native_fnc_medShow);
_real pushBack ["section", "Où les afficher", "Masquer un endroit retire toutes les données médicales de cet endroit"];
{ _real pushBack (_x call _medRow); } forEach (["contexts"] call comspec_atak_native_fnc_medShow);

private _hp = [] call comspec_atak_native_fnc_deviceHealth;
private _lq = [] call comspec_atak_native_fnc_linkQuality;
private _hw = [
    ["section", "Connexions", "Mode avion et Bluetooth (aussi dans l'app Bluetooth et le centre de notifications)"],
    ["switch", "Mode avion", [] call comspec_atak_native_fnc_airplaneMode, { ["toggle"] call comspec_atak_native_fnc_airplaneMode; [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }, "Plus de réseau : ni synchro, ni SMS, ni BFT (les envois attendent en file)"],
    ["switch", "Bluetooth", (["state"] call comspec_atak_native_fnc_btAction) get "on", { ["toggle"] call comspec_atak_native_fnc_btAction; [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }, "Appairage par code et partage de sons avec les ATAK proches (indépendant du mode avion)"],
    ["section", "Matériel et réseau", "Simulations réglées par le serveur (réglages CBA)"],
    ["info", "Dégâts du téléphone", [ "<t color='#8a9a93'>simulation coupée</t>", format ["%1 · usure %2 %%", ["<t color='#5cc76b'>intact</t>", format ["<t color='#f2ab33'>%1</t>", _hp get "reason"]] select ((_hp get "state") isNotEqualTo "OK"), round ((_hp get "damage") * 100)]] select (missionNamespace getVariable ["comspec_atak_native_damage_sim", true])],
    ["buttons", [["NETTOYER L'ÉCRAN", { [{ ["start"] call comspec_atak_native_fnc_screenClean; }] call CBA_fnc_execNextFrame; }, false, ["can"] call comspec_atak_native_fnc_screenClean],
        ["REDÉMARRER LE TÉLÉPHONE", { ["reboot"] call comspec_atak_native_fnc_deviceRepair; }]]],
    ["info", "Débit simulé", [ "<t color='#8a9a93'>simulation coupée</t>", format ["%1 · %2", _lq get "label", [format ["%1 kbit/s", _lq get "kbps"], format ["%1 Mbit/s", ((_lq get "kbps") / 1000) toFixed 1]] select ((_lq get "kbps") >= 1000)]] select (_lq get "sim")]
];

private _appsRows = [];
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
_appsRows pushBack ["section", "Applications", format ["%1 app(s) cachée(s) du lanceur", count _hidden]];
{
    private _id = _x get "id";
    if (_id isNotEqualTo "Settings" && {!((_x get "section") isEqualTo "Civil" && {!(missionNamespace getVariable ["comspec_atak_native_civil_apps", true])})}) then {
        _appsRows pushBack ["switch", format ["%1  <t size='0.8' color='#8a9a93'>%2</t>", _x get "name", _x get "section"], !(_id in _hidden), compile format ["['%1'] call (uiNamespace getVariable 'COMSPEC_ATAK_AppHide');", _id]];
    };
} forEach ([] call comspec_atak_native_fnc_appList);

private _rule = ["require_equipment"] call comspec_atak_native_fnc_tenantRule;
private _require = if (_rule in ["on", "off"]) then { _rule isEqualTo "on" } else { missionNamespace getVariable ["comspec_atak_native_require_item", true] };
private _access = [
    ["section", "Accès et touches", ""],
    ["info", "Objet requis", [["non", format ["oui · %1 objets reconnus", count ([] call comspec_atak_native_fnc_deviceCatalog)]] select _require, "imposé par la communauté"] select (_rule in ["on", "off"])],
    ["info", "Sortir / ranger (porté)", "Ctrl+U"],
    ["info", "Prendre en main / reposer", "Ctrl+Maj+U"],
    ["text", "<t size='0.8' color='#8a9a93'>En main, glissez la coque pour l'ajuster à partir du coin choisi. Touches modifiables dans les réglages CBA.</t>"]
];

// Service Athéna : la question du premier lancement (Oui : COMSPEC Link et synchro ; Non : ATAK local).
private _athMode = [] call comspec_atak_native_fnc_athenaMode;
private _athReady = _bridge && {[] call comspec_overwatch_connect_fnc_isReady};
private _athState = switch (true) do {
    case (_athMode isEqualTo "off"): { "<t color='#8a9a93'>ATAK local · aucune connexion ni donnée envoyée</t>" };
    case (_athMode isEqualTo ""): { "<t color='#e8b84a'>pas encore choisi</t>" };
    case (_athReady): { format ["<t color='#5cc76b'>compte lié</t>%1", ["", format [" · %1", missionNamespace getVariable ["comspec_tenant_name", ""]]] select ((missionNamespace getVariable ["comspec_tenant_name", ""]) isNotEqualTo "")] };
    default { "<t color='#f2ab33'>compte pas encore lié</t>" };
};
private _athena = [
    ["section", "Service Athéna", "Votre unité utilise-t-elle Athéna ?"],
    ["segment", "Mon unité utilise Athéna", [
        ["OUI", { ["on"] call comspec_atak_native_fnc_athenaChoice; }, _athMode isEqualTo "on"],
        ["NON", { ["off"] call comspec_atak_native_fnc_athenaChoice; }, _athMode isEqualTo "off"]
    ], "Oui : COMSPEC Link, synchro avec le poste, compte lié. Non : ATAK local sur le serveur, rien n'est envoyé."],
    ["info", "État", _athState],
    ["buttons", [
        ["REVOIR LA QUESTION", { ["show"] call comspec_atak_native_fnc_athenaChoice; }, false, _bridge],
        ["LIER MON COMPTE", { ["link"] call comspec_atak_native_fnc_athenaChoice; }, true, _bridge && {_athMode isEqualTo "on"} && {!_athReady}]
    ]],
    ["text", "<t size='0.8' color='#8a9a93'>Aussi dans le menu Échap, bouton Service Athéna.</t>"]
];

// Langue du mod : même réglage que CBA (COMSPEC > Langue / Language, en tête), libellés bilingues ; le changer redessine la page et les menus CBA (fn_langApply).
// Langues des mods de traduction chargés (fn_langList) : un bouton chacune, entre ENGLISH et AUTO.
private _langSet = missionNamespace getVariable ["comspec_atak_native_language", "auto"];
private _langs = [] call comspec_atak_native_fnc_langList;
private _langBtns = [["FRANÇAIS", { ["comspec_atak_native_language", "fr", 0, "client"] call CBA_settings_fnc_set; }, _langSet isEqualTo "fr"]];
{
    _x params ["_code", "_name"];
    _langBtns pushBack [toUpper _name, compile format ["['comspec_atak_native_language', '%1', 0, 'client'] call CBA_settings_fnc_set;", _code], _langSet isEqualTo _code];
} forEach _langs;
_langBtns pushBack ["AUTO", { ["comspec_atak_native_language", "auto", 0, "client"] call CBA_settings_fnc_set; }, _langSet isEqualTo "auto"];
// Quatre boutons par ligne au plus (plusieurs mods de traduction chargés) : lignes suivantes sans libellé.
private _lang = [["section", "Langue / Language", "Langue du téléphone / Phone language"]];
for "_i" from 0 to ((count _langBtns) - 1) step 4 do {
    _lang pushBack ["segment", ["", "Langue / Language"] select (_i isEqualTo 0), _langBtns select [_i, 4],
        ["", "Auto : langue du jeu / game language. Aussi dans Échap > Options > Options des addons > COMSPEC, en tête de la liste / also at the top of the COMSPEC addon options."] select (_i isEqualTo 0)];
};
private _langName = switch (true) do {
    case (_langSet isEqualTo "fr"): { "Français" };
    case ((_langs findIf { (_x select 0) isEqualTo _langSet }) >= 0): { (_langs select (_langs findIf { (_x select 0) isEqualTo _langSet })) select 1 };
    default { "Auto" };
};

// Catégories, façon Android : liste d'accueil, puis une page par catégorie.
private _cats = [
    ["LANG", "Langue / Language", _langName, "ui_language", _lang],
    ["PERSO", "Personnalisation", "Thème, coque, fond d'écran, texte, dock", "ui_palette", _perso],
    ["PLACE", "Emplacement", "Coin, taille et sens du téléphone porté", "ui_rotate", _place],
    ["MAP", "Carte", "Indicatifs, couleurs, grilles, tailles, fond", "app_map", _map],
    ["ALERTS", "Alertes", "Notifications et vibration", "ui_vibrate", _alerts],
    ["APPS", "Applications", format ["%1 app(s) cachée(s)", count (profileNamespace getVariable ["COMSPEC_ATAK_HiddenApps", []])], "ui_apps", _appsRows],
    ["HW", "Matériel et réseau", "Mode avion, Bluetooth, dégâts, débit simulé, Live cam", "app_network", _hw + _live],
    ["REAL", "Réalisme", ["Données médicales, saleté de l'écran", "Roleplay d'Overwatch, données médicales, réglages imposés"] select _bridge, "app_status", _real],
    ["ACCESS", "Accès et touches", "Objet requis, raccourcis clavier", "ui_link", _access],
    ["ATHENA", "Service Athéna", ["Non : ATAK local, aucune synchro", "Oui : COMSPEC Link et synchro", "Pas encore choisi"] select (["off", "on", ""] find _athMode), "app_athena", _athena]
];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _cat = _s getOrDefault ["setCat", ""];
private _sel = (_cats select { (_x select 0) isEqualTo _cat }) param [0, []];
private _rows = [];
if ((count _sel) isEqualTo 0) then {
    _rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_settings.paa", format ["<t size='1.3' font='COMSPEC_BarlowBold'>Réglages</t><br/><t color='#8a9a93'>COMSPEC ATAK %1%2</t>", missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", ""], ["", format [" · %1 réglage(s) imposé(s) par %2", _lockedCount, ["votre communauté", _tenantName] select (_tenantName isNotEqualTo "")]] select (_lockedCount > 0)]];
    {
        _x params ["_k", "_t", "_sub", "_icon", "_r"];
        _rows pushBack ["person", format ["\z\comspec_atak_native\addons\main\data\%1.paa", _icon], format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#8a9a93'>%2</t>", _t, _sub],
            [["OUVRIR", compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['setCat', '%1']; [{ ['SETTINGS'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k], true]], ([] call comspec_atak_native_fnc_accent) select 0];
    } forEach _cats;
} else {
    _rows pushBack ["buttons", [[format ["< RÉGLAGES · %1", toUpper (_sel select 1)], { (uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['setCat', '']; [{ ['SETTINGS'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }]]];
    _rows append (_sel select 4);
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
