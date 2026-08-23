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
    ["Son des notifications", "Joué avec les alertes (messages, ordres, connexion). Les urgences médicales ont un son dédié, y compris en mode Silencieux — vibration seule. Seul Silencieux — sans vibration coupe tout. Le mode discret ne coupe pas ces sons. Réglable aussi depuis l’app Sons de l’ATAK."],
    "COMSPEC Overwatch",
    [
        ["silent_vib", "stalker", "health", "mute"],
        ["Silencieux — vibration seule", "Ambiance tension", "Signal médical", "Silencieux — sans vibration"],
        0
    ],
    false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_sound_master", "SLIDER",
    ["Volume général ATAK", "Multiplie tous les sons du terminal (alertes, vibration, effets de liaison). 0 = silence total. Réglable depuis l’app Sons de l’ATAK."],
    "COMSPEC Overwatch",
    [0, 1, 1, 2],
    false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_sound_notif_vol", "SLIDER",
    ["Volume des alertes", "Volume des sons de notification (connexion, ordres, alertes). Réglable depuis l’app Sons de l’ATAK."],
    "COMSPEC Overwatch",
    [0, 1, 1, 2],
    false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_sound_vibrate_vol", "SLIDER",
    ["Volume de vibration", "Intensité du buzz quand l’état-major fait vibrer le terminal. Réglable depuis l’app Sons de l’ATAK."],
    "COMSPEC Overwatch",
    [0, 1, 1, 2],
    false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_sound_fx_vol", "SLIDER",
    ["Volume des effets de liaison", "Sons de zone, coupure réseau, écran endommagé, etc. Réglable depuis l’app Sons de l’ATAK."],
    "COMSPEC Overwatch",
    [0, 1, 0.8, 2],
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
    ["Mode discret — masquer les alertes à l’écran", "Masque temporairement les bandeaux lorsque « Afficher les notifications à l’écran » est activé. Les sons (réglage « Son des notifications ») continuent de jouer sauf si Silencieux — sans vibration. Les alertes restent disponibles dans la tablette (cloche / journal Alertes). Les écrans de connexion et la tablette elle-même restent utilisables."],
    "COMSPEC Overwatch", false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_milsim_ui", "CHECKBOX",
    ["Mode milsim — désactiver les aides d’interface", "Immersion : coupe les anomalies de suivi (ex. immobile), les messages système de confort et les bandeaux / chat Overwatch (même si « Afficher les notifications à l’écran » est activé). La liaison Athena, la synchronisation de position et la tablette restent actives. Les alertes médicales et les ordres restent dans la tablette (sons selon le réglage « Son des notifications »)."],
    "COMSPEC Overwatch", false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_require_item", "CHECKBOX",
    ["Exiger un terminal ATAK pour synchroniser et ouvrir l’interface", "Activé par défaut : la synchronisation Athena et l’ouverture du téléphone / des menus ATAK ne fonctionnent que si vous portez le terminal choisi ci-dessous (téléphone ATAK, tablette cTab, etc.). Décochez uniquement pour les tests sans équipement."],
    "COMSPEC Overwatch", true
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
    [
        "Interface uniquement via ATAK Enhanced (recommandé)",
        "Activé par défaut : la touche K et les menus associés ouvrent le téléphone ATAK Enhanced. La tablette Overwatch séparée n’est plus ouverte hors d’ATAK. Décochez seulement si vous devez retrouver l’ancienne tablette Overwatch hors ATAK. La liaison Athena et la synchronisation restent actives dans les deux cas."
    ],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_athena_link_help", "CHECKBOX",
    ["Windows reminder - link Athena account", "At launch, if account not yet linked, displays Windows alert with instructions. Uncheck to stop seeing this reminder."],
    "COMSPEC Overwatch", true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_reset_beta_nda", "CHECKBOX",
    [
        "Réafficher la note bêta",
        "Cochez puis validez : la note de bienvenue (bêta publique) s'affiche à nouveau. La case se décoche ensuite automatiquement. Votre inscription à la bêta n'est pas annulée."
    ],
    "COMSPEC Overwatch",
    false,
    false,
    {
        params ["_value"];
        if (!_value) exitWith {};
        0 spawn {
            uiSleep 0.05;
            // Au chargement CBA (boot), la case peut être coincée à « true » dans le profil :
            // on la décoche SANS effacer l'acceptation NDA. L'effacement n'a lieu que si le
            // joueur coche la case après le démarrage (menu Options / mission).
            private _bootGuard = missionNamespace getVariable ["COMSPEC_NDA_ResetBootGuard", true];
            if (_bootGuard) then {
                if !(isNil "CBA_fnc_setSetting") then {
                    ["comspec_overwatch_reset_beta_nda", false, 0, "client", true] call CBA_fnc_setSetting;
                };
            } else {
                if !(isNil "comspec_overwatch_connect_fnc_resetBetaNdaAck") then {
                    [] call comspec_overwatch_connect_fnc_resetBetaNdaAck;
                };
                if !(isNil "CBA_fnc_setSetting") then {
                    ["comspec_overwatch_reset_beta_nda", false, 0, "client", true] call CBA_fnc_setSetting;
                };
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
        "En plus du journal Arma (RPT), enregistre les lignes dans un fichier horodaté par session (%LOCALAPPDATA%\\Arma 3\\COMSPEC\\logs). Les 12 derniers fichiers sont conservés, les plus anciens sont supprimés automatiquement. Sans effet si le journal RPT est réglé sur « Muet »."
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
// Par défaut (ATAK Enhanced only) :
//   K / Ctrl+K / Ctrl+Shift+K → téléphone ATAK Enhanced
// Si « Interface uniquement via ATAK Enhanced » est décoché :
//   K → tablette Overwatch, Ctrl+K → messagerie, Ctrl+Shift+K → applications
[
    "COMSPEC Overwatch", "comspec_menu_hub",
    ["Téléphone ATAK / tablette", "Ouvre ATAK Enhanced (défaut) ou la tablette Overwatch si l’option ATAK-only est désactivée (K)"],
    {
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        if (missionNamespace getVariable ["comspec_overwatch_atak_ui_only", true]) exitWith {
            0 spawn { [] call comspec_overwatch_connect_fnc_openAtakEnhanced; };
            true
        };
        if !([false] call comspec_overwatch_connect_fnc_canOpenOverwatchUi) exitWith { false };
        0 spawn { [] call comspec_overwatch_connect_fnc_webBrowserShow; };
        true
    },
    "",
    [0x25, [false, false, false]], // DIK_K
    false, 0, false
] call CBA_fnc_addKeybind;

[
    "COMSPEC Overwatch", "comspec_menu_chat",
    ["Messagerie ATAK / Overwatch", "Ouvre la messagerie Athena dans ATAK Enhanced (défaut), ou la messagerie Overwatch si l’option ATAK-only est désactivée (Ctrl+K)"],
    {
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        if (missionNamespace getVariable ["comspec_overwatch_atak_ui_only", true]) exitWith {
            0 spawn { ["messages"] call comspec_overwatch_connect_fnc_openAthenaFeature; };
            true
        };
        if !([false] call comspec_overwatch_connect_fnc_canOpenOverwatchUi) exitWith { false };
        0 spawn { ["chat"] call comspec_overwatch_connect_fnc_openTabletView; };
        true
    },
    "",
    [0x25, [false, true, false]], // Ctrl+DIK_K  — [shift, ctrl, alt]
    false, 0, false
] call CBA_fnc_addKeybind;

[
    "COMSPEC Overwatch", "comspec_menu_hub_csk",
    ["Applications ATAK / Overwatch", "Ouvre Athena dans ATAK Enhanced (défaut), ou les applications Overwatch si l’option ATAK-only est désactivée (Ctrl+Shift+K)"],
    {
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        if (missionNamespace getVariable ["comspec_overwatch_atak_ui_only", true]) exitWith {
            0 spawn { ["all"] call comspec_overwatch_connect_fnc_openAthenaFeature; };
            true
        };
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
        [] call comspec_overwatch_connect_fnc_medevacDialogShow;
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
        ["TROOPS_IN_CONTACT", "IMMEDIATE", "Besoin renfort immédiat", "SQUAD", 0, "ENGAGED", getPosWorld player] call comspec_overwatch_connect_fnc_requestQRF;
        true
    },
    "",
    [], // Pas de touche par défaut

    false, 0, false
] call CBA_fnc_addKeybind;



// Cache position / tracking tactique (local client — jamais public depuis le dédié)
if (hasInterface) then {
    missionNamespace setVariable ["COMSPEC_lastPos", [0,0,0], false];
    missionNamespace setVariable ["COMSPEC_lastName", "", false];
    missionNamespace setVariable ["COMSPEC_lastRole", "", false];
    missionNamespace setVariable ["COMSPEC_lastRadio", "", false];
    missionNamespace setVariable ["COMSPEC_lastMedical", "", false];
    missionNamespace setVariable ["COMSPEC_lastMedicalAlertKind", "", false];
    missionNamespace setVariable ["COMSPEC_lastMedicalAlertAt", -1e9, false];
    missionNamespace setVariable ["COMSPEC_MedicalAlertsSeen", [], false];
    missionNamespace setVariable ["COMSPEC_MedicalAlertsBootstrapped", false, false];
    missionNamespace setVariable ["COMSPEC_MedicalAlerts", [], false];
    missionNamespace setVariable ["COMSPEC_MedicalAlertsArmed", false, false];
    missionNamespace setVariable ["COMSPEC_MedicalAlertBusy", false, false];
    missionNamespace setVariable ["COMSPEC_RespawnGraceUntil", -1e9, false];
    missionNamespace setVariable ["COMSPEC_SuppressWinMessageBoxUntil", -1e9, false];
    missionNamespace setVariable ["COMSPEC_CancelPendingAthenaHelp", false, false];
    missionNamespace setVariable ["COMSPEC_DeathThenRespawn", false, false];
    missionNamespace setVariable ["COMSPEC_VehicleTrackingInited", false, false];
    missionNamespace setVariable ["COMSPEC_lastSendTime", 0, false];
    missionNamespace setVariable ["COMSPEC_ApiBackoffUntil", 0, false];
    missionNamespace setVariable ["COMSPEC_ApiBackoffSec", 2, false];
    missionNamespace setVariable ["COMSPEC_PositionTrail", [], false];
    missionNamespace setVariable ["COMSPEC_ImmobileSince", 0, false];
    missionNamespace setVariable ["COMSPEC_ImmobileAlerted", false, false];
    missionNamespace setVariable ["COMSPEC_IncoherentAlertAt", -1e9, false];
};

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
// EventBus / caches locaux : pas de broadcast depuis le dédié.
// Orders / OrderLog : publiés pour synchro MP (émis côté client via issueOrder).
if (hasInterface || {isServer}) then {
    if (isNil {missionNamespace getVariable "COMSPEC_EventBus"}) then {
        missionNamespace setVariable ["COMSPEC_EventBus", createHashMap, false];
    };
    if (isNil {missionNamespace getVariable "COMSPEC_Orders"}) then {
        missionNamespace setVariable ["COMSPEC_Orders", [], isServer];
    };
    if (isNil {missionNamespace getVariable "COMSPEC_OrderLog"}) then {
        missionNamespace setVariable ["COMSPEC_OrderLog", [], isServer];
    };
};
if (hasInterface) then {
    missionNamespace setVariable ["COMSPEC_IntelStore", [], false];
    missionNamespace setVariable ["COMSPEC_IntelHeatmap", createHashMap, false];
    missionNamespace setVariable ["COMSPEC_RadioReplay", [], false];
    missionNamespace setVariable ["COMSPEC_Comms_Channel", "SQUAD", false];
    missionNamespace setVariable ["COMSPEC_Comms_Priority", "ROUTINE", false];
    missionNamespace setVariable ["COMSPEC_OrdersSeen", [], false];
};

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
    ["Activer le mode roleplay", "Active les dysfonctionnements simulés (réseau, capteurs). Configuration détaillée via l'administration web du portail. Le « mode troll » communauté se règle sur Athena (Configuration ATAK → Expérience en jeu)."],
    ["COMSPEC Overwatch", "Roleplay"], false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_roleplay_network_failures", "CHECKBOX",
    ["Simulations réseau", "Active les délais, pertes de paquets et déconnexions temporaires. Les paramètres (latence, taux de perte) sont configurés sur le portail."],
    ["COMSPEC Overwatch", "Roleplay"], false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_roleplay_sensor_failures", "CHECKBOX",
    ["Défauts capteurs médicaux", "Simule des dysfonctionnements du capteur de rythme cardiaque sur la carte tactique web (valeurs manquantes, erronées ou nulles). Les taux de défaillance sont configurés sur le portail (administration roleplay)."],
    ["COMSPEC Overwatch", "Roleplay"], false
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_roleplay_visual_effects", "CHECKBOX",
    ["Effets visuels de dégradation", "Affiche des glitchs, parasites et messages d'erreur dans l'interface ATAK web quand la liaison se dégrade."],
    ["COMSPEC Overwatch", "Roleplay"], true
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_atak_realism", "LIST",
    [
        "Réalisme ATAK (dommages physiques)",
        "Les blessures au torse peuvent endommager l'ATAK. Niveau 1 : peut s'éteindre (réparable). Niveau 2 : écran peut être détruit (connexion OK). Niveau 3 : ATAK peut être détruit (connexion coupée)."
    ],
    ["COMSPEC Overwatch", "Roleplay"],
    [[0, 1, 2, 3], ["Désactivé", "Niveau 1 : Extinction", "Niveau 2 : Écran détruit", "Niveau 3 : Destruction complète"], 0],
    1
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_order_compose_enabled", "CHECKBOX",
    ["Émission d’ordres / FRAGO in-game", "Autorise les chefs d’unité à ouvrir une mini-fenêtre pour rédiger et envoyer un ordre ou un FRAGO sans passer par la tablette web."],
    ["COMSPEC Overwatch", "Ordres C2"], true
] call CBA_fnc_addSetting;

[
    "comspec_sse_require_item", "CHECKBOX",
    [
        "Terminal SEEK requis",
        "Exige un terminal de recueil (SEEK, BII-10 ou telephone ATAK) pour ouvrir une fiche. Decochez pour conserver l'acces sans objet."
    ],
    ["COMSPEC Overwatch", "Renseignement SSE"], true
] call CBA_fnc_addSetting;

private _sseKeyDefault = [0x1F, [true, true, false]]; // Ctrl+Shift+S
if (isClass (configFile >> "CfgPatches" >> "comspec_sse_biometrics")) then {
    // Le mod SSE possede deja ce raccourci : eviter deux ouvertures superposees.
    _sseKeyDefault = [];
};

[
    "COMSPEC Overwatch", "comspec_open_sse_fiche",
    ["Ouvrir fiche SSE (FRS / SEEK)", "Ouvre le terminal de recueil source. Defaut : Ctrl+Shift+S (si le mod SSE n'est pas charge)."],
    {
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        [] call comspec_overwatch_connect_fnc_sseOpenFromKeybind;
        true
    },
    "",
    _sseKeyDefault,
    false, 0, false
] call CBA_fnc_addKeybind;

[
    "COMSPEC Overwatch - Ordres", "comspec_order_compose_key",
    ["Ouvrir rédaction d’ordre / FRAGO", "Ouvre la mini-fenêtre contextuelle pour écrire et envoyer un ordre (chefs d’unité)."],
    {
        if (!(missionNamespace getVariable ["comspec_overwatch_order_compose_enabled", true])) exitWith { false };
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        0 spawn { [""] call comspec_overwatch_connect_fnc_orderComposeShow; };
        true
    },
    "",
    [], // Pas de touche par défaut — à définir dans Options > Contrôles > Extension Addon
    false, 0, false
] call CBA_fnc_addKeybind;

[
    "COMSPEC Overwatch - Ordres", "comspec_frago_compose_key",
    ["Ouvrir rédaction FRAGO", "Ouvre directement le formulaire d’ordre fragmentaire (Situation · Mission · Exécution · Soutien · Commandement)."],
    {
        if (!(missionNamespace getVariable ["comspec_overwatch_order_compose_enabled", true])) exitWith { false };
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        0 spawn { ["FRAGO"] call comspec_overwatch_connect_fnc_orderComposeShow; };
        true
    },
    "",
    [],
    false, 0, false
] call CBA_fnc_addKeybind;

["INFO", "Boot", format [
    "PreInit OK — connect v%1 | enabled=%2 | logLevel=%3",
    getText (configFile >> "CfgPatches" >> "comspec_overwatch_connect" >> "versionStr"),
    missionNamespace getVariable ["comspec_overwatch_enabled", true],
    missionNamespace getVariable ["comspec_overwatch_log_level", 3]
]] call comspec_overwatch_connect_fnc_log;

if (isNil "COMSPEC_NDA_ResetBootEh") then {
    COMSPEC_NDA_ResetBootEh = ["CBA_settingsInitialized", {
        [{ missionNamespace setVariable ["COMSPEC_NDA_ResetBootGuard", false, false]; }, [], 8] call CBA_fnc_waitAndExecute;
    }] call CBA_fnc_addEventHandler;
};

if (isServer) then {
    [] call comspec_overwatch_connect_fnc_initCrashRecovery;
};