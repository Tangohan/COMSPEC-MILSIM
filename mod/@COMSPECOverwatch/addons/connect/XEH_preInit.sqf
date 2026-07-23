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
