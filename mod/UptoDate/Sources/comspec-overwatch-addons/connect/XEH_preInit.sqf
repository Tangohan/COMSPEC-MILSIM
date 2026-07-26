// Compat modpack : si Mavic/mavik lit ses settings CBA avant enregistrement
// (ou si l'enregistrement a echoue), eviter le spam variable indefinie.
private _comspecCompatMavic = {
    if (isNil "mavic_setting_enableConnectionDistance") then {
        missionNamespace setVariable ["mavic_setting_enableConnectionDistance", false];
    };
    if (isNil "mavic_setting_maxConnectionDistance") then {
        missionNamespace setVariable ["mavic_setting_maxConnectionDistance", 6000];
    };
    if (isNil "mavic_setting_showInterface") then {
        missionNamespace setVariable ["mavic_setting_showInterface", true];
    };
    if (isNil "mavic_setting_vanillaInterface") then {
        missionNamespace setVariable ["mavic_setting_vanillaInterface", false];
    };
};
[] call _comspecCompatMavic;

if (!isNil "comspec_overwatch_connect_fnc_log") then {
    ["INFO", "Compat", format [
        "Mavic setting isNil=%1 | ZEN addAttribute isNil=%2",
        isNil "mavic_setting_enableConnectionDistance",
        isNil "zen_attributes_fnc_addAttribute"
    ]] call comspec_overwatch_connect_fnc_log;
};

// Stub ZEN attributes si ZEN incomplet dans le pack (évite zen_attributes_fnc_addAttribute nil)
if (isNil "zen_attributes_fnc_addAttribute") then {
    zen_attributes_fnc_addAttribute = {
        // no-op compat — ZEN partiel / mauvais ordre de chargement
        false
    };
    if (!isNil "comspec_overwatch_connect_fnc_log") then {
        ["WARN", "Compat", "Stub zen_attributes_fnc_addAttribute installé (ZEN incomplet ou absent)"] call comspec_overwatch_connect_fnc_log;
    };
};

[
    "comspec_overwatch_enabled", "CHECKBOX",
    ["Enable Overwatch", "Athena connection"],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_api_url", "EDITBOX",
    ["URL Athena", "Base du portail (ex. https://athena.ttrd.fr/public) — sans slash final, avec /public si le site l’utilise"],
    "COMSPEC Overwatch", "https://athena.ttrd.fr/public"
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_api_key", "EDITBOX",
    ["Athena access key", "Provided by admin (required in production). Leave empty locally if server does not require key."],
    "COMSPEC Overwatch", ""
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_tenant_id", "EDITBOX",
    ["Community identifier", "Leave empty if Athena server already has a default community. Otherwise, numeric value provided by admin."],
    "COMSPEC Overwatch", ""
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_update_interval", "SLIDER",
    ["Frequency (sec)", "Delay between general synchronization cycles (longer = less load)"],
    "COMSPEC Overwatch", [1, 600, 10, 0]
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_position_interval", "SLIDER",
    ["Position interval (s)", "Time between two position checks (longer = fewer requests)"],
    "COMSPEC Overwatch", [1, 60, 3, 2]
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_batch_interval", "SLIDER",
    ["Network batching (s)", "Minimum delay between two position sends to Athena (longer = fewer requests)"],
    "COMSPEC Overwatch", [1, 60, 3, 1]
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_position_threshold", "SLIDER",
    ["Distance threshold (m)", "Send if movement > X m"],
    "COMSPEC Overwatch", [1, 50, 5, 0]
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_terminal_mode", "LIST",
    ["Terminal detection (position)", "How to recognize tactical phone (S7 Android) to authorize position reporting. Object slot = ItemAndroid equipped (like GPS/NVG). Inventory = ItemAndroidMisc simply carried (cTab object). Both accepts either."],
    "COMSPEC Overwatch",
    [
        [0, 1, 2],
        ["Object slot only (ItemAndroid)", "Inventory presence (ItemAndroidMisc)", "Both (default)"],
        2
    ],
    false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_playtime_enabled", "CHECKBOX",
    ["Record playtime", "Sends time spent in mission to portal (mod connected)"],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_playtime_report_interval", "SLIDER",
    ["Playtime report (minutes)", "Frequency of cumulative send to portal"],
    "COMSPEC Overwatch", [2, 60, 5, 0]
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_vehicle_mode", "CHECKBOX",
    ["Vehicle detail", "Send 3D orientation and speed when player is in vehicle"],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_sync_map_markers", "CHECKBOX",
    ["Synchronize map markers", "Sends to Athena markers created / modified / deleted in game"],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_profile_enabled", "CHECKBOX",
    ["Profiler (debug)", "Measures execution time of critical loops/PerFrameHandlers (position, CAS, markers). Report visible via debug panel. Zero cost when disabled."],
    "COMSPEC Overwatch", false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_notif_sound", "LIST",
    ["Notification sound", "Played with alerts (messaging, orders, connection). Medical emergencies use a dedicated sound, including in Silent (vibration) mode. Only Mute cuts everything. Discreet mode does not cut these sounds."],
    "COMSPEC Overwatch",
    [
        ["silent_vib", "stalker", "health", "mute"],
        ["Silent (vibration)", "Stalker", "Health alert", "Mute"],
        0
    ],
    false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_webbrowser_enabled", "CHECKBOX",
    ["Advanced tablet (integrated screen)", "Opens Overwatch tablet with Chromium tactical screen (inspired by cTab). Disable to force classic view."],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_screen_notifications", "CHECKBOX",
    ["Afficher les notifications à l’écran", "Affiche les bandeaux Overwatch et les lignes de chat système (ex. « Alerte médicale transmise… ») en bas à gauche de la carte. Désactivé par défaut pour une immersion plus calme. Les sons suivent le réglage « Son des notifications ». Les alertes restent disponibles dans la tablette (cloche / journal Alertes)."],
    "COMSPEC Overwatch", false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_quiet_mode", "CHECKBOX",
    ["Mode discret — masquer les alertes à l’écran", "Masque temporairement les bandeaux lorsque « Afficher les notifications à l’écran » est activé. Les sons (réglage « Son des notifications ») continuent de jouer sauf si Muet. Les alertes restent disponibles dans la tablette (cloche / journal Alertes). Les écrans de connexion et la tablette elle-même restent utilisables."],
    "COMSPEC Overwatch", false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_milsim_ui", "CHECKBOX",
    ["Mode milsim — désactiver les aides d’interface", "Immersion : coupe les anomalies de suivi (ex. immobile), les messages système de confort et les bandeaux / chat Overwatch (même si « Afficher les notifications à l’écran » est activé). La liaison Athena, la synchronisation de position et la tablette restent actives. Les alertes médicales et les ordres restent dans la tablette (sons selon le réglage « Son des notifications »)."],
    "COMSPEC Overwatch", false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_require_item", "CHECKBOX",
    ["Exiger un équipement pour synchroniser et ouvrir l’interface", "Si activé : la synchronisation Athena et l’ouverture de l’interface Overwatch ne fonctionnent que si vous portez l’équipement choisi ci-dessous. Si désactivé : sync et interface restent disponibles sans objet précis."],
    "COMSPEC Overwatch", false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_required_item", "LIST",
    ["Équipement requis", "Objet d’inventaire nécessaire lorsque l’exigence d’équipement est activée. Sans effet si l’exigence est désactivée."],
    "COMSPEC Overwatch",
    [
        ["ItemAndroid", "ItemcTab", "ItemMicroDAGR", "ACE_microDAGR", "ItemGPS", "ItemWatch"],
        ["Téléphone Android (cTab / ATAK)", "Tablette cTab", "MicroDAGR (cTab)", "MicroDAGR (ACE)", "GPS", "Montre"],
        0
    ],
    false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_required_item_custom", "EDITBOX",
    ["Équipement personnalisé (optionnel)", "Laisser vide pour utiliser la liste ci-dessus. Sinon, indiquez le nom technique de l’objet fourni par votre pack d’équipement (remplace alors le choix de la liste)."],
    "COMSPEC Overwatch", ""
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_atak_ui_only", "CHECKBOX",
    ["Interface Overwatch uniquement via ATAK Enhanced", "Désactive la tablette Overwatch hors d’ATAK Enhanced (touche K, menus ACE associés, ouverture automatique). La liaison Athena, la synchronisation et les fonctions dans ATAK Enhanced restent actives."],
    "COMSPEC Overwatch", false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_athena_link_help", "CHECKBOX",
    ["Windows reminder - link Athena account", "At launch, if account not yet linked, displays Windows alert with instructions. Uncheck to stop seeing this reminder."],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_reset_beta_nda", "CHECKBOX",
    [
        "Réafficher l’accord d’accès anticipé",
        "Cochez puis validez : l’accord de confidentialité (accès anticipé) s’affiche à nouveau tout de suite si possible, sinon au prochain retour au menu principal. La case se décoche ensuite automatiquement. Votre inscription à l’accès anticipé n’est pas annulée."
    ],
    "COMSPEC Overwatch",
    false,
    false,
    {
        params ["_value"];
        if (!_value) exitWith {};
        0 spawn {
            // Laisser CBA / le profil se stabiliser (preInit ou Options)
            uiSleep 0.05;
            if !(isNil "comspec_overwatch_connect_fnc_resetBetaNdaAck") then {
                [] call comspec_overwatch_connect_fnc_resetBetaNdaAck;
            };
            if !(isNil "CBA_fnc_setSetting") then {
                ["comspec_overwatch_reset_beta_nda", false, 0, "client", true] call CBA_fnc_setSetting;
            };
        };
    }
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_ace_menus", "CHECKBOX",
    [
        "Menus ACE Overwatch",
        "Ajoute les entrées Overwatch / ATAK dans le menu d’interaction ACE (sur soi). Désactivez si votre pack de mods affiche des erreurs ACE au démarrage (Mavic, IED, ZEN…). Les raccourcis clavier et la tablette restent disponibles."
    ],
    "COMSPEC Overwatch",
    false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_log_level", "LIST",
    [
        "Journal technique (fichier RPT)",
        "Écrit le diagnostic Overwatch dans le journal Arma (RPT). « Détaillé » aide au dépannage pack de mods ; laisser sur « Normal » en jeu courant."
    ],
    ["COMSPEC Overwatch", "Diagnostic"],
    [
        [0, 1, 2, 3, 4],
        ["Muet", "Erreurs seulement", "Alertes", "Normal", "Détaillé"],
        3
    ],
    false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_log_to_file", "CHECKBOX",
    [
        "Écrire aussi dans un fichier journal",
        "En plus du journal Arma (RPT), enregistre les mêmes lignes dans un fichier texte séparé (COMSPECExtension.log), plus simple à retrouver et à envoyer au support. Sans effet si le journal RPT est réglé sur « Muet »."
    ],
    ["COMSPEC Overwatch", "Diagnostic"],
    true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_radio_proximity_enabled", "CHECKBOX",
    ["Surveillance radio à proximité", "Détecte qui émet près de vous (ou de l’opérateur surveillé) et remonte l’état vers Athena. Nécessite un module radio (ACRE2 ou TFAR). Sans module : pastilles grisées."],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_radio_proximity_radius", "SLIDER",
    ["Radio proximity radius (m)", "Contacts and transmissions listed within this radius around reference operator"],
    "COMSPEC Overwatch", [10, 300, 75, 0]
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_radio_proximity_interval", "SLIDER",
    ["Radio scan interval (s)", "Proximity list update frequency (local tablet cache, no network spam)"],
    "COMSPEC Overwatch", [1, 10, 2, 1]
] call CBA_fnc_addSetting;

// Affichage camps sur Tacmap (inspiré Athena Remastered ATH_showEast/Guer/Civ)
[
    "comspec_overwatch_show_opfor", "CHECKBOX",
    ["Afficher l’adversaire sur la carte", "Les positions du camp adverse restent visibles pour les observateurs sur Athena / Tacmap."],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_show_independent", "CHECKBOX",
    ["Afficher les indépendants sur la carte", "Les positions des indépendants restent visibles pour les observateurs sur Athena / Tacmap."],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_show_civilian", "CHECKBOX",
    ["Afficher les civils sur la carte", "Les positions des civils restent visibles pour les observateurs sur Athena / Tacmap."],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

// Raccourcis (CBA — personnalisables dans Options > Contrôles > Extension Addon)
// K           → Tablette Athena
// Ctrl+K      → Messagerie
// Ctrl+Shift+K → Ancien menu hub (vues Overwatch)
//
// IDs : comspec_menu_hub ouvrait le hub sur K — on le réutilise pour la tablette
// afin que les joueurs qui ont déjà K restent sur la bonne action.
[
    "COMSPEC Overwatch", "comspec_menu_hub", ["Tablette Athena", "Ouvrir la tablette Athena Overwatch (K)"],
    {
        if !([false] call comspec_overwatch_connect_fnc_canOpenOverwatchUi) exitWith { false };
        0 spawn { [] call comspec_overwatch_connect_fnc_webBrowserShow; };
        true
    },
    "",
    [0x25, [false, false, false]], // DIK_K
    false, 0, false
] call CBA_fnc_addKeybind;

[
    "COMSPEC Overwatch", "comspec_menu_chat", ["Messagerie", "Ouvrir la messagerie dans la tablette Athena (Ctrl+K)"],
    {
        if !([false] call comspec_overwatch_connect_fnc_canOpenOverwatchUi) exitWith { false };
        0 spawn { ["chat"] call comspec_overwatch_connect_fnc_openTabletView; };
        true
    },
    "",
    [0x25, [false, true, false]], // Ctrl+DIK_K  — [shift, ctrl, alt]
    false, 0, false
] call CBA_fnc_addKeybind;

// ID v2 : l’ancien menu hub était sur K — maintenant Ctrl+Shift+K → Apps tablette
[
    "COMSPEC Overwatch", "comspec_menu_hub_csk", ["Applications tablette", "Ouvrir le menu Applications de la tablette Athena (Ctrl+Shift+K)"],
    {
        if !([false] call comspec_overwatch_connect_fnc_canOpenOverwatchUi) exitWith { false };
        0 spawn { ["apps"] call comspec_overwatch_connect_fnc_openTabletView; };
        true
    },
    "",
    [0x25, [true, true, false]], // Ctrl+Shift+DIK_K
    false, 0, false
] call CBA_fnc_addKeybind;

// === ATAK Tactique - Raccourcis configurables ===

[
    "comspec_atak_enable_shortcuts", "CHECKBOX",
    ["Activer raccourcis ATAK", "Active les raccourcis clavier rapides pour rapports et POI. Désactivez si conflit avec cTab ou autres mods."],
    ["COMSPEC Overwatch", "ATAK Tactique"], false  // Désactivé par défaut
] call CBA_fnc_addSetting;

[
    "COMSPEC Overwatch - ATAK", "comspec_atak_quick_report", ["Rapport Contact Rapide", "Soumettre rapidement un rapport CONTACT ennemi"],
    {
        if (!(missionNamespace getVariable ["comspec_atak_enable_shortcuts", false])) exitWith { false };
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        ["CONTACT", "IMMEDIATE", "Contact ennemi", "Ennemi détecté"] call comspec_overwatch_connect_fnc_submitTacticalReport;
        true
    },
    "",
    [], // Pas de touche par défaut - utilisateur doit configurer
    false, 0, false
] call CBA_fnc_addKeybind;

[
    "COMSPEC Overwatch - ATAK", "comspec_atak_quick_poi", ["POI Rapide", "Marquer un Point d'Intérêt à position actuelle"],
    {
        if (!(missionNamespace getVariable ["comspec_atak_enable_shortcuts", false])) exitWith { false };
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        ["POI observé", "OTHER", "UNKNOWN", "POSSIBLE", "Marqué depuis terrain"] call comspec_overwatch_connect_fnc_createPOI;
        true
    },
    "",
    [], // Pas de touche par défaut - utilisateur doit configurer
    false, 0, false
] call CBA_fnc_addKeybind;

[
    "COMSPEC Overwatch - ATAK", "comspec_atak_quick_medevac", ["MEDEVAC Rapide", "Demander évacuation médicale urgente"],
    {
        if (!(missionNamespace getVariable ["comspec_atak_enable_shortcuts", false])) exitWith { false };
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        ["URGENT", 1, 0, 0, "POSSIBLE_ENEMY", "SMOKE", "GREEN"] call comspec_overwatch_connect_fnc_requestMEDEVAC;
        true
    },
    "",
    [], // Pas de touche par défaut
    false, 0, false
] call CBA_fnc_addKeybind;

[
    "COMSPEC Overwatch - ATAK", "comspec_atak_quick_qrf", ["QRF Rapide", "Demander renfort d'urgence"],
    {
        if (!(missionNamespace getVariable ["comspec_atak_enable_shortcuts", false])) exitWith { false };
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        ["TROOPS_IN_CONTACT", "IMMEDIATE", "Besoin renfort immédiat", "SQUAD", 0, "ENGAGED"] call comspec_overwatch_connect_fnc_requestQRF;
        true
    },
    "",
    [], // Pas de touche par défaut

    false, 0, false
] call CBA_fnc_addKeybind;



// Cache position / tracking tactique
missionNamespace setVariable ["COMSPEC_lastPos", [0,0,0], true];
missionNamespace setVariable ["COMSPEC_lastName", "", true];
missionNamespace setVariable ["COMSPEC_lastRole", "", true];
missionNamespace setVariable ["COMSPEC_lastRadio", "", true];
missionNamespace setVariable ["COMSPEC_lastMedical", "", true];
missionNamespace setVariable ["COMSPEC_lastMedicalAlertKind", "", false];
missionNamespace setVariable ["COMSPEC_MedicalAlertsSeen", [], false];
missionNamespace setVariable ["COMSPEC_MedicalAlertsBootstrapped", false, false];
missionNamespace setVariable ["COMSPEC_MedicalAlerts", [], false];
missionNamespace setVariable ["COMSPEC_lastSendTime", 0, true];
missionNamespace setVariable ["COMSPEC_ApiBackoffUntil", 0, false];
missionNamespace setVariable ["COMSPEC_ApiBackoffSec", 2, false];
missionNamespace setVariable ["COMSPEC_PositionTrail", [], true];
missionNamespace setVariable ["COMSPEC_ImmobileSince", 0, true];
missionNamespace setVariable ["COMSPEC_ImmobileAlerted", false, false];
missionNamespace setVariable ["COMSPEC_IncoherentAlertAt", -1e9, false];

[
    "comspec_overwatch_athena_feed_snapshot", "CHECKBOX",
    ["Aperçus caméra automatiques", "Envoie périodiquement un aperçu (capture d’écran) de votre caméra casque ou drone connecté vers le panneau Cams Athena. Désactivé par défaut — utilisez plutôt les actions ACE ou une photo ATAK. Pas de vidéo en direct."],
    ["COMSPEC Overwatch", "Cams"], false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_athena_feed_interval", "SLIDER",
    ["Intervalle aperçus (s)", "Délai minimum entre deux aperçus automatiques casque / drone"],
    ["COMSPEC Overwatch", "Cams"], [15, 120, 35, 0]
] call CBA_fnc_addSetting;

// Badges UI — liaison Athena
missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
missionNamespace setVariable ["COMSPEC_LinkDetail", "", false];
missionNamespace setVariable ["COMSPEC_LastPositionSync", -1, false];
missionNamespace setVariable ["COMSPEC_LastHealthOk", -1, false];
missionNamespace setVariable ["COMSPEC_LastLatencyMs", -1, false];
missionNamespace setVariable ["COMSPEC_LastLatencyAt", -1, false];

// Bus d'évènements + C2 + Intel Engine
missionNamespace setVariable ["COMSPEC_EventBus", createHashMap, true];
missionNamespace setVariable ["COMSPEC_Orders", [], true];
missionNamespace setVariable ["COMSPEC_OrderLog", [], true];
missionNamespace setVariable ["COMSPEC_IntelStore", [], true];
missionNamespace setVariable ["COMSPEC_IntelHeatmap", createHashMap, true];
missionNamespace setVariable ["COMSPEC_RadioReplay", [], true];
missionNamespace setVariable ["COMSPEC_Comms_Channel", "SQUAD", true];
missionNamespace setVariable ["COMSPEC_Comms_Priority", "ROUTINE", true];
missionNamespace setVariable ["COMSPEC_OrdersSeen", [], false];

// Restaure l’indicatif profil dès le preInit (avant les lectures CAS / recon)
private _savedCallsign = trim (profileNamespace getVariable ["COMSPEC_Callsign", ""]);
if (!(_savedCallsign isEqualTo "")) then {
    missionNamespace setVariable ["COMSPEC_Callsign", _savedCallsign, false];
};

// Rôle tactique + file d’alertes tablette
private _savedRole = trim (profileNamespace getVariable ["COMSPEC_Role", ""]);
if (!(_savedRole isEqualTo "")) then {
    missionNamespace setVariable ["COMSPEC_Role", _savedRole, false];
};
missionNamespace setVariable ["COMSPEC_HtmlAlerts", [], false];

// Petit modèle (vue classique idd 9973) — désactivé temporairement (bascule auto coupait la tablette Athena).
missionNamespace setVariable ["comspec_overwatch_classic_tablet_enabled", false, false];

// ────────────────────────────────────────────────────────────────────────────
// Roleplay et simulation
// ────────────────────────────────────────────────────────────────────────────

[
    "comspec_overwatch_roleplay_enabled", "CHECKBOX",
    ["Activer le mode roleplay", "Active les dysfonctionnements simulés (réseau, capteurs). Configuration détaillée via l'administration web du portail."],
    "COMSPEC Overwatch — Roleplay", false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_roleplay_network_failures", "CHECKBOX",
    ["Simulations réseau", "Active les délais, pertes de paquets et déconnexions temporaires. Les paramètres (latence, taux de perte) sont configurés sur le portail."],
    "COMSPEC Overwatch — Roleplay", false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_roleplay_sensor_failures", "CHECKBOX",
    ["Défauts capteurs médicaux", "Simule des dysfonctionnements du capteur de rythme cardiaque (valeurs manquantes, erronées ou nulles). Les taux de défaillance sont configurés sur le portail."],
    "COMSPEC Overwatch — Roleplay", false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_roleplay_visual_effects", "CHECKBOX",
    ["Effets visuels de dégradation", "Affiche des glitchs, parasites et messages d'erreur dans l'interface ATAK web quand la liaison se dégrade."],
    "COMSPEC Overwatch — Roleplay", true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_atak_realism", "LIST",
    [
        "Réalisme ATAK (dommages physiques)",
        "Les blessures au torse peuvent endommager l'ATAK. Niveau 1 : peut s'éteindre (réparable). Niveau 2 : écran peut être détruit (connexion OK). Niveau 3 : ATAK peut être détruit (connexion coupée)."
    ],
    "COMSPEC Overwatch — Roleplay",
    [[0, 1, 2, 3], ["Désactivé", "Niveau 1 : Extinction", "Niveau 2 : Écran détruit", "Niveau 3 : Destruction complète"], 0],
    1
] call CBA_fnc_addSetting;

["INFO", "Boot", format [
    "PreInit OK — connect v%1 | enabled=%2 | logLevel=%3",
    getText (configFile >> "CfgPatches" >> "comspec_overwatch_connect" >> "versionStr"),
    missionNamespace getVariable ["comspec_overwatch_enabled", true],
    missionNamespace getVariable ["comspec_overwatch_log_level", 3]
]] call comspec_overwatch_connect_fnc_log;
