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
    ["Fréquence (sec)", "Délai PLI"],
    "COMSPEC Overwatch", [1, 60, 5, 0]
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_position_interval", "SLIDER",
    ["Intervalle position (s)", "PerFrameHandler position"],
    "COMSPEC Overwatch", [0.1, 2, 0.25, 2]
] call CBA_fnc_addSetting;

[
    "comspec_overwatch_batch_interval", "SLIDER",
    ["Batching réseau (s)", "Envoi positions max 1/s"],
    "COMSPEC Overwatch", [0.5, 5, 1, 1]
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
