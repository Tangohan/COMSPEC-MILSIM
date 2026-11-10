/*
    Réglages du téléphone, par catégories (accueil puis une page par catégorie) : personnalisation, emplacement, carte,
    alertes, applications, matériel et réseau, réalisme (communauté), accès et touches.
    Les réglages que la communauté impose depuis Athena (Contrôle serveur) s'affichent « IMPOSÉ » et ne changent pas d'ici.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _rerender = { [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; };
uiNamespace setVariable ["COMSPEC_ATAK_SettingsRerender", _rerender];

// Réglage profil (bascule) : [variable, défaut, clé communauté, libellé, aide]
uiNamespace setVariable ["COMSPEC_ATAK_ProfToggle", {
    params ["_var", "_def"];
    private _new = !(profileNamespace getVariable [_var, _def]);
    profileNamespace setVariable [_var, _new];
    [] call comspec_atak_native_fnc_saveProfile;
    // Réglages sans effet sur la mise en page ni sur les calques de la carte (lus à l'usage : effets, bandeaux, vibration,
    // écran de verrouillage, saleté) : l'interrupteur change sur place (fn_formSet), la page n'est pas redessinée.
    // Les autres (mise en page, étiquettes de la carte, fond) gardent le redessin.
    if (_var in ["COMSPEC_ATAK_Animations", "COMSPEC_ATAK_PageFx", "COMSPEC_ATAK_OpenSplash", "COMSPEC_ATAK_TitleSlide", "COMSPEC_ATAK_PressFx",
        "COMSPEC_ATAK_TapFeedback", "COMSPEC_ATAK_Notifications", "COMSPEC_ATAK_Toasts", "COMSPEC_ATAK_Vibrate", "COMSPEC_ATAK_BftAlerts",
        "COMSPEC_ATAK_Silent", "COMSPEC_ATAK_LauncherSearch", "COMSPEC_ATAK_LockScreen", "COMSPEC_ATAK_PinAsk", "COMSPEC_ATAK_DirtFx"]
        && {["set_" + _var, _new] call comspec_atak_native_fnc_formSet}) exitWith {};
    [{ [] call comspec_atak_native_fnc_layoutApply; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
}];
private _profSwitch = {
    params ["_var", "_def", "_tenantKey", "_label", ["_help", ""]];
    ([_var, _def, _tenantKey] call comspec_atak_native_fnc_pref) params ["_on", "_locked"];
    ["switch", _label, _on, compile format ["['%1', %2] call (uiNamespace getVariable 'COMSPEC_ATAK_ProfToggle');", _var, _def], _help, _locked, "set_" + _var]
};
// Choix exclusif enregistré dans le profil : [variable, valeur actuelle, [[texte, valeur]...]]
uiNamespace setVariable ["COMSPEC_ATAK_ProfSet", {
    params ["_var", "_val"];
    profileNamespace setVariable [_var, _val];
    [] call comspec_atak_native_fnc_saveProfile;
    // Modèle : la liste déroulante CBA (comspec_atak_native_phone_model) suit le choix fait dans le téléphone.
    if (_var isEqualTo "COMSPEC_ATAK_PhoneModel") then { ["comspec_atak_native_phone_model", _val, false, "client"] call CBA_settings_fnc_set; };
    // Réglages lus au prochain dessin, sans effet sur la page ni sur la mise en page : le bouton change sur place.
    if (_var in ["COMSPEC_ATAK_LabelStyle", "COMSPEC_ATAK_CompassStyle", "COMSPEC_ATAK_AllyFilter", "COMSPEC_ATAK_UnitSymbols", "COMSPEC_ATAK_AllyColor", "COMSPEC_ATAK_SelfColor", "COMSPEC_ATAK_SelfIcon", "COMSPEC_ATAK_MapToolbarScale", "COMSPEC_ATAK_GridDigits", "COMSPEC_ATAK_PhotoRelief", "COMSPEC_ATAK_MapStyle", "COMSPEC_ATAK_MapDrawStyle", "COMSPEC_ATAK_ModuleTint", "COMSPEC_ATAK_LockClockStyle", "COMSPEC_ATAK_IconStyle", "COMSPEC_ATAK_Wallpaper"]
        && {["seg_" + _var, _val] call comspec_atak_native_fnc_formSet}) exitWith {};
    [{ [] call comspec_atak_native_fnc_layoutApply; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
}];
private _profSegment = {
    params ["_var", "_def", "_label", "_opts", ["_help", ""]];
    private _cur = profileNamespace getVariable [_var, _def];
    ["segment", _label, _opts apply {
        _x params ["_t", "_v"];
        [_t, compile format ["['%1', %2] call (uiNamespace getVariable 'COMSPEC_ATAK_ProfSet');", _var, str _v], _v isEqualTo _cur, "", _v]
    }, _help, "seg_" + _var]
};
// Réglage roleplay Overwatch (CBA côté joueur) : verrouillé si la communauté ou le serveur l'impose.
uiNamespace setVariable ["COMSPEC_ATAK_CbaToggle", {
    params ["_k"];
    private _new = !(missionNamespace getVariable [_k, false]);
    [_k, _new, false, "client"] call CBA_settings_fnc_set;
    // Étiquettes de la carte (lues au dessin de la carte) : l'interrupteur change sur place, la page n'est pas redessinée.
    if ((_k select [0, 29]) isEqualTo "comspec_atak_native_map_show_" && {["cba_" + _k, _new] call comspec_atak_native_fnc_formSet}) exitWith {};
    [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
}];
private _cbaSwitch = {
    params ["_k", "_tenantKey", "_label", ["_help", ""], ["_d", false]];
    private _v = missionNamespace getVariable [_k, _d];
    if !(_v isEqualType true) then { _v = _d; };
    private _rule = [_tenantKey] call comspec_atak_native_fnc_tenantRule;
    private _locked = _rule in ["on", "off"];
    if (_locked) then { _v = _rule isEqualTo "on"; };
    // Source de la valeur, dite sur la ligne : communauté (Athena), serveur ou mission (CBA forcé), Athena pour cette partie (zones, réseau roleplay), sinon le joueur.
    private _srcCba = if (isNil "CBA_settings_fnc_priority") then { "client" } else { [_k] call CBA_settings_fnc_priority };
    private _src = switch (true) do {
        case (_locked): { format ["Imposé par %1 sur Athena", ["votre communauté", _tenantName] select (_tenantName isNotEqualTo "")] };
        case (_srcCba isEqualTo "server"): { "Imposé par le serveur" };
        case (_srcCba isEqualTo "mission"): { "Imposé par la mission" };
        case (_v && {!isNil "CBA_settings_fnc_get"} && {([_k, "client"] call CBA_settings_fnc_get) isEqualTo false}): { "Activé pour cette partie par Athena" };
        default { "Votre choix" };
    };
    private _forced = _locked || {_srcCba in ["server", "mission"]};
    ["switch", _label, _v, compile format ["['%1'] call (uiNamespace getVariable 'COMSPEC_ATAK_CbaToggle');", _k], [_help, [_src, format ["%1 · %2", _help, _src]] select (_help isNotEqualTo "")] select ((_k select [0, 18]) isEqualTo "comspec_overwatch_"), _forced, "cba_" + _k]
};
// Choix exclusif enregistré en réglage CBA côté joueur (étiquettes de la carte) : le même que dans le menu CBA.
uiNamespace setVariable ["COMSPEC_ATAK_CbaSet", {
    params ["_k", "_v"];
    [_k, _v, false, "client"] call CBA_settings_fnc_set;
    // Styles des noms de la carte : lus au dessin, le bouton change sur place.
    if (["cseg_" + _k, _v] call comspec_atak_native_fnc_formSet) exitWith {};
    [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
}];
private _cbaSegment = {
    params ["_k", "_def", "_label", "_opts", ["_help", ""]];
    private _cur = missionNamespace getVariable [_k, _def];
    ["segment", _label, _opts apply {
        _x params ["_t", "_v"];
        [_t, compile format ["['%1', %2] call (uiNamespace getVariable 'COMSPEC_ATAK_CbaSet');", _k, str _v], _v isEqualTo _cur, "", _v]
    }, _help, "cseg_" + _k]
};

// Emplacement du mini : remet à zéro le décalage glissé pour partir du nouveau coin.
uiNamespace setVariable ["COMSPEC_ATAK_SetAnchor", {
    params ["_k"];
    profileNamespace setVariable ["COMSPEC_ATAK_MiniAnchor", _k];
    { profileNamespace setVariable [_x, [0, 0]]; } forEach ["COMSPEC_ATAK_Offset_MINI_PORTRAIT", "COMSPEC_ATAK_Offset_MINI_LANDSCAPE"];
    [] call comspec_atak_native_fnc_saveProfile;
    [{ [] call comspec_atak_native_fnc_layoutApply; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
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
    [] call comspec_atak_native_fnc_saveProfile;
    [{ [] call comspec_atak_native_fnc_dockRender; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
}];
private _dockNow = profileNamespace getVariable ["COMSPEC_ATAK_DockApps", []];
if ((count _dockNow) isEqualTo 0) then { _dockNow = (([] call comspec_atak_native_fnc_appList) select { _x get "dock" }) apply { _x get "id" }; };
// Personnaliser, rangé par thème (retour de jeu : « trop de bordel ») : le téléphone, l'écran, le verrouillage, puis
// des sections repliables pour ce qui sert peu (patch, gilet, dock). Les réglages en test sont retirés (police Barlow,
// terminal porté « SUR LE JPC » en chantier, bloqué dans fn_jpcScreen).
uiNamespace setVariable ["COMSPEC_ATAK_SetFold", {
    params ["_k", "_def"];
    private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    _st set ["setFold_" + _k, !(_st getOrDefault ["setFold_" + _k, _def])];
    [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
}];
private _foldOpen = { params ["_k", ["_def", false]]; (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["setFold_" + _k, _def] };
private _fold = {
    params ["_k", "_t", "_sub", ["_def", false]];
    ["fold", _t, _sub, [_k, _def] call _foldOpen, compile format ["['%1', %2] call (uiNamespace getVariable 'COMSPEC_ATAK_SetFold');", _k, _def]]
};
// Le modèle et la couleur viennent de l'objet porté. On ne les change plus ici.
([player] call comspec_atak_native_fnc_phoneCarried) params ["_wornModel", "_wornTint", "_wornName"];
private _modelNames = createHashMapFromArray [["s7", "SAMSUNG S7"], ["s23", "SAMSUNG S23"], ["ip17", "IPHONE 17 PRO"], ["iphone", "V2 ENHANCED"], ["rugged", "XIAOMI X3D"], ["sonim", "SONIM XP8"], ["keys", "TABLETTE"]];
private _tintNames = createHashMapFromArray [["coyote", "COYOTE"], ["od", "OLIVE"], ["black", "NOIR"]];
private _modelName = _modelNames getOrDefault [_wornModel, "SAMSUNG S7"];
if (_wornModel isEqualTo "s7") then { _modelName = _modelName + " " + (_tintNames getOrDefault [_wornTint, "COYOTE"]); };
private _perso = [["section", "Téléphone", "L'objet porté, l'antenne"]] + [
    ["text", "Le modèle et la couleur sont ceux du téléphone dans vos poches. Samsung S7 coyote, olive ou noir, Samsung S23, iPhone 17 Pro, V2 Enhanced, Xiaomi X3D, Sonim XP8, tablette durcie."],
    ["info", "Téléphone porté", [_wornName, "aucun"] select (_wornName isEqualTo "")],
    ["COMSPEC_ATAK_Shell", "auto", "Coque", [["AUTO", "auto"], ["JOUR", "day"], ["NUIT", "night"]], "AUTO : coque de nuit après le coucher du soleil"] call _profSegment,
    ["segment", "Antenne (port SMA)", [["AUCUNE", { ["unmount"] call comspec_atak_native_fnc_antenna; [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }, (["get"] call comspec_atak_native_fnc_antenna) isEqualTo []]]
        + ((["owned"] call comspec_atak_native_fnc_antenna) apply {
            [toUpper ((_x select 2) select [8]), compile format ["['mount', '%1'] call comspec_atak_native_fnc_antenna; [{ ['SETTINGS'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;", _x select 0],
                ((["get"] call comspec_atak_native_fnc_antenna) param [0, ""]) isEqualTo (_x select 0)]
        }), "Antenne en plus pour un meilleur réseau, si vous en avez une dans l'inventaire : courte, fouet ou patch (patch : à l'arrêt seulement)"],
    ["COMSPEC_ATAK_VehTablet", true, "", "Tablette dans les véhicules", "Dans un véhicule équipé ou au poste de commandement, la tablette de bord remplace le téléphone"] call _profSwitch,
    ["section", "Thème et écran", "Couleurs, fond d'écran, textes et luminosité"],
    // Thème (fn_theme, 3.1.0) : sombre, jour (surfaces claires) ou nuit JVN (vert phosphore) ; aussi dans le volet rapide.
    ["segment", "Thème", (["list"] call comspec_atak_native_fnc_theme) apply {
        _x params ["_t", "_v"];
        [_t, compile format ["['set', '%1'] call comspec_atak_native_fnc_theme;", _v], _v isEqualTo (toUpper (profileNamespace getVariable ["COMSPEC_ATAK_Theme", "DARK"]))]
    }, "SOMBRE : écran nuit. JOUR : surfaces claires pour le plein soleil. NUIT JVN : vert phosphore à faible luminance, compatible jumelles de vision nocturne"],
    ["COMSPEC_ATAK_Accent", "GREEN", "Couleur d'accent", [["VERT", "GREEN"], ["BLEU", "BLUE"], ["ORANGE", "ORANGE"], ["ROUGE", "RED"], ["VIOLET", "PURPLE"], ["SABLE", "SAND"]], "Boutons principaux, onglets, pastilles. En thème NUIT JVN, l'accent reste vert"] call _profSegment,
    // Thèmes rapides : accent, fond d'écran, flou et heure du verrouillage en un appui (chaque réglage reste modifiable).
    ["text", "<t size='0.9'>Thème rapide</t>  <t size='0.75' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Accent, fond d'écran et heure du verrouillage d'un coup</t>"],
    ["buttons", [
        // Chaque thème rapide pose aussi le thème de couleurs (fn_theme « set » redessine la page) : NUIT = nuit JVN, DÉSERT = jour.
        ["OPÉRATIONS", { { profileNamespace setVariable _x; } forEach [["COMSPEC_ATAK_Accent", "GREEN"], ["COMSPEC_ATAK_Wallpaper", "topo"], ["COMSPEC_ATAK_WallBlur", false], ["COMSPEC_ATAK_LockClockStyle", "light"]]; ["set", "DARK"] call comspec_atak_native_fnc_theme; }],
        ["NUIT", { { profileNamespace setVariable _x; } forEach [["COMSPEC_ATAK_Accent", "GREEN"], ["COMSPEC_ATAK_Wallpaper", "night"], ["COMSPEC_ATAK_WallBlur", true], ["COMSPEC_ATAK_LockClockStyle", "mil"]]; ["set", "NVG"] call comspec_atak_native_fnc_theme; }],
        ["DÉSERT", { { profileNamespace setVariable _x; } forEach [["COMSPEC_ATAK_Accent", "SAND"], ["COMSPEC_ATAK_Wallpaper", "desert"], ["COMSPEC_ATAK_WallBlur", false], ["COMSPEC_ATAK_LockClockStyle", "bold"]]; ["set", "DAY"] call comspec_atak_native_fnc_theme; }],
        ["ATHENA", { { profileNamespace setVariable _x; } forEach [["COMSPEC_ATAK_Accent", "GREEN"], ["COMSPEC_ATAK_Wallpaper", "athena"], ["COMSPEC_ATAK_WallBlur", false], ["COMSPEC_ATAK_LockClockStyle", "light"]]; ["set", "DARK"] call comspec_atak_native_fnc_theme; }]
    ]],
    ["COMSPEC_ATAK_Wallpaper", "topo", "Fond d'écran de l'accueil", [["TOPO", "topo"], ["NUIT", "night"], ["DÉSERT", "desert"], ["OLIVE", "olive"], ["SOMBRE", "dark"]]] call _profSegment,
    ["COMSPEC_ATAK_Wallpaper", "topo", "", [["SOAR", "soar"], ["ATHENA", "athena"], ["OPS", "ops"], ["AUCUN", "none"]]] call _profSegment,
    // Images du site (logo Athena, bannières), embarquées dans items.pbo (fn_wallPath, tools/gen_lock_assets.py).
    ["COMSPEC_ATAK_Wallpaper", "topo", "", [["AIGLE", "eagle"], ["LOGO", "logo"], ["ATAK", "atak"], ["SSE", "sse"], ["BASE SOF", "sof"]], "Aussi sur l'écran de verrouillage"] call _profSegment,
    ["COMSPEC_ATAK_WallBlur", false, "", "Flou du fond", "Fond d'écran flou et assombri, pour mieux lire les icônes"] call _profSwitch,
    ["COMSPEC_ATAK_TextScale", 1, "Taille du texte", [["PETIT", 0.9], ["NORMAL", 1], ["GRAND", 1.15], ["TRÈS GRAND", 1.3]]] call _profSegment,
    // Police : réglage CBA comspec_atak_native_font (aussi dans Options des addons > COMSPEC).
    ["segment", "Police", [["ROBOTO", "roboto"], ["PURISTA", "purista"]] apply {
        _x params ["_t", "_v"];
        [_t, compile format ["['comspec_atak_native_font', '%1', false, 'client'] call CBA_settings_fnc_set;", _v], _v isEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_FontFam", "roboto"])]
    }, "Police de tout le téléphone : Roboto Condensed par défaut, ou Purista"],
    ["COMSPEC_ATAK_IconDensity", 0, "Icônes du lanceur", [["GRANDES", -1], ["NORMALES", 0], ["SERRÉES", 1]], "Moins ou plus d'icônes par ligne"] call _profSegment,
    ["COMSPEC_ATAK_IconStyle", "real", "Style des icônes", [["RÉALISTES", "real"], ["PLATES", "flat"]], "RÉALISTES : icônes colorées aux coins arrondis posées sur le fond d'écran, comme un vrai téléphone. PLATES : tuiles d'avant"] call _profSegment,
    ["COMSPEC_ATAK_Brightness", 1, "Luminosité", [["30 %", 0.3], ["50 %", 0.5], ["75 %", 0.75], ["100 %", 1]], "Aussi dans le volet rapide (clic sur l'heure). Moins lumineux : moins visible de nuit et la batterie tient plus"] call _profSegment,
    ["COMSPEC_ATAK_NightMode", "OFF", "Mode nuit", [["NORMAL", "OFF"], ["ROUGE", "RED"], ["SOMBRE", "DIM"]], "Filtre rouge ou écran assombri, économise la batterie. Touche à assigner dans les contrôles COMSPEC ATAK"] call _profSegment,
    ["section", "Verrouillage et sécurité", "Si vous êtes capturé, l'ennemi peut brancher un câble DL-7 sur votre téléphone. Le code le protège : 3 essais, puis tout est effacé (un code peut être cassé en 2 min). Ce code est une mécanique de jeu : n'y mettez jamais un vrai code. Il protège aussi la lecture de votre téléphone par celui qui le ramasse."],
    ["COMSPEC_ATAK_LockScreen", true, "", "Écran de verrouillage", "Heure, date et dernières notifications, quand vous sortez le téléphone la première fois, après une minute rangé ou après un redémarrage"] call _profSwitch,
    ["COMSPEC_ATAK_LockClockStyle", "light", "Heure de l'écran de verrouillage", [["FINE", "light"], ["GRASSE", "bold"], ["MILITAIRE", "mil"]], "MILITAIRE : chiffres façon afficheur, dans la couleur d'accent"] call _profSegment,
    // Le code se choisit au clavier du téléphone, jamais affiché (fn_phoneOwn « pinSetup »). Aucun code par défaut.
    ["info", "Code du téléphone", [["Aucun", "Ancien code en lettres : remplacez-le"] select (((profileNamespace getVariable ["COMSPEC_ATAK_LockCode", ""]) isEqualType "") && {(profileNamespace getVariable ["COMSPEC_ATAK_LockCode", ""]) isNotEqualTo ""}), "Réglé"] select (["hasPin"] call comspec_atak_native_fnc_phoneOwn)],
    ["buttons", [
        [["DÉFINIR LE CODE", "CHANGER LE CODE"] select (["hasPin"] call comspec_atak_native_fnc_phoneOwn), { ["pinSetup", "set"] call comspec_atak_native_fnc_phoneOwn; }, !(["hasPin"] call comspec_atak_native_fnc_phoneOwn), true, "4 à 8 chiffres, demandés sur l'écran de verrouillage"],
        ["RETIRER LE CODE", { ["pinSetup", "remove"] call comspec_atak_native_fnc_phoneOwn; }, false, ["hasPin"] call comspec_atak_native_fnc_phoneOwn, "Le code actuel est demandé d'abord"]
    ]],
    ["field", "lockHint", "Indice du code (facultatif, 40 caractères)", profileNamespace getVariable ["COMSPEC_ATAK_LockHint", ""], "ENREGISTRER", { ["setHint", ["lockHint"] call comspec_atak_native_fnc_formValue] call comspec_atak_native_fnc_phoneOwn; }],
    ["switch", "Demander le code au déverrouillage", (profileNamespace getVariable ["COMSPEC_ATAK_PinAsk", false]) isEqualTo true, {
        // Sans code : le réglage ne ferait rien en silence ; on le fait choisir tout de suite.
        if !(["hasPin"] call comspec_atak_native_fnc_phoneOwn) exitWith { ["pinSetup", "set"] call comspec_atak_native_fnc_phoneOwn; };
        ["COMSPEC_ATAK_PinAsk", false] call (uiNamespace getVariable "COMSPEC_ATAK_ProfToggle");
    }, "Sur l'écran de verrouillage, en main. Code oublié : CODE OUBLIÉ le retire après 5 min, un admin ou un Zeus le retire tout de suite", false, "set_COMSPEC_ATAK_PinAsk"],
    ["buttons", [[["EFFACEMENT D'URGENCE", "CONFIRMER L'EFFACEMENT"] select (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_WipeArm", -10]) < 5), {
        // Deux appuis en moins de 5 s : rien ne s'efface par erreur.
        if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_WipeArm", -10]) < 5) then {
            uiNamespace setVariable ["COMSPEC_ATAK_WipeArm", -10];
            ["wipe"] call comspec_atak_native_fnc_phoneSecurity;
        } else {
            uiNamespace setVariable ["COMSPEC_ATAK_WipeArm", diag_tickTime];
            ["WARNING", "Appuyez encore pour tout effacer", 4, 40] call comspec_atak_native_fnc_notify;
        };
        [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    }]]],
    ["section", "Animations et sons", "Mouvements, clics et petites vibrations"],
    ["COMSPEC_ATAK_Animations", true, "", "Animations", "Interrupteur général : désactivé, plus aucun mouvement (onde à l'appui, icônes en cascade, volet rapide, transitions)"] call _profSwitch,
    ["COMSPEC_ATAK_PageFx", true, "", "Transitions entre les pages", "Les pages arrivent en fondu (sans flash sombre). Désactivé : changement de page immédiat, et les deux réglages suivants sont sans effet"] call _profSwitch,
    ["COMSPEC_ATAK_OpenSplash", true, "", "Ouverture depuis l'icône", "L'icône de l'app grandit vers le centre de l'écran à l'ouverture (icônes réalistes)"] call _profSwitch,
    ["COMSPEC_ATAK_TitleSlide", true, "", "Titre qui glisse", "Le titre de l'app arrive de la droite à l'ouverture et de la gauche au retour"] call _profSwitch,
    // Sortie et rangement animés (4.2.1) : réglage CBA par joueur (comspec_atak_native_phone_anim), le même que dans le menu CBA.
    ["switch", "Animation de sortie et de rangement", (missionNamespace getVariable ["comspec_atak_native_phone_anim", true]) isEqualTo true,
        { ["comspec_atak_native_phone_anim"] call (uiNamespace getVariable "COMSPEC_ATAK_CbaToggle"); },
        "En main, le téléphone monte en fondu à la sortie et redescend au rangement (0,2 s). Désactivé : il apparaît et disparaît d'un coup. La miniature portée ne bouge pas (aussi dans les options CBA, ATAK · Affichage)"],
    ["COMSPEC_ATAK_CrackFx", true, "", "Fêlures détaillées", "Écran abîmé : motifs de fêlures, de bris et de dalle (lignes, encre, pixels morts), selon la cause, différents à chaque choc. Désactivé : seules les textures simples restent"] call _profSwitch,
    ["COMSPEC_ATAK_PressFx", true, "", "Icône qui s'enfonce à l'appui", "L'icône d'une app s'enfonce légèrement sous le doigt"] call _profSwitch,
    ["COMSPEC_ATAK_TapFeedback", true, "", "Clic et vibration des appuis", "Petit clic et légère vibration à chaque appui (sans son en mode discrétion)"] call _profSwitch,
    ["COMSPEC_ATAK_HandHint", true, "", "Rappel « prendre en main » quand le téléphone est porté", "Petite icône de main et Ctrl+Maj+U dans la barre du téléphone porté. Désactivé : écran sans indication, plus immersif"] call _profSwitch,
    ["COMSPEC_ATAK_LauncherSearch", true, "", "Recherche dans le lanceur", "Champ « Rechercher une app » en haut de la liste des apps (en main) : la liste se filtre pendant la frappe, Entrée ouvre la première trouvée, Échap efface"] call _profSwitch,
    ["section", "Accessoires", "Batterie externe, filtre d'écran JVN, module DL-7"],
    ["switch", format ["Batterie externe sur la plaque%1", ["", format [" (%1 %%)", round (missionNamespace getVariable ["COMSPEC_ATAK_PowerBankCharge", 100])]] select (missionNamespace getVariable ["COMSPEC_ATAK_PowerBankOn", false])],
        missionNamespace getVariable ["COMSPEC_ATAK_PowerBankOn", false],
        { [["attach", "detach"] select (missionNamespace getVariable ["COMSPEC_ATAK_PowerBankOn", false])] call comspec_atak_native_fnc_powerBank; [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }],
    ["switch", "Filtre d'écran JVN", ["filter"] call comspec_atak_native_fnc_screenGlow,
        { ["toggle"] call comspec_atak_native_fnc_screenGlow; [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }],
    // Affichage situation sous JVN (Overwatch connect, fn_ecotiApplyHudSetting) : même case que le menu CBA, profil compris.
    ["switch", "Infos tactiques dans les JVN", (missionNamespace getVariable ["comspec_overwatch_ecoti_hud", false]) isEqualTo true,
        {
            if (isNil "comspec_overwatch_connect_fnc_ecotiApplyHudSetting") exitWith {};
            [!((missionNamespace getVariable ["comspec_overwatch_ecoti_hud", false]) isEqualTo true)] call comspec_overwatch_connect_fnc_ecotiApplyHudSetting;
            [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
        },
        "Alliés, repères et véhicules dans la vue quand vos JVN sont allumées. Marche aussi sans Athena"],
    ["COMSPEC_ATAK_ModuleTint", "olive", "Module DL-7", [["OLIVE", "olive"], ["NOIR", "black"], ["TAN", "tan"]], "Couleur du module de l'app Extraction"] call _profSegment
];
_perso pushBack (["patch", "Patch au dos", format ["Porté : %1", (["name", profileNamespace getVariable ["COMSPEC_ATAK_Patch", "none"]] call comspec_atak_native_fnc_patchCatalog) regexReplace ["[<>&]", ""]]] call _fold);
if (["patch"] call _foldOpen) then {
    _perso append [
        ["COMSPEC_ATAK_Patch", "none", "Patch au dos", [["AUCUN", "none"], ["FRANCE", "fr"], ["FRANCE BD", "fr_sub"], ["USA", "us"], ["USA BD", "us_ir"]]] call _profSegment,
        ["COMSPEC_ATAK_Patch", "none", "", [["CRÂNE", "skull"], ["CROIX", "crusader"], ["ALIEN", "alien"], ["COMSPEC", "comspec"], ["NOMINATIF", "name"]]] call _profSegment,
        ["COMSPEC_ATAK_Patch", "none", "", [["INSIGNE DE L'UNIFORME", "uniform"]], "Patch velcro visible avec « Voir l'arrière ». BD = basse visibilité. NOMINATIF : votre nom, ou le texte ci-dessous. Pour un insigne d'un mod ou de la mission (RHS, 3CB, unités...) : « Choisir un insigne » ci-dessous"] call _profSegment,
        // Tous les patchs (nos patchs, insignes CfgUnitInsignia des mods et de la mission) : page PATCHES dédiée avec
        // recherche, sources et vignettes (fn_pagePatches), retour aux Réglages par la touche retour.
        ["person", ([player] call comspec_atak_native_fnc_patchTexture) select 0, format ["<t font='COMSPEC_BarlowBold'>Patch porté : %1</t><br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Recherche, nos patchs, insignes des mods et de la mission</t>",
            (["name", profileNamespace getVariable ["COMSPEC_ATAK_Patch", "none"]] call comspec_atak_native_fnc_patchCatalog) regexReplace ["[<>&]", ""]], [], [1, 1, 1, 1]],
        ["buttons", [[format ["CHOISIR UN INSIGNE (%1 disponibles)", count (["list"] call comspec_atak_native_fnc_patchCatalog)], { [{ ["PATCHES"] call comspec_atak_native_fnc_navigate; }] call comspec_atak_native_fnc_nextFrame; }, true]]],
        ["edit", "patchText", "Texte du patch nominatif", profileNamespace getVariable ["COMSPEC_ATAK_PatchText", ""]],
        ["buttons", [["ENREGISTRER LE TEXTE", {
            private _t = ["patchText"] call comspec_atak_native_fnc_formValue;
            if (_t isEqualType "") then { profileNamespace setVariable ["COMSPEC_ATAK_PatchText", (trim _t) select [0, 14]]; [] call comspec_atak_native_fnc_saveProfile; ["Patch nominatif enregistré", 2] call comspec_atak_native_fnc_toast; };
        }]]],
        ["buttons", [["VOIR L'ARRIÈRE DU TÉLÉPHONE", { [{ [] call comspec_atak_native_fnc_backView; }] call comspec_atak_native_fnc_nextFrame; }]]]
    ];
};
// Téléphone accroché au gilet : seulement si le serveur autorise l'ATAK en 3D (désactivé par défaut).
if (missionNamespace getVariable ["comspec_atak_native_phone_3d", false]) then {
    _perso pushBack (["vest", "Téléphone sur le gilet", ["Non accroché", "Accroché au torse"] select ((profileNamespace getVariable ["COMSPEC_ATAK_VestMount", "off"]) isNotEqualTo "off")] call _fold);
    if (["vest"] call _foldOpen) then {
        _perso append [
            ["COMSPEC_ATAK_VestMount", "off", "Téléphone sur le gilet", [["NON", "off"], ["GAUCHE", "left"], ["CENTRE", "center"], ["DROITE", "right"]], "Le téléphone est fixé sur votre torse et les autres joueurs le voient, quel que soit le gilet"] call _profSegment,
            ["COMSPEC_ATAK_VestDepth", 0.19, "Épaisseur du gilet", [["FIN", 0.15], ["NORMAL", 0.19], ["ÉPAIS", 0.23]], "Éloigne le téléphone du torse s'il s'enfonce dans le gilet"] call _profSegment,
            ["COMSPEC_ATAK_VestFace", "back", "Sens sur le gilet", [["DOS ET PATCH", "back"], ["ÉCRAN", "screen"]], "Dos visible (pochette fermée, patch visible) ou écran visible"] call _profSegment
        ];
    };
};
_perso pushBack (["dock", "Dock", format ["%1 app(s) en raccourci en bas de l'écran (6 au plus)", count _dockNow]] call _fold);
if (["dock"] call _foldOpen) then {
    {
        _perso pushBack ["switch", _x get "name", (_x get "id") in _dockNow, compile format ["['%1'] call (uiNamespace getVariable 'COMSPEC_ATAK_DockToggle');", _x get "id"]];
    } forEach (([] call comspec_atak_native_fnc_appList) select { [_x] call comspec_atak_native_fnc_appVisible && {!(_x getOrDefault ["wip", false])} });
    _perso pushBack ["buttons", [["DOCK PAR DÉFAUT", { profileNamespace setVariable ["COMSPEC_ATAK_DockApps", []]; [] call comspec_atak_native_fnc_saveProfile; [{ [] call comspec_atak_native_fnc_dockRender; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }]]];
};
_perso pushBack ["buttons", [["TOUT RÉINITIALISER (APPARENCE)", {
    { profileNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_Accent", "COMSPEC_ATAK_Theme", "COMSPEC_ATAK_Shell", "COMSPEC_ATAK_ShellTint", "COMSPEC_ATAK_ModuleTint", "COMSPEC_ATAK_Patch", "COMSPEC_ATAK_PatchText", "COMSPEC_ATAK_VestMount", "COMSPEC_ATAK_VestDepth", "COMSPEC_ATAK_VestFace", "COMSPEC_ATAK_PhoneModel", "COMSPEC_ATAK_CarryStyle", "COMSPEC_ATAK_VehTablet", "COMSPEC_ATAK_Wallpaper", "COMSPEC_ATAK_WallBlur", "COMSPEC_ATAK_TextScale", "COMSPEC_ATAK_IconDensity", "COMSPEC_ATAK_IconStyle", "COMSPEC_ATAK_PageFx", "COMSPEC_ATAK_OpenSplash", "COMSPEC_ATAK_TitleSlide", "COMSPEC_ATAK_PressFx", "COMSPEC_ATAK_DockApps", "COMSPEC_ATAK_LockClockStyle", "COMSPEC_ATAK_HandHint", "COMSPEC_ATAK_LauncherSearch"];
    [] call comspec_atak_native_fnc_saveProfile;
    [{ [] call comspec_atak_native_fnc_layoutApply; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
}]]];
// Taille du plein écran : réglage CBA par joueur (comspec_atak_native_full_scale), le même que dans le menu CBA.
uiNamespace setVariable ["COMSPEC_ATAK_SetFullScale", {
    params ["_v"];
    ["comspec_atak_native_full_scale", _v, false, "client"] call CBA_settings_fnc_set;
    [{ [] call comspec_atak_native_fnc_layoutApply; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
}];
private _fullNow = missionNamespace getVariable ["comspec_atak_native_full_scale", 1];
if !(_fullNow isEqualType 0) then { _fullNow = 1; };
private _place = [
    ["COMSPEC_ATAK_MiniScale", 1, "Taille du mini", [["PETIT", 0.8], ["NORMAL", 1], ["GRAND", 1.2]]] call _profSegment,
    ["segment", "Taille en plein écran", [["60 %", 0.6], ["70 %", 0.7], ["80 %", 0.8], ["90 %", 0.9], ["100 %", 1]] apply {
        _x params ["_t", "_v"];
        [_t, compile format ["[%1] call (uiNamespace getVariable 'COMSPEC_ATAK_SetFullScale');", _v], (abs (_fullNow - _v)) < 0.05]
    }, "Taille du téléphone en main : il reste centré, 100 % = taille normale (aussi dans les options CBA, ATAK · Affichage)"],
    ["COMSPEC_ATAK_Orientation", "PORTRAIT", "Sens du mini", [["VERTICAL", "PORTRAIT"], ["HORIZONTAL", "LANDSCAPE"]]] call _profSegment,
    ["segment", "Coin de l'écran", [[["HAUT G", "TL"], ["MILIEU G", "ML"], ["BAS G", "BL"]]] call _anchorSeg],
    ["segment", "", [[["HAUT D", "TR"], ["MILIEU D", "MR"], ["BAS D", "BR"]]] call _anchorSeg],
    ["segment", "", [[["HAUT CENTRE", "TC"], ["BAS CENTRE", "BC"]]] call _anchorSeg, "BAS CENTRE : le téléphone est tout en bas de l'écran, au milieu"],
    ["buttons", [["DÉPLACER À LA SOURIS (MODE DISPOSITION)", { [{ ["ARRANGE_ON"] call comspec_atak_native_fnc_phoneDrag; }] call comspec_atak_native_fnc_nextFrame; }, true]]],
    ["buttons", [["REMETTRE LE TÉLÉPHONE EN PLACE", {
        { profileNamespace setVariable [_x, [0, 0]]; } forEach ["COMSPEC_ATAK_Offset_MINI_PORTRAIT", "COMSPEC_ATAK_Offset_MINI_LANDSCAPE", "COMSPEC_ATAK_Offset_FULL_LANDSCAPE"];
        [] call comspec_atak_native_fnc_saveProfile;
        [{ [] call comspec_atak_native_fnc_layoutApply; ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    }]]]
];
private _map = [
    ["COMSPEC_ATAK_Labels", true, "native_map_labels", "Indicatifs des unités", "Nom à côté de chaque allié"] call _profSwitch,
    ["COMSPEC_ATAK_LabelStyle", "FULL", "Étiquettes des unités", [["COMPLET", "FULL"], ["INDICATIF SEUL", "CALLSIGN"], ["AUCUN", "NONE"]], "Encadré à côté de l'icône : indicatif, puis distance et azimut depuis vous en zoom rapproché. Contacts ennemis bordés de rouge, avec l'heure et qui les a signalés"] call _profSegment,
    ["COMSPEC_ATAK_MarkerTags", true, "native_marker_tags", "Coordonnées des marqueurs", "Nom et coordonnées sous le marqueur, en zoom rapproché"] call _profSwitch,
    ["comspec_atak_native_map_marker_style", "plate", "Noms des marqueurs", [["PLAQUE", "plate"], ["PILULE", "pill"], ["CARTOUCHE", "card"], ["CONTOUR", "outline"], ["CLASSIQUE", "classic"]], "Marqueurs et cibles verrouillées (LOCK) : plaque, pilule, étiquette reliée avec distance et azimut, texte avec contour ou texte Arma d'origine"] call _cbaSegment,
    ["comspec_atak_native_map_place_style", "tactical", "Noms des lieux", [["TACTIQUE", "tactical"], ["ÉTIQUETTE", "tag"], ["DISCRET", "quiet"], ["CLASSIQUE", "classic"], ["CAPITALES", "small"]], "Villes, villages, lieux-dits et sites : majuscules avec contour, étiquette sombre, petit texte clair, couleurs de la carte Arma ou petites capitales avec un point turquoise (TACTIQUE s'affiche ainsi sur le fond ARDOISE)"] call _cbaSegment,
    ["COMSPEC_ATAK_MapDrawStyle", "TACTICAL", "Marqueurs et traits", [["AUTO", "AUTO"], ["TACTIQUE", "TACTICAL"], ["CLASSIQUE", "STANDARD"]], "TACTIQUE : pions ronds avec lettre d'équipe, petites plaques à chasse fixe, routes orange épaisses, zones hachurées, mesure en tirets verts, cercle fin autour des marqueurs nommés. STANDARD : symboles OTAN et style choisi ci-dessus. AUTO : TACTIQUE sur le fond ARDOISE, STANDARD sur les autres"] call _profSegment,
    ["comspec_atak_native_map_show_marker_text", "", "Afficher les noms des marqueurs", "Les icônes restent, seul le texte disparaît (aussi dans CALQUES)", true] call _cbaSwitch,
    ["comspec_atak_native_map_show_cities", "", "Afficher les villes", "", true] call _cbaSwitch,
    ["comspec_atak_native_map_show_villages", "", "Afficher les villages", "", true] call _cbaSwitch,
    ["comspec_atak_native_map_show_locals", "", "Afficher les lieux-dits", "", true] call _cbaSwitch,
    ["comspec_atak_native_map_show_sites", "", "Afficher les sites militaires", "Aéroports, bases et camps militaires", true] call _cbaSwitch,
    ["COMSPEC_ATAK_Compass", true, "native_compass", "Boussole", "Boussole et direction en haut à gauche"] call _profSwitch,
    ["COMSPEC_ATAK_CompassStyle", "CLASSIC", "Style de la boussole", [["CLASSIQUE", "CLASSIC"], ["CADRAN", "CARD"], ["FLÈCHE", "ARROW"], ["BANDEAU", "TAPE"]], "CLASSIQUE : boussole fixe, l'aiguille indique votre direction. CADRAN : le cadran tourne, le haut est toujours devant vous. FLÈCHE : l'aiguille seule. BANDEAU : réglette graduée en haut à gauche"] call _profSegment,
    ["COMSPEC_ATAK_AllyFilter", "ALL", "Alliés affichés", [["TOUS", "ALL"], ["MON GROUPE", "GROUP"], ["MON ORBAT", "ORBAT"]], ["", format ["ORBAT : %1", missionNamespace getVariable ["comspec_profile_unit", ""]]] select ((missionNamespace getVariable ["comspec_profile_unit", ""]) isNotEqualTo "")] call _profSegment,
    ["COMSPEC_ATAK_HideAllyAi", false, "", "Masquer les IA alliées", "Seuls les joueurs alliés restent sur la carte et le BFT, pas les IA"] call _profSwitch,
    ["COMSPEC_ATAK_UnitSymbols", "NATO", "Icônes des unités", [["OTAN MIL-STD", "NATO"], ["2525D", "MILSTD"], ["POINTS", "DOTS"]], "OTAN : symboles du jeu (cadre et type d'unité, niveau des chefs). 2525D : symboles complets (affiliation, dimension, type d'après le rôle, le véhicule et l'ORBAT du site, échelon, QG, état, textes en vue rapprochée)"] call _profSegment,
    ["COMSPEC_ATAK_AllyColor", "BLUE", "Couleur des alliés", [["BLEU", "BLUE"], ["VERT", "GREEN"], ["BLANC", "WHITE"], ["JAUNE", "YELLOW"], ["ROSE", "PINK"], ["FUCHSIA", "FUCHSIA"], ["LIME", "LIME"], ["VIOLET", "VIOLET"], ["TURQUOISE", "TURQ"], ["JAUNE FLUO", "NEON"]]] call _profSegment,
    ["COMSPEC_ATAK_SelfColor", "CYAN", "Ma couleur", [["CYAN", "CYAN"], ["BLEU", "BLUE"], ["VERT", "GREEN"], ["BLANC", "WHITE"], ["ORANGE", "ORANGE"]]] call _profSegment,
    ["COMSPEC_ATAK_SelfIcon", "", "Mon icône (vue par tous)", [["FLÈCHE", ""], ["INF", "b_inf"], ["RECO", "b_recon"], ["MÉDIC", "b_med"], ["PC", "b_hq"], ["SOUTIEN", "b_support"]]] call _profSegment,
    ["COMSPEC_ATAK_MapToolbarScale", 1, "Barre d'outils de la carte", [["PETITE", 0.75], ["NORMALE", 1], ["GRANDE", 1.3]], "Boutons à droite de la carte : centrer, suivre, zoom, marqueur, noms"] call _profSegment,
    ["COMSPEC_ATAK_GridDigits", 6, "Précision des grilles", [["6 CHIFFRES", 6], ["8 CHIFFRES", 8], ["10 CHIFFRES", 10]], "100 m, 10 m ou 1 m"] call _profSegment,
    ["section", "Taille et fond de carte", "Taille des textes et des icônes, type de fond de carte"],
    // Réglettes de 80 à 220 % (lues par fn_mapOnDraw, fn_pageMap et fn_planDraw, bornées à 0,8-2,2).
    ["slider", "Textes de la carte", round (100 * (profileNamespace getVariable ["COMSPEC_ATAK_MapTextScale", 1.25])), { profileNamespace setVariable ["COMSPEC_ATAK_MapTextScale", (_this select 0) / 100]; }, "Indicatifs, coordonnées, étiquettes, panneaux d'info", false, "", {}, "", [80, 220]],
    ["slider", "Icônes de la carte", round (100 * (profileNamespace getVariable ["COMSPEC_ATAK_MapIconScale", 1.25])), { profileNamespace setVariable ["COMSPEC_ATAK_MapIconScale", (_this select 0) / 100]; }, "Unités, marqueurs, charges, points de passage", false, "", {}, "", [80, 220]],
    ["COMSPEC_ATAK_MapStyle", [] call comspec_atak_native_fnc_mapStyle, "Fond de carte", [["TOPO", "TOPO"], ["CLAIR", "LIGHT"], ["SOMBRE", "DARK"], ["NUIT", "NIGHT"]], ""] call _profSegment,
    // Deuxième rangée : vues satellite (texture du terrain, capture HD là où elle existe) et capture seule.
    ["COMSPEC_ATAK_MapStyle", [] call comspec_atak_native_fnc_mapStyle, "", [["ARDOISE", "SLATE"], ["CLASSIQUE", "SAT"], ["HYBRIDE", "HYBRID"]] + ([[], [["SATELLITE", "PHOTO"]]] select ((["info"] call comspec_atak_native_fnc_photoMap) isNotEqualTo [])), "Carte topo d'Arma, version claire, sombre, bleu nuit, ardoise (bleu-gris sombre, routes turquoise), classique (vue satellite du terrain d'Arma), hybride (classique avec routes et pistes), ou satellite (la vraie photo de la carte, capturée par COMSPEC : mod COMSPEC ATAK Photo ou téléchargée depuis Athena)"] call _profSegment,
    ["COMSPEC_ATAK_PhotoRelief", true, "Relief des bâtiments", [["OUI", true], ["NON", false]], "Fond SATELLITE : ombre portée et contour de chaque bâtiment aux zooms proches"] call _profSegment,
    // Réglages de la carte gardés dans le profil, remis par défaut en deux appuis (les réglages CBA et mon icône, vue
    // par les autres, ne bougent pas).
    ["buttons", [[["RÉTABLIR LA CARTE PAR DÉFAUT", "CONFIRMER : CARTE PAR DÉFAUT"] select (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_MapResetArm", -10]) < 5), {
        if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_MapResetArm", -10]) < 5) then {
            uiNamespace setVariable ["COMSPEC_ATAK_MapResetArm", -10];
            { profileNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_Labels", "COMSPEC_ATAK_LabelStyle", "COMSPEC_ATAK_MarkerTags", "COMSPEC_ATAK_Compass", "COMSPEC_ATAK_CompassStyle", "COMSPEC_ATAK_AllyFilter", "COMSPEC_ATAK_HideAllyAi", "COMSPEC_ATAK_UnitSymbols", "COMSPEC_ATAK_AllyColor", "COMSPEC_ATAK_SelfColor", "COMSPEC_ATAK_MapToolbarScale", "COMSPEC_ATAK_GridDigits", "COMSPEC_ATAK_MapTextScale", "COMSPEC_ATAK_MapIconScale", "COMSPEC_ATAK_MapStyle", "COMSPEC_ATAK_MapDrawStyle", "COMSPEC_ATAK_LblEni", "COMSPEC_ATAK_LblGrid", "COMSPEC_ATAK_LblBgAlpha"];
            // Fond des noms (fn_mapPrefs « nameBg ») : à part, la liste ci-dessus est vérifiée telle quelle par les tests.
            { profileNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_NameBgAlpha", "COMSPEC_ATAK_NameBgTone"];
            uiNamespace setVariable ["COMSPEC_ATAK_NameBgCfg", []];
            [] call comspec_atak_native_fnc_saveProfile;
            ["Réglages de la carte rétablis", 2] call comspec_atak_native_fnc_toast;
        } else {
            uiNamespace setVariable ["COMSPEC_ATAK_MapResetArm", diag_tickTime];
        };
        [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    }, false, true, "Indicatifs, couleurs, boussole, coordonnées, tailles et fond de carte reviennent aux réglages d'origine"]]]
];
// Fond des noms (niveau, couleur, opacité) juste sous les réglettes de taille des textes et des icônes.
_map insert [(_map findIf { (_x param [1, ""]) isEqualTo "Icônes de la carte" }) + 1, ["rows", "names"] call comspec_atak_native_fnc_mapPrefs];
// Cartes hors ligne : fond PHOTO de la carte en cours téléchargé depuis Athena (état, téléchargement, effacement).
_map append (["rows"] call comspec_atak_native_fnc_photoMap);
// Bandeaux coupés par type (profil COMSPEC_ATAK_NotifMuted, aussi réglable depuis le web) : gardés dans le centre seulement.
uiNamespace setVariable ["COMSPEC_ATAK_MuteToggle", {
    params ["_t"];
    private _m = profileNamespace getVariable ["COMSPEC_ATAK_NotifMuted", []];
    if !(_m isEqualType []) then { _m = []; };
    _m = +_m;
    if (_t in _m) then { _m = _m - [_t]; } else { _m pushBack _t; };
    profileNamespace setVariable ["COMSPEC_ATAK_NotifMuted", _m];
    [] call comspec_atak_native_fnc_saveProfile;
    [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
}];
private _mutedTypes = profileNamespace getVariable ["COMSPEC_ATAK_NotifMuted", []];
if !(_mutedTypes isEqualType []) then { _mutedTypes = []; };
private _notifTypes = [["Messages", "MESSAGE", "Messagerie et appels"], ["Tactique", "TACTICAL", "Contacts, alertes, missions de tir"], ["Avertissements", "WARNING", "Batterie, liaison, réseau…"], ["Erreurs", "ERROR", ""], ["Réussites", "SUCCESS", "Envoi confirmé, photo transmise…"], ["Informations", "INFO", ""]];
private _notifRows = _notifTypes apply {
    _x params ["_lbl", "_t", "_h"];
    ["switch", _lbl, !(_t in _mutedTypes), compile format ["['%1'] call (uiNamespace getVariable 'COMSPEC_ATAK_MuteToggle');", _t], _h, false]
};
private _alerts = [
    ["COMSPEC_ATAK_Notifications", true, "", "Bandeaux de notification", "Tous les bandeaux en haut de l'écran (ils restent dans le centre de notifications)"] call _profSwitch,
    ["COMSPEC_ATAK_Toasts", true, "", "Petits messages en bas", "Confirmations brèves (« Code enregistré », « Numéro copié »…)"] call _profSwitch,
    ["COMSPEC_ATAK_Vibrate", true, "native_vibrate", "Vibration", "À chaque nouveau message ou alerte"] call _profSwitch,
    ["COMSPEC_ATAK_BftAlerts", true, "", "Alertes BFT du groupe", "Équipier hors ligne, inconscient ou qui ne répond plus"] call _profSwitch,
    ["COMSPEC_ATAK_Silent", false, "", "Mode discrétion", "Aucun son, seulement la vibration. Touche à assigner dans les contrôles COMSPEC ATAK"] call _profSwitch
];
// Bandeaux par type, seulement quand les bandeaux sont actifs.
if (profileNamespace getVariable ["COMSPEC_ATAK_Notifications", true]) then {
    _alerts append ([["section", "Bandeaux par type", "Un type coupé reste visible dans le centre de notifications"]] + _notifRows);
};
private _real = [];
private _live = [];
if (_bridge) then {
    _real append [
        ["section", "Réalisme", ["Réglages roleplay de la communauté", format ["%1 réglage(s) imposé(s) par %2, toutes rubriques confondues. Athena : Configuration ATAK, Contrôle serveur.", _lockedCount, ["votre communauté", _tenantName] select (_tenantName isNotEqualTo "")]] select (_lockedCount > 0)],
        ["comspec_overwatch_roleplay_enabled", "rp_enabled", "Mode roleplay", "Autorise les simulations cochées ci-dessous : chacune garde sa propre valeur"] call _cbaSwitch,
        ["comspec_overwatch_roleplay_network_failures", "rp_network_failures", "Coupures réseau", "Selon le terrain et les relais"] call _cbaSwitch,
        ["comspec_overwatch_roleplay_sensor_failures", "rp_sensor_failures", "Défauts des capteurs médicaux", "Sans effet pour l'instant : aucune simulation ne lit ce réglage"] call _cbaSwitch,
        ["comspec_overwatch_roleplay_visual_effects", "rp_visual_effects", "Effets visuels de dégradation", "Écran parasité quand la liaison faiblit", true] call _cbaSwitch,
        ["comspec_overwatch_link_degrade_sim", "rp_link_degrade", "Liaison instable simulée", "Retards et petites coupures"] call _cbaSwitch,
        ["comspec_overwatch_show_link_strip", "rp_data_bar", "Barre d'infos sous la carte", "Vitesse, altitude, direction, coordonnées"] call _cbaSwitch
    ];
    private _dmg = ["atak_realism"] call comspec_atak_native_fnc_tenantRule;
    private _lvl = missionNamespace getVariable ["comspec_overwatch_atak_realism", 0];
    _real pushBack ["check", "off", "Dégâts au téléphone", ["aucun", "peut s'éteindre", "écran destructible", "téléphone destructible"] param [_lvl, str _lvl], [format ["Imposé par %1 sur Athena", ["votre communauté", _tenantName] select (_tenantName isNotEqualTo "")], "Réglage du serveur"] select (_dmg isEqualTo "player")];
    if !(_tenantOn) then { _real pushBack ["text", "<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Réglages de la communauté pas encore reçus : connectez-vous à Athena.</t>"]; };
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
    [] call comspec_atak_native_fnc_saveProfile;
    [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
}];
private _medRow = {
    params ["_k", "_label"];
    (["rule", _k] call comspec_atak_native_fnc_medShow) params ["_on", "_locked", "_by"];
    ["switch", _label, _on, compile format ["['%1'] call (uiNamespace getVariable 'COMSPEC_ATAK_MedToggle');", _k], ["", format ["Imposé par %1", _by]] select _locked, _locked]
};
_real append [
    ["section", "Téléphone", "Dégâts selon l'endroit où il est porté, saleté de l'écran"],
    ["info", "Dégâts localisés", ["<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>coupés par le serveur</t>", "actifs : main, gilet, poche ou sac ; balles, éclats, explosions, chutes, eau"] select (missionNamespace getVariable ["comspec_atak_native_damage_sim", true])],
    if (missionNamespace getVariable ["comspec_atak_native_dirt_sim", true]) then {
        ["COMSPEC_ATAK_DirtFx", true, "native_dirt", "Saleté et sang sur l'écran", "Poussière, traces de doigts, sang (soins, blessures, mains en sang). Nettoyage gratuit et illimité : action ACE, app Profil ou Réglages"] call _profSwitch
    } else { ["info", "Saleté et sang sur l'écran", "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>coupés par le serveur</t>"] },
    ["section", "Données médicales affichées", "Une donnée masquée disparaît de tout l'ATAK"]
];
{ _real pushBack (_x call _medRow); } forEach (["fields"] call comspec_atak_native_fnc_medShow);
_real pushBack ["section", "Où les afficher", "Masquer un endroit retire toutes les données médicales de cet endroit"];
{ _real pushBack (_x call _medRow); } forEach (["contexts"] call comspec_atak_native_fnc_medShow);

private _hp = [] call comspec_atak_native_fnc_deviceHealth;
private _lq = [] call comspec_atak_native_fnc_linkQuality;
// Notification MPU à l'écran pendant l'alternat (fn_mpuHud) : le même réglage CBA que dans les paramètres de l'addon (ATAK · Radio de données (MPU5)).
private _mpuHudOn = (missionNamespace getVariable ["comspec_atak_native_mpu_hud", true]) isEqualTo true;
private _hw = [
    ["section", "Connexions", "Mode avion et Bluetooth (aussi dans l'app Bluetooth et le centre de notifications)"],
    ["switch", "Mode avion", [] call comspec_atak_native_fnc_airplaneMode, { ["toggle"] call comspec_atak_native_fnc_airplaneMode; [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }, "Coupe tout le réseau : ni synchro, ni SMS, ni BFT (les envois attendent le retour du réseau)"],
    ["switch", "Bluetooth", (["state"] call comspec_atak_native_fnc_btAction) get "on", { ["toggle"] call comspec_atak_native_fnc_btAction; [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }, "Jumelage par code et partage de sons avec les téléphones proches (marche aussi en mode avion)"],
    ["section", "Radio", "Ce que l'écran du jeu montre pendant que vous parlez à la radio"],
    ["comspec_atak_native_mpu_hud", "", "Notification MPU à l'écran", [
        "Coupée dans les paramètres de l'addon (même réglage : Échap > Options > Options des addons > COMSPEC > ATAK · Radio de données (MPU5)). Active, elle montre par exemple « MPU · voix par le maillage », « 2 sauts · 3 correspondants ».",
        "Pendant l'alternat, un encadré juste au-dessus du cadre ACRE, en bas à droite, dit si votre voix passe par la MPU5. Exemple : « MPU · voix par le maillage », « 2 sauts · 3 correspondants » ; en réception : « DUPONT via MPU ». Même réglage que dans les paramètres de l'addon."
    ] select _mpuHudOn, true] call _cbaSwitch,
    ["comspec_atak_native_mpu_hud_why", "", "Dire pourquoi la MPU n'est pas utilisée", "Une ligne grise quand votre voix ne passe pas par la MPU5. Exemple : « MPU : non utilisée (aucun relais) »", false] call _cbaSwitch,
    ["section", "Matériel et réseau", "Simulations réglées par le serveur (options CBA)"],
    ["info", "Dégâts du téléphone", [ "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>simulation coupée</t>", format ["%1 · usure %2 %%", ["<t color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>intact</t>", format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t>", _hp get "reason"]] select ((_hp get "state") isNotEqualTo "OK"), round ((_hp get "damage") * 100)]] select (missionNamespace getVariable ["comspec_atak_native_damage_sim", true])],
    ["buttons", [["NETTOYER L'ÉCRAN", { [{ ["start"] call comspec_atak_native_fnc_screenClean; }] call comspec_atak_native_fnc_nextFrame; }, false, ["can"] call comspec_atak_native_fnc_screenClean],
        ["NETTOYAGE COMPLET", { [{ ["deep"] call comspec_atak_native_fnc_screenClean; }] call comspec_atak_native_fnc_nextFrame; }, false, ["can"] call comspec_atak_native_fnc_screenClean]]],
    ["buttons", [["REDÉMARRER LE TÉLÉPHONE", { ["reboot"] call comspec_atak_native_fnc_deviceRepair; }]]],
    ["info", "Réseau simulé", [ "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>simulation coupée</t>", format ["%1 · %2", _lq get "label", [format ["%1 kbit/s", _lq get "kbps"], format ["%1 Mbit/s", ((_lq get "kbps") / 1000) toFixed 1]] select ((_lq get "kbps") >= 1000)]] select (_lq get "sim")]
];

// Applications : installées et désinstallées dans l'App Store (fn_pageStore), qui remplace les bascules « cacher l'app ».
private _appsAll = ([] call comspec_atak_native_fnc_appList) select { !((_x get "id") in ["Admin", "Toc"]) };
private _appsSt = _appsAll apply { ["state", _x get "id"] call comspec_atak_native_fnc_appInstall };
private _appsRows = [
    ["section", "Applications", "Installer, désinstaller et retrouver les apps du téléphone"],
    ["cells", [
        ["\z\comspec_atak_native\addons\main\data\ui_check.paa", [0.36, 0.78, 0.42, 1], "Installées", format ["<t color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>%1</t>", { _x in ["system", "required", "installed"] } count _appsSt], "Sur l'écran d'accueil et dans le dock"],
        ["\z\comspec_atak_native\addons\main\data\app_store.paa", [0.55, 0.6, 0.57, 1], "À installer", format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%1</t>", { _x isEqualTo "removed" } count _appsSt], "Disponibles dans l'App Store"]
    ], 2],
    ["buttons", [["OUVRIR L'APP STORE", { [{ ["STORE"] call comspec_atak_native_fnc_navigate; }] call comspec_atak_native_fnc_nextFrame; }, true]]]
];
private _imp = { _x in ["required", "forbidden"] } count _appsSt;
if (_imp > 0) then { _appsRows pushBack ["text", format ["<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1 app(s) imposée(s) ou interdite(s) par l'encadrement</t>", _imp]]; };

// Apps regroupées, profil de rôle et mode pilote (fn_appSpaces, fn_pilotMode). Le serveur peut imposer les deux premiers choix.
private _spLocked = ["locked"] call comspec_atak_native_fnc_appSpaces;
_appsRows pushBack ["section", "Lanceur et mode pilote", "Présenter les apps autrement, sans rien désinstaller"];
_appsRows pushBack ["switch", "Apps regroupées", ["on"] call comspec_atak_native_fnc_appSpaces, { ["setOn", !(["on"] call comspec_atak_native_fnc_appSpaces)] call comspec_atak_native_fnc_appSpaces; },
    ["Navigation et feux, Renseignement, Spécialités, Messages, Mission et téléphone, avec des sous-groupes", "Imposé par le serveur"] select _spLocked, _spLocked, "spacesOn"];
private _rolePref = ["rolePref"] call comspec_atak_native_fnc_appSpaces;
private _roleOpts = ["all", "auto", "pilot", "jtac", "medic", "infantry"] apply {
    [["roleName", _x] call comspec_atak_native_fnc_appSpaces, compile format ["['setRole', %1] call comspec_atak_native_fnc_appSpaces;", str _x], _x isEqualTo _rolePref, "Le lanceur ne montre que l'utile ; « Toutes les apps » montre le reste"]
};
_appsRows pushBack ["segment", "Profil de rôle", _roleOpts select [0, 3]];
_appsRows pushBack ["segment", "", _roleOpts select [3, 3]];
if (_rolePref isEqualTo "auto") then {
    private _det = ["detect"] call comspec_atak_native_fnc_appSpaces;
    _appsRows pushBack ["text", format ["<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%1</t>", [("Aucun rôle reconnu avec certitude : toutes les apps restent affichées") call comspec_atak_native_fnc_tr, format [("Rôle reconnu : %1") call comspec_atak_native_fnc_tr, (["roleName", _det] call comspec_atak_native_fnc_appSpaces) call comspec_atak_native_fnc_tr]] select (_det isNotEqualTo "")]];
};
private _piLocked = ["locked"] call comspec_atak_native_fnc_pilotMode;
_appsRows pushBack ["switch", "Mode pilote", ["on"] call comspec_atak_native_fnc_pilotMode, { ["setOn", !(["on"] call comspec_atak_native_fnc_pilotMode)] call comspec_atak_native_fnc_pilotMode; [] call (uiNamespace getVariable ["COMSPEC_ATAK_SettingsRerender", {}]); },
    ["Proposé en montant dans un aéronef : grande carte, appareils alliés, FIRES en un geste", "Imposé par le serveur"] select _piLocked, _piLocked, "pilotOn"];
if (["on"] call comspec_atak_native_fnc_pilotMode) then {
    _appsRows pushBack ["switch", "Ouvrir directement l'écran pilote à bord", profileNamespace getVariable ["COMSPEC_ATAK_PilotAuto", false], { ["COMSPEC_ATAK_PilotAuto", false] call (uiNamespace getVariable "COMSPEC_ATAK_ProfToggle"); }, "Le téléphone s'ouvre sur l'écran pilote, sans proposition", false, "pilotAuto"];
    _appsRows pushBack ["switch", "HUD discret", profileNamespace getVariable ["COMSPEC_ATAK_PilotHud", true], { ["COMSPEC_ATAK_PilotHud", true] call (uiNamespace getVariable "COMSPEC_ATAK_ProfToggle"); }, "Mission en cours et allié le plus proche, en haut de l'écran, téléphone rangé", false, "pilotHud"];
};

private _rule = ["require_equipment"] call comspec_atak_native_fnc_tenantRule;
private _require = if (_rule in ["on", "off"]) then { _rule isEqualTo "on" } else { missionNamespace getVariable ["comspec_atak_native_require_item", true] };
// Accès et touches : deux cartes d'état (item requis, téléphone sur soi), puis les raccourcis par groupe. Chaque
// touche se change ici (fn_keybinds : appui sur la touche affichée, puis sur la nouvelle touche) ou dans le menu CBA.
private _catalogN = count ([] call comspec_atak_native_fnc_deviceCatalog);
private _hasDev = [player, true] call comspec_atak_native_fnc_hasDevice;
private _access = [
    ["section", "Accès au téléphone", "Ce qu'il faut sur soi pour ouvrir l'ATAK"],
    ["cells", [
        ["\z\comspec_atak_native\addons\main\data\ui_check.paa", [[0.36, 0.78, 0.42, 1], [0.55, 0.6, 0.57, 1]] select !_require, "Item requis",
            format ["<t color='%1'>%2</t>", [(COMSPEC_ATAK_Th get "hexOk"), (COMSPEC_ATAK_Th get "hexTextMuted")] select !_require, [["NON", "OUI"] select _require, "IMPOSÉ"] select (_rule in ["on", "off"])],
            [["N'importe quel joueur peut ouvrir l'ATAK", format ["Un des %1 items reconnus, équipé ou dans le sac", _catalogN]] select _require,
                format ["Réglé par votre communauté : %1", ["pas d'item", "item obligatoire"] select _require]] select (_rule in ["on", "off"])],
        ["\z\comspec_atak_native\addons\main\data\ui_hand.paa", [[0.95, 0.67, 0.2, 1], [0.36, 0.78, 0.42, 1]] select _hasDev, "Sur vous",
            format ["<t color='%1'>%2</t>", [(COMSPEC_ATAK_Th get "hexWarn"), (COMSPEC_ATAK_Th get "hexOk")] select _hasDev, ["NON", "OUI"] select _hasDev],
            ["Aucun ATAK dans votre équipement", "Votre téléphone est bien dans l'équipement"] select _hasDev]
    ], 2]
];
private _capAct = ["capturing"] call comspec_atak_native_fnc_keybinds;
private _accent = ([] call comspec_atak_native_fnc_accent) select 0;
private _kbAll = ["list"] call comspec_atak_native_fnc_keybinds;
private _changed = 0;
{
    _x params ["_grp", "_gTitle", "_gSub", ["_folded", false]];
    private _items = _kbAll select { (_x select 1) isEqualTo _grp };
    private _open = true;
    if (_folded) then {
        _access pushBack ([_grp, _gTitle, _gSub] call _fold);
        _open = [_grp] call _foldOpen;
    } else {
        _access pushBack ["section", _gTitle, _gSub];
    };
    {
        _x params ["_id", "", "_title", "_help", "_icon", ["_wip", false]];
        (["get", _id] call comspec_atak_native_fnc_keybinds) params ["_cur", "_def"];
        if (_cur isNotEqualTo _def) then { _changed = _changed + 1; };
        if (_open) then {
            private _users = ["users", _cur, _id] call comspec_atak_native_fnc_keybinds;
            private _note = switch (true) do {
                case (_wip): { "<br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>En chantier (W.I.P), désactivé pour le moment</t>" };
                case (_capAct isEqualTo _id): { "<br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>Appuyez sur la nouvelle touche (avec Ctrl, Maj ou Alt si besoin). Échap : annuler.</t>" };
                case ((count _users) > 0): { format ["<br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>Même touche que : %1</t>", _users joinString ", "] };
                default { format ["<br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%1</t>", _help] };
            };
            private _keyBtn = switch (true) do {
                case (_wip): { ["W.I.P", {}, false, false, "En chantier, la touche ne fait rien pour le moment"] };
                case (_capAct isEqualTo _id): { ["APPUYEZ…", compile "['capture', ''] call comspec_atak_native_fnc_keybinds;", "red", true, "Échap ou nouvel appui : annuler"] };
                case (_cur isEqualTo []): { ["AUCUNE", compile format ["['capture', '%1'] call comspec_atak_native_fnc_keybinds;", _id], false, true, "Pas de touche : appuyez pour en choisir une"] };
                default { [toUpper (["name", _cur] call comspec_atak_native_fnc_keybinds), compile format ["['capture', '%1'] call comspec_atak_native_fnc_keybinds;", _id], true, true, "Appuyez, puis tapez la nouvelle touche"] };
            };
            _access pushBack ["person", format ["\z\comspec_atak_native\addons\main\data\%1.paa", _icon],
                format ["<t font='COMSPEC_BarlowBold'%3>%1</t>%4%2", _title, _note, ["", " color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'"] select _wip, ["", "  <t size='0.75' color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>W.I.P</t>"] select _wip],
                [_keyBtn, ["DÉFAUT", compile format ["['reset', '%1'] call comspec_atak_native_fnc_keybinds; [{ ['SETTINGS'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;", _id], false, !_wip && {_cur isNotEqualTo _def},
                    format ["Touche par défaut : %1", ["name", _def] call comspec_atak_native_fnc_keybinds]]],
                [[0.55, 0.6, 0.57, 1], _accent] select (!_wip && {_cur isNotEqualTo []})];
        };
    } forEach _items;
} forEach [
    ["PHONE", "Prendre le téléphone", "Sortir, ranger, prendre en main"],
    ["MINI", "Miniature et carte", "Téléphone porté dans un coin de l'écran"],
    ["QUICK", "Raccourcis rapides", "Sans ouvrir le téléphone"],
    ["WATCH", "Montre Garmin", "Les 5 boutons de la montre et ses raccourcis", true],
    ["DRONE", "Drone et marquage", "Drone appairé, réticule", true],
    ["GESTURE", "Gestes", "Gestes du personnage", true]
];
_access append [
    ["buttons", [[[format ["TOUTES LES TOUCHES PAR DÉFAUT (%1 changée(s))", _changed], "CONFIRMER : TOUCHES PAR DÉFAUT"] select (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_KeyResetArm", -10]) < 5), {
        if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_KeyResetArm", -10]) < 5) then {
            uiNamespace setVariable ["COMSPEC_ATAK_KeyResetArm", -10];
            [format ["%1 touche(s) remise(s) par défaut", ["resetAll"] call comspec_atak_native_fnc_keybinds], 2.5] call comspec_atak_native_fnc_toast;
        } else {
            uiNamespace setVariable ["COMSPEC_ATAK_KeyResetArm", diag_tickTime];
        };
        [{ ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    }, false, _changed > 0, "Deux appuis : remet toutes les touches de l'ATAK comme à l'installation"]]],
    ["text", "<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Les touches choisies ici sont les mêmes que dans Échap > Options > Contrôles > Configurer les addons > COMSPEC ATAK. En main, glissez la coque pour l'ajuster à partir du coin choisi.</t>"]
];

// Service Athéna : la question du premier lancement (Oui : COMSPEC Link et synchro ; Non : ATAK local).
// Mode full local imposé par le serveur (réglage comspec_atak_native_local_only) : plus de question.
private _athMode = [] call comspec_atak_native_fnc_athenaMode;
private _forcedLocal = missionNamespace getVariable ["comspec_atak_native_local_only", false];
private _athReady = _bridge && {[] call comspec_overwatch_connect_fnc_isReady};
private _athState = switch (true) do {
    case (_forcedLocal): { "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>mode full local (serveur) · aucune connexion ni donnée envoyée</t>" };
    case (_athMode isEqualTo "off"): { "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>ATAK local · aucune connexion ni donnée envoyée</t>" };
    case (_athMode isEqualTo ""): { "<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>pas encore choisi</t>" };
    case (_athReady): { format ["<t color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>compte lié</t>%1", ["", format [" · %1", missionNamespace getVariable ["comspec_tenant_name", ""]]] select ((missionNamespace getVariable ["comspec_tenant_name", ""]) isNotEqualTo "")] };
    default { "<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>compte pas encore lié</t>" };
};
private _athena = [
    ["section", "Service Athéna", "Votre unité utilise-t-elle Athéna ?"],
    if (_forcedLocal) then {
        ["switch", "Mode full local imposé par le serveur", true, {}, "Le serveur joue sans Athena : numéros, carnet et poste TOC restent dans config.json (app Numéros).", true]
    } else {
        ["segment", "Mon unité utilise Athéna", [
            ["OUI", { ["on"] call comspec_atak_native_fnc_athenaChoice; }, _athMode isEqualTo "on"],
            ["NON", { ["off"] call comspec_atak_native_fnc_athenaChoice; }, _athMode isEqualTo "off"]
        ], "Oui : COMSPEC Link, synchro avec le poste, compte lié. Non : ATAK local sur le serveur, rien n'est envoyé."]
    },
    ["info", "État", _athState],
    ["buttons", [
        ["LIER MON COMPTE", { ["link"] call comspec_atak_native_fnc_athenaChoice; }, true, _bridge && {_athMode isEqualTo "on"} && {!_athReady}]
    ]],
    ["text", "<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Aussi dans le menu Échap, bouton Service Athéna.</t>"]
];

// Synchronisation (même catégorie que le service Athéna) : où en est la liaison, ce qui attend, et les raccourcis vers
// les apps qui la règlent (Athena, Resynch, Réseau). L'état vient de fn_athenaStatus, comme l'app Athena.
private _st = [] call comspec_atak_native_fnc_athenaStatus;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _cat = _s getOrDefault ["setCat", ""];
private _query = _s getOrDefault ["setQuery", ""];
if (_athMode isEqualTo "on") then {
    (["state"] call comspec_atak_native_fnc_offline) params ["_offNow", "_offSync", "_offFor"];
    private _queue = count (missionNamespace getVariable ["COMSPEC_ATAK_NetQueue", []]);
    if (!isNil "comspec_overwatch_connect_fnc_outboxState") then { _queue = _queue + ((["get"] call comspec_overwatch_connect_fnc_outboxState) getOrDefault ["count", 0]); };
    private _resAt = missionNamespace getVariable ["COMSPEC_ATAK_ResynchAt", ""];
    private _steps = _st get "steps";
    _athena append [
        ["section", "Synchronisation", "Ce qui est envoyé au poste et ce qui attend le réseau"],
        ["status", "\z\comspec_atak_native\addons\main\data\app_athena.paa", _st get "color", _st get "title", _st get "text", _st get "action"],
        ["info", "Réseau", ["<t color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>en ligne</t>", format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>hors connexion depuis %1</t>", [format ["%1 s", round _offFor], format ["%1 min", floor (_offFor / 60)]] select (_offFor >= 60)]] select _offNow],
        ["info", "Dernière synchro", [_offSync, "—"] select (_offSync isEqualTo "")],
        ["info", "En attente d'envoi", ["<t color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>rien</t>", format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t>", _queue]] select (_queue > 0)],
        ["info", "Dernière resynchronisation", [_resAt, "jamais"] select (_resAt isEqualTo "")]
    ];
    // Volume échangé : lu en mémoire dans COMSPEC Link (fn_syncStats, sans requête), seulement sur cette page.
    if (_cat isEqualTo "ATHENA") then {
        private _ss = [] call comspec_atak_native_fnc_syncStats;
        if (_ss get "ok") then {
            private _ko = { params ["_b"]; [format ["%1 ko", round (_b / 1000)], format ["%1 Mo", (_b / 1e6) toFixed 1]] select (_b >= 1e6) };
            private _sp = (_ss get "span") max 1;
            _athena pushBack ["info", "Volume (5 dernières min)", format ["envoyé %1/min · reçu %2/min", [(_ss get "wOut") * 60 / _sp] call _ko, [(_ss get "wIn") * 60 / _sp] call _ko]];
        };
    };
    _athena pushBack ["fold", "Étapes de la liaison", format ["%1 sur %2 en ordre", { (_x select 1) isEqualTo "ok" } count _steps, count _steps], ["steps", !(_st get "logged")] call _foldOpen,
        compile format ["['steps', %1] call (uiNamespace getVariable 'COMSPEC_ATAK_SetFold');", !(_st get "logged")]];
    if (["steps", !(_st get "logged")] call _foldOpen) then {
        { _athena pushBack ["check", _x select 1, _x select 0, _x select 2, _x select 3]; } forEach _steps;
    };
    _athena pushBack ["buttons", [
        ["RESYNCH", { [{ ["RESYNCH"] call comspec_atak_native_fnc_navigate; }] call comspec_atak_native_fnc_nextFrame; }, (_queue > 0) || {_offSync isEqualTo ""}, _bridge, "Renvoie votre position, vos marqueurs, votre groupe et vos messages au poste"],
        ["APP ATHENA", { [{ ["ATHENA"] call comspec_atak_native_fnc_navigate; }] call comspec_atak_native_fnc_nextFrame; }, !(_st get "logged"), true, "Connexion au compte, fiche opérateur"],
        ["DÉTAIL", { [{ ["NETWORK"] call comspec_atak_native_fnc_navigate; }] call comspec_atak_native_fnc_nextFrame; }, false, true, "App Réseau : trafic par canal, envois en attente"]
    ]];
};

// À propos du téléphone, façon Android : modèle, identité, versions et contrôle de l'installation, sur cette page seulement
// (la DLL n'est interrogée qu'ici).
private _about = [];
private _build = missionNamespace getVariable ["COMSPEC_ATAK_NativeBuild", missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", "?"]];
if (_cat isEqualTo "ABOUT" || {_query isNotEqualTo ""}) then {
    ([player] call comspec_atak_native_fnc_phoneIdent) params [["_num", "—"], ["_imei", "—"], ["_mac", "—"]];
    private _linkWant = missionNamespace getVariable ["COMSPEC_ATAK_LinkExpected", ""];
    private _linkHave = ([] call comspec_atak_native_fnc_extVersion) regexReplace ["^[^0-9]*", ""];
    private _anom = missionNamespace getVariable ["COMSPEC_ATAK_Integrity", []];
    if !(_anom isEqualType []) then { _anom = []; };
    (missionNamespace getVariable ["COMSPEC_ATAK_LatestRemote", []]) params [["_lastAtak", ""], ["_lastLink", ""]];
    // Dossier des captures vérifié en tâche de fond à l'ouverture (une fois par minute au plus) ; la page se redessine.
    private _shot = uiNamespace getVariable ["COMSPEC_ATAK_ShotDirInfo", []];
    if ((diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_ShotDirAskAt", -1e9])) > 60 && {_shot isEqualTo [] || {(diag_tickTime - (_shot param [10, -1e9])) > 60}}) then {
        uiNamespace setVariable ["COMSPEC_ATAK_ShotDirAskAt", diag_tickTime];
        ["check", false] spawn comspec_atak_native_fnc_shotDir;
    };
    _about = [
        ["hero", "\z\comspec_atak_native\addons\main\data\app_settings.paa", format ["<t size='1.2' font='COMSPEC_BarlowBold'>%1</t><br/><t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>COMSPEC ATAK %2</t>", _modelName, _build]],
        ["section", "Téléphone", "Identité roleplay : le numéro est lié à la carte SIM"],
        ["info", "Indicatif", [player, true] call comspec_atak_native_fnc_unitCallsign],
        ["info", "Numéro", _num],
        ["info", "IMEI", _imei],
        ["info", "Adresse MAC", _mac],
        ["info", "Batterie", format ["%1 %%", round (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100])]],
        ["section", "Logiciel", "Versions installées et vérification de l'installation"],
        ["info", "COMSPEC ATAK", _build],
        ["info", "COMSPEC Link", switch (true) do {
            case (_linkHave isEqualTo ""): { "<t color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>DLL absente ou bloquée</t>" };
            case (_linkWant isNotEqualTo "" && {(_linkHave splitString " ") param [0, ""] isNotEqualTo _linkWant}): { format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1 (attendue : %2)</t>", _linkHave, _linkWant] };
            default { format ["<t color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>%1</t>", _linkHave] };
        }],
        ["info", "Dernière version publiée", [format ["ATAK %1 · Link %2", _lastAtak, _lastLink], "pas encore vérifiée"] select (_lastAtak isEqualTo "")],
        ["info", "Installation", ["<t color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>aucune anomalie</t>", format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1 anomalie(s) : voir l'app Debug</t>", count _anom]] select ((count _anom) > 0)],
        // Dépannage en un bouton (fn_deviceGuard) : plus besoin de supprimer le cache à la main dans Documents.
        ["section", "Dépannage", "Téléphone qui plante ou se fige : remet ses données locales au propre"],
        ["text", format ["<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%1</t>", ([
            "Efface la dernière page, les apps en quarantaine et la sauvegarde hors ligne, et met de côté les caches du dossier Documents. Votre compte, vos réglages et l'identifiant de cet appareil ne changent pas.",
            "Caméras en mode sans échec : le jeu s'est arrêté pendant le chargement du téléphone. Réparez vos données pour les rétablir."
        ] select (["safe"] call comspec_atak_native_fnc_deviceGuard)) call comspec_atak_native_fnc_tr]],
        ["buttons", [["RÉPARER MES DONNÉES", { ["repair", "Réglages"] call comspec_atak_native_fnc_deviceGuard; }, "amber"]]],
        // Dossier où les DLL cherchent les photos (réglage CBA « Dossier des captures d'écran », sinon profil Arma).
        ["section", "Photos", "Dossier où le téléphone cherche les captures du jeu"],
        ["text", format ["<t size='0.8'>%1</t>", ((["text"] call comspec_atak_native_fnc_shotDir) regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"]]],
        ["buttons", [["VÉRIFIER LE DOSSIER", { ["check", true] spawn comspec_atak_native_fnc_shotDir; }], ["OUVRIR LE DOSSIER CAPTURES", { ["open"] call comspec_atak_native_fnc_shotDir; }]]],
        ["buttons", [
            ["CRÉDITS", { [{ ["CREDITS"] call comspec_atak_native_fnc_navigate; }] call comspec_atak_native_fnc_nextFrame; }],
            ["DEBUG", { [{ ["DEBUG"] call comspec_atak_native_fnc_navigate; }] call comspec_atak_native_fnc_nextFrame; }, (count _anom) > 0],
            // Note de service du menu principal (accès, licence) : on range le téléphone pour lui laisser la souris.
            ["RELIRE LA LICENCE", {
                [] call comspec_atak_native_fnc_close;
                [{ if (!isNil "comspec_overwatch_connect_fnc_showBetaAccessNote") then { [true] call comspec_overwatch_connect_fnc_showBetaAccessNote; }; }, [], 0.1] call comspec_atak_native_fnc_waitAndExec;
            }, false, !isNil "comspec_overwatch_connect_fnc_showBetaAccessNote"]
        ]]
    ];
};

// Langue du mod : même réglage que CBA (COMSPEC > Langue / Language, en tête), libellés bilingues ; le changer redessine la page et les menus CBA (fn_langApply).
// ENGLISH (par défaut depuis 2.1.0), FRANÇAIS, puis les langues des mods de traduction chargés (fn_langList), puis AUTO.
private _langSet = missionNamespace getVariable ["comspec_atak_native_language", "en"];
private _langs = [] call comspec_atak_native_fnc_langList;
private _langBtns = [
    ["ENGLISH", { ["comspec_atak_native_language", "en", 0, "client"] call CBA_settings_fnc_set; }, _langSet isEqualTo "en"],
    ["FRANÇAIS", { ["comspec_atak_native_language", "fr", 0, "client"] call CBA_settings_fnc_set; }, _langSet isEqualTo "fr"]
];
{
    _x params ["_code", "_name"];
    _langBtns pushBack [toUpper _name, compile format ["['comspec_atak_native_language', '%1', 0, 'client'] call CBA_settings_fnc_set;", _code], _langSet isEqualTo _code];
} forEach (_langs select { (_x select 0) isNotEqualTo "en" });
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
    ["ATHENA", "Athéna et synchronisation", ["Non : ATAK local, aucune synchro", "Oui : liaison, synchro, resynchronisation", "Pas encore choisi"] select (["off", "on", ""] find _athMode), "app_athena", _athena],
    ["LANG", "Langue / Language", _langName, "ui_language", _lang],
    ["PERSO", "Personnalisation", "Thème, coque, fond d'écran, texte, dock", "ui_palette", _perso],
    ["PLACE", "Emplacement", "Coin, taille et sens du téléphone porté", "ui_rotate", _place],
    ["MAP", "Carte", "Indicatifs, couleurs, grilles, tailles, fond", "app_map", _map],
    ["ALERTS", "Alertes", "Notifications et vibration", "ui_vibrate", _alerts],
    ["APPS", "Applications", format ["App Store : %1 app(s) à installer", { _x isEqualTo "removed" } count _appsSt], "ui_apps", _appsRows],
    ["HW", "Matériel et réseau", "Mode avion, Bluetooth, dégâts, réseau simulé", "app_network", _hw + _live],
    ["REAL", "Réalisme", ["Données médicales, saleté de l'écran", "Roleplay, données médicales, réglages imposés"] select _bridge, "app_status", _real],
    ["ACCESS", "Accès et touches", format ["Item requis, %1 raccourcis clavier réglables ici", count _kbAll], "ui_hand", _access],
    ["ABOUT", "À propos du téléphone", format ["%1 · COMSPEC ATAK %2", _modelName, _build], "ui_help", _about]
];
private _sel = (_cats select { (_x select 0) isEqualTo _cat }) param [0, []];
private _openCat = { params ["_k"]; compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['setCat', '%1']; [{ ['SETTINGS'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;", _k] };
private _toHome = { private _st = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]; _st set ['setCat', '']; _st set ['setQuery', '']; [{ ['SETTINGS'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; };
// Recherche : un mot (français ou langue du téléphone) cherché dans le libellé, l'aide et les choix de chaque réglage.
private _search = {
    private _q = ["setSearch"] call comspec_atak_native_fnc_formValue;
    if !(_q isEqualType "") then { _q = ""; };
    private _st = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap];
    _st set ['setQuery', trim _q];
    _st set ['setCat', ''];
    [{ ['SETTINGS'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
};
private _rows = [];
// Touche retour dans une catégorie ou une recherche : revient à l'accueil des Réglages au lieu de quitter l'app.
if ((count _sel) > 0 || {_query isNotEqualTo ""}) then { _s set ["backSub", ["SETTINGS", _toHome]]; };
if ((count _sel) isEqualTo 0) then {
    // Accueil : état de la liaison en tête (un appui ouvre la catégorie qui la règle), recherche, puis les catégories.
    _rows pushBack ["status", "\z\comspec_atak_native\addons\main\data\app_settings.paa", _st get "color", _st get "title", _st get "text", _st get "action"];
    private _needFix = !(_st get "logged") && {_athMode isNotEqualTo "off"};
    _rows pushBack ["buttons", [[switch (true) do { case (_athMode isEqualTo "off"): { "SERVICE ATHÉNA" }; case (_needFix): { "RÉGLER LA CONNEXION" }; default { "SYNCHRONISATION" }; }, ["ATHENA"] call _openCat, _needFix]]];
    if (_lockedCount > 0) then { _rows pushBack ["text", format ["<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1 réglage(s) imposé(s) par %2</t>", _lockedCount, ["votre communauté", _tenantName] select (_tenantName isNotEqualTo "")]]; };
    _rows pushBack ["field", "setSearch", "Rechercher un réglage", _query, "CHERCHER", _search, true];
    if (_query isEqualTo "") then {
        {
            _x params ["_k", "_t", "_sub", "_icon"];
            // Ligne de liste haute avec chevron (composant « row », 3.1.0) : toute la ligne ouvre la catégorie.
            _rows pushBack ["row", format ["\z\comspec_atak_native\addons\main\data\%1.paa", _icon], _t, _sub, "", [_k] call _openCat, COMSPEC_ATAK_Th get "accentInk"];
        } forEach _cats;
    } else {
        // Texte d'une ligne pour la recherche : balises retirées, en minuscules, en français et traduit.
        private _txt = {
            private _parts = [];
            switch (_this select 0) do {
                case "switch": { _parts = [_this param [1, ""], _this param [4, ""]]; };
                case "segment": { _parts = [_this param [1, ""], _this param [3, ""]] + ((_this param [2, []]) apply { _x select 0 }); };
                case "info";
                case "section";
                case "fold": { _parts = [_this param [1, ""], _this param [2, ""]]; };
                case "buttons": { _parts = (_this param [1, []]) apply { _x select 0 }; };
                case "person": { _parts = [_this param [2, ""]]; };
                case "text": { _parts = [_this param [1, ""]]; };
                case "edit";
                case "check": { _parts = [_this param [2, ""]]; };
            };
            _parts = _parts select { _x isEqualType "" && {_x isNotEqualTo ""} };
            toLower (((_parts + (_parts apply { _x call comspec_atak_native_fnc_tr })) joinString " ") regexReplace ["<[^>]*>", " "])
        };
        private _q = toLower _query;
        private _hits = 0;
        {
            _x params ["_k", "_t", "", "_icon", "_r"];
            private _found = [];
            private _inSec = false;
            private _prev = false;
            private _prevType = "";
            {
                private _type = _x select 0;
                private _hit = ((_x call _txt) find _q) >= 0;
                if (_type isEqualTo "section") then { _inSec = _hit; };
                // Suite d'un choix sur plusieurs lignes, ou boutons d'un champ ou d'un texte : suivent la ligne d'avant.
                if (!_hit && {_prev} && {(_type isEqualTo "segment" && {(_x param [1, ""]) isEqualTo ""}) || {_type isEqualTo "buttons" && {_prevType in ["text", "edit", "person"]}}}) then { _hit = true; };
                if ((_hit || _inSec) && {!(_type in ["hero", "status"])}) then { _found pushBack _x; };
                _prev = _hit || _inSec;
                _prevType = _type;
            } forEach _r;
            if ((count _found) > 0) then {
                _hits = _hits + count _found;
                _rows pushBack ["row", format ["\z\comspec_atak_native\addons\main\data\%1.paa", _icon], _t, format ["%1 réglage(s)", count _found], "", [_k] call _openCat, COMSPEC_ATAK_Th get "accentInk"];
                _rows append _found;
            };
        } forEach _cats;
        if (_hits isEqualTo 0) then { _rows pushBack ["empty", "\z\comspec_atak_native\addons\main\data\ui_help.paa", "Aucun réglage trouvé", "Essayez un autre mot, ou ouvrez une catégorie"]; };
        _rows pushBack ["buttons", [["EFFACER LA RECHERCHE", _toHome]]];
    };
} else {
    // En-tête d'app (composant « header », 3.1.0) : titre de la catégorie, bouton retour vers l'accueil des Réglages.
    _rows pushBack ["header", _sel select 1, _sel select 2, [], _toHome];
    _rows append (_sel select 4);
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
