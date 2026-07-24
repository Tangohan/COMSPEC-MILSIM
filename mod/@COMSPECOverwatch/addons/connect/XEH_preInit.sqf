[
    "comspec_overwatch_enabled", "CHECKBOX",
    ["Activer Overwatch", "Liaison Athena"],
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
    ["Fréquence (sec)", "Délai entre les cycles de synchronisation généraux (plus long = moins de charge)"],
    "COMSPEC Overwatch", [1, 600, 10, 0]
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_position_interval", "SLIDER",
    ["Intervalle position (s)", "Temps entre deux vérifications de position (plus long = moins de requêtes)"],
    "COMSPEC Overwatch", [1, 60, 3, 2]
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_batch_interval", "SLIDER",
    ["Batching réseau (s)", "Délai minimum entre deux envois de position vers Athena (plus long = moins de requêtes)"],
    "COMSPEC Overwatch", [1, 60, 3, 1]
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_position_threshold", "SLIDER",
    ["Seuil distance (m)", "Envoi si déplacement > X m"],
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
    ["Enregistrer le temps de jeu", "Envoie au portail le temps passé en mission (mod connecté)"],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_playtime_report_interval", "SLIDER",
    ["Rapport temps de jeu (minutes)", "Fréquence d’envoi du cumul au portail"],
    "COMSPEC Overwatch", [2, 60, 5, 0]
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_vehicle_mode", "CHECKBOX",
    ["Détail véhicule", "Envoyer orientation 3D et vitesse quand le joueur est en véhicule"],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_sync_map_markers", "CHECKBOX",
    ["Synchroniser les marqueurs carte", "Envoie vers Athena les marqueurs créés / modifiés / supprimés en jeu"],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_profile_enabled", "CHECKBOX",
    ["Profiler (debug)", "Mesure le temps d'exécution des boucles/PerFrameHandlers critiques (position, CAS, marqueurs). Rapport visible via le panneau de debug. Coût nul quand désactivé."],
    "COMSPEC Overwatch", false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_notif_sound", "LIST",
    ["Son des notifications", "Joué en même temps que les alertes (messagerie, ordres, liaison…). Les urgences médicales (inconscient / arrêt cardiaque) utilisent un son dédié, y compris en mode « Silencieux (vibration) ». Seul « Muet » coupe tout. Le Mode discret ne coupe pas ces sons."],
    "COMSPEC Overwatch",
    [
        ["silent_vib", "stalker", "health", "mute"],
        ["Silencieux (vibration)", "Stalker", "Alerte santé", "Muet"],
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
    ["Intervalle scan radio (s)", "Fréquence de mise à jour de la liste de proximité (cache local tablette, sans spam réseau)"],
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
