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
    ["Notification sound", "Played at the same time as alerts (messaging, orders, connection…). Medical emergencies (unconscious / cardiac arrest) use dedicated sound, including in \"Silent (vibration)\" mode. Only \"Mute\" cuts everything. Discreet mode does not cut these sounds."],
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
    "comspec_overwatch_quiet_mode", "CHECKBOX",
    ["Discreet mode - hide on-screen alerts", "Hides Overwatch banners and system messages in game. Sounds continue to play unless Mute. Alerts remain available in tablet. Connection screens remain usable."],
    "COMSPEC Overwatch", false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_athena_link_help", "CHECKBOX",
    ["Windows reminder - link Athena account", "At launch, if account not yet linked, displays Windows alert with instructions. Uncheck to stop seeing this reminder."],
    "COMSPEC Overwatch", true
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

// IDs v2 : l’ancien comspec_open_chat était enregistré sur K seul (sans Ctrl).
// CBA conserve les binds du profil joueur — sans nouvel ID, Ctrl+K ne s’applique jamais.
[
    "COMSPEC Overwatch", "comspec_menu_hub", ["Menu ATAK", "Ouvrir le menu des vues Overwatch (K)"],
    {
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        0 spawn { [] call comspec_overwatch_connect_fnc_openHub; };
        true
    },
    "",
    [0x25, [false, false, false]], // DIK_K
    false, 0, false
] call CBA_fnc_addKeybind;

[
    "COMSPEC Overwatch", "comspec_menu_chat", ["Messagerie", "Ouvrir directement la messagerie (Ctrl+K)"],
    {
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        0 spawn { createDialog "COMSPEC_Chat_Dialog"; };
        true
    },
    "",
    [0x25, [false, true, false]], // Ctrl+DIK_K  — [shift, ctrl, alt]
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
missionNamespace setVariable ["COMSPEC_lastSendTime", 0, true];
missionNamespace setVariable ["COMSPEC_ApiBackoffUntil", 0, false];
missionNamespace setVariable ["COMSPEC_ApiBackoffSec", 2, false];
missionNamespace setVariable ["COMSPEC_PositionTrail", [], true];
missionNamespace setVariable ["COMSPEC_ImmobileSince", 0, true];

// Badges UI — liaison Athena
missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
missionNamespace setVariable ["COMSPEC_LinkDetail", "", false];
missionNamespace setVariable ["COMSPEC_LastPositionSync", -1, false];
missionNamespace setVariable ["COMSPEC_LastHealthOk", -1, false];

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
