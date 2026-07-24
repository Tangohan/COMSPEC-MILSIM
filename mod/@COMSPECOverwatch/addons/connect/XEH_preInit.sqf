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
    ["Clé d’accès Athena", "Fournie par l’admin (obligatoire en production). Laissée vide en local si le serveur n’exige pas de clé."],
    "COMSPEC Overwatch", ""
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_tenant_id", "EDITBOX",
    ["Identifiant de communauté", "Laisser vide si le serveur Athena a déjà une communauté par défaut. Sinon, valeur numérique fournie par l’admin."],
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
    ["Détection terminal (position)", "Comment reconnaître le téléphone tactique (S7 Android) pour autoriser la remontée de position. « Slot d’objet » = ItemAndroid équipé (comme un GPS/NVG). « Inventaire » = ItemAndroidMisc simplement transporté (objet cTab, sans effet si cTab n’est pas chargé). « Les deux » accepte l’un ou l’autre."],
    "COMSPEC Overwatch",
    [
        [0, 1, 2],
        ["Slot d’objet uniquement (ItemAndroid)", "Présence en inventaire (ItemAndroidMisc)", "Les deux (par défaut)"],
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
    ["Rapport temps de jeu (minutes)", "Fréquence d’envoi du cumul au portail"],
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
    ["Tablette avancée (écran intégré)", "Ouvre la tablette Overwatch avec l’écran tactique Chromium (inspiré cTab). Désactivez pour forcer la vue classique."],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_quiet_mode", "CHECKBOX",
    ["Mode discret — masquer les alertes à l’écran", "Cache les bandeaux et messages système Overwatch en jeu. Les sons (réglage « Son des notifications ») continuent de jouer sauf si Muet. Les alertes restent disponibles dans la tablette (cloche / journal Alertes). Les écrans de connexion et la tablette elle-même restent utilisables."],
    "COMSPEC Overwatch", false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_athena_link_help", "CHECKBOX",
    ["Rappel Windows — lier mon compte Athena", "Au lancement, si votre compte n’est pas encore lié, affiche une alerte Windows avec la marche à suivre. Décochez pour ne plus voir ce rappel (vous pouvez aussi choisir « Non » dans l’alerte)."],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_radio_proximity_enabled", "CHECKBOX",
    ["Surveillance radio à proximité", "Détecte qui émet près de vous (ou de l’opérateur surveillé) et remonte l’état vers Athena. Nécessite un module radio (ACRE2 ou TFAR). Sans module : pastilles grisées."],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_radio_proximity_radius", "SLIDER",
    ["Rayon radio proximité (m)", "Contacts et émissions listés dans ce rayon autour de l’opérateur de référence"],
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
